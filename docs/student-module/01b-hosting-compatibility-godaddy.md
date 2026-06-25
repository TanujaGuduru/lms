# Addendum to Phase 1 — Hosting Compatibility: GoDaddy

Supersedes the AWS-specific service choices in `01-architecture-overview.md` §2 where noted. Assumes **GoDaddy shared/cPanel hosting** (same `public_html` layout as the Admin panel) — the most restrictive case. Everything here also works unmodified on GoDaddy VPS/dedicated.

## What changes

| Layer | Phase 1 original | GoDaddy-compatible replacement | Why |
|---|---|---|---|
| Primary DB | AWS Aurora MySQL | **GoDaddy-provided MySQL 8** (cPanel MySQL Database Wizard) | Schema design in Phase 2 uses no Aurora-specific features — it's portable as-is. |
| Cache / sessions / queue backend | Redis (ElastiCache) | **MySQL `database` driver** (Laravel's built-in DB session/cache/queue driver) | Redis usually isn't available on shared plans. Slightly slower than Redis, fine at low-to-mid scale; revisit if/when moving to a VPS or cloud host that offers Redis. |
| Background jobs (Laravel Queues) | Persistent `queue:work` daemon, SQS | **Cron-driven batch draining**: cPanel Cron Job runs `php artisan schedule:run` every minute, which internally triggers `queue:work --stop-when-empty --max-time=50` | Shared hosting kills long-running processes; this is the standard, well-established pattern for running Laravel queues on shared hosting. Jobs run in short bursts every minute instead of instantly — acceptable for AI report generation, certificate generation, notification fan-out; not acceptable for anything needing sub-second response (handled differently, see below). |
| Real-time / WebSockets (live quiz, collaborative cursors, chat, presence) | Self-hosted Laravel Reverb | **Pusher or Ably** (managed WebSocket SaaS, first-class Laravel Broadcasting drivers) | The GoDaddy app publishes events via a normal HTTPS API call; the browser connects directly to Pusher/Ably's infrastructure, not to GoDaddy at all. Completely sidesteps the "no persistent processes" limitation. |
| Code execution sandbox | Self-hosted Piston/Firecracker on ECS | **Separate small dedicated host** (cheap VPS — e.g., a $6–12/mo droplet) running Piston in Docker, called by the GoDaddy app over HTTPS | Non-negotiable: cannot run on shared hosting (no containers/root) and should never share a box with production data even where it technically could. This is one piece that genuinely cannot live inside a GoDaddy shared plan — budget for one small, isolated VPS just for this. |
| Video transcoding | AWS MediaConvert | **Mux, Cloudinary, or api.video** (managed video API) | Same logic as Agora for live video: a SaaS handles upload → transcode → adaptive streaming → storage, called via API. Also avoids CPU-heavy transcoding work a shared/small VPS plan can't handle anyway. |
| DRM / offline access | AWS MediaPackage | Token-gated signed URLs + AES-128 HLS via the chosen video API (Mux/Cloudinary both support this) | Full studio-grade DRM (Widevine) is unnecessary cost/complexity for this risk profile; covered in detail in the Phase 36 workflow doc. |
| Vector DB (AI/RAG) | Pinecone or pgvector | **Pinecone only** | pgvector assumed a self-hosted Postgres, which doesn't exist on GoDaddy's MySQL-based hosting. Pinecone is pure API, no change needed. |
| Object storage | AWS S3 | **AWS S3, unchanged** | S3 is just an API — works identically regardless of where the calling app is hosted. Use the `aws-sdk-php` package or Laravel's S3 filesystem driver from GoDaddy. |
| Search (transcripts, notes) | AWS OpenSearch | **MySQL FULLTEXT indexes** for v1; Bonsai.io or Elastic Cloud (managed, API-based) as the upgrade path once search sophistication/volume demands it | Self-hosted OpenSearch isn't available on GoDaddy; FULLTEXT is free, built into MySQL, and genuinely sufficient until you need fuzzy/semantic search at scale. |
| CDN | AWS CloudFront | **Cloudflare** (free tier) in front of the GoDaddy domain | Trivial DNS-level setup in front of any host; gives CDN caching + DDoS protection regardless of underlying hosting. |
| LLM access | AWS Bedrock (Claude) | **Direct Anthropic API / OpenAI API calls** over HTTPS | Bedrock specifically requires AWS hosting context to be the natural choice; calling the provider's API directly works identically from anywhere, including GoDaddy. |
| Live classroom video | Agora.io | **Unchanged** | Agora is pure SaaS + client SDK — hosting location is irrelevant. |

## What this means practically

- **The Laravel app + MySQL database are the only things that actually live "in GoDaddy."** Everything else — video, AI, vector search, code execution, real-time messaging, video transcoding — is an external API call. This is true regardless of which GoDaddy tier you're on, and it's the right architecture even if you eventually move off GoDaddy entirely, since none of these integrations are hosting-dependent.
- **One hard requirement that isn't optional:** the code execution sandbox needs its own small, separate, isolated server. It cannot be "GoDaddy only." Budget for one cheap VPS (DigitalOcean, Linode, or a GoDaddy VPS plan itself) dedicated to nothing but running student code in disposable containers.
- **Database schema (Phase 2) is unaffected** — every table designed so far and going forward uses standard MySQL 8, no Aurora-specific syntax, so it runs identically on GoDaddy's MySQL as it would on AWS.

Continuing with Phase 2b now.
