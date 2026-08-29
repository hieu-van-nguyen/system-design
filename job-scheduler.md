# System Design: Distributed Job Scheduler

> FAANG-level interview guide — a general-purpose scheduler that executes one-time and recurring jobs reliably at scale.
> Think: Quartz Scheduler, AWS EventBridge, Airflow, Sidekiq Cron, Google Cloud Scheduler.

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | **Schedule one-time jobs** to run at a specific future timestamp |
| FR-2 | **Schedule recurring jobs** using cron expressions (`0 9 * * MON`) or intervals (`every 5m`) |
| FR-3 | **Execute jobs reliably** — at-least-once delivery; idempotency at the application layer |
| FR-4 | Jobs have a **type + payload** (HTTP callback, function invocation, message queue publish) |
| FR-5 | Jobs have configurable **retry policies** (max attempts, backoff strategy, dead-letter) |
| FR-6 | Track **job execution history**: status, start/end time, output, error |
| FR-7 | Support job **priorities** (critical, high, normal, low) |
| FR-8 | **Cancel or pause** a scheduled job before it fires |
| FR-9 | Jobs have **timeouts** — execution killed if it exceeds wall-clock limit |
| FR-10 | Support **job dependencies** — Job B runs only after Job A succeeds |
| FR-11 | (Optional) **Rate limiting** — max N executions per job per time window |
| FR-12 | (Optional) **Exactly-once execution** — fencing tokens prevent duplicate runs |

**Out of scope:** Full DAG workflow orchestration (Airflow-level), ML pipeline scheduling, long-running data processing.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 100M jobs scheduled; 10M executions/day; 1M concurrent active jobs |
| **Availability** | 99.99% — scheduler itself never loses a scheduled job |
| **Timeliness** | Jobs fire within **5 seconds** of their scheduled time (p99) |
| **Throughput** | 10,000 job executions/sec at peak |
| **Consistency** | A job fires **at most once per trigger time** (no duplicate executions) |
| **Durability** | Scheduled jobs survive full cluster restarts; never silently dropped |
| **Observability** | Full audit log per execution: scheduled_at, fired_at, completed_at, output |
| **Latency** | Job scheduling API p99 < 50ms; job cancellation takes effect within 10s |

---

## 3. Back-of-Envelope Estimation

### Job Volume

```
Total scheduled jobs          = 100M
Executions/day                = 10M
Executions/sec (avg)          = 10M / 86,400 ≈ 116/sec
Peak (top-of-hour cron burst) ≈ 116 × 20 = 2,320/sec
  (millions of "run at midnight" jobs all fire simultaneously)
Absolute peak (all crons aligned at midnight Jan 1) ≈ 10,000/sec (design target)

Recurring jobs                = 60% (cron)
One-time jobs                 = 40% (delayed tasks)
```

### Scheduler Polling (Critical Design Constraint)

```
Jobs due in next 60 seconds   = 10M/day / 1440 min × 1 min ≈ 6,944 jobs/min ≈ 116/sec
Polling interval              = 1 second (tight loop)
Jobs scanned per poll         = need to find all jobs with next_run_at ≤ NOW() + 5s
DB rows evaluated/sec         = with index on next_run_at → O(results), not O(table)
```

### Storage

```
Per job record:
  Metadata (id, name, cron, payload, config) ≈ 1 KB
  100M jobs × 1 KB             = 100 GB (scheduled jobs table)

Execution history:
  Per execution record          ≈ 500 bytes
  10M/day × 500 bytes          = 5 GB/day
  1-year retention             = 5 GB × 365 ≈ 1.8 TB

Payload storage (large payloads):
  Avg payload                   = 10 KB
  100M jobs × 10 KB             = 1 TB (offloaded to S3, DB stores reference)
```

### Scheduler Nodes

```
Each scheduler node can manage N jobs:
  CPU-bound: scheduling loop, cron evaluation, DB polling
  Practical capacity per node: ~1M active jobs (with index-based scan)
  At 100M jobs: 100 scheduler nodes (but most jobs are idle/far future)
  Active (firing within 1 min): ~7K jobs/min → 5 nodes handle comfortably
  Use sharding: each node owns a partition of job space
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                         CLIENTS                                              │
│    REST API / SDK / Web Console / Terraform provider                         │
└──────────────────────────────┬───────────────────────────────────────────────┘
                               │ HTTPS
                    ┌──────────▼──────────┐
                    │    API Service       │
                    │  - CRUD for jobs     │
                    │  - Auth + rate limit │
                    │  - Input validation  │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │    Job Store (DB)    │   PostgreSQL / CockroachDB
                    │  - jobs table        │   Primary source of truth
                    │  - executions table  │
                    └──────────┬──────────┘
                               │
         ┌─────────────────────▼────────────────────────┐
         │              SCHEDULER CLUSTER                 │
         │  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
         │  │Scheduler │  │Scheduler │  │Scheduler │   │
         │  │  Node 1  │  │  Node 2  │  │  Node N  │   │
         │  │ (shard 0)│  │ (shard 1)│  │ (shard N)│   │
         │  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
         └───────┼─────────────┼──────────────┼─────────┘
                 │             │              │
                 └─────────────▼──────────────┘
                               │ publish due jobs
                    ┌──────────▼──────────┐
                    │   Job Queue (Kafka)  │
                    │  Partitioned by      │
                    │  priority + tenant   │
                    └──────────┬──────────┘
                               │
         ┌─────────────────────▼────────────────────────┐
         │              WORKER POOL                       │
         │  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
         │  │ Worker 1 │  │ Worker 2 │  │ Worker N │   │
         │  │ - Execute│  │ - Execute│  │ - Execute│   │
         │  │ - Report │  │ - Report │  │ - Report │   │
         │  └────┬─────┘  └────┬─────┘  └────┬─────┘   │
         └───────┼─────────────┼──────────────┼─────────┘
                 │             │              │
         ┌───────▼─────────────▼──────────────▼─────────┐
         │           Execution Result Service             │
         │  - Update execution status in DB               │
         │  - Schedule next_run_at for recurring jobs     │
         │  - Trigger retry if failed                     │
         │  - Fan-out notifications                       │
         └──────────────────┬─────────────────────────────┘
                            │
            ┌───────────────┴────────────────┐
            │                                │
   ┌────────▼──────────┐          ┌──────────▼──────────┐
   │  Notification      │          │  Metrics / Alerting  │
   │  Service           │          │  (Prometheus/Grafana)│
   │  (webhook, email,  │          │  - Lag, error rate   │
   │   Slack, PagerDuty)│          │  - Queue depth       │
   └────────────────────┘          └──────────────────────┘
```

### Core API

```
// Create a job
POST /v1/jobs
{
  "name": "send-weekly-digest",
  "type": "http",                       // http | sqs | function | pubsub
  "schedule": "0 9 * * MON",           // cron OR
  "runAt": "2026-09-01T09:00:00Z",     // one-time OR
  "interval": "300s",                  // interval
  "timezone": "America/Chicago",
  "payload": { "url": "https://api.example.com/digest", "method": "POST", "body": {...} },
  "retryPolicy": { "maxAttempts": 3, "backoff": "exponential", "initialDelay": "30s" },
  "timeout": "60s",
  "priority": "normal",
  "ttl": "2026-12-31T00:00:00Z"        // auto-delete after date
}
Response 201: { "jobId": "job_abc123", "nextRunAt": "2026-09-01T09:00:00Z" }

// Manage jobs
GET    /v1/jobs/{jobId}                          → JobDefinition + nextRunAt
PUT    /v1/jobs/{jobId}                          → Update schedule/payload/retry
DELETE /v1/jobs/{jobId}                          → Cancel (removes from scheduler)
POST   /v1/jobs/{jobId}/pause                    → Suspend recurring job
POST   /v1/jobs/{jobId}/resume                   → Resume suspended job
POST   /v1/jobs/{jobId}/trigger                  → Force immediate execution

// Execution history
GET    /v1/jobs/{jobId}/executions               → ExecutionRecord[]
GET    /v1/executions/{executionId}              → status, output, logs, retries
POST   /v1/executions/{executionId}/cancel       → Kill in-progress execution
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Job Store — PostgreSQL vs. DynamoDB vs. Redis

| Approach | Atomic Claim | Rich Queries | Latency | Operational Cost |
|----------|-------------|-------------|---------|-----------------|
| **PostgreSQL (Recommended)** | ✅ `FOR UPDATE SKIP LOCKED` | ✅ SQL + indexes | Medium | Low–Medium |
| DynamoDB | ❌ Conditional writes only | ❌ Limited | Low | Low |
| Redis | ❌ Lua scripts needed | ❌ None | < 1ms | Medium |
| CockroachDB | ✅ Same as PG | ✅ SQL | Medium | High |

```
Why PostgreSQL wins for a job scheduler:

The critical operation — "claim a due job atomically" — maps perfectly to
  SELECT ... FOR UPDATE SKIP LOCKED

  This one SQL primitive gives us:
    1. Atomic claim: exactly one scheduler node gets each job
    2. Skip-locked: competing nodes skip rows locked by others (no spinning)
    3. No coordinator: PostgreSQL IS the distributed lock manager
    4. Zero extra infrastructure (no ZooKeeper, no Redis for claiming)

  This pattern powers: Sidekiq (Ruby), GoodJob (Rails), pgBoss (Node.js)
  Real production scale: GoodJob processes 10M+ jobs/day on single PostgreSQL

DynamoDB — why it fails for scheduling:
  No FOR UPDATE SKIP LOCKED equivalent
  Conditional writes: ok for single-item claim, but scanning due jobs requires:
    GSI on next_run_at → eventually consistent reads → might miss jobs or double-fire
  No partial index (can't index only status='active' rows) → full GSI scans expensive
  Clock-skew risk: DynamoDB GSI reads may lag primary → scheduler sees stale next_run_at

Redis — why it fails as primary store:
  No durability by default (RDB snapshot may lose seconds of jobs)
  AOF persistence adds latency, still not as safe as PostgreSQL WAL
  No SQL query power: "find all jobs for tenant X due in next 60s" requires SCAN + filter
  Use Redis as cache (for rate limiting, token buckets) — not source of truth for jobs

Decision: PostgreSQL as source of truth. Read replicas for scheduler polling.
  DynamoDB is a credible alternative if you can demonstrate atomic claim via
  conditional updates + GSI + careful consistency design — but harder to get right.
```

---

### Trade-Off 2: Scheduling Trigger — DB Polling vs. In-Memory Heap vs. Time-Series DB

| Approach | Precision | Scale | Crash Recovery | Complexity |
|----------|----------|-------|---------------|-----------|
| **DB Polling (Recommended for ≥1s)** | ±1s | 100M jobs | ✅ DB is source of truth | Low |
| **In-Memory Min-Heap (for sub-second)** | < 1ms | ~10M per node | ❌ Lose on crash | Medium |
| Time-series DB (InfluxDB, TimescaleDB) | High | High | Medium | High |
| Redis Sorted Set (ZRANGEBYSCORE) | ±1s | ~10M | ❌ Durability risk | Low |

```
DB polling (the right default):
  Every tick: SELECT ... WHERE next_run_at <= NOW() + 5s
  The partial index idx_jobs_due (shard_id, next_run_at) WHERE status='active'
    → Index-only scan: O(results), not O(jobs table)
    → At 100M jobs with 1% active in next 60s = 1M rows in index → fast range scan

  Practical limit: ≥ 1-second scheduling intervals (polling overhead)
  Above 10,000 claims/sec: hit DB write bottleneck (UPDATE next_run_at at scale)
  Mitigation: batch UPDATEs (500 in one statement), write replicas, Citus sharding

Redis ZRANGEBYSCORE:
  Key: sorted_set "jobs:shard:{N}" → score = next_run_at (epoch seconds)
  ZRANGEBYSCORE jobs:shard:42 0 {NOW} LIMIT 0 100 → O(log N + results)
  Very fast reads; near-zero scan overhead
  Fatal problem: if Redis crashes and loses WAL → scheduled jobs vanish
  Acceptable only if PostgreSQL is also kept in sync (dual write) → adds complexity

In-memory min-heap (separate tier for sub-second):
  Startup: load all sub-second jobs into min-heap (keyed by next_fire_time)
  Timer goroutine: sleeps until heap.Peek().fireTime → wakes exactly on time
  Precision: depends on OS scheduler, typically 1-10ms
  Crash: heap lost → rebuild from DB on restart (tolerable if jobs are ≤10M)
  Scale limit: ~10M jobs per node at reasonable memory (each entry ~200 bytes = 2 GB)
  Only needed for enterprise-tier sub-second intervals; standard scheduler uses DB polling

Decision: DB polling for standard jobs (≥ 1s), in-memory heap for sub-second tier.
  Two separate architectural tiers — don't force one mechanism to do both.
```

---

### Trade-Off 3: Exactly-Once Execution — Distributed Transactions vs. Idempotency Layer

| Approach | Exactly-Once | Complexity | Performance | Failure Handling |
|----------|-------------|-----------|-------------|-----------------|
| 2-Phase Commit (XA) | ✅ True | Very high | Slow | Coordinator SPOF |
| **At-least-once + Fencing Tokens (Recommended)** | ✅ Effective | Medium | Fast | Self-healing |
| At-least-once only | ❌ Duplicates | Low | Fast | Risky |
| Kafka exactly-once semantics (EOS) | ✅ Within Kafka | Medium | Medium | Limited scope |

```
The fundamental problem: dual-write between PostgreSQL and Kafka

  Scheduler must atomically:
    1. Advance next_run_at in PostgreSQL (prevents re-fire)
    2. Publish to Kafka (delivers to worker)

  These are two different systems — no shared transaction coordinator.
  If step 2 fails after step 1: job claimed in DB, never fires → lost job
  If step 1 fails after step 2: Kafka delivers, worker executes, next scheduler
    tick finds next_run_at unchanged → fires again → DUPLICATE EXECUTION

Outbox pattern (recommended solution):
  -- In SAME PostgreSQL transaction as next_run_at update:
  INSERT INTO job_outbox (execution_id, payload, published=false)

  Separate outbox relay reads unpublished rows, publishes to Kafka, marks published=true
  → If relay crashes: PostgreSQL outbox ensures at-least-once Kafka delivery
  → next_run_at already advanced → scheduler won't re-fire

  Duplicate delivery still possible (relay crashes after Kafka publish, before PG update)
  → Handled by worker-side idempotency check:
      UPDATE executions SET status='running' WHERE id=$id AND status='pending'
      → Only one worker transitions 'pending' → 'running'; second gets 0 rows

Fencing tokens for downstream:
  Worker sends X-Fencing-Token: {sequence} to target HTTP service
  Target service stores processed tokens (Redis SET / DB column)
  Duplicate delivery: same token → target rejects as already processed
  → End-to-end exactly-once even if worker re-executes the job

2PC/XA — why to avoid:
  Requires Kafka XA support (not widely supported, not in cloud-managed Kafka)
  Blocking protocol: coordinator crash leaves participants in uncertainty
  Performance: 2× round trips; throughput drops significantly
  Our approach gives same guarantee with no blocking, no coordinator

Decision: Outbox pattern + at-least-once + fencing tokens = effectively exactly-once.
  This is the correct FAANG answer. 2PC is the textbook answer but wrong for this system.
```

---

### Trade-Off 4: Worker Queue — Kafka vs. SQS vs. Direct DB Polling vs. gRPC Push

| Approach | Backpressure | Replayability | Ordered Delivery | Ops Cost |
|----------|-------------|--------------|-----------------|---------|
| **Kafka (Recommended)** | ✅ Consumer lag | ✅ Offset replay | ✅ Per-partition | Medium |
| SQS / RabbitMQ | ✅ Queue depth | ❌ No replay | ❌ Best-effort | Low |
| DB polling by workers | ✅ Natural | ✅ DB is replay | ✅ ORDER BY | Zero |
| gRPC push to worker | ❌ Complex | ❌ Lost on push | N/A | High |

```
Why Kafka for the scheduler → worker pipeline:

Priority-based multi-queue:
  Separate Kafka topics per priority tier:
    job-executions-critical  (4 partitions, reserved consumers)
    job-executions-high      (16 partitions)
    job-executions-normal    (64 partitions)
    job-executions-low       (16 partitions)
  Workers: always drain critical before high before normal (consumer group priority)

Backpressure (critical for thundering herd):
  At midnight when 4M cron jobs fire: scheduler publishes to Kafka at its own rate
  Workers consume at their capacity (10K/sec)
  Kafka consumer lag grows → alert fires → autoscale workers
  Without Kafka: scheduler directly invokes workers → fan-out creates 4M simultaneous calls
  → Workers crash → jobs lost

Replayability for debugging:
  A misconfigured worker processes jobs incorrectly for 30 minutes
  → Rewind Kafka offset to 30 min ago → reprocess with fixed worker
  → Not possible with SQS (message deleted on consume)

DB polling by workers (viable alternative at smaller scale):
  Workers directly SELECT ... FOR UPDATE SKIP LOCKED from executions table
  Eliminates Kafka operational complexity entirely
  Used by: Sidekiq, GoodJob, Delayed::Job, pg-boss
  Breaks at: > 1,000 concurrent workers polling aggressively → DB connection saturation

SQS:
  Simpler ops, managed service, native AWS integration
  No replay — once consumed, message gone
  No per-message ordering across a queue (FIFO SQS has ordering but 300 TPS limit)
  Good choice for AWS-native deployments at moderate scale

Decision: Kafka for multi-priority fan-out and thundering-herd isolation at FAANG scale.
  DB polling (FOR UPDATE SKIP LOCKED) is a credible simpler alternative;
  mention it explicitly as the right answer for teams without Kafka expertise.
```

---

### Trade-Off 5: Cron Top-of-Hour Thundering Herd — Jitter vs. Look-Ahead vs. Queue

| Mitigation | Execution Accuracy | Implementation | Side Effects |
|-----------|-------------------|---------------|-------------|
| No mitigation | ❌ System overload | Zero | DB crash at midnight |
| **Jitter at creation (Recommended)** | ±60s | Very low | Not exact :00 fire |
| Look-ahead window (60s pre-load) | ✅ Exact timing | Low | Workers wait for fire time |
| Kafka queue as absorber | ✅ Exact timing | Zero extra | Queue depth spikes |
| Rate-limited claim loop | ✅ Exact | Low | Slower drain |

```
The problem at FAANG scale:
  10M jobs total; 4M scheduled for "0 * * * *" (every hour)
  At 15:00:00.000 UTC: 4M rows have next_run_at = '2026-08-19 15:00:00'
  Scheduler: all 32 nodes simultaneously query (4M / 32 = 125K rows per node to claim)
  PostgreSQL: 32 concurrent transactions each claiming 500 rows → thousands of lock cycles
  → DB CPU spikes to 100% → query timeout → scheduler nodes fail → jobs not fired

Layer 1 — Claim batching (always needed):
  LIMIT 500 per tick, sleep remainder of 1s
  Absorbs burst: 4M jobs / 500 per tick = 8,000 ticks (8,000 seconds total, across all shards)
  With 32 nodes × 8 shards each: 32 × 500 = 16K claims/tick → 4M / 16K = 250 ticks = 250s

  Problem: jobs fire 4 minutes late during burst → violates SLO (< 5s)

Layer 2 — Jitter injection (at job creation):
  next_run_at = cron_next_at + random(0, jitter_window_sec)
  Default jitter: 60 seconds
  → 4M jobs spread uniformly across 60 seconds = 67K/sec instead of 4M/sec
  → Scheduler handles comfortably

  When to skip jitter:
    Priority: CRITICAL → no jitter (must fire exactly at :00)
    Compliance jobs: audit logs, financial cutoffs → jitter disabled
    User opt-in: "strict_timing": true in job config

Layer 3 — Look-ahead window (60-second pre-load):
  Scheduler reads: WHERE next_run_at <= NOW() + 60s
  Pre-publishes to Kafka with earliest_execute_at = actual_fire_time
  Workers: consume from Kafka, check earliest_execute_at, sleep if too early
  → DB query happens before the burst (no thundering herd at DB layer)
  → Workers absorb the timing in-process (cheap)

Layer 4 — Kafka as absorber (always present):
  Even without jitter: 4M Kafka publishes are cheap (Kafka is built for this)
  Workers consume at max capacity (autoscaled) → jobs drain in seconds
  Kafka queue depth = natural back-pressure gauge → drives autoscaling

Decision: Layered approach — jitter (configurable off for strict jobs) + look-ahead +
  Kafka buffering. No single mitigation is sufficient alone.
  State which jobs skip jitter and why — shows depth.
```

---

### Trade-Off 6: Scheduler Sharding — Static Hash vs. Consistent Hash vs. Range Sharding

| Strategy | Rebalancing | Hot Spots | Operational Simplicity | Node Failure Recovery |
|----------|------------|-----------|----------------------|----------------------|
| **Static hash (Recommended)** | Manual | Possible | Very high | Fast (reassign) |
| Consistent hash (rendezvous) | Automatic | Low | Medium | Automatic |
| Range sharding | Manual | High | Medium | Fast |
| Single coordinator (no sharding) | N/A | N/A | Highest | SPOF |

```
Why sharding is needed:
  100M jobs in a single table; 32 scheduler nodes
  Without sharding: all 32 nodes poll entire table simultaneously
  → FOR UPDATE SKIP LOCKED fights over ALL due rows → high lock contention
  → Optimal: each node owns a disjoint subset → zero contention between nodes

Static hash sharding (recommended):
  shard_id = hash(job_id) % NUM_SHARDS  (set on INSERT, never changes)
  NUM_SHARDS = 256 (static constant; pre-provisioned)
  Each scheduler node owns: num_shards / num_nodes shards (e.g., 256/32 = 8 shards)

  DB index: CREATE INDEX ON jobs (shard_id, next_run_at) WHERE status = 'active'
  Each node query: WHERE shard_id IN (my_8_shards) AND next_run_at <= NOW()+5s
  → Zero cross-shard contention (different rows, different index ranges)

  Rebalancing on node failure:
    Node 5 dies → its 8 shards reassigned to surviving nodes (round-robin)
    No data migration: only update shard_assignments metadata table
    New owner starts polling those shard IDs on next tick

  Hot spots: if one shard is disproportionately active (all CRITICAL jobs hash to shard 0)
    Mitigation: CRITICAL priority jobs use separate shard range
    Or: weight shard assignment by current job density (once at startup)

Consistent hash (Chord/Rendezvous):
  Automatic rebalancing: as nodes join/leave, minimal shard movement
  Complexity: requires gossip protocol or etcd to maintain ring membership
  Not necessary for our system: we already use ZooKeeper/etcd for shard_assignments
  The complexity of consistent hashing isn't justified when static reassignment works

Range sharding:
  Shard 0: jobs 0–1M, Shard 1: jobs 1M–2M ...
  Hot spot risk: recently created jobs all have high IDs → all in last shard
  With next_run_at index: not a problem (we shard by job_id, query by next_run_at)

Single coordinator (no sharding):
  One scheduler node polls all jobs → SPOF, scale ceiling
  Acceptable up to ~5M active jobs (some companies run this way with fast PG)

Decision: static hash with 256 virtual shards mapped to physical nodes via metadata table.
  Mention 256 virtual shards: allows adding up to 256 nodes without re-hashing jobs.
```

---

### Trade-Off 7: Retry Scheduling — New Execution Row vs. Kafka Delayed Message vs. Delay Service

| Approach | Audit Trail | Implementation | Delay Precision | Dependencies |
|----------|------------|---------------|----------------|-------------|
| **New execution row + scheduler (Recommended)** | ✅ Full | Very low | ±1 second | PostgreSQL only |
| Kafka delayed message | ❌ Buried in offsets | Medium | Exact | Kafka + delay service |
| Redis delayed queue (ZADD score=fireTime) | ❌ No persistence | Low | < 100ms | Redis |
| SQS delay queue (max 15 min) | Medium | Very low | ±seconds | SQS only |

```
The retry problem: job fails at 15:00:00; retry should fire at 15:00:30.

Option A: New execution row (recommended):
  On failure:
    INSERT INTO executions (job_id, attempt=2, scheduled_at=NOW()+30s, status='pending')
    → This row becomes a "mini one-time job"
    → Existing scheduler loop: WHERE next_run_at <= NOW()+5s → picks it up at 15:00:30

  Advantages:
    Reuses entire scheduler infrastructure — zero new components
    Full audit trail: execution_2 row shows attempt=2, reason for retry
    Works for any delay duration (minutes, hours — no SQS 15-min cap)
    Crash-safe: row persists in PostgreSQL even if scheduler crashes before retry fires

  Disadvantage:
    Retry precision: ±scheduler_tick_interval (1 second) — acceptable for most jobs
    Not sub-second retry (pathological, not needed)

Option B: Kafka delayed message:
  Kafka has no native delay semantics
  Workarounds:
    1. Timestamp in message header → worker reads, checks if too early → re-enqueue
       Problem: workers waste time in polling loops; message re-queued to END of topic
    2. Dedicated delay service: in-memory heap, publishes to Kafka at the right time
       Problem: another stateful service to build, operate, crash-recover

  Result: more complexity, same outcome as new execution row

Option C: Redis ZADD (delayed queue):
  ZADD retry_queue {fireTimestamp} {executionId}
  Delay consumer: ZPOPMIN (score ≤ NOW()) → publish to Kafka → worker executes
  Fast and simple, but:
    Redis AOF off → loses pending retries on crash
    Redis AOF on → latency cost, still not as durable as PostgreSQL

Decision: New execution row. Retry becomes a first-class scheduled execution.
  Consistent audit trail, zero new components, correct durability.
  The clean solution that most senior engineers miss — they immediately jump to Kafka delays.
```

---

## 6. Deep Dive

### 6.1 Job Storage Schema

```sql
CREATE TABLE jobs (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id       UUID NOT NULL,
  name            TEXT NOT NULL,
  job_type        TEXT NOT NULL,             -- http, sqs, function, pubsub
  schedule_type   TEXT NOT NULL,             -- cron, one_time, interval
  cron_expr       TEXT,                      -- "0 9 * * MON"
  interval_sec    INT,                       -- 300 for every 5 min
  timezone        TEXT DEFAULT 'UTC',
  payload         JSONB NOT NULL,
  retry_policy    JSONB NOT NULL,
  timeout_sec     INT DEFAULT 60,
  priority        SMALLINT DEFAULT 50,       -- 10=critical, 50=normal, 90=low
  status          TEXT DEFAULT 'active',     -- active, paused, completed, deleted
  next_run_at     TIMESTAMPTZ,               -- THE key scheduling index
  last_run_at     TIMESTAMPTZ,
  created_at      TIMESTAMPTZ DEFAULT NOW(),
  updated_at      TIMESTAMPTZ DEFAULT NOW(),
  expires_at      TIMESTAMPTZ,               -- auto-delete TTL
  shard_id        SMALLINT NOT NULL          -- for scheduler sharding
);

-- THE critical index: scheduler polls this every second
CREATE INDEX idx_jobs_due ON jobs (shard_id, next_run_at, priority)
  WHERE status = 'active';

CREATE TABLE executions (
  id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID REFERENCES jobs(id),
  tenant_id       UUID NOT NULL,
  attempt         SMALLINT DEFAULT 1,
  status          TEXT DEFAULT 'pending',   -- pending, running, succeeded, failed, timed_out, cancelled
  scheduled_at    TIMESTAMPTZ NOT NULL,
  fired_at        TIMESTAMPTZ,
  started_at      TIMESTAMPTZ,
  completed_at    TIMESTAMPTZ,
  worker_id       TEXT,
  output          JSONB,
  error           TEXT,
  fencing_token   BIGINT                    -- monotonic, for exactly-once fencing
);

CREATE INDEX idx_executions_job ON executions (job_id, scheduled_at DESC);
CREATE INDEX idx_executions_running ON executions (status, started_at)
  WHERE status = 'running';                 -- for timeout detection
```

---

### 6.2 The Scheduling Loop — Core Algorithm

The scheduler is the heart of the system. Every second, each scheduler node does:

```
SCHEDULING LOOP (runs every 1 second per node):

1. CLAIM due jobs (atomic, no double-fire):

   BEGIN;
     SELECT id, job_type, payload, retry_policy, priority
     FROM jobs
     WHERE shard_id = MY_SHARD
       AND status = 'active'
       AND next_run_at <= NOW() + INTERVAL '5 seconds'   -- look-ahead window
     ORDER BY priority ASC, next_run_at ASC
     LIMIT 500
     FOR UPDATE SKIP LOCKED;                              -- skip rows locked by other nodes

     -- Advance next_run_at immediately (prevents other nodes from seeing same job)
     UPDATE jobs
     SET next_run_at = compute_next_run(schedule_type, cron_expr, interval_sec, timezone),
         last_run_at = NOW()
     WHERE id = ANY($claimed_ids);

     -- Create execution record
     INSERT INTO executions (job_id, scheduled_at, status, fencing_token)
     VALUES ($jobId, $next_run_at, 'pending', nextval('fencing_seq'))
     RETURNING id, fencing_token;
   COMMIT;

2. PUBLISH to Kafka:
   For each claimed job → publish { executionId, jobId, payload, priority, fencingToken }
   to topic: job-executions (partition key = priority bucket + tenant_id)

3. SLEEP until next tick (1 second - elapsed time)
```

**Why `FOR UPDATE SKIP LOCKED`?**
- Multiple scheduler nodes poll the same table (for HA)
- `SKIP LOCKED` means each node atomically claims rows no other node is processing
- Zero coordination overhead — no ZooKeeper, no leader election needed for job claiming
- PostgreSQL handles the mutex via row-level locks

---

### 6.3 Cron Expression Engine

```
Cron fields: second minute hour day-of-month month day-of-week year

Standard: "0 9 * * MON"  → 9:00 AM every Monday
Extended: "*/5 * * * *"  → every 5 minutes
With tz:  schedule="0 9 * * MON", timezone="America/New_York"

next_run_at computation:
  1. Parse cron into CronExpression AST
  2. Convert current time to job's timezone
  3. Find next time matching all fields (iterate forward, max 4 years)
  4. Convert back to UTC → store as next_run_at

Edge cases:
  - DST transitions: "0 2 * * *" in America/New_York skips 2am on spring-forward
    → Use "wall clock" semantics: find next 2am regardless of UTC offset
  - Leap seconds: ignored (NTP handles, not scheduler)
  - "Last day of month": non-standard cron extension → parse "L" syntax
  - "0 0 31 * *" in February: no match → skip month, find next valid date
  - Timezone changes by country: re-evaluate after each IANA database update

Libraries: java.time (Java), croniter (Python), robfig/cron (Go)
```

---

### 6.4 Exactly-Once Execution with Fencing Tokens

The hardest problem: **what if the scheduler fires a job twice?**

Failure scenarios causing duplicate fires:
- Scheduler crashes after publishing to Kafka but before advancing `next_run_at`
- Worker receives job, processes it, crashes before ACKing Kafka → Kafka redelivers

```
Fencing token approach:

1. Each execution row gets a monotonically increasing fencing_token (Postgres sequence)
2. Token is included in job payload sent to worker
3. Worker executes job AND sends token to downstream system:
   - HTTP: X-Fencing-Token: 4821 header
   - SQS: MessageDeduplicationId = fencing_token
   - DB: INSERT ... WHERE NOT EXISTS (SELECT 1 FROM processed WHERE token=4821)

4. Downstream system rejects duplicate if token already seen

5. Worker marks execution complete:
   UPDATE executions
   SET status='succeeded', completed_at=NOW()
   WHERE id=$executionId AND fencing_token=$token
   → If 0 rows updated: stale execution (superseded by newer attempt) → discard

Kafka exactly-once:
  - Kafka producer: idempotent producer + transactional API
  - Wrap DB UPDATE + Kafka publish in a single Kafka transaction (outbox pattern)
  - Ensures: if DB commit succeeds, Kafka message will be delivered exactly once
```

**Outbox Pattern** (prevents dual-write inconsistency):

```sql
-- In same transaction as next_run_at update:
INSERT INTO job_outbox (execution_id, payload, created_at)
VALUES ($executionId, $payload, NOW());

-- Separate outbox relay process:
LOOP:
  SELECT * FROM job_outbox WHERE published = false ORDER BY created_at LIMIT 100 FOR UPDATE;
  Publish each to Kafka;
  UPDATE job_outbox SET published = true WHERE id = ANY($ids);
```

---

### 6.5 Scheduler Sharding

100M jobs cannot be polled by a single scheduler node efficiently. Solution: **static sharding**.

```
Sharding strategy:
  shard_id = hash(job_id) % NUM_SHARDS
  Each scheduler node owns 1 shard (or a range of shards)

  NUM_SHARDS = 256 (pre-assigned at creation; never rehashed)
  Scheduler nodes = 32 (each owns 256/32 = 8 shards)

  jobs.shard_id set on INSERT, never changed
  Index: CREATE INDEX ON jobs(shard_id, next_run_at) WHERE status='active'
  → Each node scans only its shards — full table never scanned

Rebalancing on node failure:
  Scheduler nodes register in ZooKeeper / etcd
  ZooKeeper watches detect node failure → trigger shard rebalancing
  Surviving nodes split the failed node's shards among themselves

  Rebalancing: update shard_assignments table (logical mapping, no data migration)
  New owner starts polling new shards immediately

Rebalancing on scale-out:
  Add new scheduler node → transfer N/new_count shards to it
  Atomic: UPDATE shard_assignments SET owner = $newNode WHERE shard_id IN (...)
  Old node stops polling transferred shards on next tick
```

---

### 6.6 Worker Execution

```
Worker types (pluggable):
  - HTTP worker:     POST to callback URL with payload, verify 2xx response
  - SQS worker:      Publish message to target SQS queue
  - Lambda worker:   Invoke AWS Lambda function synchronously
  - gRPC worker:     Call remote procedure
  - Script worker:   Execute shell command in isolated container

Worker execution flow:
  1. Consume job from Kafka (manual commit — don't ACK until execution complete)
  2. Check execution status in DB:
     SELECT status FROM executions WHERE id=$executionId
     → If status != 'pending' → skip (duplicate delivery, already processed)
  3. Mark execution running:
     UPDATE executions SET status='running', started_at=NOW(), worker_id=$workerId
     WHERE id=$executionId AND status='pending'
     → If 0 rows → skip (another worker grabbed it — rare but possible)
  4. Execute with timeout enforcement:
     context.WithTimeout(ctx, job.timeout_sec)
  5. On success:
     UPDATE executions SET status='succeeded', completed_at=NOW(), output=$result
     UPDATE jobs SET last_run_at=NOW() (if needed)
     Kafka COMMIT offset
  6. On failure:
     Evaluate retry policy → if retryable:
       INSERT INTO executions (job_id, attempt=N+1, scheduled_at=NOW()+backoff) [retry]
       OR re-publish to retry topic with delay
     Else:
       UPDATE executions SET status='failed', error=$err
       Publish to dead-letter topic if max_attempts exceeded
     Kafka COMMIT offset (regardless — don't re-process via Kafka)

Timeout enforcement:
  Dedicated Timeout Watchdog (separate process):
    Every 30s:
      SELECT * FROM executions
      WHERE status='running'
        AND started_at < NOW() - INTERVAL '? seconds'  -- job.timeout_sec
      FOR UPDATE SKIP LOCKED;
      → Mark each as 'timed_out', trigger retry logic
```

---

### 6.7 Retry System

```
Retry policy config:
  {
    "maxAttempts": 5,
    "backoff": "exponential",     // fixed | linear | exponential | jitter
    "initialDelay": "30s",
    "maxDelay": "1h",
    "retryOn": ["timeout", "5xx", "connection_error"],
    "deadLetterQueue": "arn:aws:sqs:us-east-1:123:dlq"
  }

Backoff calculation:
  exponential: delay = min(initialDelay × 2^(attempt-1), maxDelay)
    attempt 1: 30s
    attempt 2: 60s
    attempt 3: 120s
    attempt 4: 240s
    attempt 5: 480s → capped at maxDelay

  jitter (full): delay = random(0, exponential_delay)
    → Prevents thundering herd when mass failure causes mass retry

Retry scheduling:
  Option A: Insert new execution row with scheduled_at = NOW() + backoff
    → Scheduler picks it up on next tick (clean, uses existing infrastructure)
  Option B: Kafka delayed message (Kafka doesn't support native delays)
    → Use separate retry topic + delay service (reads messages, holds until ready)

  Recommended: Option A (simpler, uses DB scheduler, consistent audit trail)

Dead-letter queue:
  After maxAttempts exhausted:
    INSERT INTO dead_letter_jobs (original_execution_id, job_id, error, failed_at)
    Publish to DLQ (SQS/Kafka dead-letter topic)
    Notify on-call if job.priority = CRITICAL

Manual retry from DLQ:
  POST /v1/executions/{executionId}/retry  → creates fresh execution, resets attempt count
```

---

### 6.8 The Top-of-Hour / Midnight Thundering Herd

The most dangerous scaling problem: millions of cron jobs scheduled for `0 * * * *` all fire simultaneously.

```
Problem:
  10M active jobs, 40% are "every hour" = 4M jobs
  At the top of every hour: 4M jobs fire in same second
  DB: 4M rows with next_run_at = "2026-08-16 15:00:00 UTC"
  Scheduler: tries to claim all 4M in one tick → DB overloaded

Solutions:

1. Claim batching + back-pressure:
   Scheduler claims max 500 jobs/tick, publishes to Kafka
   Kafka consumers drain at worker capacity (10K/sec)
   Queue depth grows but drains within seconds
   → Queue absorbs burst; workers process at steady rate

2. Jitter injection (at job creation):
   When user creates "0 * * * *" job, add random jitter:
   next_run_at = top_of_hour + random(0, 60) seconds
   → Spreads 4M jobs across 60 seconds = 67K/sec (manageable)
   Trade-off: job doesn't fire exactly at :00 → acceptable for most use cases
   For strict timing (financial, compliance): opt-out of jitter

3. Predictive pre-loading (look-ahead):
   Scheduler reads jobs due in next 60s (not just due NOW)
   Pre-publishes to Kafka with delivery_time metadata
   Workers receive early but wait until delivery_time to execute
   → DB query happens 60s before spike; no thundering herd at query layer

4. Read replicas for scheduler:
   Scheduler polls PostgreSQL read replicas (not primary)
   Primary handles writes only
   Read replicas: 3 replicas × 500 jobs/tick = 1,500 jobs/tick capacity per scheduler
```

---

### 6.9 Job Dependencies

```
Dependency model:
  Job B depends on Job A (Job B.depends_on = [Job A])

  job_dependencies:
    { dependent_job_id: B, upstream_job_id: A, condition: 'succeeded' }

Execution:
  1. Job A executes normally
  2. On Job A SUCCEEDED → Execution Result Service evaluates dependents:
     SELECT dependent_job_id FROM job_dependencies
     WHERE upstream_job_id = $A AND condition = 'succeeded'
  3. For each dependent job B:
     a. Check all B's upstreams are SUCCEEDED
     b. If yes → UPDATE jobs SET next_run_at = NOW(), status = 'active' WHERE id = B
     c. Scheduler picks up B on next tick

  Condition types:
    succeeded:       downstream fires only on success
    failed:          downstream fires only on failure (alert job)
    completed:       downstream fires regardless of outcome
    succeeded_or_failed: alias for completed

Cycle detection:
  On job creation with depends_on:
  DFS from new job through dependency graph → if visits itself → reject with 400

Fan-out / fan-in:
  Fan-out:  Job A → [B, C, D] (all fire after A)
  Fan-in:   Job E depends_on [B, C, D] (fires only when all complete)

  Fan-in tracking:
    SELECT COUNT(*) FROM job_dependencies d
    JOIN executions e ON d.upstream_job_id = e.job_id
    WHERE d.dependent_job_id = $E
      AND e.status != 'succeeded'
    → If count = 0: all upstreams done → fire E
```

---

### 6.10 Observability & Monitoring

```
Key metrics (Prometheus):
  scheduler_jobs_fired_total{priority, tenant}           counter
  scheduler_fire_lag_seconds{p50, p95, p99}              histogram  ← jobs fired late?
  scheduler_queue_depth{priority}                        gauge
  worker_execution_duration_seconds{job_type, status}   histogram
  worker_active_executions                               gauge
  retry_attempts_total{reason}                           counter
  dead_letter_jobs_total{tenant}                         counter
  scheduler_db_poll_duration_seconds                     histogram  ← DB index efficiency

Alerting thresholds:
  fire_lag_p99 > 10s          → page on-call (SLO breach imminent)
  queue_depth > 100K          → warning (worker starvation)
  dead_letter_jobs_rate > 1%  → warning (systemic failure in job type)
  scheduler_node_count < 2    → critical (single point of failure)
  DB replication_lag > 5s     → critical (scheduler might read stale data)

Distributed tracing:
  Each execution tagged with: traceId, jobId, executionId, attempt, workerId
  Spans: scheduler_claim → kafka_publish → worker_receive → job_execute → result_report
  → End-to-end latency breakdown per job type

Audit log:
  Every state transition written to audit_log table:
  { jobId, executionId, from_status, to_status, actor, timestamp, reason }
  Immutable, append-only, retained 2 years
```

---

### 6.11 Multi-Tenancy & Rate Limiting

```
Tenant isolation:
  - All tables partitioned by tenant_id
  - API authentication: API key → tenant_id (validated on every request)
  - Scheduler shards split across tenants (no cross-tenant data in a single poll)

Per-tenant limits:
  - Max active jobs: 100K (free), 10M (enterprise)
  - Max executions/sec: 100 (free), 10K (enterprise)
  - Max payload size: 64 KB (free), 1 MB (enterprise)
  - Max cron frequency: every 1 min (free), every 1 sec (enterprise)

Execution rate limiting (token bucket):
  Key: ratelimit:{tenantId}:executions
  Redis token bucket: N tokens/sec, burst up to 2N
  Worker checks before execution:
    EVAL lua_token_bucket_script KEYS[1] ARGV[1=tokens] ARGV[2=now]
  → If rate exceeded → delay execution (re-queue with 1s delay) rather than drop

Fair scheduling (prevent noisy tenant):
  Priority queue per tenant in Kafka (separate partition sets)
  Global scheduler round-robins across tenant queues
  Burst isolation: single tenant spike doesn't starve others
```

---

## 7. Data Flow Summary

### Schedule a One-Time Job

```
Client: POST /v1/jobs { runAt: "2026-09-01T09:00:00Z", type: "http", payload: {...} }
  → API Service validates, computes shard_id = hash(jobId) % 256
  → INSERT INTO jobs (next_run_at="2026-09-01T09:00:00Z", shard_id=42, status='active')
  → Response 201: { jobId }

[2026-09-01 08:59:55 UTC] Scheduler Node (owns shard 42):
  → SELECT ... WHERE shard_id=42 AND next_run_at <= NOW()+5s FOR UPDATE SKIP LOCKED
  → Finds this job
  → UPDATE jobs SET next_run_at=NULL (one-time: set status='completed' after fire)
  → INSERT INTO executions (status='pending', fencing_token=9821)
  → Publish to Kafka: { executionId, payload, fencingToken=9821 }

Worker:
  → Consume from Kafka
  → Check execution: status='pending' ✓
  → Mark 'running'
  → HTTP POST to callback URL (with X-Fencing-Token: 9821)
  → Response 200 → mark 'succeeded'
  → Kafka offset commit
  → Notification Service: webhook to client (if configured)
```

### Recurring Cron Job (Every Hour, With Failure)

```
Job: "0 * * * *", maxAttempts=3, backoff=exponential(30s)

[15:00:00] Scheduler claims job:
  → next_run_at advanced to 16:00:00 immediately (prevents re-fire)
  → execution_1 created (attempt=1), published to Kafka

[15:00:02] Worker executes → target service returns 503
  → execution_1 = FAILED, attempt=1 < maxAttempts=3
  → Retry: INSERT execution_2 (attempt=2, scheduled_at=NOW()+30s)
  → Scheduler picks up execution_2 at 15:00:32

[15:00:34] Worker retries → timeout (job.timeout=30s exceeded)
  → execution_2 = TIMED_OUT
  → Retry: INSERT execution_3 (attempt=3, scheduled_at=NOW()+60s)

[15:01:34] Worker retries execution_3 → success
  → execution_3 = SUCCEEDED
  → Notification: "✅ Job send-report succeeded after 3 attempts"
  → [16:00:00] Scheduler fires again normally (next_run_at already set to 16:00:00)
```

---

## 8. Follow-Up Questions

### Q1: How do you guarantee a job fires at most once?
```
Defense in depth (three layers):

Layer 1 — DB-level (FOR UPDATE SKIP LOCKED):
  Scheduler atomically advances next_run_at before publishing
  Two scheduler nodes cannot claim the same job in the same tick

Layer 2 — Worker-level (optimistic concurrency):
  UPDATE executions SET status='running' WHERE status='pending'
  → Only one worker transitions 'pending' → 'running' (even if Kafka delivers twice)
  → Second worker: 0 rows updated → discards message

Layer 3 — Application-level (fencing tokens):
  Worker sends fencing token to downstream system
  Downstream stores processed tokens; rejects duplicates
  → Handles: worker crashes after downstream success, Kafka redelivers

Idempotency key (for HTTP jobs):
  HTTP callback receives: X-Idempotency-Key: {executionId}
  Target service deduplicates on executionId (client responsibility)
```

---

### Q2: How do you handle clock skew between scheduler nodes?
```
Problem: Node A thinks it's 14:59:59.900, Node B thinks it's 15:00:00.100
→ Both claim different "due" jobs, potential double-fire

Solutions:
  1. NTP synchronization: all nodes sync to NTP (max skew ~10ms in practice)
     → Look-ahead window of 5s dwarfs any NTP skew

  2. DB-authoritative time: scheduler uses NOW() from PostgreSQL (not local clock)
     next_run_at <= (SELECT NOW() FROM pg_catalog.now())
     → Single authoritative clock; node clocks irrelevant

  3. Logical clocks (Hybrid Logical Clocks) for distributed event ordering
     → Overkill for scheduler; DB clock is sufficient

Recommended: DB clock (option 2) + NTP for monitoring (detect drifting nodes)
```

---

### Q3: How would you support sub-second scheduling (every 100ms)?
```
Challenges:
  1. Polling every 100ms: 10× more DB queries → index must be extremely hot
  2. Processing latency: DB query + Kafka publish + worker startup must be < 100ms
  3. 10K jobs/sec at 100ms intervals = 1M jobs/sec throughput target

Solutions:
  1. In-memory scheduling (for sub-second jobs):
     Load all sub-second jobs into memory on scheduler startup (TTL: 1h)
     Use min-heap (priority queue): { next_fire_time → jobId }
     Timer goroutine fires when heap root's time arrives → publish to Kafka (no DB poll)
     DB only used for persistence; memory heap drives real-time firing

  2. Reduce job count:
     Sub-second jobs must be "interval" type (not cron) — simple to schedule in memory
     Only enterprise tier gets sub-second (reduces scope dramatically)

  3. Dedicated sub-second scheduler tier:
     Separate service for sub-second jobs (different architecture, always in-memory)
     Standard scheduler for ≥1 second intervals
```

---

### Q4: How do you handle the scheduler DB becoming a bottleneck?
```
The scheduling loop writes to jobs + executions every second.

Optimizations:
  1. Index on (shard_id, next_run_at) WHERE status='active'
     → Partial index: only active jobs indexed (~60% of table vs all rows)
     → O(results) scan, not O(table)

  2. Write batching:
     Instead of individual UPDATEs, batch 500 updates in single statement:
     UPDATE jobs SET next_run_at=v.next FROM (VALUES ...) AS v WHERE jobs.id=v.id
     → One round-trip to DB for 500 jobs

  3. UNLOGGED tables for executions (faster writes, no WAL):
     Trade-off: lose data on crash (acceptable — executions are re-creatable)
     Execution history table: regular LOGGED table (permanent record)

  4. Partitioning executions by month (time-based):
     Old partitions auto-archived to cold storage
     Active partition stays hot in DB cache

  5. Read replicas for scheduler reads:
     Scheduler polls read replica (eventually consistent, 10ms lag)
     Only writes (next_run_at update) go to primary
```

---

### Q5: How would you design a "distributed cron" that replaces crontab on servers?
```
Requirements:
  - Run shell commands (not just HTTP callbacks)
  - Replace per-host crontab (no more server-specific jobs)
  - Workers are registered servers (not cloud workers)

Additional components:
  Worker agent: lightweight daemon installed on each server
    - Registers with scheduler: POST /v1/workers { hostname, labels: [region, env, role] }
    - Maintains WebSocket/long-poll connection to receive assigned jobs
    - Executes shell command in subprocess, streams stdout/stderr
    - Reports exit code back to scheduler

Job targeting (which server runs the job):
  "runOn": { "labels": { "role": "batch-processor", "env": "prod" } }
  Scheduler selects one matching registered server (round-robin or least-loaded)

  One server (pinned):   runOn: { hostname: "worker-42.example.com" }
  All matching servers:  runOn: { labels: {...}, fanout: true }   ← broadcast mode

  Broadcast mode (run on all matching servers simultaneously):
    Creates N executions (one per matching server)
    Useful for: config reload, cache clear across fleet

Agent failure:
  Agent sends heartbeat every 30s
  If no heartbeat for 90s → agent marked offline
  In-progress jobs on dead agent → re-assigned to live agent (retry)
```

---

### Q6: How do you handle a backlog of 10M delayed jobs all becoming due at once (system outage recovery)?
```
Scenario: System is down for 4 hours. 4M jobs that should have fired are now past-due.

Naive approach: fire all 4M immediately → thundering herd → downstream systems DDoS'd

Smart catch-up strategy:

  1. Detect catch-up mode:
     if (past_due_jobs_count > THRESHOLD):
       enter_catchup_mode()

  2. Prioritize:
     CRITICAL priority past-due jobs → fire immediately (small count)
     NORMAL priority → rate-limited firing (e.g., 10K/sec instead of full speed)
     LOW priority → delay further, fire after normal jobs caught up

  3. Staleness check:
     For each past-due job: if (NOW() - scheduled_at > job.staleness_tolerance):
       skip this execution (mark as 'skipped_stale'), log it
     Many jobs are idempotent-by-design: "send hourly stats" at T+4h is stale

  4. Rate-limited catch-up:
     Token bucket: 1,000 firings/sec during catch-up (configurable per tenant)
     Catches up in: 4M / 1000 = 4,000 seconds ≈ 66 min (acceptable)

  5. Notification:
     Alert tenants: "System recovered; X jobs from outage window were skipped/delayed"
     Provide catch-up report: { skipped, fired_late, fired_on_time }
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Scheduling claim | PostgreSQL `FOR UPDATE SKIP LOCKED` | Atomic job claim without coordination service; proven at scale (Sidekiq, GoodJob) |
| Sharding | Static hash-based shard_id | Simple, no rebalancing on scale; deterministic; index-friendly |
| Duplicate prevention | Fencing tokens + optimistic concurrency | Defense in depth without XA transactions |
| Retry scheduling | New execution row (not Kafka delay) | Consistent audit trail; uses existing scheduler infrastructure |
| Top-of-hour thundering herd | Jitter at creation + look-ahead window + Kafka buffering | Multi-layer mitigation; preserves timing SLO for strict jobs |
| Cron evaluation | DB-authoritative clock `NOW()` | Eliminates clock skew vulnerability; no NTP dependency for correctness |
| Sub-second jobs | In-memory min-heap (separate tier) | DB polling can't achieve < 1s precision; heap is O(log n) per fire |
| Multi-tenancy | Shard isolation + per-tenant Kafka partitions | Noisy-tenant containment without dedicated infrastructure per tenant |
| Outbox pattern | job_outbox table → relay process | Eliminates dual-write between DB and Kafka; at-least-once + idempotency = effectively exactly-once |
| Worker | Kafka consumer (manual commit) | Back-pressure built in; no job lost on worker crash; re-delivery for retries |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 45–60 minutes.*
