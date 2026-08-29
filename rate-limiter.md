# System Design: Distributed Rate Limiter

> FAANG-level interview guide — multi-rule, multi-algorithm, distributed rate limiting as a platform service.
> Asked at **Google**, **Microsoft**, **Stripe**, **Cloudflare**. Think: AWS API Gateway throttling, Stripe's rate limiting, Cloudflare WAF.

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | **Limit requests** based on configurable rules: reject or delay requests exceeding limits |
| FR-2 | Support **multiple rule dimensions**: per-user, per-IP, per-API-key, per-endpoint, per-tenant, global |
| FR-3 | Support **multiple time windows**: per-second, per-minute, per-hour, per-day |
| FR-4 | Support **multiple algorithms**: Token Bucket, Sliding Window, Fixed Window (per rule config) |
| FR-5 | Rules support **hierarchical composition**: global cap AND per-user cap AND per-endpoint cap |
| FR-6 | Return **standard rate limit headers**: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` |
| FR-7 | **Soft limits** (warn but allow) and **hard limits** (block with 429) |
| FR-8 | **Dynamic rule updates**: change limits without service restart or deployment |
| FR-9 | Rate limit state survives **service restarts** (durable counters) |
| FR-10 | **Audit log**: every throttled request logged (who, what endpoint, what rule triggered) |
| FR-11 | (Optional) **Distributed quota**: shared quota across multiple services (e.g., 1,000 calls/day shared across microservices A, B, C) |
| FR-12 | (Optional) **Priority lanes**: allow critical traffic (health checks, admin) to bypass limits |

**Out of scope:** Authentication, billing, circuit breaking, load shedding (though related).

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 1M requests/sec through the rate limiter fleet |
| **Latency** | Rate limit check adds < **5ms** p99 to request latency |
| **Availability** | 99.99% — limiter failure must **fail open** (allow traffic) to prevent outage |
| **Consistency** | Slight over-allowance acceptable (within 0.1% of quota); hard correctness required for billing-linked quotas |
| **Throughput** | Support 100M unique users; 10,000 unique rules |
| **Durability** | Counter state survives single Redis node failure (replication) |
| **Accuracy** | No false positives (legitimate traffic blocked) > 0.01% |
| **Rule propagation** | New/updated rules active within **10 seconds** across all nodes |

---

## 3. Back-of-Envelope Estimation

### Traffic

```
Total API requests/sec           = 1,000,000 (1M RPS through limiter)
Unique users/day                 = 100M
Peak concurrent users            = 100M × 10% = 10M
Avg rules evaluated per request  = 3 (global + per-user + per-endpoint)
Redis operations per request     = 3 rules × 2 ops (read+write) = 6 Redis ops/request
Total Redis ops/sec              = 1M × 6 = 6M ops/sec

Redis throughput:
  Single Redis node: ~100K ops/sec (single-threaded, but pipelining helps)
  With pipelining (batch 6 ops): effective = 500K–1M ops/sec per node
  Cluster nodes needed: 6M / 500K = ~12 Redis nodes (with headroom: 20 nodes)
```

### Storage (Counters)

```
Per counter entry (Redis):
  Key: "rl:{ruleId}:{dimension}:{windowBucket}"  ≈ 60 bytes
  Value: integer count                            ≈ 8 bytes
  TTL metadata                                    ≈ 8 bytes
  Redis overhead per key                          ≈ 64 bytes
  Total per entry                                 ≈ 140 bytes

Active counters at any time:
  Sliding window (per user, per endpoint): 100M users × 5 endpoints × 3 rules = 1.5B counters
  1.5B × 140 bytes ≈ 210 GB → too large for single Redis

Optimization:
  Most users are inactive at any moment (10% active = 10M users)
  10M active × 5 × 3 = 150M active counters × 140 bytes ≈ 21 GB → fits in Redis cluster
  TTL-based cleanup: counters expire when window closes (TTL = window size)

Rule storage:
  10,000 rules × 1 KB = 10 MB → trivially stored in Redis + DB
```

### Rule Evaluation Latency Budget

```
Target: < 5ms total rate limiter overhead

  Rule lookup (Redis GET):      ~0.5ms
  Counter read (Redis GET):     ~0.5ms
  Counter write (Redis INCR):   ~0.5ms
  × 3 rules (pipelined):        ~2ms total Redis round-trips
  Local cache lookup (rules):   ~0.01ms
  Decision logic (CPU):         ~0.1ms
  Network (co-located sidecar): ~0.5ms
  Total budget:                  ≈ 3ms p50, 5ms p99 ✓
```

---

## 4. High-Level Design

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           CLIENT                                             │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │ HTTP Request
                        ┌──────────▼──────────────┐
                        │   API Gateway / Proxy    │
                        │   (Nginx / Envoy / Kong) │
                        └──────────┬──────────────┘
                                   │ Rate limit check (sidecar or middleware)
                        ┌──────────▼──────────────┐
                        │   Rate Limiter Service   │
                        │   (Sidecar or gRPC call) │
                        │                         │
                        │  1. Extract identity     │
                        │     (userId, IP, apiKey) │
                        │  2. Load applicable rules│
                        │  3. Evaluate all rules   │
                        │  4. Return: ALLOW/DENY   │
                        └──────┬──────────┬────────┘
                               │          │
                    ┌──────────▼──┐  ┌────▼────────────────────┐
                    │  Redis      │  │  Rule Engine             │
                    │  Cluster    │  │  (rules cached locally + │
                    │  (Counters) │  │   synced from DB)        │
                    └─────────────┘  └──────────────────────────┘
                               │
                    ┌──────────▼──────────────────────────────────┐
                    │          Control Plane                        │
                    │  ┌──────────────────────────────────────┐   │
                    │  │  Rule Management API                  │   │
                    │  │  - CRUD rules                         │   │
                    │  │  - Rule validation                    │   │
                    │  │  - Config versioning                  │   │
                    │  └──────────────────────────────────────┘   │
                    │  ┌──────────────────────────────────────┐   │
                    │  │  Rule DB (PostgreSQL)                 │   │
                    │  │  - Source of truth for rules          │   │
                    │  └──────────────────────────────────────┘   │
                    │  ┌──────────────────────────────────────┐   │
                    │  │  Analytics & Audit Log                │   │
                    │  │  (Kafka → ClickHouse)                 │   │
                    │  └──────────────────────────────────────┘   │
                    └─────────────────────────────────────────────┘
```

### Deployment Modes

```
Mode A — Sidecar (co-located, lowest latency):
  Rate limiter runs as sidecar container in each pod
  Shared Redis cluster for distributed counters
  Rule cache: in-process (local memory), synced every 5s from Redis/DB
  Latency: ~1ms (no network hop to separate service)

Mode B — Centralized gRPC service:
  Dedicated rate limiter fleet (8 pods)
  API gateway calls limiter.Check(request) before forwarding
  Latency: ~5ms (one network hop)
  Easier to manage / update than sidecar

Mode C — Middleware (embedded in API gateway):
  Rate limiting logic built into Nginx (Lua), Envoy, or Kong plugin
  No separate service; Redis is external
  Latency: ~2ms

Recommended: Mode A (sidecar) for lowest latency + Mode C for edge/WAF use case
```

### Core API (Rate Limiter Service)

```
// Check if request is allowed
grpc.Check(CheckRequest) → CheckResponse

CheckRequest {
  requestId:   UUID             // for idempotency / audit
  identities:  map<string,string>  // { "userId": "u123", "ip": "1.2.3.4", "apiKey": "ak_..." }
  resource:    string           // "/api/v1/search"
  method:      string           // GET
  tenantId:    string
}

CheckResponse {
  decision:    ALLOW | DENY | SOFT_LIMIT
  rule:        RuleMatch        // which rule triggered (if DENY)
  headers:     map<string,string> {
    X-RateLimit-Limit:     "1000"
    X-RateLimit-Remaining: "42"
    X-RateLimit-Reset:     "1724000060"   // Unix epoch when window resets
    Retry-After:           "58"            // seconds (only on DENY)
  }
}

// Rule management (Control Plane REST API)
POST   /v1/rules                  → create rule
GET    /v1/rules/{ruleId}
PUT    /v1/rules/{ruleId}         → update (propagates within 10s)
DELETE /v1/rules/{ruleId}         → soft-delete
GET    /v1/rules?tenantId=acme    → list rules for tenant

// Quota query (for dashboards)
GET /v1/quotas?userId=u123&ruleId=r456
→ { used: 857, limit: 1000, remaining: 143, resetAt: "..." }
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Algorithm Choice — Fixed Window vs. Sliding Window Counter vs. Token Bucket vs. Sliding Window Log

| Algorithm | Memory | Accuracy | Burst Handling | Best Use Case |
|-----------|--------|---------|---------------|--------------|
| Fixed Window | O(1) | Low (boundary burst) | Allows 2× at edge | Simplest deployments |
| **Sliding Window Counter** | O(1) | High (~0.003% error) | No burst | General-purpose (Recommended) |
| Token Bucket | O(1) | High | ✅ Configurable burst | API clients needing burst |
| Sliding Window Log | O(limit) | Perfect | No burst | Billing-critical quotas |

```
Why the algorithm choice is the first question to answer:

Fixed Window — the naive trap:
  100 req/min limit. User sends 100 at :59:59 and 100 at :00:01 → 200 requests in 2s.
  The boundary burst doubles effective throughput.
  When to still use it: internal service-to-service calls where exact counts don't matter;
  you want minimal Redis ops (1 INCR per check, not 4).

Sliding Window Log — billing-correct but memory-explosive:
  Stores every request timestamp: ZADD key {now_ms} {requestId}
  At 10,000 req/min limit: 10,000 ZSET entries per user → 100M users = 10^12 entries impossible
  Only viable for: low-volume, billing-critical quotas (e.g., 100 API calls/day per key)
  The key constraint: memory is O(limit), not O(users). At limit=10K → impractical at scale.

Sliding Window Counter — the production sweet spot:
  Two fixed-window buckets (current + previous) + weighted interpolation
  Memory: 2 integers per user per rule → O(1) regardless of limit value
  Error: |true_count - estimated_count| ≤ (current_window_elapsed / window_size) × prev_count
  Worst case: 0% elapsed → 0 error. 50% elapsed → up to 50% of prev_count × error_factor
  Empirical error: ~0.003% — negligible for rate limiting (not billing)
  This is what Cloudflare and Stripe use for general rate limiting.

Token Bucket — best when burst tolerance is a feature, not a bug:
  Example: mobile apps go offline for 30 min → reconnect → burst of queued API calls
  Token bucket absorbs the burst (up to cap) while enforcing the sustain rate
  Fixed/Sliding window would immediately throttle the reconnecting app → bad UX
  Cost: Lua script required for atomicity (atomic read-modify-write of {tokens, last_refill})

Decision framework (say this in the interview):
  "If the client needs burst tolerance: Token Bucket.
   If billing accuracy matters: Sliding Window Log.
   For everything else: Sliding Window Counter — O(1) memory, minimal error, simple."
```

---

### Trade-Off 2: Counter Storage — Redis vs. In-Process Memory vs. Database

| Storage | Latency | Distributed Accuracy | Durability | Failure Behavior |
|---------|---------|---------------------|-----------|-----------------|
| **Redis (Recommended)** | < 1ms | ✅ Exact | ✅ Replication | Fail open |
| In-process (per-node) | < 0.01ms | ❌ Over-allows N× | ❌ Lost on restart | Always works |
| Database (PostgreSQL) | 5–20ms | ✅ Exact | ✅ ACID | Blocks on failure |
| In-process + Redis sync | < 0.1ms | ✅ ~100ms lag | ✅ | Fail open |

```
The core problem: rate limiting is only meaningful if it's distributed.

In-process only (wrong at scale):
  10 rate limiter nodes; user limit = 100/min per user
  Each node allows 100 → user makes 1,000 requests → rate limiter does nothing
  Valid only for: single-node deployments (not FAANG scale)

Database (PostgreSQL):
  UPDATE counters SET count = count + 1 WHERE user_id=$1 AND window=$2
  RETURNING count > limit AS denied;
  ✅ ACID: atomic read-modify-write
  ❌ Latency: 5–20ms per request → violates 5ms budget
  ❌ At 1M RPS: 1M writes/sec → PostgreSQL cannot sustain this
  ❌ Connection pool saturation: 1M concurrent threads impossible
  Use only for: audit/compliance counters (async, not in the hot path)

Redis (correct for rate limiting):
  INCR is atomic → one caller gets each integer value → no race conditions
  Latency: < 1ms over local network (sidecar deployment: 0.1ms)
  At 1M RPS with 6 ops per request: 6M Redis ops/sec
  Clustered Redis: 20 nodes × 300K ops/sec = 6M ops/sec capacity ✓
  TTL-based cleanup: counters auto-expire when window closes → no maintenance
  Failure: Redis down → fail open (brief over-allowance vs. false 429s)

In-process + Redis sync (best of both):
  Each limiter node maintains local integer for each user
  Every 100ms: flush local delta → Redis INCRBY; sync remaining quota from Redis
  Result: 10× fewer Redis ops (batch syncing); 100ms accuracy window
  Failure: Redis down → local counters continue (brief over-allowance, not outage)
  This is the recommended architecture at large scale (1M+ RPS).

Decision: Redis as shared counter store + local batching for ops reduction.
  The key insight: INCR's atomicity is the mechanism, not a workaround.
  Never use a DB as the rate limit counter store — latency alone disqualifies it.
```

---

### Trade-Off 3: Deployment Model — Sidecar vs. Centralized Service vs. API Gateway Middleware

| Model | Latency | Operational Complexity | Scale | Failure Isolation |
|-------|---------|----------------------|-------|-----------------|
| **Sidecar (Recommended)** | ~1ms | High (many instances) | ✅ Scales with app | ✅ Per-pod |
| Centralized gRPC service | ~5ms | Low (one fleet) | Requires own scaling | ❌ Shared SPOF |
| API Gateway middleware (Nginx/Envoy) | ~2ms | Low | ✅ | ✅ Per-gateway |

```
Sidecar (co-located per pod):
  Rate limiter runs in same pod as the application (as a container sidecar)
  Communication: localhost → ~0.1ms (no network hop)
  Rule cache: in-process memory (same process space, fastest possible)
  Redis: shared across all sidecars (same Redis cluster)

  Advantages:
    Latency < 1ms (sub-millisecond intra-pod IPC)
    No network single point of failure between app and limiter
    Scales automatically: 1 sidecar per app pod → scales with app tier
    Independent restart: limiter can restart without app restart (separate container)

  Disadvantages:
    Rule updates: must propagate to each individual sidecar instance (Kafka solves this)
    Observability: throttle events spread across many pods → aggregate with Kafka
    Resource overhead: ~50 MB RAM × 1,000 pods = 50 GB total (acceptable)

Centralized gRPC service:
  Dedicated fleet of rate limiter servers; app pods call limiter.Check() via gRPC
  One additional network hop: ~5ms (within DC) — borderline for 5ms budget

  Advantages:
    Simpler rule propagation (fewer instances to update)
    Centralized debugging (all throttle logic in one fleet)
    Easier to hot-upgrade (single fleet rollout, not 1,000 sidecars)

  Disadvantages:
    5ms latency overhead per request → tight against NFR budget
    gRPC call failure → must fail open → adds circuit breaker complexity
    Centralized fleet failure → all apps affected simultaneously

API Gateway middleware (Envoy/Nginx/Kong plugin):
  Rate limiting logic in the gateway layer; requests blocked before reaching app
  Redis external; gateway plugin implements the algorithm

  Advantages:
    Blocks before app compute (saves backend resources on throttled requests)
    Centrally managed (one place to update Nginx/Envoy config)
    Kong/Cloudflare do this natively — no custom code

  Disadvantages:
    Gateway and rate limiter coupled — gateway upgrade affects rate limiting
    Less flexible: gateway plugins have limited algorithm support vs. custom service
    For complex rules (multi-dimensional, tenant-aware): plugins become complex

Decision: Sidecar for lowest latency + API gateway for edge/WAF use (DDoS protection).
  Two tiers: gateway for IP-level blocking (coarse), sidecar for user/API key limits (fine).
  Never centralized service as sole architecture: the 5ms budget and SPOF risk are too high.
```

---

### Trade-Off 4: Fail Behavior — Fail Open vs. Fail Closed vs. Stale Cache

| Strategy | On Redis Failure | Risk | Correct For |
|----------|-----------------|------|------------|
| **Fail Open (Recommended)** | Allow all traffic | Brief over-allowance | 99% of use cases |
| Fail Closed | Block all traffic | Service outage | Payment fraud prevention only |
| Stale Cache (Recommended) | Use last known count | Minor over-allowance | Middle ground |

```
The fundamental question: what's worse — letting through extra requests or blocking all users?

Fail Closed (block on Redis failure):
  Redis goes down → every request returns 429
  "Safe" from quota perspective: nobody exceeds their limit
  Reality: Redis going down becomes a complete service outage for all users
  For a social platform, e-commerce site: catastrophic → users churn, revenue lost
  Valid ONLY for: financial fraud prevention (blocking 1 fraudulent transaction > serving 1M users)

Fail Open (allow on Redis failure):
  Redis goes down → all requests allowed (no rate limiting)
  Brief over-allowance: attacker can make unlimited requests during Redis outage
  Duration: seconds to minutes until Redis recovers or circuit breaker opens
  At-risk: DDoS attacks during the Redis outage window
  Mitigation: secondary rate limiting at load balancer / WAF (IP-level, not user-level)
  For most systems: brief over-allowance is acceptable. False 429s are never acceptable.

Stale cache (the production-correct answer):
  Rate limiter maintains local counter cache (last 30s of Redis values)
  On Redis failure: use last known count + apply conservative rule
    If last_known_count < 50% of limit → ALLOW (user was clearly within quota)
    If last_known_count > 80% of limit → DENY (user was close to limit)
    Gap (50-80%): ALLOW with soft-limit header warning
  As Redis recovers: counts may lag → brief over-allowance in the gray zone
  This is the nuanced answer that satisfies most use cases

Circuit breaker around Redis:
  Redis p99 latency > 50ms → open circuit (stop calling Redis)
  Use stale cache decisions for circuit-open duration (30s)
  On circuit close: re-sync local counters from Redis

Decision: Fail open + stale cache for the gray zone + secondary WAF for IP-level protection.
  State this clearly: "The rate limiter must fail open. A rate limiter outage must never
  become a service outage. Brief over-allowance is the correct trade-off."
```

---

### Trade-Off 5: Rule Propagation — Kafka Push vs. Polling vs. gRPC Streaming

| Mechanism | Propagation Latency | Complexity | Ordering Guarantee |
|-----------|--------------------|-----------|--------------------|
| **Kafka push (Recommended)** | < 2s | Medium | ✅ Per-partition |
| Short-poll (DB every 5s) | 5–10s | Low | ❌ |
| gRPC server streaming | < 500ms | High | ✅ |
| Redis pub/sub | < 100ms | Low | ❌ (at-most-once) |

```
The propagation requirement: rule changes must reach all limiter instances within 10 seconds.

Short polling (DB every N seconds):
  Each limiter instance: SELECT * FROM rules WHERE updated_at > last_poll_at
  Every 5 seconds → max 5s propagation lag (within 10s SLO)
  Simplest implementation; no additional infrastructure
  Problem at scale: 1,000 limiter instances × polling every 5s = 12,000 DB queries/min
  Mitigated with: Redis as intermediary (DB → Redis on write, all instances poll Redis)
  Redis poll every 5s: 1,000 instances × 12/min = 12,000 Redis GETs/min → trivial

Kafka push (recommended):
  Rule write → DB → publish to Kafka rule-updates topic
  All limiter instances subscribe → consume update → apply to local cache
  Propagation: Kafka delivery ~1ms → cache update ~1ms → all instances updated < 2s
  Ordering: Kafka partition per tenant → rule updates for same tenant always ordered
  Exactly-once: Kafka consumer group with offset tracking → no duplicate rule applications
  At-least-once is acceptable (applying the same rule update twice is idempotent)

  Additional benefit: Kafka log is a full audit of all rule changes (who changed what, when)

gRPC server streaming:
  Control plane holds a streaming gRPC connection to each limiter instance
  On rule change: push new rule via stream → < 500ms propagation
  Problem: control plane must maintain 1,000+ streaming connections → complex
  Connection management: reconnection logic, health checks, backpressure
  Overhead exceeds benefit vs. Kafka for this use case

Redis pub/sub:
  Publish to channel rule-updates → all subscribers receive immediately
  Problem: at-most-once delivery → subscriber that's offline misses the message
  No message retention → new limiter instances can't replay missed updates
  Workaround: Redis pub/sub for notification + Redis hash for full rule state
    → Pub/sub fires → subscriber re-reads full rule from Redis hash
  Simple and fast, but less reliable than Kafka for critical rule updates

Decision: Kafka for rule propagation (reliable, ordered, replay-capable) + Redis hash
  for full rule state (new instances bootstrap from Redis, not from Kafka replay).
  The two together: Kafka as the event bus + Redis as the materialized view of current rules.
```

---

### Trade-Off 6: Multi-Region Consistency — Per-Region Limits vs. Quota Partitioning vs. CRDTs

| Strategy | Accuracy | Cross-Region Latency | Complexity | Over-Allowance Risk |
|----------|---------|---------------------|-----------|-------------------|
| Per-region independent limits | ❌ User can abuse all regions | Zero | Low | High |
| **Quota partitioning (Recommended)** | ✅ Within one rebalance window | Zero | Medium | Low |
| Centralized global Redis | ✅ Exact | 50–100ms | Low | None |
| CRDT G-Counters | ✅ Eventual | Zero | High | Medium |

```
Why multi-region rate limiting is the hardest distributed systems problem in this design:

Centralized global Redis (single region):
  All rate limiters across all DCs call the same Redis cluster
  Cross-region latency: US-EAST ↔ EU = 80ms; US-EAST ↔ APAC = 150ms
  At 5ms budget: 80ms latency kills it immediately
  Valid only for: non-latency-sensitive quota checks (daily limits, billing quotas)
  Users won't notice 80ms added latency on their once-a-day quota check

Per-region independent limits:
  Each region has its own Redis; limit = global_limit / num_regions
  User gets 1,000/min globally → 333/min per region
  Abuse: user VPNs to 3 regions → makes 1,000 requests × 3 = 3,000 requests total
  Unused quota: EU region quiet at night → its 333/min budget wasted
  When to use: when abuse via multi-region is unlikely (internal APIs, trusted clients)

Quota partitioning (recommended for consumer APIs):
  Central quota allocator (runs per region, globally consistent via Raft/Paxos):
    Every 60 seconds: measure actual traffic per region
    Rebalance: allocate quota proportional to traffic weight + small buffer
    Example: 1,000/min global; US-EAST 60%, EU 25%, APAC 15%
    → US-EAST gets 600, EU gets 250, APAC gets 150

  Each region rate limits locally against its allocated quota (fast, no cross-DC call)
  On rebalancing: allocator reclaims unused quota from underused regions → redistributes
  Over-allowance: max one rebalance window (60s) of local over-allowance on burst

  Rebalancer design: simple Raft-elected leader (3 nodes) per region
    writes quota allocation to Redis in each region (low write frequency = cheap cross-DC write)

CRDT G-Counters:
  Each region maintains a G-Counter (grow-only, merge = component-wise max)
  Sync between regions: gossip every 100ms
  Global count = sum of all regional counters
  Over-allowance: up to 100ms × (N regions) of independent increments
  At 1,000 req/sec global limit with 100ms sync: 100 extra requests per region per sync cycle
  Problem: sum across regions becomes the limit, but each region doesn't know others' latest
  Requires careful design to avoid over-allowing by >10% — complex to get right

Decision: quota partitioning for user-facing APIs (< 60s accuracy, no cross-DC latency).
  Centralized Redis for billing-critical daily quotas (user won't notice 80ms on rare checks).
  Never per-region independent limits for public APIs with abuse potential.
```

---

### Trade-Off 7: Accurate vs. Approximate Counting — When Over-Allowance is Acceptable

| Use Case | Over-Allowance Acceptable | Recommended Algorithm |
|----------|--------------------------|----------------------|
| DDoS / abuse prevention | ✅ Yes | Fixed Window or Sliding Window Counter |
| API rate limiting (UX) | ✅ Small amount | Sliding Window Counter |
| Billing-linked quotas | ❌ No | Sliding Window Log |
| Financial transaction limits | ❌ No | Sliding Window Log + DB audit |
| Healthcare data export limits | ❌ No | Sliding Window Log + DB audit |

```
The over-allowance trade-off is what separates algorithmic understanding from systems thinking:

Over-allowance in rate limiting = allowing slightly more requests than the stated limit.
Under-allowance = blocking requests that should be allowed (false negatives in blocking).
False denial = legitimate user gets 429 when they shouldn't (worst outcome for UX).

Sliding Window Counter error bound:
  Maximum over-count = prev_window_count × (1 - elapsed/window_size)
  Worst case: at window start (elapsed=0), error = prev_window_count
  If prev_window_count = limit (100), error can be up to 100 requests
  But: if user is at their limit AND previous window was full → user is a heavy user
  → Slight over-allowance for heavy users is usually acceptable

  For 100 req/min limit: worst-case error ≈ 0-5 extra requests in edge cases
  Empirical error rate: 0.003% of requests affected (Cloudflare measurement)

When you CANNOT tolerate any over-allowance:
  1. Billing quotas: "100 API calls/day costs $0.10 each"
     → 1 over-allowed call = $0.10 revenue not captured
     → At 100M users × 1 over-allowed call: $10M/day lost → use Sliding Window Log

  2. Financial transactions: "max 3 password reset attempts/hour" (security)
     → Over-allowance means attacker gets attempt 4 → potential breach
     → Use Sliding Window Log + DB record of every attempt

  3. Compliance: HIPAA data exports → over-allowance violates audit requirements

  4. Hard SLA commitments: "guaranteed exactly 1000 API calls/month per contract"
     → Customer disputes extra usage → needs exact count

The interview framing:
  "The accuracy requirement tells me which algorithm to use. For most rate limiting,
  the Sliding Window Counter's ~0.003% error is invisible in practice. For billing
  or security-critical limits, I'd use the Sliding Window Log and accept the memory cost,
  because over-allowance has direct financial or security consequences."
```

---

## 6. Deep Dive

### 6.1 Rate Limiting Algorithms

**Algorithm 1 — Fixed Window Counter**

```
Concept: count requests in fixed calendar windows (1:00–1:01, 1:01–1:02, ...)

Redis:
  Key:   rl:fixed:{ruleId}:{dimension}:{window_epoch}
         window_epoch = floor(now_unix / window_size_sec)
  Op:    INCR key → if result > limit → DENY
         (Set TTL = window_size_sec on first INCR via SET NX or EXPIRE)

  Example: 100 req/min
    window_epoch = floor(1724000030 / 60) = 28733333
    Key: rl:fixed:r1:user:u123:28733333

  Pros:  Simple; O(1) Redis ops; memory-efficient
  Cons:  Boundary burst: user sends 100 at :59 and 100 at :00 → 200 in 2 seconds
         This is the key weakness interviewers probe on

  ──────────────────────────────────────
  Window 1 [00:00–01:00]  ████████ 100
  Window 2 [01:00–02:00]  ████████ 100
  At boundary:            ████████████████ 200 in 2 seconds ← PROBLEM
  ──────────────────────────────────────
```

**Algorithm 2 — Sliding Window Log**

```
Concept: store timestamp of every request; count requests in [now-window, now]

Redis:
  Key:  rl:log:{ruleId}:{dimension}
  Type: Sorted Set (ZSET), score = timestamp_ms, member = requestId
  Ops:
    ZADD key {now_ms} {requestId}               # add new request
    ZREMRANGEBYSCORE key 0 {now_ms - window_ms}  # remove old entries
    ZCARD key → if > limit → DENY
    EXPIRE key {window_sec}

  Example: 100 req/min
    Every request: O(log N) for ZADD, O(expired) for ZREM, O(1) for ZCARD
    3 Redis commands per check

  Pros:  Perfectly accurate; no boundary burst; smooth enforcement
  Cons:
    - Memory: stores every request timestamp → O(limit) per user per window
      100 req/min × 10M users = 1B entries → ~100 GB RAM
    - Latency: 3 Redis round-trips (or pipelined)
    - Not practical at scale for high limits (10,000 req/sec)
  Use when: low-volume, high-accuracy needed (billing quotas)
```

**Algorithm 3 — Sliding Window Counter (Recommended for most cases)**

```
Concept: approximate sliding window using two fixed windows + weighted interpolation

Two buckets: current window + previous window
Sliding count = prev_count × overlap_fraction + curr_count

  overlap_fraction = (window_size - elapsed_in_current_window) / window_size

  Example: 100 req/min limit
    Previous window: 80 requests
    Current window:  30 requests (40s elapsed)
    Overlap fraction: (60 - 40) / 60 = 0.33
    Sliding count: 80 × 0.33 + 30 = 26.4 + 30 = 56.4 → ALLOW

Redis:
  Two keys (current + previous window):
    rl:sw:{ruleId}:{dimension}:{window_epoch}        // current
    rl:sw:{ruleId}:{dimension}:{window_epoch - 1}    // previous
  2 INCR + 2 GET ops (pipelined = 1 round-trip)

  Pros:
    - O(1) memory (2 integers per user per rule) → scales to 100M users
    - Very accurate (~0.003% error rate)
    - Fast: 4 Redis ops pipelined
    - No boundary burst (weighted interpolation smooths the edge)
  Cons:
    - Slight approximation (acceptable for most use cases)

  ──────────────────────────────────────────────────────
  This is what Cloudflare, Stripe, and most production systems use.
  ──────────────────────────────────────────────────────
```

**Algorithm 4 — Token Bucket**

```
Concept: bucket holds N tokens; refilled at rate R/sec; each request consumes 1 token

State: { tokens: float, last_refill: timestamp }

Redis (Lua script — atomic execution):
  local tokens = tonumber(redis.call('HGET', key, 'tokens'))
  local last   = tonumber(redis.call('HGET', key, 'last_refill'))
  local now    = tonumber(ARGV[1])
  local rate   = tonumber(ARGV[2])   -- tokens per second
  local cap    = tonumber(ARGV[3])   -- bucket capacity

  -- Refill tokens based on elapsed time
  local elapsed = now - last
  tokens = math.min(cap, tokens + elapsed * rate)

  if tokens >= 1 then
    tokens = tokens - 1
    redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
    return {1, tokens}  -- ALLOW, remaining
  else
    redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
    return {0, 0}  -- DENY
  end

Pros:
  - Handles bursts gracefully (bucket absorbs traffic spike up to cap)
  - Smooth traffic shaping: no thundering herd at window boundary
  - Intuitive: rate = tokens/sec, cap = max burst size
Cons:
  - Two fields per user (tokens + timestamp) → slightly more memory than counter
  - Lua script required for atomicity → slightly more latency
  - Floating point precision issues at extreme rates

Use when: burst tolerance required (e.g., allow 100 requests in a burst but sustain ≤ 10/sec)
```

**Algorithm 5 — Leaky Bucket**

```
Concept: requests enter a queue (bucket); processed at constant rate; queue overflow = DENY

  │ Requests ─► [Queue (bucket)] ─► constant rate ─► Backend
  │                ↓ overflow
  │              DENY (429)

Implementation: token bucket with strict output rate (no burst at backend)
Use case: smoothing traffic to protect fragile backends
Rarely used standalone in distributed systems — token bucket is more common
```

**Algorithm Comparison:**

```
┌───────────────────────┬──────────┬──────────┬─────────────┬──────────┬──────────┐
│ Algorithm             │ Accuracy │ Memory   │ Burst Allow │ Latency  │ Best For │
├───────────────────────┼──────────┼──────────┼─────────────┼──────────┼──────────┤
│ Fixed Window          │ Low      │ O(1)     │ ✓ (edge)    │ 1 op     │ Simple   │
│ Sliding Window Log    │ Perfect  │ O(limit) │ ✗           │ 3 ops    │ Billing  │
│ Sliding Window Counter│ High     │ O(1)     │ ✗           │ 4 ops    │ General  │
│ Token Bucket          │ High     │ O(1)     │ ✓ (capped)  │ Lua+2 ops│ Burst OK │
│ Leaky Bucket          │ High     │ O(queue) │ ✗           │ queue    │ Smoothing│
└───────────────────────┴──────────┴──────────┴─────────────┴──────────┴──────────┘
```

---

### 6.2 Rule Engine — Multi-Dimensional Rules

```
Rule schema:

CREATE TABLE rate_limit_rules (
  id           UUID PRIMARY KEY,
  name         TEXT,
  tenant_id    UUID,
  priority     INT,           -- lower number = evaluated first; wins on conflict
  active       BOOLEAN DEFAULT TRUE,
  algorithm    TEXT,          -- fixed_window | sliding_window | token_bucket
  limit        INT,           -- max requests
  window_sec   INT,           -- time window
  burst_cap    INT,           -- for token bucket: max burst
  action       TEXT,          -- deny | soft_limit | delay
  dimensions   JSONB,         -- which identity axes to key on
  conditions   JSONB,         -- filter: when does this rule apply
  version      INT            -- for optimistic locking on updates
);

-- dimensions example:
{
  "key_by": ["userId"],           -- partition counters by userId
  "scope": ["endpoint", "method"] -- rule applies per unique (endpoint, method)
}

-- conditions example:
{
  "endpoints":  ["/api/v1/search", "/api/v1/suggest"],
  "methods":    ["GET"],
  "user_tiers": ["free"],           -- only applies to free-tier users
  "ip_ranges":  [],                 -- empty = all IPs
  "time_of_day": { "from": "00:00", "to": "23:59", "tz": "UTC" }
}
```

**Rule examples:**

```yaml
# Rule 1: Global API cap (catch-all)
name: global-api-cap
priority: 100
algorithm: sliding_window
limit: 10000000      # 10M requests
window_sec: 60       # per minute (global, not per-user)
key_by: []           # no dimension = single global counter
action: deny

# Rule 2: Per-user, per-minute limit
name: per-user-minute
priority: 10
algorithm: sliding_window_counter
limit: 60            # 60 requests per minute per user
window_sec: 60
key_by: [userId]
conditions:
  user_tiers: [free, basic]
action: deny

# Rule 3: Per-IP brute force protection
name: ip-brute-force
priority: 5          # highest priority (lowest number)
algorithm: fixed_window
limit: 100
window_sec: 60
key_by: [ip]
conditions:
  endpoints: [/api/v1/auth/login, /api/v1/auth/token]
action: deny

# Rule 4: Per-API-key, per-day quota (billing-linked)
name: api-key-daily-quota
priority: 20
algorithm: sliding_window_log   # billing requires exact accuracy
limit: 10000         # 10K calls per day
window_sec: 86400
key_by: [apiKey]
action: deny

# Rule 5: Enterprise customer burst allowance
name: enterprise-burst
priority: 8
algorithm: token_bucket
limit: 100           # sustain: 100 req/sec
window_sec: 1
burst_cap: 500       # burst: up to 500 in a moment
key_by: [tenantId]
conditions:
  user_tiers: [enterprise]
action: soft_limit   # warn, don't block
```

---

### 6.3 Rule Evaluation Logic

```
Request arrives → Rate Limiter Service:

Step 1 — Extract identities:
  userId   = JWT claims.sub     (from Authorization header)
  apiKey   = headers["X-API-Key"]
  ip       = X-Forwarded-For (first IP, trusted proxy)
  endpoint = request.path normalized  (/api/v1/users/123 → /api/v1/users/{id})
  tenantId = JWT claims.tenant_id
  tier     = JWT claims.tier

Step 2 — Load applicable rules:
  rules = LocalRuleCache.get(tenantId, endpoint, tier)
  (Cache TTL: 5s; refreshed from Redis/DB asynchronously)
  Sort by priority ASC (lowest number first = highest priority)

Step 3 — Evaluate rules in priority order:
  for rule in sorted_rules:
    if not rule.conditions_match(request): continue

    counter_key = build_key(rule, identities)
    result = check_and_increment(rule.algorithm, counter_key, rule)

    if result == DENY:
      log_throttle_event(request, rule)    // async, non-blocking
      return DENY, headers(rule, result)

    // Track tightest remaining for headers
    tightest = min(tightest, result.remaining_pct)

  return ALLOW, headers(tightest_rule, tightest_result)

Step 4 — Response headers:
  X-RateLimit-Limit:     {rule.limit}
  X-RateLimit-Remaining: {remaining}
  X-RateLimit-Reset:     {unix_epoch of next window reset}
  X-RateLimit-Policy:    "60;w=60"   // IETF draft standard

  On DENY:
    HTTP 429 Too Many Requests
    Retry-After: {seconds until window resets}
    body: { "error": "rate_limit_exceeded", "rule": "per-user-minute", "retryAfter": 42 }
```

---

### 6.4 Atomic Counter Operations in Redis

The critical correctness requirement: **read + increment must be atomic**.

**Problem without atomicity:**

```
Thread A: GET counter → 99 (under limit of 100)
Thread B: GET counter → 99 (under limit of 100)
Thread A: SET counter = 100 → ALLOWED
Thread B: SET counter = 100 → ALLOWED (should have been denied!)
Result: 101 requests allowed against a limit of 100
```

**Solutions:**

```
Solution A — INCR + check (for fixed window / sliding window counter):
  val = INCR key           # atomic; returns new value after increment
  if val == 1:
    EXPIRE key window_sec  # set TTL on first increment (no race: INCR + EXPIRE in pipeline)
  if val > limit:
    return DENY, remaining=0
  return ALLOW, remaining=limit-val

  Why this works: INCR is atomic → only one caller gets any given value
  Race: two callers both increment → one gets 100, one gets 101 → correct (101 = denied)

Solution B — Lua script (for token bucket / complex logic):
  Redis executes Lua scripts atomically (single-threaded, no interleaving)
  Used when: multiple reads + conditional writes in one operation
  Example: token bucket (read tokens, compute refill, check, decrement, write)

Solution C — Redis EVAL + WATCH/MULTI/EXEC (optimistic locking):
  WATCH key
  MULTI
    GET key
    INCR key
  EXEC  → fails if key changed between WATCH and EXEC → retry
  Complex; prefer Lua for simplicity

Solution D — Redis Pipeline (batch multiple ops, NOT atomic):
  Pipeline: sends commands without waiting for each response
  NOT atomic: use only for independent reads/writes (not check-then-act)
  Correct use: pipeline INCR for multiple rules simultaneously (each INCR is itself atomic)
```

---

### 6.5 Distributed Rate Limiting Challenges

**Problem: rate limiter runs on N nodes, each with local counters**

```
Scenario: 100 req/min limit per user
  10 rate limiter nodes
  Naive local counting: each node allows 100 → user makes 1,000 requests!

Solutions:

Option A — Centralized Redis (shared counter):
  All nodes read/write the same Redis counter
  ✅ Perfectly accurate
  ❌ Redis is a bottleneck + single point of failure (mitigate with cluster + replicas)
  ❌ Every request = Redis round-trip (latency)

Option B — Sticky routing (consistent hashing):
  Route requests for user U always to the same rate limiter node
  That node owns U's counter (local memory, no Redis)
  ✅ Zero inter-node coordination
  ❌ Node failure loses all counters for its users (brief over-allowance on failover)
  ❌ Hot users cause imbalanced load

Option C — Token sharing / gossip:
  Each node keeps local counter
  Periodically (every 100ms) gossip count deltas to other nodes
  Approximate global count = sum of last known local counts
  ✅ Low latency (local reads)
  ❌ Window of up to 100ms over-allowance
  ❌ Complex to implement correctly

Option D — Local + Redis hybrid (Recommended):
  Local in-process counter: absorbs first N requests per 100ms window
  Sync to Redis: every 100ms flush local delta to Redis INCRBY
  Read from Redis: re-sync local remaining quota every 100ms

  Each node claims a "token budget" from Redis in batches:
    Node requests 10 tokens from Redis (batch size = local_limit/num_nodes)
    Redis decrements shared counter by 10, returns remaining
    Node distributes 10 requests locally without Redis round-trips
    When node's local budget exhausted → fetch next batch from Redis

  ✅ ~100ms granularity (acceptable for per-minute limits)
  ✅ 10× reduction in Redis ops (batch fetching)
  ✅ Node failure: only wastes unclaimed batch (small over-allowance)
  ✅ Works for per-second limits too (smaller batch size)
```

---

### 6.6 Rule Propagation (Dynamic Updates)

```
Requirement: new/updated rules active within 10 seconds across all nodes

Architecture:

1. Operator updates rule via Control Plane API:
   PUT /v1/rules/{ruleId} { limit: 200 }

2. Control Plane:
   a. UPDATE rules SET limit=200, version=version+1 WHERE id=$id  (PostgreSQL)
   b. Publish to Kafka: rule-updates topic { ruleId, version, action: "upsert" }
   c. Update Redis: SET rule:{ruleId} {serialized_rule} EX 3600

3. Rate Limiter nodes (all instances):
   a. Kafka consumer: each node subscribes to rule-updates
   b. On message: update local rule cache (in-process HashMap)
   c. Propagation time: Kafka delivery ~1ms, cache update ~1ms
   d. Total: < 2 seconds for all nodes to have updated rule ✓

4. Fallback (if Kafka missed):
   Rule cache TTL: 30 seconds
   On expiry: re-fetch from Redis (Redis has fresh version)
   Max stale time: 30 seconds (within 10-second SLO? adjust TTL to 10s if needed)

5. Config versioning:
   Each rule has version integer
   Nodes reject rules with version ≤ current (prevent rollback attacks)
   Rule deletion: set active=false (never hard-delete; audit trail preserved)

Rule validation before activation:
  - Syntax check (valid algorithm, positive limit, valid dimensions)
  - Conflict detection: overlapping rules with same priority → warn operator
  - Simulation: "dry run" mode — evaluate rule against last 1h of traffic without enforcing
    → shows impact: "would have blocked X% of requests"
```

---

### 6.7 Handling Cascading Failures — Fail Open Strategy

```
Rate limiter MUST fail open (allow traffic) when degraded.
Blocking legitimate traffic due to limiter failure = worse than a few extra requests.

Failure scenarios:

Scenario 1 — Redis cluster down:
  Symptom: Redis connection timeout
  Response:
    if redis_unavailable():
      local_fallback.allow(request)   # allow all (fail open)
      increment_local_error_counter()
      alert_ops_if(error_rate > 5%)
  Duration: log warning, allow traffic until Redis recovers
  Risk: brief over-allowance during outage (acceptable vs. false 429s)

Scenario 2 — Redis slow (high latency):
  Symptom: Redis p99 > 50ms
  Response:
    Async check: start Redis call but don't block critical path > 5ms
    if redis_call.wait(5ms) → timeout:
      use last_known_count from local cache (30-second stale allowed)
      if last_known_count < 80% of limit → ALLOW (conservative)
      if last_known_count > 80% → async check completes or ALLOW with log

Scenario 3 — Rate limiter service crash (gRPC mode):
  API gateway: circuit breaker around rate limiter call
  if limiter.check() fails:
    ALLOW request (fail open)
    increment error counter
    open circuit for 30s (stop calling limiter)
  Hystrix / Resilience4j circuit breaker pattern

Scenario 4 — Rule cache miss (DB down):
  Fall back to last cached rules (in-process)
  If no cached rules: use conservative hardcoded fallback (e.g., 60 req/min per user)
  Never fail open completely with 0 rate limiting (DoS risk)

Monitoring:
  rate_limiter_errors_total{type=redis_timeout}   → alert if > 1%
  rate_limiter_decisions_total{decision=allow_failopen}
  rate_limiter_latency_p99                         → alert if > 10ms
```

---

### 6.8 Hierarchical / Composed Rules

```
Real-world requirement: multiple caps must ALL pass

Example: Stripe API rate limits
  - Global: 100 req/sec (entire platform)
  - Per API key: 25 req/sec
  - Per endpoint: POST /charges → 10 req/sec per API key
  - Per IP: 50 req/min (abuse protection)

Evaluation: request must pass ALL rules (logical AND)

  Global counter: 1 INCR (shared, no dimension)
  Per API key:    1 INCR (keyed by apiKey)
  Per endpoint:   1 INCR (keyed by apiKey + endpoint)
  Per IP:         1 INCR (keyed by ip)

  → 4 Redis INCRs in one pipeline (4 commands, 1 round-trip)
  → If ANY INCR exceeds limit → 429, most restrictive rule returned in response

Quota sharing across services (distributed quota):
  Scenario: API key gets 10,000 calls/day shared across service A, B, C

  Centralized quota ledger (Redis counter):
    Key: quota:{apiKey}:daily:{day}
    All services INCR the same key
    Each service checks: if result > 10,000 → DENY

  Challenge: each service is a separate Kafka consumer group, different codebases
  Solution: quota client library (shared across services)
    - Wraps Redis INCR with retry + circuit breaker
    - Publishes quota events to Kafka for analytics
    - Services include library as dependency (no separate service needed for simple cases)
```

---

### 6.9 Audit Log & Analytics

```
Every throttled (and optionally allowed) request logged asynchronously:

Throttle event:
  { requestId, userId, ip, apiKey, endpoint, ruleId, ruleName,
    algorithm, limit, currentCount, decision: "deny",
    timestamp, tenantId, responseTime }

Async pipeline (non-blocking):
  Rate limiter → Kafka topic: rate-limit-events (fire-and-forget, no impact on latency)
  Kafka → ClickHouse (analytics, searchable, 1-year retention)
  Kafka → S3 (raw archive, 7-year compliance retention)

Analytics queries (ClickHouse):
  -- Which users are hitting limits most?
  SELECT userId, COUNT(*) AS throttle_count
  FROM rate_limit_events
  WHERE decision='deny' AND timestamp > now() - INTERVAL 1 HOUR
  GROUP BY userId ORDER BY throttle_count DESC LIMIT 20;

  -- Which rules are firing?
  SELECT ruleName, COUNT(*) / 3600 AS throttles_per_sec
  FROM rate_limit_events
  WHERE decision='deny' AND timestamp > now() - INTERVAL 1 HOUR
  GROUP BY ruleName;

  -- Throttle rate over time (for dashboards)
  SELECT toStartOfMinute(timestamp) AS min,
         countIf(decision='deny') / count() AS throttle_rate
  FROM rate_limit_events
  GROUP BY min ORDER BY min;

Dashboards:
  - Real-time throttle rate per rule
  - Top throttled users/IPs (abuse detection)
  - Quota utilization per API key (customer-facing dashboard)
  - Rule effectiveness: did tightening rule X reduce API abuse?
```

---

### 6.10 Special Cases

```
Priority bypass (exempt traffic):
  Health checks, monitoring probes, admin APIs → bypass rate limiting
  Implementation:
    if request.headers["X-Internal-Token"] == valid_hmac_token:
      return ALLOW (skip all rule evaluation)
  Internal token: rotated daily, shared via Vault

Allowlist / Blocklist:
  Allowlist: specific IPs/users always bypass (e.g., partner IPs)
    Redis SET: allowlist:{ip} = 1  → check before rule evaluation
  Blocklist: specific IPs/users always denied (known abusers)
    Redis SET: blocklist:{ip} = 1  → immediate DENY, don't even count

Idempotent requests (retry-safe):
  Client retries with same Idempotency-Key header after 429
  Rate limiter: don't count identical request IDs twice within window
    Redis: SET idempotency:{requestId} = 1 NX EX 60
    If already seen → skip counter increment → ALLOW (it was already counted)
  Used by: Stripe, Twilio for payment APIs

Soft limits (warn, don't block):
  Return 200 but add warning header: X-RateLimit-Warning: "80% of quota used"
  Useful: give users a heads up before hard limit, avoid surprise 429s
  Threshold: configurable per rule (e.g., warn at 80%, block at 100%)
```

---

## 7. Data Flow Summary

### Request Processing (Happy Path)

```
Client: POST /api/v1/search (Authorization: Bearer eyJ..., X-API-Key: ak_live_xxx)

1. API Gateway receives request
2. Rate Limiter (sidecar) intercepts BEFORE forwarding:

   a. Extract: { userId=u123, apiKey=ak_live_xxx, ip=1.2.3.4, endpoint=/api/v1/search }
   b. Check blocklist: Redis GET blocklist:1.2.3.4 → null (not blocked)
   c. Check allowlist: Redis GET allowlist:1.2.3.4 → null (not allowlisted)
   d. Load rules from cache: [ip-brute-force, per-user-minute, api-key-daily-quota]
   e. Pipeline to Redis (all rules in one round-trip):
      INCR rl:sw:ip-brute-force:ip:1.2.3.4:28733333  → 5   (< 100 limit ✓)
      INCR rl:sw:per-user-minute:user:u123:28733333   → 43  (< 60 limit ✓)
      INCR rl:log:api-key-daily:ak_live_xxx           → 857 (< 10000 limit ✓)
   f. All checks pass → ALLOW

   Response headers added:
      X-RateLimit-Limit: 60
      X-RateLimit-Remaining: 17   (60 - 43 = 17, tightest rule)
      X-RateLimit-Reset: 1724000060

3. API Gateway forwards to backend service
4. Backend responds → gateway returns response + rate limit headers

Async: throttle event NOT published (request allowed → log only denials by default)
```

### Request Processing (429 Path)

```
Client: POST /api/v1/search (same user, 61st request in same minute)

1–2c. Same as above
   d. Load rules: [per-user-minute]
   e. INCR rl:sw:per-user-minute:user:u123:28733333 → 61 (> 60 limit ✗)
   f. → DENY

   Response:
      HTTP 429 Too Many Requests
      X-RateLimit-Limit:     60
      X-RateLimit-Remaining: 0
      X-RateLimit-Reset:     1724000060
      Retry-After:           17   (seconds until window resets)
      Body: { "error": "rate_limit_exceeded", "rule": "per-user-minute" }

3. API Gateway returns 429; backend never invoked
4. Async: publish throttle event to Kafka → ClickHouse (audit log)
```

---

## 8. Follow-Up Questions

### Q1: How do you prevent a single Redis node from being the bottleneck?
```
Redis Cluster (horizontal sharding):
  Key space sharded across 16,384 hash slots → distributed to N nodes
  Counter key: rl:{ruleId}:{userId}:{window}
  Hash slot: CRC16(key) % 16384 → maps to a shard node
  Each node owns a subset of slots → no cross-node communication for single-key ops

  20 nodes × 100K ops/sec = 2M ops/sec capacity (vs. need for 6M ops/sec)
  → Scale to 60 nodes, or pipeline better (reduce ops per request)

Read replicas:
  Each shard has 1+ replicas (Redis Cluster replication)
  INCR always goes to primary (strong consistency)
  GET for quota queries can read from replica (eventual, acceptable)
  Replica reads: 60 nodes = 120 total instances → very high read throughput

Batching to reduce ops:
  Local token pre-fetching (section 5.5): reduces Redis ops by 10×
  6M → 600K ops/sec → 7 nodes sufficient
```

---

### Q2: How do you handle rate limiting across multiple data centers?
```
Option A — Centralized Redis (single region):
  All DC's rate limiters point to same Redis cluster
  ✅ Perfectly accurate
  ❌ Cross-DC latency: 50–100ms → violates 5ms budget
  ❌ Single region = SPoF for global traffic

Option B — Per-region limits (independent):
  Each region has its own Redis; limits are per-region
  Set limit = global_limit / num_regions (e.g., 100 global → 33 per region)
  ✅ No cross-region latency
  ❌ User can abuse by switching regions; uneven traffic distribution wastes quota

Option C — Quota partitioning (Recommended):
  Central quota allocator distributes quota to regions based on traffic weight
  Each region gets a dynamic quota slice: region_limit = global_limit × region_traffic_fraction

  Global: 1,000 req/min
  Region traffic: US-EAST 50%, EU 30%, APAC 20%
  Quotas: US-EAST=500, EU=300, APAC=200

  Rebalancing: every 60 seconds, allocator redistributes based on actual usage
    If EU uses only 150/300, 150 tokens reclaimed → redistributed to others

  ✅ Global accuracy within ~60s window
  ✅ Low latency (local Redis per region)
  ❌ Brief over-allowance during rebalance

Option D — Eventual consistency with CRDT counters:
  Conflict-free Replicated Data Types for counters
  Each region maintains a G-Counter (grow-only, merge = union max)
  Sync between regions every 100ms → eventually consistent global view
  ✅ Available even during network partition
  ❌ Complex; over-allows during partition (total across regions may exceed limit)
```

---

### Q3: How would you design a rate limiter for GraphQL where a single request might cost different amounts?
```
Problem: GraphQL query complexity varies wildly
  - Simple: { user { name } }                    → cost: 1
  - Complex: { users { posts { comments { ... } } } } → cost: 1000

Cost-based rate limiting:
  1. At request ingestion: parse GraphQL AST
  2. Calculate query complexity score:
     Complexity = Σ (field_weight × depth_multiplier × list_size_estimate)
     Field weights defined per schema: { users: 10, posts: 5, comments: 2 }
     List size: use default estimate (e.g., 100) for lists without explicit limits
  3. Rate limit by cost (not request count):
     Token bucket: capacity=1000 cost-tokens/min; request consumes complexity cost
     INCRBY key {complexity_cost} (not INCR by 1)

  User query: { users { posts { comments } } }
  Complexity = 10 × 5 × 2 × depth_factor = 400 cost units
  User has 1000/min budget → this query costs 40% of their budget

  Reject if: complexity > max_single_query_cost (e.g., 500)
    → Protects against intentionally complex queries before execution

  Headers:
    X-RateLimit-Cost: 400          (cost of this request)
    X-RateLimit-Remaining: 600     (remaining cost budget)
```

---

### Q4: How would you implement a "fair share" rate limiter (relative, not absolute limits)?
```
Scenario: 10 tenants sharing 100 req/sec. Fair share = 10 each.
But if 5 tenants are idle, the 5 active ones should get 20 each.

Fair Weighted Queuing (WFQ):
  Weight per tenant = tier_weight (free=1, paid=2, enterprise=5)
  Current capacity = total_rps - active_tenants × min_guarantee
  Distribute excess proportionally by weight

Implementation:
  Every 1 second, Quota Manager:
    1. Measure last-second usage per tenant (from Redis counters)
    2. Identify idle tenants (used < 10% of allocation)
    3. Reclaim idle quota: idle_quota = Σ (allocation - usage) for idle tenants
    4. Redistribute to active tenants proportionally by weight:
       bonus = idle_quota × (tenant_weight / active_weight_sum)
    5. Update Redis: SET quota:{tenantId}:bonus = bonus EX 2

  Rate limiter: check base limit + bonus tokens
    allowed = base_limit + bonus (if bonus SET in Redis)

Simpler alternative: just use token bucket per tenant with:
  refill_rate = total_capacity × (tenant_weight / sum_of_all_weights)
  cap = refill_rate × 5  (allow 5s burst)
  Idle tokens accumulate naturally → active users spend them → fair distribution
```

---

### Q5: How do you test a rate limiter in production safely?
```
Shadow mode (dry run):
  Deploy new rules in "shadow" mode: evaluate but don't enforce
  Log what WOULD have been blocked
  Analyze: false positive rate, coverage, impact
  Promote to enforcement only after validation

Gradual rollout:
  Rules applied to X% of traffic (random sampling):
    if hash(requestId) % 100 < rollout_percentage:
      enforce rule
    else:
      log only
  Ramp: 1% → 5% → 25% → 100% with monitoring at each step

Chaos testing:
  Inject Redis failures → verify fail-open behavior (no false 429s)
  Spike traffic to 10× → verify graceful degradation (429s, not crashes)
  Rule update mid-load → verify hot reload without dropped requests

Canary rules:
  New rule applied only to canary user segment (1% of users)
  Compare: throttle rate, error rate for canary vs control
  Auto-rollback if throttle rate > expected range by > 2×
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Primary algorithm | Sliding Window Counter | O(1) memory, ~0.003% error rate, no boundary burst; best all-around for general use |
| Billing quotas | Sliding Window Log | Perfect accuracy required when quota affects billing; higher memory acceptable for lower traffic |
| Burst-tolerant use cases | Token Bucket (Lua) | Configurable burst cap + sustain rate; handles traffic spikes without false throttling |
| Counter store | Redis Cluster (INCR / Lua) | Atomic; sub-millisecond; scales horizontally; built-in TTL for window cleanup |
| Distributed limiting | Local token pre-fetch (batch) | 10× Redis op reduction; < 100ms accuracy window; graceful on node failure |
| Rule propagation | Kafka + local cache (5s TTL) | < 2s propagation; local cache absorbs Redis latency spikes |
| Failure mode | Fail open | False 429s (blocking legitimate traffic) worse than brief over-allowance |
| Atomicity | INCR (for counters) + Lua (for token bucket) | INCR natively atomic; Lua for multi-step operations without distributed locks |
| Deployment | Sidecar | Lowest latency (~1ms); no network hop; scales with application pods |
| Audit log | Async Kafka → ClickHouse | Non-blocking (no latency impact); queryable analytics; compliance retention |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 45–60 minutes.*
