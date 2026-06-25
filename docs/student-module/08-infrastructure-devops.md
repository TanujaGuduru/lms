# Delivery Phase 8 — Infrastructure & DevOps

The final delivery phase. Covers CI/CD, environment topology, monitoring/alerting, and disaster recovery — for **both** the AWS-target path (`01`) and the GoDaddy-compatible path (`01b`), since this program has maintained both as real, equally-supported deployment targets throughout (`01b`'s opening line: "Everything here also works unmodified on GoDaddy VPS/dedicated"), not the second as a temporary stopgap to be abandoned at the first opportunity.

---

## 1. CI/CD

### AWS path
GitHub Actions → lint/test → build Docker image → push to ECR → ECS rolling deploy, gated on health checks, with automatic rollback on a failed check (`01`'s stated pipeline, detailed here). **Migrations run as their own pre-deploy step** — a one-off ECS task, not inside application boot — because running migrations from inside multiple concurrently-starting rolling instances would race against each other.

### GoDaddy path
No containers, no ECR — genuinely simpler. GitHub Actions → SSH (or the cPanel API) → `git pull` + `composer install --no-dev --optimize-autoloader` + `php artisan migrate --force` + cache rebuild, directly on the box. This is the standard, well-established pattern for deploying Laravel to shared/VPS hosting — not a compromise unique to this project, and genuinely sufficient at Stage 0/1 scale (`07`).

### The one migration discipline that matters regardless of path
The Admin PHP app and the new Laravel app read/write the **same** MySQL schema (`01`'s strangler-fig decision), but only the Laravel side has migration tooling — the Admin codebase's raw SQL queries have no migration awareness to break gracefully against. So: **every migration touching a table the Admin app also reads/writes is additive-only** — never a rename or drop of a column/table — and ships with a check (a grep across the Admin codebase for that table/column) as part of the same PR, not a separate, skippable step someone forgets to run.

---

## 2. Environment Topology

dev / staging / prod, with one constraint specific to this platform's user base stated explicitly because it's easy to get wrong by default: **staging never holds real student PII.** Given the user base is minors, QA running against a "sanitized" copy of production data is a real, recurring source of accidental PII leaks in practice — staging runs against synthetic/seeded fixtures instead, full stop, not a name-swapped prod export. On AWS this means separate Aurora instances/ECS services per environment once at Stage 2+ scale (`07`); on GoDaddy, staging is a subdomain on its **own** database, never a shared DB with a "staging_" table-prefix convention — a prefix convention is exactly the kind of thing a careless query bypasses someday.

---

## 3. Monitoring & Alerting

Generic infra metrics (ECS CPU/memory, ALB 5xx rate, Aurora connections/replica lag, queue depth) are table stakes and not detailed further here. The business-specific alerts, calibrated to this platform's actual risk profile rather than a generic checklist, are the part worth specifying:

- **Live-class join-failure rate spiking** — `01`'s stated highest-priority SLA ("a dropped class directly damages trust in a premium product"); this is the single most important alert in the entire system, full stop.
- **AI Gateway fallback-to-secondary-provider rate** (`05a`) — a rising rate is the leading indicator of a primary-provider outage, catchable before students notice degraded response quality or latency.
- **Credit-ledger reconciliation drift** (`02a`'s nightly ledger-vs-balance reconciliation job) — any nonzero drift pages someone immediately, since `02a` already established that drift specifically means an application bug exists, not background noise to dashboard and ignore.
- **Communication-queue backlog depth** — the exact same metric that's also `07`'s concrete trigger signal for the Redis crossover. Worth flagging explicitly that this is one number serving two purposes (an operational alert *and* a scaling-decision input), not two separate things that happen to look similar.
- **Support-ticket SLA breaches** (`03h`/`04g`) — already specified at the application level as "alerts a supervisor"; this is where that becomes an actual PagerDuty/Opsgenie integration with a real on-call rotation behind it, not a database flag nobody is actually paged on.

**GoDaddy-specific constraint**: no CloudWatch-equivalent deep infrastructure visibility exists underneath a shared-hosting account — monitoring leans more heavily on application-level `/health` endpoints polled by an external uptime service (UptimeRobot/Pingdom), since there's no infrastructure console to inspect the way there is on AWS.

---

## 4. Disaster Recovery

**AWS**: Aurora automated backups + point-in-time recovery; multi-AZ failover (`01`'s stated availability driver). For a live class specifically, `03c` already specifies the *business* response to a platform-wide outage — class-level cancellation, no credit deduction for anyone in it, a makeup class offered, detected via a spike of simultaneous disconnects across a class (or many at once). This phase adds the infrastructure layer **underneath** that detection: an Aurora failover event or an ECS task replacement happening concurrently with that disconnect spike should surface to ops as one correlated signal, not two unrelated alerts that happen to fire near each other — knowing *why* the spike happened is what turns "something broke" into "the database failed over, and recovered in 40 seconds," which materially changes how ops responds.

**GoDaddy**: cPanel's own backup tooling is **never treated as the sole backup** — an independent nightly `mysqldump` piped directly to S3 runs regardless of whatever GoDaddy's own backup product does, specifically because relying solely on the hosting provider's own backup as the *only* copy is a single point of failure if that account itself is ever compromised, suspended, or simply mismanaged.

**Recordings/attachments in S3** already get lifecycle-managed cold storage (Glacier, `01` §5) — S3's own durability guarantees mean this needs no separate DR design layered on top.

**Infrastructure-as-code** (Terraform, for the AWS resources) — stated once as a governing principle rather than modeled module-by-module here: the AWS environment should be reproducible from version-controlled definitions, not hand-clicked through a console, so a region issue or account problem means re-applying code, not rebuilding from institutional memory.

---

## Delivery Program: complete

All eight delivery phases (`1`, `1b`, `2a`–`2e`, `3a`–`3k`, `4a`–`4j`, `5a`–`5d`, `6`, `7`, `8` — 35 documents in total) are done, covering the full architecture for the CodeGurukul Student/Parent portal end to end: system architecture and GoDaddy-vs-AWS hosting duality, database schema for all 39 lifecycle phases, business workflows and edge cases for each, the complete student/parent-facing REST API catalog, AI prompt/Gateway design for every AI-touching feature, the cross-cutting Communication Engine, a scaling strategy from 100 to 100,000+ students, and now CI/CD, environments, monitoring, and disaster recovery.

Two corrections were made and recorded along the way rather than silently patched: the `assignments`/`assignment_submissions` schema mix-up caught and fixed in `04d`, and the originally-missing API coverage for lifecycle phase 14 (Assignments) folded into the same document. Both are visible in `00-master-index.md`'s status notes for anyone picking this back up later.
