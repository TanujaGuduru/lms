# Delivery Phase 7 — Scaling Strategy (100 → 100,000+ Students)

Consolidates every cost-crossover decision flagged as "revisit at scale" throughout this program (`01`, `01b`, `02d`, `02e`) into one decision framework — concrete trigger signals, not just "once volume justifies it," plus the schema-growth and contention concerns at the 100k tier that haven't been addressed yet anywhere in this series.

---

## 1. Growth-Stage Model

Four stages, each with a concrete operating band — a scaling strategy without concrete numbers is just a menu of options with no decision rule attached:

| Stage | Student count | Hosting | What's true here |
|---|---|---|---|
| **0 — Launch** | 100–2,000 | GoDaddy shared (`01b`) | Everything per the hosting addendum: MySQL-driver queue/cache, Pinecone, MySQL FULLTEXT search, cron-batch comms. |
| **1 — GoDaddy ceiling** | ~2,000–10,000, or earlier if concurrency spikes | GoDaddy → AWS migration | The first and biggest crossover — see §2's first row for the concrete trigger. |
| **2 — AWS growth** | 10,000–50,000 | AWS, modest instance sizes | Read replica, Redis, and the comms queue's persistent worker all come online together (§4). |
| **3 — AWS at target scale** | 50,000–100,000+ | AWS, full `01` §7 design | Approaching `01`'s stated design target: ~5,000 concurrent live-class participants at peak evening hours (§3). Sandbox/SFU/search crossovers evaluated independently, on their own signals, not bundled with a stage boundary. |

---

## 2. Consolidated Crossover Table

| Component | v1 (Stage 0) | Upgrade | Concrete trigger signal |
|---|---|---|---|
| Hosting | GoDaddy shared | AWS (ECS Fargate, per `01`) | Concurrent live-class participants at evening peak regularly approaching shared-hosting's practical ceiling (realistically a few hundred concurrent connections), **or** sustained MySQL connection-limit errors during peak hours, **or** the `ai_messages` archival job (`02d`) firing more frequently than its design intent because the database-size cap keeps getting approached despite it running. Any one of these alone is sufficient — this is a hard ceiling, not a soft cost optimization like the rows below. |
| Cache/sessions/queue | MySQL `database` driver | Redis (ElastiCache) | Queue backlog depth consistently nonzero at the top of each cron minute (`01b`'s 60-second batch cadence starting to visibly lag user-facing sends), **or** session-table contention showing up in slow-query logs during peak concurrent logins. |
| Leaderboard | Indexed MySQL query (`02e`) | Redis sorted sets | Same Redis-availability trigger as above — `02e` already states this is the same underlying decision, not a separate one; once Redis exists for the queue, moving the leaderboard onto it is close to free. |
| Vector DB (RAG) | Pinecone | pgvector on Aurora | **Pure cost crossover, not a capability one** (`01`'s explicit framing) — Pinecone's per-query/storage cost at the platform's actual embedding volume (course content + every Doubt Solver/Coding Assistant RAG call + Notebook AI) exceeds the engineering cost of operating pgvector on an Aurora instance that already exists for everything else. Only relevant once off GoDaddy — `01b` already established pgvector has no path on GoDaddy's MySQL-only hosting. |
| Sandbox isolation | Piston | Firecracker microVMs | Sustained concurrent executions exceeding what Piston's per-container model isolates/throughputs cheaply at acceptable latency, **or** — independent of volume — a real attempted sandbox-escape incident, which raises the required isolation bar regardless of how much traffic is flowing. |
| Live video SFU | Agora (managed) | Self-hosted (mediasoup/Janus) | **A finance/headcount crossover, not a technical one** — Agora's usage-based cost at sustained ~5,000 concurrent peak (`01`'s stated target, §3 below) exceeds what a dedicated SFU/network-ops team would cost to run instead. Worth stating plainly because this one is a different *kind* of decision than every other row — it's "hire people," not "flip a config." |
| Search | MySQL FULLTEXT | Bonsai/Elastic Cloud → OpenSearch | FULLTEXT's lack of fuzzy/semantic matching becomes a real, recurring support complaint ("I know that's in my notes, search didn't find it"), **or** transcript/notes search query latency visibly degrades as content volume grows. |
| Read traffic | Single Aurora primary | + read replica | Student-app read traffic (dashboards, progress analytics, leaderboard, recommendations) materially exceeds the existing Admin panel's read load (`01`'s exact stated trigger), **or** read-heavy, replica-lag-tolerant queries start contending with write-heavy paths (credit deductions, attendance) on the primary. |

---

## 3. Live-Class Concurrency — the actual capacity anchor

`01` §7 already states the number this whole program designs against: **~5,000 concurrent live-class participants at peak evening hours, at the 100k-student tier.** Restated here because it's the concrete anchor for two different sizing exercises that are easy to conflate:
- **Agora's cost model** scales against this number directly (Agora is a managed service — the media itself isn't something this platform's own infrastructure has to handle).
- **The ECS auto-scaling group for the orchestration layer** scales against something much smaller — Laravel's job for those same 5,000 sessions is just session metadata, attendance writes, and recording triggers (`01` §3), not the media stream. Sizing the API tier for "5,000 concurrent video streams" worth of compute would be a significant over-provisioning; it should be sized for "5,000 concurrent lightweight metadata-write sessions" instead, which is a much smaller number.

---

## 4. Order of Operations

Real growth doesn't trigger these one at a time — give a sequencing rule for when several signals fire close together:

1. **GoDaddy → AWS first, if still on GoDaddy.** Every other row's trigger signal in §2 is calibrated assuming AWS-scale infrastructure already exists underneath it — optimizing for AWS-specific signals while still bound by GoDaddy's hard ceiling is wasted effort until that ceiling is actually lifted.
2. **Aurora read replica next.** Cheapest, most mechanical change once on AWS — unlocks headroom for read-heavy features without any application-level architecture change.
3. **Redis next.** One infrastructure addition resolves three crossover rows at once (queue, sessions, leaderboard) — genuinely efficient to do together rather than staged separately.
4. **Pinecone→pgvector, Piston→Firecracker, and Agora→self-hosted SFU are independent and lower-priority** — each is its own cost/ops tradeoff with its own specific trigger signal, not on the critical path of the first three structural changes, and shouldn't be bundled with them just because they're all "scaling work."

---

## 5. Schema-Growth Concerns Not Yet Addressed Anywhere in This Series

**Append-only ledgers grow unboundedly by design** (`credit_transactions`, `xp_transactions`, `ai_messages`, `code_executions`, `exam_responses`, `communication_logs`) — that's the correct tradeoff for audit integrity, but at the 100k-student tier it's genuinely large. The mitigation already exists for one table (`02d`'s monthly `ai_messages` → S3 archival, keeping the summary row, dropping the body) — this generalizes to every other high-volume ledger once each hits a comparable size profile, rather than being a one-off special case for AI messages specifically. MySQL native range partitioning on `created_at` (monthly partitions) on the highest-volume tables is the mechanical complement to archival — partitioning keeps recent-data queries fast, archival keeps total size bounded.

**`domain_events` is the one exception to "never delete a ledger row"** — once *both* `processed_by_admin` and `processed_by_student_app` are true and a retention window has passed (e.g. 30 days), a row has done its entire job (handing an event from one runtime to the other) and has no ongoing audit value the way a financial or XP ledger does. An outbox table that never prunes processed rows just grows forever for no benefit — worth stating explicitly since every other table in this design is described as append-only-forever, and this is the deliberate exception, not an oversight.

**Shared-database contention between the existing Admin PHP app and the new Student/Parent Laravel app** at real scale: both write to overlapping tables (`users`, `enrollments`, `payments`). A read replica per app helps reads, but writes still funnel to one primary. The leading indicator to watch, before a dedicated-primary-per-app split would ever be on the table (a much bigger change, deliberately out of scope here): primary write IOPS specifically attributable to the *new* app's tables (`xp_transactions`, `ai_messages`, `code_executions` — all genuinely new load profiles the Admin panel never had) climbing relative to the Admin panel's existing, already-proven write pattern.

---

## Next

**Delivery Phase 8 — Infrastructure & DevOps**: CI/CD pipeline detail, environment topology (dev/staging/prod), monitoring/alerting, and disaster recovery/backup strategy. The final delivery phase — closes out the entire architecture program. Say "continue."
