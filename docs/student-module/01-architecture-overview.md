# Delivery Phase 1 — Architecture Overview
## CodeGurukul Student Module

---

## 1. Executive Summary

CodeGurukul's Student Module is the customer-facing core of the platform: the surface where a paying family experiences the product every day. It must feel premium (low latency live classes, instant AI help, slick portfolio pages) while quietly running enterprise-grade plumbing underneath (credit accounting, attendance-to-billing linkage, multi-channel comms, content pipelines, AI cost governance).

Two facts drive every decision in this document:

1. **There is already a working system.** The Admin panel (PHP 8.3 manual MVC, MySQL, Bootstrap/jQuery) was just brought to a stable, schema-consistent state. It is not being thrown away.
2. **The Student/Teacher surfaces are where the hard engineering lives** — live video, real-time collaboration, AI, content pipelines, multi-channel orchestration, scale to 100k+ users. This is exactly the kind of workload Laravel + React is built to support well (queues, broadcasting, notifications, a real ORM, a real frontend component model), and exactly the kind of workload a hand-rolled MVC framework would force you to re-invent from zero.

**Decision: strangler-fig migration, not a rewrite.** New Student and Teacher portals are built in Laravel + React, reading and writing the *same* MySQL database the Admin panel already uses (schema extended, not duplicated). The Admin panel keeps running unchanged. Over time, if desired, Admin can be migrated into the same Laravel codebase — but that is not on the critical path and is not assumed by anything below.

---

## 2. Tech Stack Decision Matrix

| Layer | Choice | Why |
|---|---|---|
| Backend framework | **Laravel 11** | Queues, Horizon (queue observability), Notifications (multi-channel out of the box), Broadcasting (Reverb/Pusher for websockets), Sanctum (API auth), Eloquent ORM, mature ecosystem for everything Phases 7–39 need. Hand-rolling this in the existing manual MVC app would mean rebuilding all of it first. |
| Frontend | **React 18 + TypeScript** (despite the rest of the platform avoiding TS — student-facing app is a new, separate codebase, and TS materially reduces bugs in a codebase this complex: real-time state, WebRTC, collaborative editors) | Component model fits live classroom UI, coding sandbox panels, collaborative editor, quiz overlays — all highly stateful, highly interactive surfaces that are painful in server-rendered PHP views. |
| Mobile (future) | React Native (shares logic/types with the React web app) | Not in scope for this design pass, but the API layer below is built mobile-ready (versioned REST + token auth) so it isn't a re-architecture later. |
| Primary DB | **MySQL 8 (AWS Aurora MySQL)** — same instance/schema family as the existing Admin DB | Single source of truth. Aurora gives read replicas for the read-heavy student dashboard/analytics queries without duplicating data. |
| Cache / session / rate limiting | **Redis (AWS ElastiCache)** | Laravel sessions, queue backend, leaderboards (sorted sets — perfect fit for Phase 31 gamification and Phase 34 live quiz leaderboards), rate limiting AI usage. |
| Object storage | **AWS S3** | Recordings, notebooks attachments, assignment submissions, certificates, exported PDFs. |
| Search / transcripts | **OpenSearch (AWS Managed)** | Full-text search over recording transcripts (Phase 11), notes (Phase 32), support tickets (Phase 30). |
| Vector DB (AI/RAG) | **Pinecone** (primary) or **pgvector on a dedicated Aurora PostgreSQL** (cost-optimized self-hosted alternative once volume justifies it) | Pinecone for fastest path to production; pgvector documented as the scale-cost-optimization path — see Phase 5/7 documents. |
| LLM access | **AI Gateway service** fronting Anthropic Claude (via AWS Bedrock, primary) with OpenAI as a documented fallback provider | Vendor-abstraction layer so a model/provider change is a config change, not a rewrite. Bedrock keeps inference inside the AWS network boundary (helps with the compliance posture given minors' data — see §7). |
| Live classroom (video) | **Agora.io RTC SDK** (managed) | Explicit requirement: classes happen *inside* the platform, not via Meet/Zoom redirects. Self-hosting a global low-latency SFU fleet (mediasoup/Janus + worldwide TURN/STUN PoPs) is a multi-engineer, multi-quarter undertaking; Agora gives global PoPs, cloud recording, and usage-based pricing that scales naturally from 100 to 100k+ students without pre-provisioning infrastructure. Self-hosted SFU is documented in Phase 7 (Scaling) as the cost-crossover path once volume is large enough to justify the ops investment. |
| Real-time collaboration sync | **Yjs (CRDT)** over WebSockets (Laravel Reverb as the relay) | Collaborative code editing (Phase 33) and shared whiteboard need conflict-free merge semantics. CRDTs (Yjs) are the modern, battle-tested approach — far more robust than hand-rolling Operational Transform. |
| Code execution sandbox | **Piston** (self-hosted, open-source multi-language execution engine) for v1, with a documented upgrade path to **Firecracker microVM-backed execution** (AWS Lambda-style isolation) once usage volume and the security bar both justify the added ops complexity | See §6 and Phase 7 for the full isolation/scaling tradeoff analysis. |
| Background jobs | **Laravel Queues on SQS** | Video transcoding triggers, AI report generation, notification fan-out, risk-scoring batch jobs, certificate generation. |
| Video processing | **AWS MediaConvert** (transcoding) + **AWS Elemental MediaPackage** (packaging/light DRM via AES-128 HLS) | Recording pipeline (Phase 11) and offline/DRM (Phase 36). |
| Infra | **AWS ECS Fargate** (containers, no server fleet to patch) behind **Application Load Balancer**, **CloudFront** CDN for static assets/video delivery | Matches "AWS preferred"; Fargate avoids EC2 fleet management at the team's current scale. |
| CI/CD | GitHub Actions → ECR → ECS rolling deploy | Standard, low-maintenance pipeline; detailed in Phase 8. |

---

## 3. System Landscape

```
                         ┌──────────────────────────────┐
                         │   Existing Admin Panel        │
                         │   PHP 8.3 manual MVC          │
                         │   Bootstrap/jQuery            │
                         │   (UNCHANGED, runs as-is)     │
                         └───────────────┬───────────────┘
                                         │ reads/writes
                                         ▼
                         ┌──────────────────────────────┐
                         │   Aurora MySQL 8              │
                         │   `codegurukul` schema        │
                         │   (extended, not forked)      │
                         └───────────────┬───────────────┘
                                         │ reads/writes (Eloquent)
                         ┌───────────────┴───────────────┐
                         │   Laravel API (new)            │
                         │   Student + Teacher domains     │
                         └───────────────┬───────────────┘
                    ┌────────────────────┼────────────────────┐
                    ▼                    ▼                    ▼
          ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
          │  React Student   │ │  React Teacher   │ │  Internal Service │
          │  Web App         │ │  Web App (later) │ │  Workers (Queues) │
          └──────────────────┘ └──────────────────┘ └──────────────────┘
```

The Admin panel and the new Laravel API are **peers against the same database**, not client/server of each other. Neither calls the other's HTTP endpoints for core data — they share the schema as the contract. This is deliberate: it avoids a synchronous dependency between two different runtimes/teams, and it's how the existing `users`, `courses`, `enrollments`, `exams`, `payments` tables (already fixed this session) get reused rather than duplicated.

**Where they do need to talk:** a small number of cross-cutting actions (e.g., Admin issuing a manual credit adjustment that the Student app should see instantly, or a teacher reassignment triggered from Admin) are handled by **domain events written to a shared `domain_events` table** (outbox pattern) that either side can poll/consume — avoiding tight coupling while still getting near-real-time propagation. Detailed in Phase 2 (schema) and Phase 4 (APIs).

---

## 4. Architectural Style: Modular Monolith, Not Microservices

For a team building this, a full microservices split (separate deployable services per bounded context) adds operational overhead — service discovery, distributed tracing, network failure modes — that isn't justified until traffic and team size demand it. Instead:

**One Laravel codebase, organized into strict bounded-context modules** (folder-per-domain, no cross-module Eloquent reach-arounds — modules talk through service classes/events, not by querying each other's tables directly):

- `Enrollment` — lead conversion, account creation, parent linking
- `Billing` — credit wallet, transactions, payments, refunds, freezes
- `Assessment` — placement tests, exams, quizzes, grading
- `Scheduling` — batches, class scheduling, reschedules, waitlists
- `Classroom` — live session orchestration, attendance, recordings
- `Content` — video library, notes/materials, notebook
- `Sandbox` — coding workspaces, execution, replay
- `AI` — doubt solver, coding assistant, risk detection, recommendations
- `Projects` — project lifecycle, publishing, portfolio
- `Communication` — orchestration across WhatsApp/Email/SMS/Push/IVR
- `Gamification` — XP, badges, streaks, leaderboards
- `Support` — tickets, PTM booking

**Three pieces are deliberately carved out as separate services from day one**, because their resource/scaling profile is fundamentally different from a request/response web app:

1. **Live Classroom / RTC** — not a Laravel responsibility at all; Agora handles media transport. Laravel only orchestrates session metadata (who's allowed in, attendance, recording triggers).
2. **Code Execution Sandbox** — must run in a tightly isolated, disposable compute environment (untrusted user code). Implemented as its own containerized service (Piston, or later Firecracker microVMs) invoked by Laravel via an internal API, never inline in the web request path.
3. **AI Gateway** — centralizes all LLM calls so cost, rate limits, prompt versioning, and provider fallback are governed in one place rather than scattered across every feature that calls an LLM.

This gives the benefits people usually reach for microservices for (independent scaling of the spiky/expensive parts) without paying the full distributed-systems tax everywhere else.

---

## 5. Data Architecture

| Store | Used for | Notes |
|---|---|---|
| Aurora MySQL (primary) | All transactional data: users, enrollments, credits, schedules, attendance, assignments, assessments, projects | Existing schema extended — see Phase 2. Read replica added once Student-app read traffic (dashboards, analytics) materially exceeds Admin's. |
| Redis | Sessions, queues, rate limits, leaderboards, live-quiz ephemeral state, "presence" (who's online in a class) | Sorted sets give O(log N) leaderboard updates — critical for Phase 31/34 to not hammer MySQL on every XP/score change. |
| S3 | Recordings, transcoded video renditions, assignment file submissions, notebook attachments, generated PDFs (certificates, parent reports), exported portfolios | Lifecycle policies move cold recordings to S3 Glacier after a configurable retention window (cost control at scale). |
| OpenSearch | Recording transcript search, notes search, support ticket search | Indexed asynchronously via queue jobs after transcript/notes are written — never blocks the write path. |
| Pinecone (or pgvector) | Embeddings for AI Doubt Solver RAG, course-content embeddings, notebook "AI summarize" embeddings | Namespaced per course/curriculum so retrieval doesn't leak cross-course content into answers. |
| Code event log (S3 + lightweight MySQL index) | Code replay event streams (Phase 35) | Append-only compressed event deltas in S3, with a MySQL row per session pointing at the S3 object + key timeline markers (errors, runs) for fast seek without downloading the full stream. |

---

## 6. Code Execution Sandbox — Isolation Strategy (Phase 15 preview)

You asked for an explicit comparison; this is the architecture-level call so later phases can build on it:

| Option | Isolation strength | Ops complexity | Verdict |
|---|---|---|---|
| Plain Docker containers | Weak — shares host kernel; known container-escape CVEs exist | Low | **Rejected** as the sole layer for untrusted student code. Acceptable only as the execution layer *inside* a stronger boundary. |
| Piston (self-hosted) | Containers + resource limits (CPU/memory/time), purpose-built for code execution, isolates network access | Low–Medium | **Recommended for v1.** Mature, open-source, supports all required languages out of the box, simple to self-host on ECS. |
| Firecracker microVMs (Lambda-style) | Strong — hardware-virtualized, kernel-level isolation per execution | High | **Recommended scaling path** once concurrent execution volume and the security bar (e.g., handling more sophisticated abuse attempts) justify the added orchestration work. |
| Full sandbox VMs (one VM per session) | Strongest | Very high, slow cold-start | **Rejected** — cold-start latency (seconds) is incompatible with the "instant run" UX a premium product needs. |

Every execution request goes through: a request queue → a fresh disposable sandbox (no state carried between runs) → hard limits on CPU time, memory, output size, and wall-clock timeout (kills infinite loops) → result streamed back. Full detail (multi-file projects, debugging, version history) is in Phase 3e.

---

## 7. Non-Functional Requirements

| Dimension | Target | Driving decision |
|---|---|---|
| Scale | 100 → 100,000+ concurrent students across the platform; design for ~5,000 concurrent live-class participants at peak evening hours in the 100k-student tier | Drives Aurora read replicas, Agora's managed scaling, ECS auto-scaling groups, Redis for hot-path reads |
| Live class latency | <150ms median RTC latency within a region | Agora's regional PoPs; classes pinned to nearest available teacher/region where possible |
| Availability | 99.9% for core platform; live classroom path treated as the highest-priority SLA since a dropped class directly damages trust in a premium product | Multi-AZ Aurora, ECS across AZs, Agora's own SLA, documented failover behavior per Phase 3c |
| AI response latency | <3s for doubt-solver first token; <8s for full coding-assistant review | Streaming responses (SSE) from the AI Gateway, smaller/faster models (e.g., Claude Haiku) for low-latency hint mode, larger models reserved for deeper "explain mode" |
| Data durability | No credit-ledger or attendance record is ever hard-deleted | Append-only ledger design (Phase 2/3a) — corrections are reversing entries, never edits |

---

## 8. Security & Compliance Baseline

This is a first-class architectural concern, not a checklist added later, because **the user base includes children aged 8–18**.

- **Parental consent is structural, not optional.** Every student account under a configurable age threshold (default 13, configurable per jurisdiction) requires a verified parent/guardian account linked *before* the student account is activated. This shapes the Phase 2 schema (`parent_links` with a `consent_status` and `consent_recorded_at`, not just a loose FK) and the Phase 3a account-creation workflow.
- **Recording consent is tracked per session, not assumed.** Live classes are recorded by default per your spec; the consent record (parent-level, captured once at enrollment, revocable) is stored and checked before a recording is retained beyond a short buffer window — not just before it's *taken*.
- **Data minimization for minors**: AI doubt-solver logs, code-replay streams, and chat transcripts involving under-13 students are subject to a shorter default retention window than adult/professional users, configurable per compliance regime.
- **Encryption**: at rest (Aurora encryption, S3 SSE-KMS), in transit (TLS everywhere, including the Agora media path), and field-level encryption for anything classified sensitive (payment identifiers, parent contact details used for IVR/WhatsApp).
- **RBAC**: Student, Parent, Teacher, Mentor, Admin, Support roles — each with explicit, auditable permission grants (extending the existing Admin RBAC tables rather than inventing a second permission system).
- **Audit logging**: every credit deduction, attendance override, reschedule, refund, and AI interaction is written to an append-only audit trail (reusing/extending the existing `audit_logs` table pattern already proven in the Admin panel).
- **Account-sharing detection**: device/session fingerprinting + concurrent-session limits flagged for review (detailed in Phase 33 security notes), since credit-based pricing creates an incentive to share logins.

---

## 9. What's Next

**Delivery Phase 2 — Database Schema** is next: the full extended schema (every table, column, relationship, and index needed across all 39 workflow phases), built on top of — not replacing — the existing `codegurukul` schema.

Say "continue" / "next phase" whenever you're ready and I'll proceed through the index in `00-master-index.md`.
