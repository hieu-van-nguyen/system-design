# System Design: TinyURL (URL Shortener)

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Medium  
> Core challenges: **No-collision code generation at 1,200 writes/sec · 115K redirect reads/sec with < 10ms p99 · Analytics without impacting redirect latency**

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | Given a long URL, generate a unique short URL (e.g., `tinyurl.com/abc123`) |
| FR-2 | Redirect users from a short URL to the original long URL |
| FR-3 | Short URLs expire after a configurable TTL (default: 5 years; custom TTL supported) |
| FR-4 | Users can optionally provide a custom alias (`tinyurl.com/my-brand`) |
| FR-5 | (Optional) Analytics: click count, referrer, geo, device per short URL |
| FR-6 | (Optional) Users can delete or update their short URLs |

**Out of scope for core design:** User authentication, billing, team management, link previews.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 100M URLs created/day; 10B redirects/day |
| **Availability** | 99.99% uptime (≤52 min downtime/year) |
| **Latency** | Redirect p99 < 10ms; creation p99 < 100ms |
| **Consistency** | Eventual consistency acceptable for analytics; strong for URL creation (no duplicates) |
| **Durability** | Zero data loss — persisted URLs must survive failures |
| **Security** | No enumeration attacks; short codes must be unpredictable |
| **Storage** | 5-year retention; ~3 TB for URL mappings (see estimation) |
| **Idempotency** | Same long URL + same user → same short URL (optional, configurable) |

---

## 3. Back-of-Envelope Estimation

### Traffic

```
Write QPS  = 100M / 86,400 ≈ 1,200 writes/sec
Read QPS   = 10B  / 86,400 ≈ 115,000 reads/sec
Read:Write ratio ≈ 100:1  → read-heavy system
```

### Storage

```
Avg URL size          = 500 bytes (long URL) + 7 bytes (short code) + metadata ≈ 1 KB/record
5-year URL count      = 100M/day × 365 × 5 = 182.5 B records  ← too large
Realistic (with TTL)  = 100M/day × 365 days active ≈ 36.5B active records
Storage (DB)          = 36.5B × 1 KB ≈ 36.5 TB (sharded)
Practical (hot data)  = top 20% URLs serve 80% traffic → 7.3 TB in cache
```

### Short Code Namespace

```
Base62 charset: [a-z A-Z 0-9] = 62 chars
6-char code    = 62^6  ≈ 56.8 billion unique URLs  ✓
7-char code    = 62^7  ≈ 3.5 trillion unique URLs  ✓✓
→ Use 7 chars for safety margin
```

### Bandwidth

```
Writes: 1,200 req/s × 1 KB  ≈ 1.2 MB/s
Reads:  115,000 req/s × 100 B (just redirect, no body) ≈ 11.5 MB/s
```

### Cache

```
Cache 20% of daily reads = 0.2 × 10B × 100B ≈ 200 GB/day of hot URL data
Fits in Redis cluster across ~4 nodes (64 GB RAM each)
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────┐
│                          CLIENT                                  │
└───────────────────────────┬──────────────────────────────────────┘
                            │ HTTPS
                ┌───────────▼───────────┐
                │      CDN / Edge       │  ← Cache 301/302 redirects at edge
                └───────────┬───────────┘
                            │
                ┌───────────▼───────────┐
                │   Global Load Balancer │  (Anycast DNS, GeoDNS)
                └───┬───────────────┬───┘
                    │               │
         ┌──────────▼──┐     ┌──────▼──────────┐
         │  Write API   │     │   Redirect API   │
         │  Service     │     │   Service        │
         └──────┬───────┘     └──────┬───────────┘
                │                   │
         ┌──────▼───────┐    ┌──────▼───────────┐
         │ ID Generator │    │   Redis Cache     │
         │ (Snowflake / │    │   (URL Lookup)    │
         │  ZooKeeper)  │    └──────┬────────────┘
         └──────┬───────┘           │ cache miss
                │             ┌─────▼──────┐
         ┌──────▼──────────── ┤   Database │
         │  Primary DB (Write)│   (Read    │
         │  PostgreSQL /      │   Replicas)│
         │  Cassandra         └────────────┘
         └────────────────────────────────┘
                        │
                ┌───────▼──────────┐
                │  Analytics Queue │  (Kafka)
                └───────┬──────────┘
                        │
                ┌───────▼──────────┐
                │  Analytics Store │  (ClickHouse / BigQuery)
                └──────────────────┘
```

### Core API

```
POST /api/v1/urls
Body: { "longUrl": "https://...", "alias": "optional", "ttlDays": 365 }
Response 201: { "shortUrl": "https://tinyurl.com/abc1234", "expiresAt": "..." }

GET /{shortCode}
Response 302 → Location: <longUrl>   (or 301 if URL is immutable)

DELETE /api/v1/urls/{shortCode}       (authenticated)
GET    /api/v1/urls/{shortCode}/stats (authenticated)
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Short Code Generation — Hash vs. Counter vs. KGS vs. Random

| Approach | Collision Risk | Enumerable | Dedup | Coord. Needed |
|----------|---------------|-----------|-------|---------------|
| Hash (MD5 truncated) | Yes — must check DB | Somewhat | Natural | No |
| Counter (Snowflake + base62) | No | Slightly | No | Worker ID assignment |
| **KGS (Pre-generated keys)** | No | No | No | KGS service |
| Random base62 | Rare — must check DB | No | No | No |

```
KGS (Key Generation Service) — worth knowing for interviews:
  Offline: pre-generate billions of 7-char codes, store in "available_keys" table
  KGS marks each code "used" the moment it's handed out
  Write API requests a batch of 1,000 codes from KGS → caches locally
  No DB collision check at write time → pure O(1) inserts

  Pros:
    Zero collision → no DB round-trip to check uniqueness
    Codes are pre-validated (no collision table scan)
    Easy to distribute: hand out different batches to different API pods

  Cons:
    KGS is a single point of failure (mitigated: replicated KGS + standby)
    Codes handed out but not used (API pod crashes with 500-code batch)
    → 500 codes wasted: acceptable at 62^7 = 3.5T capacity

  KGS vs Snowflake:
    KGS: fully opaque codes, no time-ordering visible, slightly complex ops
    Snowflake: simpler, no separate service, time-ordering helps DB B-tree inserts
    → Snowflake + shuffled alphabet recommended unless ops team prefers KGS

Decision: Snowflake + shuffled alphabet for simplicity. KGS as alternative
  if interviewer probes code unpredictability requirements.
```

---

### Trade-Off 2: 301 Permanent vs. 302 Temporary Redirect

| | 301 Permanent | 302 Temporary |
|--|--------------|--------------|
| Browser caches redirect | ✅ Indefinitely | ❌ Never |
| Server sees every request | ❌ No | ✅ Yes |
| Analytics accuracy | ❌ Miss repeat visits | ✅ Every click counted |
| URL updateable after creation | ❌ Browser ignores server | ✅ Always re-fetches |
| Server load reduction | ✅ Lower (browser serves cached) | ❌ Higher |

```
The right answer depends on product requirements:

301 is right when:
  - Link is permanent and will never change
  - You want to minimize server load at all costs
  - Analytics are not important
  - Example: canonical redirects for SEO

302 is right when:
  - You need accurate click analytics (marketing use case)
  - URL may be updated or deleted in the future
  - You need to enforce expiry (301 cached in browser ignores server-side TTL)
  - Example: campaign links, affiliate links

Mixed approach (what TinyURL actually does):
  Default: 302 (safe, preserves analytics, updatable)
  Creator opt-in: 301 for "permanent" links (API flag: "permanent": true)
  CDN layer: Cache-Control: max-age=300 (5 min) on 302 — gives server-load
             benefit for viral URLs while still logging at 5-min resolution

Decision: 302 default. State this explicitly and explain why analytics matter.
```

---

### Trade-Off 3: Primary Database — Cassandra vs. PostgreSQL vs. DynamoDB

| Criterion | PostgreSQL | Cassandra | DynamoDB |
|-----------|-----------|-----------|---------|
| Horizontal write scale | ❌ (sharding via Citus) | ✅ Native | ✅ Native |
| UNIQUE constraint for aliases | ✅ Native | ❌ Needs LWT | ✅ Conditional writes |
| Native row-level TTL | ❌ (cron job) | ✅ Native | ✅ Native |
| Strong consistency | ✅ | ❌ Tunable | ✅ (with `ConsistentRead`) |
| Operational simplicity | ✅ Familiar | ❌ Complex tuning | ✅ Managed service |
| Cost at scale | ❌ Expensive to shard | Low | Medium |

```
Why not PostgreSQL at TinyURL scale?
  100M writes/day = 1,200 writes/sec — manageable on one PostgreSQL primary
  10B reads/day = 115,000 reads/sec — requires many read replicas
  At 5× growth: PostgreSQL sharding (Citus) adds significant complexity
  TTL requires a separate cron job (Cassandra handles it natively)

Why Cassandra wins:
  Partition key = short_code → O(1) point lookup (the only access pattern needed)
  RF=3, write quorum → durability without single point of failure
  Horizontal scale by adding nodes (no resharding needed — consistent hashing)
  Native TTL → no cleanup job for expired URLs

Why DynamoDB is also valid:
  Fully managed → no ops overhead
  TTL built-in → same as Cassandra
  Conditional writes → handle custom alias uniqueness
  Cost: DynamoDB gets expensive at high RCU/WCU; Cassandra cheaper at large scale

Decision: Cassandra for self-hosted scale / cost control.
  DynamoDB is equally valid answer on AWS (simpler ops, slightly higher cost).
  State your reasoning — either is correct.
```

---

### Trade-Off 4: Caching Strategy — Cache-Aside vs. Write-Through vs. Read-Through

| Strategy | Consistency | Cache Pollution | Implementation |
|----------|------------|----------------|----------------|
| **Cache-Aside (Lazy)** | Slight staleness | Low — only read URLs cached | Application handles |
| Write-Through | Strong | High — every write goes to cache | Application handles |
| Read-Through | Slight staleness | Low | Cache library handles |

```
Cache-Aside (Recommended):
  Read: check Redis → miss → read Cassandra → SET Redis (TTL) → return
  Write: write Cassandra → (do NOT pre-populate Redis)
  Delete/Update: write Cassandra → DEL Redis key (invalidate)

  Why preferred for TinyURL:
    1M URLs created/day; only 20% ever get significant traffic
    Write-through wastes Redis memory on URLs nobody clicks
    Cache-aside: only clicked URLs enter cache (hot data stays hot)
    Simple to reason about: cache is always a subset of DB truth

Write-Through (when to use instead):
  If almost all URLs will be read shortly after creation
  (e.g., marketing links that go in an email blast → clicked immediately)
  Can pre-warm cache for known high-traffic links via POST-creation cache seeding

Cache TTL strategy:
  Short URL TTL in Redis: min(url_expires_at, NOW + 24h)
    → URL expiring in 2 hours? Cache for 2 hours, not 24
    → Prevents serving expired URLs from stale cache
    → After expiry: Redis entry gone; Cassandra tombstone returns 404

Race condition (thundering herd on cache miss):
  Many requests for viral URL hit simultaneously → all miss → all query Cassandra
  Solution: Redis SETNX (set-if-not-exists) "lock:shortCode" → only 1 query goes to DB
    Others: wait 50ms → re-check Redis → cache populated → serve from cache
```

---

### Trade-Off 5: Analytics — Synchronous vs. Asynchronous

| Approach | Data Loss Risk | Redirect Latency | Accuracy |
|----------|---------------|-----------------|---------|
| Synchronous (write click to DB in redirect path) | Zero | High (+10-50ms) | Perfect |
| **Async via Kafka (fire-and-forget)** | Tiny (broker failure) | None (~0ms added) | Near-perfect |
| Counter-only (Redis INCR) | Some (Redis crash) | Minimal | Approximate |

```
Decision: Async Kafka with Redis counter front-end

Why not synchronous:
  Redirect p99 target is < 10ms — writing to ClickHouse in the redirect path
  adds 10-50ms per request at 115K RPS — unacceptable
  Also: ClickHouse write latency causes redirect latency to spike on traffic bursts

Async Kafka approach:
  Redirect Service: publish to Kafka (non-blocking, < 0.5ms overhead)
  Kafka consumer: batch-inserts to ClickHouse every 1 second
  Availability: if Kafka broker down → fire-and-forget fails silently
    → Acceptable: losing < 1 second of analytics during rare broker failover
    → URL redirect still succeeds (analytics not in critical path)

Redis counter layer:
  INCR click:{shortCode} → real-time click count visible to creator in dashboard
  Flushed to ClickHouse every 5 minutes (reconciliation)
  If Redis fails: INCR fails silently → count temporarily stale → recovered from Kafka

Why this is correct:
  Creator doesn't need exact-to-the-millisecond analytics
  "302 redirects" are 99% of the SLA — analytics are best-effort
  Separating analytics from the critical redirect path is the right architectural choice
```

---

### Trade-Off 6: Global Deduplication of Long URLs

| Approach | Storage | UX | Complexity |
|----------|---------|-----|-----------|
| **No dedup (recommended)** | Highest | Each call = new code (good for campaigns) | Simple |
| Per-user dedup | Medium | Same user + same URL = same code | Medium |
| Global dedup | Lowest | Anyone who shortened the same URL gets the same code | Medium-high |

```
Global dedup sounds appealing (storage savings) but has serious problems:

Problem 1 — Privacy leak:
  Alice shortens https://bank.com/private-account/12345
  Bob shortens same URL → gets the same short code → can see Alice's link exists

Problem 2 — Analytics confusion:
  Alice uses tinyurl.com/abc1234 in a tweet (campaign A)
  Bob uses tinyurl.com/abc1234 in an email (campaign B)
  Analytics: impossible to attribute clicks to correct campaign

Problem 3 — Deletion conflict:
  Alice deletes her URL → Bob's link also breaks (same short code)

Per-user dedup is the sweet spot:
  Index: (user_id, long_url_hash) → short_code
  Same user shortening the same URL twice → returns existing short code
  Different users → different short codes (privacy preserved)
  Implementation: secondary index in Cassandra or separate lookup table

Decision: Per-user dedup as opt-in behavior (API flag: "dedup": true).
  Default: no dedup → always creates new short code.
  State trade-offs explicitly — this is a common interviewer follow-up.
```

---

## 6. Deep Dive

### 6.1 Short Code Generation

**Option A — Hash-based (MD5/SHA256 + truncation)**
```
shortCode = base62(md5(longUrl))[0:7]
```
- ✅ Same long URL → same short code (natural dedup)
- ❌ Hash collisions possible; must check DB on every write
- ❌ Predictable/enumerable if attacker knows the algorithm

**Option B — Counter-based (Globally unique ID)**
```
id        = snowflake_id()   // 64-bit, time-ordered
shortCode = base62(id)[0:7]
```
- ✅ No collisions by construction
- ✅ Monotonic → sequential DB inserts (B-tree friendly)
- ❌ Slightly enumerable (time component visible)
- Fix: shuffle base62 alphabet per deployment secret

**Option C — Random token**
```
shortCode = secure_random_base62(7)
```
- ✅ Unpredictable — blocks enumeration
- ❌ Must check for collision on every insert (rare at 7 chars)
- ❌ Not deterministic (same URL → different codes)

**Recommended: Option B (Snowflake) + shuffled alphabet**
- Distributed ID generation via Twitter Snowflake or Sonyflake
- Each region gets a worker ID range; no central coordination at write time
- Alphabet shuffle is a deployment secret → codes are opaque

---

### 6.2 Database Choice

| Criterion | PostgreSQL (RDBMS) | Cassandra (Wide-Column) |
|-----------|-------------------|------------------------|
| Strong consistency | ✅ | ❌ (tunable) |
| Horizontal write scale | ❌ (needs Citus) | ✅ native |
| Point-lookup performance | ✅ (index) | ✅ (partition key) |
| TTL support | ❌ (needs cron job) | ✅ native |
| Custom alias uniqueness | ✅ UNIQUE constraint | ❌ (LWT needed) |
| Operational complexity | Low | High |

**Decision:** Cassandra for URL mappings at scale.

```
Table: url_mappings
  short_code TEXT  PRIMARY KEY
  long_url   TEXT
  user_id    UUID
  created_at TIMESTAMP
  expires_at TIMESTAMP
  TTL set at row level
```

For custom alias uniqueness, use Cassandra Lightweight Transactions (LWT):
```cql
INSERT INTO url_mappings (short_code, long_url, ...)
VALUES (?, ?, ...)
IF NOT EXISTS;
```

---

### 6.3 Redirect Strategy: 301 vs 302

| | 301 (Permanent) | 302 (Temporary) |
|--|----------------|----------------|
| Browser caches | ✅ Forever | ❌ Not cached |
| Server load | Lower (cached) | Higher (every hit) |
| Analytics accuracy | ❌ Misses repeat visits | ✅ Every click logged |
| URL update possible | ❌ Browser bypasses server | ✅ Always re-fetched |

**Decision:** Use **302** by default to preserve analytics and allow URL updates. Allow 301 as opt-in for static/permanent links.

---

### 6.4 Caching Strategy

```
Read path:
  1. Check Redis (shortCode → longUrl)        [TTL = min(URL expiry, 24h)]
  2. On miss → Cassandra read → populate cache
  3. CDN caches 302 response with Cache-Control: max-age=300 (5 min)

Write path:
  1. Write to Cassandra (strong write)
  2. Invalidate Redis key if updating existing URL
  3. No pre-warming needed (lazy cache population)

Eviction: LRU; 20% of URLs serve 80% traffic → fits easily in 200 GB cluster
```

**Cache-aside pattern** is preferred over write-through because:
- Avoids cache pollution from write-heavy, rarely-read URLs
- Simpler invalidation on URL update/delete

---

### 6.5 Handling Custom Aliases

```
POST /api/v1/urls  { "alias": "my-brand" }

1. Validate alias format (regex: [a-zA-Z0-9_-]{3,20})
2. Attempt Cassandra LWT INSERT IF NOT EXISTS
3. If conflict → return HTTP 409 Conflict
4. Rate-limit custom alias creation per user (e.g., 100/day)
```

**Reservation table** (optional): Pre-block offensive/reserved words (`admin`, `api`, `login`, `signup`).

---

### 6.6 Expiry & Cleanup

**Active expiry:** Cassandra TTL automatically tombstones rows.

**Passive cleanup for Redis:** Cache entries have matching TTL; expired entries are auto-evicted.

**Soft-delete pattern:**
```
1. On expiry, mark record deleted = true (keep for audit log)
2. Async job purges tombstones older than 30 days
3. Recycle short codes after quarantine period (avoid reuse confusion)
```

---

### 6.7 Analytics Pipeline

```
Redirect Service
     │
     ▼ (async, fire-and-forget)
  Kafka Topic: click-events
     │
     ├── ClickHouse (real-time aggregation, per-URL stats)
     └── S3 / GCS (raw event archive for replay)

Schema: { shortCode, timestamp, ip, userAgent, referer, country }
```

- **Pre-aggregated counters** in Redis (`INCR click:{shortCode}`) for sub-second dashboard updates
- **Batch reconciliation** from ClickHouse every 5 min to correct Redis counters

---

### 6.8 Scalability & Fault Tolerance

| Concern | Solution |
|---------|----------|
| Write hotspot | Cassandra consistent hashing; Snowflake worker IDs per pod |
| Read hotspot (viral URL) | CDN + Redis; local in-process LRU cache for top-1000 URLs |
| Single region failure | Multi-region active-active; GeoDNS routes to nearest healthy region |
| Redis failure | Fallback to Cassandra read; slight latency increase, no outage |
| DB node failure | Cassandra RF=3, quorum reads; no data loss |
| Cascading failures | Circuit breakers (Hystrix/Resilience4j) on DB/cache calls |

---

### 6.9 Rate Limiting & Abuse Prevention

```
Per IP / API key limits (token bucket):
  - Anonymous: 10 URLs/min
  - Authenticated: 1,000 URLs/min

Implementation: Redis sliding window counter
  Key: ratelimit:{userId}:{windowMinute}
  INCR + EXPIRE in Lua script (atomic)

Abuse signals → block list:
  - Malicious long URLs (integrate Google Safe Browsing API)
  - Spam custom aliases
  - Automated enumeration (rate of 404s per IP)
```

---

## 7. Data Flow Summary

### Create Short URL
```
Client → LB → Write API
  → Validate long URL (format, safe browsing check)
  → Generate shortCode (Snowflake ID → base62)
  → Cassandra INSERT (LWT if custom alias)
  → Redis SET shortCode→longUrl (with TTL)
  → Return shortUrl to client
```

### Redirect
```
Client → CDN (cache hit? → 302 immediately)
       → LB → Redirect API
          → Redis GET shortCode (cache hit? → 302)
          → Cassandra GET shortCode (cache miss)
          → Redis SET (populate cache)
          → Publish click event to Kafka (async)
          → 302 → longUrl
```

---

## 8. Follow-Up Questions

### Q1: How do you handle the same long URL being shortened multiple times?
**Options:**
1. **No dedup** — each call gets a new short code (simplest, allows tracking per-campaign)
2. **Per-user dedup** — index `(user_id, long_url)` → return existing short code for same user
3. **Global dedup** — hash long URL → single short code (saves storage, loses per-user control)

Recommended: per-user dedup via secondary index or hash-based lookup table.

---

### Q2: How would you support vanity/custom domains? (`go.mycompany.com/promo`)
- **Multi-tenant DNS**: each custom domain CNAME → `tinyurl.com`
- TLS termination via wildcard cert or Let's Encrypt per domain
- DB schema adds `domain` field: primary key becomes `(domain, short_code)`
- Redirect service resolves domain → tenant config → URL lookup

---

### Q3: How do you prevent short code enumeration?
- **Shuffled alphabet**: base62 encoding uses a deployment-secret-shuffled charset
- **7+ char codes**: 62^7 = 3.5T codes; brute force is impractical
- **Rate limiting**: 429 after N consecutive 404s per IP
- **No sequential codes**: Snowflake IDs are time-ordered but the alphabet shuffle breaks visual sequence

---

### Q4: How do you handle a URL that goes viral (thundering herd)?
- **CDN caching**: 302 cached at edge for 5 minutes; 99% of traffic never hits origin
- **In-process cache**: Top-1000 URLs cached in each Redirect API pod's heap (LRU, 1s TTL)
- **Redis cluster**: horizontal scaling; viral URL hits one shard but Redis is fast enough
- **Circuit breaker**: if Cassandra is overwhelmed, serve stale cache rather than failing

---

### Q5: How would you scale to 1B redirects/day?
```
Current design at 115K RPS:
  - 10 Redirect API pods (each handles ~12K RPS)
  - Redis cluster: 4 nodes × 64GB
  - Cassandra: 6 nodes RF=3
  - CDN absorbs 70-80% of traffic

At 1B redirects/day (≈ 11,500 RPS after CDN):
  → CDN hit rate improves as content is hotter
  → Actual origin QPS may stay flat or decrease
  → Scale Redis and Cassandra node count linearly
```

---

### Q6: How would you add A/B testing or link campaigns?
- Short URL carries `campaign_id` metadata
- Analytics pipeline segments clicks by campaign
- A/B: single short URL resolves to different long URLs based on user segment (cookie/IP hash)
- Stored as weighted routing rules in Redis: `route:{shortCode}` → `[{url, weight}]`

---

### Q7: GDPR / Data deletion
- On user account deletion: mark all user's URLs deleted = true, stop redirects (404)
- Async job purges from Cassandra + invalidates Redis within 30 days
- Click events in Kafka/ClickHouse anonymized (drop IP, user agent) via stream processor

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Short code generation | Snowflake + shuffled base62 | No collisions, distributed, opaque |
| Primary store | Cassandra | Horizontal scale, native TTL, fast point lookups |
| Cache | Redis cluster | Sub-millisecond reads, LRU eviction, TTL support |
| Redirect type | 302 (default) | Preserves analytics, allows URL updates |
| Analytics | Kafka → ClickHouse | Decoupled, real-time aggregation, replay-able |
| Consistency | Eventual (reads), Strong (writes) | Matches read-heavy workload requirements |
| ID coordination | No central coordinator | Snowflake worker IDs assigned per pod/region |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 45–60 minutes.*
