# CodeGurukul Student Module — Master Architecture Program

Tracks the full multi-phase design deliverable. Each delivery phase below is a separate document in this folder.

## Delivery Phases (requested format)

| # | Delivery Phase | File | Status |
|---|---|---|---|
| 1 | Architecture Overview | `01-architecture-overview.md` | Done |
| 1b | Hosting Compatibility Addendum (GoDaddy) | `01b-hosting-compatibility-godaddy.md` | Done |
| 2a | Schema: Enrollment + Billing/Credits | `02a-schema-enrollment-billing.md` | Done |
| 2b | Schema: Assessment + Scheduling | `02b-schema-assessment-scheduling.md` | Done |
| 2c | Schema: Classroom + Content + Digital Notebook | `02c-schema-classroom-content.md` | Done |
| 2d | Schema: Sandbox + Collaborative Coding + Code Replay + AI | `02d-schema-sandbox-collab-ai.md` | Done |
| 2e | Schema: Projects + Gamification + Achievement Wall + Support + PTM + Calendar + Communication | `02e-schema-projects-gamification-support.md` | Done |
| 3a | Workflows: Lead→Enrollment, Account, Credits (lifecycle 1-3) | `03a-workflows-enrollment-credits.md` | Done |
| 3b | Workflows: Assessment, Batch Allocation, Scheduling (4-6) | `03b-workflows-assessment-scheduling.md` | Done |
| 3c | Workflows: Live Classroom, Attendance, Reschedule, Teacher Change (7-10) | `03c-workflows-classroom-attendance.md` | Done |
| 3d | Workflows: Recordings, Video Library, Materials, Assignments (11-14) | `03d-workflows-content-assignments.md` | Done |
| 3e | Workflows: Coding Sandbox, AI Doubt Solver, AI Coding Assistant (15-17) | `03e-workflows-sandbox-ai.md` | Done |
| 3f | Workflows: Assessments, Projects, Publishing, Progress Analytics (18-21) | `03f-workflows-assessment-projects.md` | Done |
| 3g | Workflows: AI Risk Detection, Parent Visibility/Reports, Payments (22-25) | `03g-workflows-risk-parent-billing.md` | Done |
| 3h | Workflows: Completion, Certificates, Renewal, Referrals, Support (26-30) | `03h-workflows-completion-growth.md` | Done |
| 3i | Workflows: Gamification, Digital Notebook, Collaborative Coding (31-33) | `03i-workflows-notebook-collab.md` | Done |
| 3j | Workflows: Live Quizzes, Code Replay, Offline Access, Calendar Sync (34-37) | `03j-workflows-quizzes-replay-offline.md` | Done — also adds `live_quizzes`/`live_quiz_responses`/`offline_downloads` schema not covered in Phase 2 |
| 3k | Workflows: Achievement Wall, PTM Booking (38-39) | `03k-workflows-showcase-ptm.md` | Done |
| 4a | APIs: Conventions, Auth/Account, Enrollment, Credit Wallet (1-3) | `04a-apis-conventions-enrollment-billing.md` | Done |
| 4b | APIs: Assessment, Batch Allocation, Scheduling (4-6) | `04b-apis-assessment-scheduling.md` | Done |
| 4c | APIs: Live Classroom, Attendance, Recordings, Video Library, Materials (7-8, 11-13) | `04c-apis-classroom-content.md` | Done |
| 4d | APIs: Assignments, Coding Sandbox, AI Doubt Solver, AI Coding Assistant (14-17) | `04d-apis-assignments-sandbox-ai.md` | Done — `assignments`/`assignment_submissions` reuse the existing Admin-panel tables (`database/schema.sql`), amended with the `draft` status + `extended_due_date` that `3d` already called for but never materialized as SQL |
| 4e | APIs: Assessments (exams), Project Lifecycle + Publishing, Progress Analytics (18-21) | `04e-apis-assessments-projects.md` | Done |
| 4f | APIs: AI Risk Detection (internal), Parent Visibility, Monthly Reports, Payments (22-25) | `04f-apis-parent-billing.md` | Done |
| 4g | APIs: Completion, Certificates, Renewal, Referrals, Support (26-30) | `04g-apis-completion-growth.md` | Done |
| 4h | APIs: Gamification, Digital Notebook, Collaborative Coding (31-33) | `04h-apis-notebook-collab.md` | Done |
| 4i | APIs: Live Quizzes, Code Replay, Offline Access, Calendar Sync (34-37) | `04i-apis-quizzes-replay-offline.md` | Done |
| 4j | APIs: Achievement Wall, PTM Booking (38-39) | `04j-apis-showcase-ptm.md` | Done — closes out Delivery Phase 4 in full |
| 5a | AI Workflows: Gateway Architecture, RAG Retrieval, Cost Control, Moderation | `05a-ai-gateway-rag-cost-control.md` | Done |
| 5b | AI Workflows: Doubt Solver + Coding Assistant prompt design, sandbox-verification loop | `05b-ai-doubt-solver-coding-assistant-prompts.md` | Done |
| 5c | AI Workflows: Notebook AI prompts, Risk/Report narrative generation | `05c-ai-notebook-risk-report-prompts.md` | Done |
| 5d | AI Workflows: AI-scored placement assessment, project originality check, course recommendation engine | `05d-ai-placement-originality-recommendations.md` | Done — closes out Delivery Phase 5 in full |
| 6 | Communication Orchestration Engine | `06-communication-engine.md` | Done |
| 7 | Scaling Strategy (100 → 100,000+ students) | `07-scaling-strategy.md` | Done |
| 8 | Infrastructure & DevOps | `08-infrastructure-devops.md` | Done — closes out the entire Delivery Program (35 documents, Phases 1–8) |

## Delivery Program: complete

All 8 phases (35 documents) are done — full architecture, schema, workflows, APIs, AI design, communications, scaling, and infra/DevOps for the CodeGurukul Student/Parent portal, covering all 39 original lifecycle phases end to end.

## Key decisions locked in Phase 1

- **Stack**: Student + Teacher portals built in Laravel + React (new), sharing the *same* MySQL database as the existing manual-PHP-MVC Admin panel (`codegurukul` schema, extended not replaced). Admin panel stays on its current stack — strangler-fig pattern, not a big-bang rewrite.
- **Compliance baseline**: Platform serves minors (8–18). Parental consent, data minimization for under-13s, and recording consent are first-class schema/workflow concerns, not bolted on later.
- **Scope sequencing**: Full equal depth requested across all 39 workflow phases — delivered progressively across many turns per the index above.
