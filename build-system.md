# System Design: Distributed Build System

> FAANG-level interview guide — Git-triggered CI/CD build pipeline with artifact management, status tracking, and retries.
> Think: GitHub Actions, Google Cloud Build, Bazel Remote Execution.

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | **Detect changes** in Git repositories (push, PR, tag) and trigger builds automatically |
| FR-2 | **Execute build jobs** in isolated environments (containers/VMs) |
| FR-3 | **Stream build logs** in real time to the user |
| FR-4 | **Upload artifacts** (binaries, Docker images, test reports) to artifact storage on success |
| FR-5 | **Track build status**: queued → running → succeeded / failed / cancelled |
| FR-6 | **Retry failed builds** automatically (configurable: max retries, backoff, conditions) |
| FR-7 | Support **pipeline DAGs** — multi-step builds with dependencies (build → test → deploy) |
| FR-8 | **Cancel** an in-progress build |
| FR-9 | Users can **manually trigger** a build (ad hoc, with custom parameters) |
| FR-10 | **Notifications** on build completion (Slack, email, GitHub status checks) |
| FR-11 | (Optional) **Incremental builds** — skip unchanged modules (Bazel/Nx-style) |
| FR-12 | (Optional) **Build caching** — reuse outputs for identical inputs |

**Out of scope:** Deployment orchestration (Kubernetes rollouts), secret rotation, cost billing per team.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 50,000 repositories; 1M builds/day; 10M build steps/day |
| **Availability** | 99.9% — build queue survives single-region failure |
| **Latency** | Build starts within **5 seconds** of Git event; log streaming p99 < 1s lag |
| **Throughput** | 10,000 concurrent build jobs across the fleet |
| **Consistency** | Build state transitions are atomic (no ghost "running" jobs after crash) |
| **Durability** | Build logs retained 1 year; artifacts retained per retention policy (30–365 days) |
| **Isolation** | Each build runs in a clean, isolated environment — no cross-job contamination |
| **Idempotency** | Re-delivering the same Git webhook must not trigger duplicate builds |
| **Observability** | Full audit trail: who triggered, what commit, which worker, how long each step |

---

## 3. Back-of-Envelope Estimation

### Traffic

```
Builds/day                   = 1,000,000
Builds/sec (avg)             = 1M / 86,400 ≈ 12 builds/sec
Peak (deploy window, 10×)    ≈ 120 builds/sec

Build steps/build (avg)      = 10 (lint, compile, test, package, scan, publish...)
Build steps/day              = 10M
Concurrent builds            = 10,000

Git webhook events/day       = 5M (pushes, PRs, tags across 50K repos)
Webhook events/sec           = 58/sec (bursts to ~500/sec during working hours)
```

### Build Duration

```
Avg build duration           = 8 min = 480 sec
Total build-seconds/day      = 1M × 480 = 480M build-seconds/day
= 5,556 CPU-seconds/sec → requires ~5,600 concurrent workers at 100% utilization
With 30% headroom           → 7,300 workers minimum
Worker fleet size            = 10,000 workers (handles 10K concurrent builds)
```

### Storage

```
Build logs:
  Avg log size/build         = 5 MB (compressed)
  1M builds/day × 5 MB      = 5 TB/day
  1-year retention           = 5 TB × 365 ≈ 1.83 PB (with compression ~600 TB)

Artifacts:
  Avg artifact size          = 200 MB (Docker image, binary, test reports)
  20% of builds produce artifacts (milestone/main builds)
  200K artifacts/day × 200 MB = 40 TB/day (new uploads)
  With dedup/layer cache     ≈ 8–15 TB/day net new storage
  1-year artifact store      ≈ 3–5 PB

Build metadata (DB):
  Per build record           ≈ 2 KB (status, timestamps, commit SHA, config)
  1M builds/day × 2 KB      = 2 GB/day metadata
  1-year                     = 730 GB → fits in single sharded RDBMS
```

### Webhook Processing

```
Webhook payload              ≈ 5 KB
58 events/sec × 5 KB         = 290 KB/sec inbound → trivial
Dedup window                 = 10 seconds (idempotency key: repo + commit SHA + event_type)
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    GIT PROVIDERS                                             │
│      GitHub / GitLab / Bitbucket / self-hosted Gitea                        │
└──────────────┬───────────────────────────────────────────────────────────────┘
               │ Webhook (HTTPS POST on push/PR/tag)
               ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                    WEBHOOK GATEWAY                                           │
│  - Validate HMAC signature                                                   │
│  - Idempotency check (Redis, 10s window)                                    │
│  - Parse event → publish to Kafka topic: git-events                         │
└──────────────┬───────────────────────────────────────────────────────────────┘
               │ Kafka: git-events
               ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                    BUILD TRIGGER SERVICE                                     │
│  - Consume git-events                                                        │
│  - Load build config (.buildrc.yml / .github/workflows) from repo           │
│  - Evaluate trigger rules (branch filter, path filter, labels)              │
│  - Resolve pipeline DAG                                                      │
│  - Create Build record (status=QUEUED) in Build DB                          │
│  - Publish job(s) to Build Queue (Kafka: build-jobs)                        │
└──────────────┬───────────────────────────────────────────────────────────────┘
               │ Kafka: build-jobs (partitioned by priority + org)
               ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                    SCHEDULER / DISPATCHER                                    │
│  - Pulls jobs from queue                                                     │
│  - Matches job requirements → available worker (OS, arch, labels, resources)│
│  - Assigns job to worker via gRPC                                           │
│  - Tracks worker heartbeats (60s) → re-queue on worker death               │
└──────────────┬───────────────────────────────────────────────────────────────┘
               │ gRPC (assign job)
               ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                    WORKER FLEET (10,000 nodes)                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │  Worker Agent (runs on each node)                                        ││
│  │  1. Pull job spec                                                        ││
│  │  2. Provision isolated environment (Docker / gVisor / microVM / Firecracker)│
│  │  3. Clone repo at commit SHA                                             ││
│  │  4. Execute build steps (shell commands / actions)                      ││
│  │  5. Stream logs → Log Ingestion Service                                  ││
│  │  6. Upload artifacts → Artifact Store (S3)                              ││
│  │  7. Report step results → Build State Service                           ││
│  │  8. Teardown environment (wipe container)                                ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└──────┬───────────────────────┬────────────────────────┬──────────────────────┘
       │                       │                        │
┌──────▼──────┐  ┌─────────────▼──────────┐  ┌─────────▼──────────────────────┐
│  Build DB   │  │  Log Ingestion Service  │  │  Artifact Store                │
│ (PostgreSQL │  │  Kafka → ClickHouse     │  │  (S3 / GCS)                    │
│  + Redis)   │  │  + S3 raw log archive   │  │  + Artifact Registry           │
└──────┬───────┘  └─────────────┬──────────┘  └────────────────────────────────┘
       │                        │
┌──────▼──────────────────────────────────────────────────────────────────────┐
│                    API SERVICE (REST + WebSocket)                            │
│  - GET  /builds/{id}              → build status + step results             │
│  - GET  /builds/{id}/logs         → WebSocket log stream                   │
│  - POST /builds/{id}/cancel                                                  │
│  - POST /repos/{id}/builds        → manual trigger                          │
│  - GET  /repos/{id}/artifacts                                               │
└──────┬──────────────────────────────────────────────────────────────────────┘
       │
┌──────▼──────────────────────────────────────────────────────────────────────┐
│                    NOTIFICATION SERVICE                                      │
│  - GitHub Commit Status API (✓/✗ on PR)                                    │
│  - Slack webhook                                                             │
│  - Email (SES / SendGrid)                                                   │
│  - PagerDuty (on repeated main branch failures)                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Core API

```
// Webhook (inbound from Git provider)
POST /webhooks/github         { X-Hub-Signature-256, body: GitHubPushEvent }

// Build lifecycle
GET  /v1/builds/{buildId}                 → BuildStatus
GET  /v1/builds/{buildId}/steps           → StepResult[]
GET  /v1/builds/{buildId}/logs            → WebSocket stream
POST /v1/builds/{buildId}/cancel
POST /v1/repos/{repoId}/builds            → ManualTrigger { branch, commit, params }

// Artifacts
GET  /v1/builds/{buildId}/artifacts       → ArtifactMetadata[]
GET  /v1/artifacts/{artifactId}/download  → presignedUrl

// Status (for dashboards)
GET  /v1/repos/{repoId}/builds?branch=main&limit=50
GET  /v1/orgs/{orgId}/stats?from=2026-08-01
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Job Queue — Kafka vs. PostgreSQL SKIP LOCKED vs. Redis vs. SQS

| Approach | Ordering | Backpressure | Replay | Ops Complexity |
|----------|---------|-------------|--------|----------------|
| PostgreSQL `SKIP LOCKED` | ✅ Priority-aware | ✅ Natural | ❌ Hard | Low |
| Redis List / Sorted Set | Partial | ❌ Manual | ❌ No | Low |
| **Kafka (Recommended)** | ✅ Per-partition | ✅ Consumer lag | ✅ Replay | Medium |
| SQS / RabbitMQ | Partial | ✅ DLQ | ❌ Limited | Low |

```
PostgreSQL SKIP LOCKED (surprisingly good for build queues):
  SELECT * FROM jobs WHERE status='QUEUED' ORDER BY priority DESC, created_at
  FOR UPDATE SKIP LOCKED LIMIT 1

  Works well when:
    Concurrent workers < 500 (lock contention manageable)
    Queue depth < 100K (B-tree index fast)
    You need exactly-once claim semantics (UPDATE ... RETURNING atomically)

  Limitation: at 10,000 concurrent workers polling aggressively → DB thrashing
  Mitigation: long-poll (worker waits 30s on empty queue, not spinning)

Kafka wins at our scale (10K workers, 1M builds/day):
  - Partition by org_id: all builds from same org on same partition → ordering
  - Consumer group: each scheduler instance independently consumes partitions
  - No polling storm: Kafka push-based → workers consume their assigned partitions
  - Replay: if scheduler bug processes jobs wrong → replay from offset, re-process
  - Consumer lag = built-in queue depth metric → drives autoscaling
  - Retention: 7 days of build jobs retained → postmortem analysis

Priority within Kafka:
  Kafka has no native priority → use separate topics per priority tier:
    kafka:build-jobs-critical (1 partition, dedicated consumers)
    kafka:build-jobs-high     (10 partitions)
    kafka:build-jobs-normal   (50 partitions)
    kafka:build-jobs-low      (10 partitions)
  Scheduler: always drain critical before high before normal before low

Decision: Kafka with per-priority topics. PostgreSQL SKIP LOCKED as a
  valid simpler alternative at smaller scale — mention both.
```

---

### Trade-Off 2: Worker Isolation — Docker vs. gVisor vs. Firecracker vs. Full VM

| Isolation | Startup | Security | Overhead | Use Case |
|-----------|---------|----------|---------|---------|
| Docker (runc) | ~2s | Medium — shared kernel | Low | Trusted internal builds |
| gVisor (runsc) | ~3s | High — user-space kernel | Medium | Semi-trusted |
| **Firecracker MicroVM** | ~150ms | Very high — hypervisor | Very low | Multi-tenant |
| Full EC2 VM | ~30s | Highest — dedicated HW | High | macOS / Windows |

```
Why isolation matters in a build system:
  Builds run arbitrary user-supplied code (any shell command in .buildrc.yml)
  A malicious build could:
    - Escape container → access host filesystem, steal secrets of other builds
    - Consume unlimited CPU/RAM → starve other tenants
    - Exfiltrate data via DNS/network channels

Docker (runc) — the dangerous default:
  Shares Linux kernel with host
  Container escape CVEs exist (runc CVE-2019-5736 — root on host via symlink)
  Acceptable for internal builds where all repos are trusted
  NOT acceptable for public CI (GitHub Actions public runners face this)

gVisor:
  Google's project: intercepts syscalls at user-space kernel (Sentry)
  Malicious container cannot reach real kernel → escape much harder
  30-40% performance overhead (syscall interception is expensive)
  Used by: Google Cloud Run, some GitHub Actions runners

Firecracker (AWS's choice for Lambda):
  Lightweight KVM-based hypervisor: each build gets its own micro-VM
  Isolation: hypervisor-level (same as EC2) — not just kernel namespacing
  Boot time: 125ms (kernel is pre-loaded, only rootfs is fresh)
  Memory overhead: 5 MB per VM (vs 50-100 MB for full VM)
  AWS Lambda and Fargate use this internally

  Best for: multi-tenant CI (arbitrary public repos) — our target use case

Full VM (EC2):
  Needed for: macOS builds (Apple silicon, Xcode) — no other option
  Windows: can use Hyper-V or physical Windows machines
  Cost: dedicated bare metal for macOS → expensive

Decision: Firecracker for Linux (security + speed).
  Full VM for macOS/Windows builds.
  Docker only for internal/trusted-repo builds where speed matters most.
  The key interview insight: "isolation choice depends on trust level of code."
```

---

### Trade-Off 3: Scheduling Model — Push (Scheduler Assigns) vs. Pull (Workers Poll)

| Model | Scheduler Complexity | Worker Utilization | Latency to Start |
|-------|---------------------|-------------------|-----------------|
| **Push: scheduler assigns job to worker** | High — knows worker state | High — optimal assignment | Low |
| Pull: workers poll queue for jobs | Low — workers self-serve | Medium — may mismatch | Low |
| Hybrid: worker registers capability, scheduler matches | Medium | High | Low |

```
Pull model (used by Jenkins, simple Celery setups):
  Workers poll a shared queue (Redis/SQS/Kafka) for any available job
  Simple: no scheduler needs to track worker state
  Problem: job might require GPU worker but gets picked up by CPU worker
    → Worker must check compatibility → reject → re-queue → another worker tries
    → Wasted poll cycles; potential starvation for specialized jobs

Push model (used by GitHub Actions, Borg):
  Scheduler maintains registry of all workers and their capabilities
  On new job: query registry → find best match → gRPC push to specific worker
  Worker ACKs → job is RUNNING

  Advantages:
    Optimal placement: scheduler knows exact worker state (labels, free resources)
    GPU jobs only go to GPU workers — no capability mismatch
    Fair scheduling: scheduler controls assignment → enforce per-org quotas
    Priority: CRITICAL jobs jump the queue and get the best available worker

  Disadvantages:
    Scheduler is stateful → must recover worker registry on crash
    gRPC push: what if worker is unreachable at push time?
      → Scheduler marks worker DEAD → finds next available → retry push

Worker heartbeat + recovery:
  Workers send heartbeat every 30s to scheduler
  If heartbeat missed for 90s → worker marked DEAD
  All RUNNING jobs on dead worker → status: QUEUED, retry_count++
  Re-dispatched to next available matching worker

Decision: Push model with gRPC assignment + heartbeat-based dead worker detection.
  Pull as the fallback if gRPC push fails (re-enqueue to Kafka consumer).
```

---

### Trade-Off 4: Build State Storage — PostgreSQL vs. Redis vs. Dedicated State Machine

| Approach | Consistency | Query Power | Latency | Crash Recovery |
|----------|------------|------------|---------|----------------|
| **PostgreSQL (source of truth)** | ✅ ACID | ✅ Rich SQL | Medium | ✅ WAL |
| Redis only | ❌ No persistence | ❌ Limited | < 1ms | ❌ Data loss on crash |
| PostgreSQL + Redis (layered) | ✅ + ✅ | ✅ SQL + fast reads | Fast reads | ✅ |

```
Why PostgreSQL as source of truth for build state:

Build state transitions must be atomic:
  UPDATE jobs SET status='RUNNING', worker_id=$1 WHERE id=$2 AND status='QUEUED'
  RETURNING id
  → This UPDATE is the single atomic claim — exactly one worker wins
  → Cannot do this atomically with Redis alone (Redis doesn't support conditional
    multi-key atomics without Lua scripts, which add complexity)

ACID properties needed:
  Two workers must not both transition the same job to RUNNING (lost update)
  Payment (artifact billing) cannot be double-processed
  Build history queries: "all failed builds on main branch last 30 days"
    → SQL query with WHERE + ORDER BY → PostgreSQL shines

Redis as read cache:
  Build status pages poll every 2 seconds → 10K concurrent users = 20K reads/sec
  Redis GET build:status:{buildId} → < 1ms
  PostgreSQL for same: ~5ms + connection overhead
  Cache-aside: write to PostgreSQL → SETEX build:status:{buildId} 300 {status_json}
  On build completion: DEL key (force next read from PostgreSQL)

State machine durability:
  Worker crashes mid-build: PostgreSQL still has status=RUNNING
  Heartbeat timeout → PostgreSQL UPDATE status='QUEUED' (re-queue)
  No Redis dependency for recovery: PostgreSQL is ground truth even if Redis is down

Decision: PostgreSQL as source of truth (ACID state transitions).
  Redis as read cache for high-frequency status polls.
  This is the right layered architecture — state in durable store, cache for reads.
```

---

### Trade-Off 5: Log Streaming — Synchronous vs. Asynchronous Delivery

| Approach | Real-Time | Durability | Back-Pressure | Complexity |
|----------|----------|-----------|--------------|------------|
| Sync (worker → API → client) | ✅ Immediate | ❌ Lost if API crashes | Hard | Medium |
| **Async (worker → Kafka → Redis Stream → WebSocket)** | ✅ < 1s lag | ✅ Kafka retains | ✅ Natural | High |
| S3 batch (upload at end) | ❌ No real-time | ✅ Durable | N/A | Low |

```
Why logs cannot be synchronous:
  Worker → API Server direct path: if API server crashes → log connection drops
  Client must reconnect → where does replay start? API server has no state
  At 10K concurrent builds × 4 KB/s log rate = 40 MB/s through API servers → bottleneck

Async pipeline (recommended):
  Worker → gRPC stream → Log Ingestion Service → Redis Stream (real-time buffer)
                                               → Kafka → S3 (durable archive)

  Redis Stream (XADD/XREAD):
    Acts as circular buffer per build (last 10K lines)
    XREAD BLOCK: API server blocks waiting for new entries → pushes to WebSocket
    On reconnect: XREAD from last seen ID → instant replay (no missed lines)
    TTL: 1 hour (in-progress builds); on build complete → trim → only S3 survives

  Kafka as durable pipe:
    Log chunks also published to Kafka (same Log Ingestion consumer)
    Kafka consumer → S3 writer (batches every 5 seconds)
    Log chunk order guaranteed by Kafka partition (partitioned by buildId)

  Back-pressure handling:
    If S3 writer is slow → Kafka buffers → Kafka partition lag increases
    Does NOT block the worker (gRPC stream to Log Ingestion is independent)
    Log Ingestion → Redis is fast (in-memory) → never blocks worker

Client side:
  WebSocket: API server reads Redis XREAD BLOCK → push to client as JSON frames
  Reconnect: client sends { lastSeqId } → XREAD from that ID → seamless resume
  Build complete: client switches to S3 presigned URL for full log download

Decision: Async pipeline with Redis Stream as real-time buffer + S3 as durable store.
  The key insight: decouple worker execution from log delivery.
  Worker should never block waiting for a log consumer — logs are fire-and-forget.
```

---

### Trade-Off 6: Build Caching — Local Worker Cache vs. Shared Remote Cache

| Approach | Cache Hit Rate | Cross-Build Reuse | Security Risk | Cost |
|----------|---------------|------------------|--------------|------|
| Local disk cache (per worker) | Low — worker changes each job | ❌ | None | Zero |
| Shared NFS / EFS | Medium | Partial | Medium | High (EFS cost) |
| **Shared S3 remote cache (Recommended)** | High — org-wide | ✅ | Managed | Low |
| CDN-fronted S3 cache | Highest — global | ✅ | Same | Medium |

```
Local worker disk cache:
  Worker caches node_modules/ after first build
  Next build on SAME worker: cache hit
  Problem: 10,000 workers; builds randomly assigned to different workers
  Hit rate: 1/10,000 chance of landing on same worker → ~0% effective hit rate

Shared NFS (EFS):
  All workers mount same EFS → share cache directory
  Cache hit rate: excellent (one copy for all workers)
  Problems:
    EFS throughput: at 10K concurrent builds each reading 500MB cache → 5 TB/s → $$$
    Single point of contention: NFS at this scale becomes a bottleneck
    Latency: EFS p99 50ms vs. S3 GET 30ms (similar, but EFS more variable)

Shared S3 remote cache (optimal for build systems):
  Cache key: sha256(lockfile) → s3://build-cache/{orgId}/{sha256}/deps.tar.gz
  Cache PUT: after successful dependency restore (< 1 write per unique lockfile)
  Cache GET: before dependency install → if hit, download + extract
  Hit rate: ~85% for dependency caches (lockfiles rarely change per branch)

  Content-addressed build cache (Bazel Remote Cache API):
    Key: sha256(action_inputs + toolchain) → s3://build-cache/{digest}/output
    True incremental build: if inputs haven't changed → use cached output
    Hit rate: 60-80% for typical code changes (most modules unchanged)

  S3 advantages:
    Reads parallel by default (presigned URLs, no coordination)
    Regional: deploy S3 bucket in same region as workers → 30ms GET
    Versioning: immutable keys (content-addressed) → no invalidation complexity

Cache poisoning risk (critical for security):
  Fork PR submits malicious build output → cached as org build cache
  Next legitimate build uses poisoned cache → supply chain attack

  Mitigation:
    Separate cache namespaces by trust level (see §5.10 in Deep Dive)
    Fork PRs: read-only from parent cache; writes to isolated namespace
    Signed cache entries: HMAC of (cacheKey + buildId) stored alongside entry
      → Verifies cache was written by trusted build, not injected externally

Decision: Shared S3 remote cache with trust-level namespacing.
  Content-addressed keys (no invalidation needed).
  Mention cache poisoning risk proactively — shows security awareness.
```

---

### Trade-Off 7: Monorepo vs. Polyrepo Build Strategy

| Strategy | Build Scope | Dependency Management | Team Autonomy | CI Complexity |
|----------|------------|----------------------|---------------|---------------|
| Full rebuild (monorepo, naive) | All packages | Implicit | Low | Low |
| **Affected-package detection (Recommended)** | Changed + dependents | Explicit graph | Medium | High |
| Polyrepo (one repo per service) | Per-repo only | Version pinned | High | Low per repo |

```
Naive monorepo CI problem:
  200-package monorepo; developer changes 1 file in libs/auth
  Naive: rebuild all 200 packages → 30 minutes (all failing fast, 3 minutes realistic)
  With affected detection: rebuild only auth + its 3 consumers → 2 minutes
  90% CI time reduction from this single optimization

Affected package detection (critical for FAANG-scale monorepos):
  Step 1: git diff --name-only {base}..{head} → [libs/auth/src/token.ts]
  Step 2: file → package mapping → @company/auth
  Step 3: reverse dependency graph traversal (BFS):
    @company/api imports @company/auth → include @company/api
    @company/frontend imports @company/api → include @company/frontend
    Total affected: 3 packages (not 200)

Dependency graph storage:
  Built at analysis time by parsing import statements (TypeScript, Go, Java)
  Stored in Redis adjacency list:
    SADD pkg_deps:@company/api @company/auth @company/logger
    SADD pkg_rev_deps:@company/auth @company/api @company/frontend
  Updated on every merge to main (post-build step)
  Used at build-trigger time to compute affected set

Polyrepo vs. Monorepo:
  Polyrepo advantages:
    Each repo has independent CI → no affected-package complexity
    Teams own their own pipeline config
    Clean dependency boundaries (versioned artifacts, not symlinks)
  Polyrepo disadvantages:
    Cross-repo changes: update library → update all consumers → N separate PRs
    Version drift: consumers on different library versions → dependency hell
    Shared code: must publish to npm/Maven before consumers can use it

  Most FAANG companies (Google, Meta, Twitter) use monorepos for core platform
  Most startups and distributed teams: polyrepo (simpler, team autonomy)

Decision for interview: design for monorepo support (more interesting, more depth).
  Affected-package detection is the most important monorepo optimization to cover.
```

---

## 6. Deep Dive

### 6.1 Git Event Ingestion & Idempotency

```
Webhook validation:
  1. HMAC-SHA256 signature verification against shared secret per repo
     → Reject if signature invalid (401)
  2. Parse event type: push, pull_request, create (tag), workflow_dispatch

Idempotency (prevent duplicate builds):
  Key: sha256(repoId + commitSHA + eventType + branchRef)
  Redis SET idempotency:{key} NX EX 10  ← 10-second dedup window
  If key already exists → return 200 (already accepted), discard event

  Why Redis (not DB): 10s window, microsecond SET NX — DB would be overkill

Publish to Kafka:
  Topic: git-events
  Partition key: repoId  ← ensures ordered processing per repo
  Payload: { eventId, repoId, orgId, commitSHA, branch, actor, timestamp, rawEvent }
```

---

### 6.2 Build Config Resolution

Build Trigger Service reads the pipeline definition from the repo:

```yaml
# .buildrc.yml (checked into repo root)
version: 2

triggers:
  - on: push
    branches: [main, release/*]
  - on: pull_request
    paths: ["src/**", "Dockerfile"]

pipeline:
  jobs:
    lint:
      runs-on: ubuntu-22.04
      steps:
        - uses: actions/checkout@v4
        - run: make lint

    test:
      runs-on: ubuntu-22.04
      needs: [lint]           # DAG dependency
      steps:
        - uses: actions/checkout@v4
        - run: make test
      retry:
        max: 2
        on: [timeout, infrastructure_error]

    build-image:
      runs-on: ubuntu-22.04
      needs: [test]
      steps:
        - run: docker build -t myapp:${{ commit.sha }} .
        - run: docker push registry.example.com/myapp:${{ commit.sha }}
      artifacts:
        - path: dist/
          retention: 30d
```

```
Config resolution:
  1. Fetch .buildrc.yml via Git provider API (or local clone)
  2. Parse YAML → validate schema
  3. Evaluate trigger conditions against event (branch match, path filter)
  4. If no match → skip (no build created)
  5. Resolve DAG: topological sort of jobs by `needs` field
  6. Create Build record + N Job records (one per pipeline job)
  7. Enqueue leaf jobs (no `needs` dependencies) immediately
  8. Enqueue downstream jobs only when all dependencies succeed
```

---

### 6.3 Build State Machine

```
States per Build:
  QUEUED → RUNNING → SUCCEEDED
                   → FAILED → (retry?) → QUEUED (retry)
                   → CANCELLED
                   → TIMED_OUT → (retry?) → QUEUED

States per Step (within a job):
  PENDING → RUNNING → PASSED | FAILED | SKIPPED | TIMED_OUT

Transitions:
  QUEUED   → RUNNING    : worker picks up job (atomic claim via DB UPDATE ... RETURNING)
  RUNNING  → SUCCEEDED  : all steps passed, artifacts uploaded
  RUNNING  → FAILED     : any non-retryable step failed
  RUNNING  → TIMED_OUT  : wall-clock > job.timeout (default 60 min)
  RUNNING  → CANCELLED  : user or system cancels
  FAILED   → QUEUED     : retry condition met, retry count < max
  SUCCEEDED→ (terminal) : immutable

State transition atomicity (critical):
  UPDATE jobs
  SET status = 'RUNNING', worker_id = $1, started_at = NOW()
  WHERE id = $2 AND status = 'QUEUED'
  RETURNING id

  → Only one worker wins (no double-assignment)
  → Optimistic concurrency: if 0 rows updated → job already taken, skip
```

---

### 6.4 Worker Assignment & Scheduling

```
Job requirements from config:
  { os: ubuntu-22.04, arch: amd64, labels: [gpu, large], memory: 8GB, cpus: 4 }

Worker registry (Redis Hash per worker):
  Key: worker:{workerId}
  Fields: { status, os, arch, labels[], available_memory, available_cpus, lastHeartbeat }

Scheduling algorithm:
  1. Dequeue next job from Kafka (priority queue: CRITICAL > HIGH > NORMAL > LOW)
  2. Query Redis for workers matching {os, arch, labels, memory, cpus}
  3. Score matching workers: prefer least-loaded (available_memory DESC)
  4. Assign via gRPC: worker.RunJob(jobSpec)
  5. Worker ACKs → mark job RUNNING in DB
  6. If no available worker → re-enqueue with backoff (check every 5s)

Priority levels:
  CRITICAL: main/release branch failures during deploy window
  HIGH:     manual triggers, PR builds for team leads
  NORMAL:   regular PR builds
  LOW:      scheduled builds, dependency scans

Worker heartbeat:
  Worker sends HEARTBEAT every 30s to Scheduler
  If no heartbeat for 90s → worker marked DEAD
  All RUNNING jobs on dead worker → re-queued (status: QUEUED, retry++)
  Dead worker removed from registry
```

---

### 6.5 Isolated Build Environments

```
Isolation options (tradeoff: speed vs security):

┌──────────────────┬──────────────┬────────────┬─────────────┬──────────────┐
│ Isolation        │ Startup Time │ Overhead   │ Security    │ Use Case     │
├──────────────────┼──────────────┼────────────┼─────────────┼──────────────┤
│ Docker (runc)    │ ~2s          │ Low        │ Medium      │ Internal OSS │
│ gVisor (runsc)   │ ~3s          │ Medium     │ High        │ Untrusted    │
│ Firecracker MicroVM│ ~150ms     │ Very low   │ Very high   │ Multi-tenant │
│ Full VM (EC2)    │ ~30s         │ High       │ Very high   │ macOS builds │
└──────────────────┴──────────────┴────────────┴─────────────┴──────────────┘

Recommended: Firecracker for Linux jobs (fast boot, VM-level isolation)
             Full macOS VMs for iOS/macOS builds

Container image caching:
  - Pre-warm common base images (ubuntu, node, python, golang) on each worker
  - Layer cache: worker stores pulled layers in local Docker layer cache
  - Reduces startup from 30s (cold pull) to 2s (cache hit)

Environment cleanup:
  - Container/MicroVM destroyed after job completes (no persistence)
  - Worker's filesystem wiped between jobs
  - Secrets injected via tmpfs mount (never written to disk)
  - Network: isolated network namespace per job; egress filtered
```

---

### 6.6 Log Streaming Architecture

```
Real-time log streaming (p99 < 1s lag):

Worker → Log Ingestion Service:
  Protocol: gRPC streaming (bidirectional)
  Chunks: 4KB log chunks with { buildId, stepId, sequence, timestamp, line }
  Sequence numbers: detect gaps, enable ordered reassembly

Log Ingestion Service:
  - Receives gRPC stream from worker
  - Appends to Redis Stream (XADD): build:logs:{buildId}
    → Acts as real-time buffer (last 10K lines, 1h TTL)
  - Simultaneously writes to Kafka → async flush to S3 (raw, compressed, permanent)

Client (browser) → API Service:
  - WebSocket connection: GET /v1/builds/{buildId}/logs
  - API Service reads from Redis Stream (XREAD BLOCK)
  - Streams chunks to client as Server-Sent Events or WebSocket frames
  - On reconnect: client sends lastSequence → resume from offset (no replay from start)

Historical log retrieval (build complete):
  - Redis TTL expired → read from S3 (presigned URL for download)
  - Large builds (>100MB logs) → paginated S3 read via Range headers

Log storage:
  Hot (< 1h, in-progress):  Redis Stream
  Warm (< 7 days):          ClickHouse (queryable, searchable)
  Cold (> 7 days):          S3 Glacier (compressed, archival)
```

---

### 6.7 Artifact Management

```
Artifact upload flow (worker → store):
  1. Worker signals artifact upload intent: POST /v1/builds/{id}/artifacts/init
     → Returns: presignedUrl (S3 multipart upload)
  2. Worker uploads directly to S3 (bypasses build system — saves bandwidth)
     → Multipart for large artifacts (>100 MB parts of 100MB each)
  3. Worker confirms: POST /v1/builds/{id}/artifacts/complete
     → Build service writes artifact metadata to DB:
        { artifactId, buildId, name, s3Key, size, sha256, contentType, expiresAt }

Artifact deduplication:
  Hash = SHA256(artifact content)
  Before upload: check artifact_hashes table for existing hash
  If found → record pointer to existing S3 object (no re-upload)
  Saves storage for repeated Docker layers, identical binaries

Artifact registry (Docker images):
  - Worker pushes Docker image to internal registry (Harbor / ECR)
  - Tagged: registry.example.com/{org}/{repo}:{commitSHA}
  - Build service records image digest in artifact metadata

Retention policy:
  - Main branch artifacts: 365 days
  - PR artifacts: 7 days (deleted after PR merge/close)
  - Custom policy per repo (configured in .buildrc.yml)
  - S3 lifecycle rules enforce deletion automatically

Artifact serving:
  - Presigned S3 URL (15-min TTL) issued per download request
  - No streaming through build service (S3 serves directly)
  - CDN (CloudFront) for frequently-downloaded artifacts (SDKs, Docker base images)
```

---

### 6.8 Retry System

```
Retry classification:
  Retryable errors:
    - Worker crash / OOM killed (infrastructure_error)
    - Build timeout (timeout) → may indicate flaky test or slow infra
    - Transient network failure during git clone or artifact upload

  Non-retryable errors:
    - Compilation failure (test_failure, compile_error)
    - Config parse error (config_error)
    - Explicit user cancellation

Retry config (per job in .buildrc.yml):
  retry:
    max: 3
    on: [timeout, infrastructure_error]
    backoff: exponential   # 30s, 60s, 120s
    conditions:
      - exit_code: 137    # OOM kill signal

Retry execution:
  1. Job fails with retryable error
  2. Retry count check: retry_count < max_retries
  3. Create new Job record (same build, new attempt, retry_count++)
  4. Schedule with delay (backoff): ZADD retry_queue {score=executeAt} {jobId}
  5. Retry Scheduler polls ZRANGEBYSCORE retry_queue -inf NOW() every second
  6. Enqueues ready jobs to Kafka: build-jobs

Retry lineage:
  Jobs table: { id, parent_job_id, retry_count, retry_reason }
  → Full retry history visible in build timeline
  → Root cause analysis: which step failed across N attempts?

Flaky test detection:
  If same step fails on attempt 1 but passes on attempt 2 → mark as FLAKY
  Aggregate per (repo, step_name): flakiness_rate = failures / runs
  Threshold > 5% → alert repo owner, surface in dashboard
```

---

### 6.9 Pipeline DAG Execution

```
DAG resolution:
  jobs: { lint, test (needs: lint), build (needs: test), deploy (needs: build) }

  Topological order: lint → test → build → deploy

  Stored as:
    job_dependencies: { job_id, depends_on_job_id }

Fan-out (parallel steps):
  jobs: { lint, unit-test, integration-test, security-scan }  (no dependencies)
  → All 4 enqueued simultaneously → run in parallel

Fan-in (join):
  jobs: { package (needs: [lint, unit-test, integration-test, security-scan]) }
  → Only enqueued when ALL dependencies reach SUCCEEDED

Fan-in logic (DAG Manager):
  On every job status change → DAG Manager evaluates:
    SELECT COUNT(*) FROM job_dependencies
    WHERE dependent_job_id = $1
    AND upstream_job_id NOT IN (SELECT id FROM jobs WHERE status = 'SUCCEEDED')
  → If count = 0 → enqueue dependent job

Failure propagation:
  - By default: if any job FAILS → downstream jobs SKIPPED (entire pipeline fails)
  - continue-on-error: true → downstream jobs still run despite upstream failure
  - Matrix builds: failure in one matrix cell doesn't cancel siblings (configurable)

Matrix builds:
  strategy:
    matrix:
      os: [ubuntu-22.04, windows-2022, macos-14]
      node: [18, 20, 22]
  → 9 parallel jobs created; each combination runs independently
```

---

### 6.10 Caching & Incremental Builds

```
Layer 1 — Dependency cache (npm, Maven, pip, Go modules):
  Key: sha256(lockfile content)   e.g., sha256(package-lock.json)
  Store: S3 (tar.gz of node_modules / .m2 / go/pkg)
  Hit rate: ~85% (lockfile rarely changes)
  Worker: download + extract before build → save 2-5 min per build

Layer 2 — Build output cache (Bazel/Nx Remote Cache):
  Key: sha256(action inputs + env vars + toolchain version)
  Store: S3 (content-addressed) or dedicated cache server (Bazel Remote Cache API)
  Hit: skip recompilation, reuse outputs from prior identical build
  Miss: execute action, upload result to cache

  Bazel Remote Cache protocol:
    ActionCache: { actionDigest → actionResult (output files, exit code) }
    ContentAddressableStorage: { digest → blob }

Layer 3 — Docker layer cache:
  Layers cached in local worker registry
  Cross-worker: registry mirror (Harbor) acts as shared layer cache

Cache invalidation:
  - Dependency cache: invalidated when lockfile SHA changes
  - Build cache: invalidated automatically (content-addressed by inputs)
  - No manual invalidation needed (immutable by design)

Cache storage:
  Dependency caches: 50 GB × 50K repos = 2.5 PB max (LRU eviction, 90-day TTL)
  Build output cache: content-addressed → natural dedup; 30-day TTL on cold entries
```

---

### 6.11 Scalability & Fault Tolerance

| Component | Failure | Mitigation |
|-----------|---------|------------|
| Webhook Gateway | Single node crash | Stateless; auto-scaled behind ALB; Kafka retains events |
| Build Trigger Service | Crash mid-processing | Kafka consumer group offset committed only after DB write; replays from last committed offset |
| Scheduler | Crash | Stateless; new instance picks up from Kafka; worker heartbeat re-queues orphaned jobs |
| Worker crash (mid-build) | Heartbeat timeout → job re-queued | Retry count incremented; job rescheduled on healthy worker |
| Kafka broker failure | RF=3; producer retries with idempotent producer | Zero message loss |
| PostgreSQL primary failure | HA failover (< 30s); read replicas for status queries | Brief write unavailability; reads unaffected |
| Redis failure | Build state in PostgreSQL (source of truth); Redis is cache only | Slight latency increase; no data loss |
| S3 outage | Log streaming to Redis buffer continues; upload retried on S3 recovery | Logs not lost; artifact upload retried up to 3× |
| Region failure | Multi-region active-passive; Git webhooks re-routed via GeoDNS | < 60s failover for new builds; in-progress builds on failed region lost → re-triggered |

---

## 7. Data Flow Summary

### Push-Triggered Build (Happy Path)

```
Developer pushes commit to GitHub (branch: main)

1. GitHub → POST /webhooks/github  (HMAC verified)
2. Webhook Gateway: idempotency check (Redis) → publish to Kafka: git-events

3. Build Trigger Service:
   a. Fetch .buildrc.yml from repo (Git API)
   b. Evaluate trigger: branch=main ✓, path filter ✓
   c. Resolve DAG: lint → [unit-test, security-scan] → build-image → notify-deploy
   d. INSERT Build (QUEUED) + INSERT Jobs (lint as first runnable)
   e. Publish job to Kafka: build-jobs

4. Scheduler:
   a. Dequeue job from Kafka
   b. Find available worker (labels match, sufficient resources)
   c. gRPC: worker.RunJob(jobSpec)

5. Worker:
   a. Pull Firecracker MicroVM image
   b. Clone repo at commitSHA (or restore from git cache)
   c. Restore dependency cache from S3 (cache hit)
   d. Execute step: make lint → stream logs → Redis Stream → WebSocket → browser
   e. Step exits 0 → mark step PASSED

6. Build State Service:
   a. All steps PASSED → job=SUCCEEDED
   b. DAG Manager: unit-test and security-scan now eligible → enqueue both (parallel)

7. After all jobs SUCCEEDED:
   a. Artifacts uploaded to S3 (Docker image pushed to registry)
   b. Build marked SUCCEEDED
   c. Notification Service: POST to GitHub Commit Status API (✓ green check)
   d. Slack message: "✅ Build #4821 passed (main @ abc1234) — 4m 32s"
```

### Failed Build with Retry

```
Step: integration-test → exit code 137 (OOM killed)
  → Worker reports: FAILED, exit_code=137, retry_eligible=true
  → Build State Service: retry_count (1) < max_retries (3)
  → Create new Job (retry_count=2, parent_job_id=original)
  → ZADD retry_queue score=NOW()+30s job_id   (30s backoff)

Retry Scheduler (after 30s):
  → ZRANGEBYSCORE → job ready
  → Publish to Kafka: build-jobs (with label: large → bigger worker)

Worker (larger machine):
  → Job completes successfully
  → Build resumes DAG from this job
```

---

## 8. Follow-Up Questions

### Q1: How do you prevent one noisy tenant from starving others?
```
Multi-level queue isolation:

Per-org quota:
  - Max concurrent builds per org: configurable (e.g., 50 for free, 500 for enterprise)
  - Enforced by Scheduler: before assigning worker, check:
      SELECT COUNT(*) FROM jobs WHERE org_id=$1 AND status='RUNNING'
    → If at quota → hold in queue

Fair-share scheduling:
  - Weighted Fair Queue (WFQ): orgs get time-proportional to their tier
  - Burst: orgs can exceed quota if global capacity available (unused by others)
  - Priority within org: CRITICAL > HIGH > NORMAL > LOW

Worker pool segmentation:
  - Dedicated pool for enterprise orgs (guaranteed capacity)
  - Shared pool for free tier (preemptible workers, lower SLA)
  - Spot/preemptible instances for low-priority builds → 70% cost reduction
```

---

### Q2: How do you handle secrets in build jobs?
```
Secret injection (never in env vars or logs):

Storage:
  - HashiCorp Vault or AWS Secrets Manager
  - Secrets scoped per org/repo/environment

Injection:
  1. Worker authenticates to Vault using short-lived token (OIDC from build system)
  2. Worker mounts secrets as tmpfs files inside MicroVM (in-memory only)
  3. Secret values never written to disk, never in environment (process isolation)
  4. Log scrubbing: all log lines scanned for known secret patterns → redacted before storage

Rotation:
  - Secrets rotated by Vault automatically
  - Build uses latest version at job start time (no caching)

Audit:
  - Every secret access logged: { buildId, secretName, actor, timestamp }
  - Alerts on abnormal access patterns (secret read from unexpected repo)
```

---

### Q3: How would you implement build caching across forks / PRs?
```
Cache poisoning risk:
  - Fork PR could craft malicious build output → cached → used by main repo build
  - Solution: cache isolation by trust level

Cache namespaces:
  - TRUSTED: same org, same repo, same branch → read/write access to cache
  - SEMI-TRUSTED: same org, different repo → read-only from parent repo's cache
  - UNTRUSTED: fork PR → read-only from parent repo cache; writes go to isolated namespace

Cache key scoping:
  cache_key = sha256(orgId + repoId + branchName + inputHash)
  Fork PR:    cache_key = sha256(orgId + forkRepoId + prNumber + inputHash)
              → Fork cache never pollutes main repo cache
```

---

### Q4: How do you design the build config as code (like GitHub Actions)?
```
Config language evolution:
  v1: Simple shell scripts per repo (no DAG)
  v2: YAML pipeline (current design) → DAG, matrix, retry, cache
  v3: Starlark (like Bazel BUILD files) → programmable, no YAML limits

Config validation:
  - Schema validation on push (webhook) → fail fast before enqueuing
  - Lint: deprecated fields, circular dependencies in DAG, unknown runner labels
  - Dry-run mode: resolve config without executing → preview DAG in UI

Config versioning:
  - Config stored in repo → versioned with code (GitOps)
  - Build always uses config at the triggering commit SHA (not HEAD)
  - Prevents: "I changed the pipeline mid-build" race condition

Reusable workflows:
  - Shared config templates: jobs: - uses: org/shared-workflows/.buildrc.yml@main
  - Parameterized: inputs: { node_version: 20 }
  - Reduces duplication across 50K repos
```

---

### Q5: How do you support 10× peak load (release day)?
```
Autoscaling strategy:

Worker fleet (Kubernetes or EC2 Autoscaling):
  - Metric: build_queue_depth (jobs waiting / jobs running)
  - Scale-out trigger: queue_depth > 100 → add workers (batch of 50)
  - Scale-in: queue_depth < 10 for 5 min → remove idle workers
  - Pre-scale: schedule +30% capacity 1h before known events (deploy windows, Monday morning)

Preemptible/Spot instances:
  - 60% of fleet on spot → 70% cost reduction
  - Low-priority jobs scheduled on spot first
  - On spot reclaim (2-min warning) → checkpoint build state → migrate job to on-demand

Queue overflow protection:
  - Global build queue cap: 500K queued jobs
  - Beyond cap → HTTP 503 with Retry-After header to webhook callers
  - Webhooks buffer in Git provider until system recovers

Cold start acceleration:
  - Worker AMI pre-baked with common toolchains (Node, Java, Python, Go, Docker)
  - Warm pool: 100 idle workers kept hot at all times → < 10s job start
  - Above warm pool → cold start (AMI boot ~60s) → acceptable for non-CRITICAL builds
```

---

### Q6: How do you detect and handle flaky tests?
```
Flakiness tracking:
  Table: test_results { buildId, jobId, testName, status, duration, attempt }

  Flakiness score per test:
    flaky_rate = (failed_runs - where reruns passed) / total_runs
    Window: last 14 days, same branch

  Threshold: flaky_rate > 5% → flag test as FLAKY

Automatic quarantine:
  - FLAKY tests auto-retried up to 3× within same job (fast, no full re-run)
  - If passes on retry → job SUCCEEDS but marked with FLAKY warning
  - If fails consistently → job FAILS (real failure, not flake)

Flakiness dashboard:
  - Top 20 flaky tests per repo → surfaced in PR UI
  - Alert to repo owner when flakiness crosses threshold
  - Trend: improving / worsening

Quarantine mode (optional):
  - Mark test as quarantined → excluded from blocking the build
  - Creates Jira ticket automatically → must be fixed within SLA
```

---

### Q7: How do you support monorepos with 50,000 files?
```
Challenge: full build of monorepo for every commit is too slow (30+ min)

Change detection:
  1. Compute diff: git diff --name-only {base_sha}..{head_sha}
  2. Map changed files → affected packages via dependency graph
     (e.g., package A imports package B → change to B triggers A's build too)

Dependency graph:
  Built at repo analysis time (pre-build):
    - Node: parse package.json workspaces
    - Java: parse BUILD/pom.xml
    - Go: parse go.mod imports
  Stored in graph DB (or Redis adjacency list) per commit

Affected package computation:
  affected = BFS(changed_files → package) ∪ reverse_deps(changed_packages)

  Example:
    Changed: libs/auth/src/token.ts
    Direct package: @company/auth
    Reverse deps: @company/api, @company/cli (both import auth)
    → Only build @company/auth, @company/api, @company/cli (3 builds, not 200)

Build matrix from affected packages:
  Each affected package → separate parallel job
  Significant reduction: 200-package monorepo → avg 8 affected packages per commit
  Build time: 30 min → 3 min
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Event ingestion | Kafka (partitioned by repoId) | Ordered per repo, replayable, decoupled from trigger service |
| Idempotency | Redis SET NX (10s window) | Sub-millisecond, prevents duplicate builds from webhook retries |
| Job assignment | Optimistic DB UPDATE + RETURNING | Atomic claim without distributed lock; scales to 10K concurrent workers |
| Worker isolation | Firecracker MicroVM | VM-level security, 150ms boot, low overhead vs full VM |
| Log streaming | Redis Stream → WebSocket | Real-time with ordering guarantees; S3 for durable storage |
| Build state | PostgreSQL (source of truth) + Redis (cache) | Strong consistency for state machine; Redis for fast status reads |
| Artifact upload | Direct S3 presigned URL from worker | Offloads bandwidth from build service; scales to PB artifacts |
| Retry | Kafka delayed queue (ZADD score=executeAt) | Decoupled from main queue; precise backoff without polling |
| DAG execution | Event-driven fan-in (DB counter) | No polling; DAG Manager reacts to job completion events |
| Cache | Content-addressed S3 + trust namespaces | Prevents cache poisoning from forks; natural dedup |
| Scheduling fairness | Weighted Fair Queue + per-org concurrency cap | Prevents noisy-tenant starvation; burst allowed when capacity free |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 50–60 minutes.*
