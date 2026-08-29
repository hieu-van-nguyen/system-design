# System Design: Ticketmaster (Event Ticketing Platform)

> Context: An online platform where users can browse events, select seats, purchase tickets, and receive digital tickets. The system must handle massive traffic spikes (Taylor Swift on-sale) with zero double-bookings and fair seat selection.

---

## 1. Functional Requirements

**Core (In Scope)**
- Users can browse events (concerts, sports, theater) by category, date, city
- Users can view venue seat maps with real-time availability
- Users can select specific seats and hold them for a short window (e.g., 10 minutes)
- Users can purchase held seats (checkout + payment)
- Users receive digital tickets (QR/barcode) via email and in-app
- Venue staff can scan/validate tickets at entry
- Event organizers can create events, configure pricing tiers, and set on-sale dates
- Waitlist support when sold out
- Ticket transfer between users

**Out of Scope**
- Ticket resale / secondary marketplace (StubHub-style)
- Venue operations management
- Artist / event promotion tools
- Refunds and cancellation flows (separate service)

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Availability | 99.99% general; 99.999% during active on-sale windows |
| Seat Hold Latency | Seat lock confirmed < **300ms** p99 |
| Throughput | 500K concurrent users during hot on-sale (Taylor Swift = 14M users in queue) |
| Consistency | **Strongly consistent** for seat reservation — zero double-booking |
| Read Scale | Seat map reads: 10M/min during on-sale; heavily cached |
| Durability | Zero ticket loss after purchase confirmed |
| Fairness | Virtual queue ensures FIFO access during high-demand on-sales |
| Ticket Validation | QR scan validation < **200ms** at venue gate |
| Idempotency | Duplicate payment/purchase submissions are safe |

---

## 3. Back of Envelope Calculation

**Scale Assumptions**
- 500K events/year, avg 20K seats/event
- 10B seat views/year → **~317/sec** average; **500K/sec** peak (hot event on-sale)
- 1M tickets sold/day → **~12 transactions/sec** average; **50K/sec** peak
- Avg ticket: $150 → $150M/day peak revenue flowing through system

**Storage**
- Event record: ~5 KB; 500K events = **2.5 GB**
- Seat record: ~200 bytes; 500K × 20K = 10B rows → **2 TB** (partitioned by event)
- Ticket (purchased): ~1 KB; 365M tickets/year → **365 GB/year**
- Booking hold state (ephemeral, Redis): ~500 bytes × 500K concurrent holds = **250 MB**

**Throughput**
- Seat map reads: 500K users × 1 poll/2s = **250K reads/sec** peak → must be cached
- Seat lock writes: 50K/sec peak → Redis + DB writes
- Payment processing: 50K/sec × $150 avg = $7.5M/sec flowing through payment gateway at peak

**Queue**
- 14M users for Taylor Swift on-sale → virtual queue service must hold 14M entries
- Queue position updates: 14M × 1 update/10s = **1.4M updates/sec** outbound

---

## 4. High-Level Design

```
┌────────────────────────────────────────────────────────┐
│               Users / Venue Staff / Organizers          │
└──────────────────────┬─────────────────────────────────┘
                       │ HTTPS / WebSocket
                       ▼
┌──────────────────────────────────────────────────────┐
│              API Gateway + CDN (CloudFront)           │
│         (auth, rate-limit, static assets)             │
└───────┬──────────────┬──────────────────┬────────────┘
        │              │                  │
        ▼              ▼                  ▼
┌─────────────┐ ┌───────────────┐ ┌──────────────────┐
│   Event     │ │  Booking      │ │  Virtual Queue   │
│   Service   │ │  Service      │ │  Service         │
└──────┬──────┘ └──────┬────────┘ └────────┬─────────┘
       │               │                   │
       ▼               ▼                   ▼
┌──────────────┐ ┌───────────────────┐ ┌──────────────┐
│  PostgreSQL  │ │  Redis            │ │  Redis /     │
│  (events,   │ │  (seat holds,     │ │  Kafka       │
│   seats,    │ │   locks, maps)    │ │  (queue      │
│   tickets)  │ │                   │ │   positions) │
└──────────────┘ └──────────────────-┘ └──────────────┘
        │               │
        ▼               ▼
┌──────────────────────────────────────────┐
│              Apache Kafka                │
│     (BookingConfirmed, PaymentCharged,   │
│      TicketIssued, SeatReleased)         │
└──────┬──────────────┬────────────────────┘
       │              │
       ▼              ▼
┌─────────────┐  ┌───────────────────┐
│  Payment    │  │ Notification Svc  │
│  Service   │  │ (Email/SMS/Push)  │
│ (Stripe)   │  └───────────────────┘
└─────────────┘
       │
       ▼
┌─────────────────────────┐
│  Ticket Service         │
│  (QR generation, PDF,  │
│   validation at gate)   │
└─────────────────────────┘
```

### Core Services

| Service | Responsibility |
|---|---|
| **Event Service** | CRUD for events, venue maps, pricing tiers, on-sale scheduling |
| **Booking Service** | Seat selection, hold, purchase — core reservation engine |
| **Virtual Queue Service** | FIFO queue during hot on-sales; issues tokens to enter booking flow |
| **Payment Service** | Charge via Stripe/Braintree, idempotency, refund hooks |
| **Ticket Service** | Generate QR codes, issue digital tickets, validate at venue |
| **Notification Service** | Booking confirmation, reminder, transfer, gate-scan confirmation |
| **Search Service** | Event discovery via Elasticsearch (city, date, artist, category) |

---

## 5. Deep Dive

### 5.1 Seat Reservation — The Core Problem

**The hardest part:** two users must never book the same seat. At 50K booking attempts/sec during peak, locking must be fast and safe.

**Three-Phase Flow: Browse → Hold → Purchase**

```
Phase 1 — BROWSE (read-heavy, cached)
  User views seat map → served from Redis cache
  Seat states: AVAILABLE | HELD | SOLD

Phase 2 — HOLD (write, must be atomic)
  User selects seat(s) → Booking Service acquires lock
  Hold duration: 10 minutes (TTL in Redis)
  Seat state → HELD (reserved for this session only)

Phase 3 — PURCHASE (write, transactional)
  User completes payment → seat state → SOLD
  Ticket issued → hold released or converted to sold
  If payment fails → seat released back to AVAILABLE
```

**Hold Implementation (Redis SET NX):**
```lua
-- Atomic seat claim; returns 1 on success, 0 if already held/sold
local key = "seat:" .. ARGV[1]  -- seat:<seat_id>
local result = redis.call('SET', key, ARGV[2], 'NX', 'EX', 600)
-- ARGV[2] = session_id:user_id (owner of hold)
-- EX 600 = 10-minute TTL (auto-release on timeout or abandonment)
if result == false then
  return 0  -- seat already taken
end
return 1  -- hold acquired
```

**Multi-seat selection (atomic):**
```lua
-- MULTI-EXEC equivalent via Lua: hold all or none
local seats = cjson.decode(ARGV[1])
-- First pass: check all seats are AVAILABLE
for _, seat_id in ipairs(seats) do
  if redis.call('EXISTS', 'seat:' .. seat_id) == 1 then
    return {0, seat_id}  -- conflict: this seat is taken
  end
end
-- Second pass: claim all
for _, seat_id in ipairs(seats) do
  redis.call('SET', 'seat:' .. seat_id, ARGV[2], 'NX', 'EX', 600)
end
return {1, ""}
```

**Durability: Write-Through to PostgreSQL**
- After Redis hold succeeds → async write to `seat_holds` table via Kafka
- On hold expiry: Redis TTL fires, Kafka event published → seat marked AVAILABLE in DB
- PostgreSQL is source of truth for SOLD seats (payment committed)

```sql
CREATE TABLE seat_holds (
    hold_id     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id    UUID NOT NULL,
    seat_id     UUID NOT NULL,
    user_id     UUID NOT NULL,
    session_id  UUID NOT NULL,
    held_until  TIMESTAMPTZ NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'HELD', -- HELD | PURCHASED | RELEASED
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (event_id, seat_id, status)
    -- partial unique: only one HELD row per seat at a time
);
```

---

### 5.2 Seat Map — Read Scaling

**Problem:** 500K users viewing the same Taylor Swift seat map simultaneously. Each polling every 2s = 250K reads/sec on seat state.

**Solution: Read-Through Cache with Smart Invalidation**

```
Client → API Gateway → Seat Map Service
                              │
                    ┌─────────▼──────────┐
                    │   Redis Cache       │
                    │  key: seatmap:<eid> │
                    │  TTL: 5 seconds     │
                    └─────────┬──────────┘
                         miss │
                              ▼
                    ┌─────────────────────┐
                    │   PostgreSQL         │
                    │   (seat states)      │
                    └─────────────────────┘
```

- Seat map cached as compact bitmask or sparse JSON (only HELD/SOLD seats stored; AVAILABLE is implied)
- Cache TTL: **5 seconds** — slight staleness acceptable (seat shown available, held by another → handled at hold-time)
- On seat state change (HELD/SOLD/RELEASED): publish invalidation event to Redis Pub/Sub → cache layer re-fetches
- WebSocket push to active viewers on seat map change (for premium UX)

**Seat Map Data Model:**
```sql
CREATE TABLE seats (
    seat_id    UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id   UUID NOT NULL,
    section    VARCHAR(20) NOT NULL,
    row        VARCHAR(10) NOT NULL,
    number     INT NOT NULL,
    price_tier VARCHAR(30) NOT NULL,
    status     VARCHAR(20) NOT NULL DEFAULT 'AVAILABLE',
    version    BIGINT NOT NULL DEFAULT 0,
    UNIQUE (event_id, section, row, number)
);
CREATE INDEX idx_seats_event_status ON seats(event_id, status);
```

---

### 5.3 Virtual Queue (Hot On-Sale)

**Problem:** 14M users attempt to buy Taylor Swift tickets simultaneously. Without a queue, the system collapses and rich/fast users win unfairly.

**Design: Token-Based Virtual Queue**

```
1. On-sale opens → users join queue (enqueue API call)
2. Queue Service assigns position number (Redis INCR = atomic, sequential)
3. Users receive WebSocket connection → receive position updates
4. Queue processor drains at controlled rate (e.g., 5K users/min released to booking)
5. User receives QUEUE_TOKEN (JWT, 15-min TTL) → gates access to Booking Service
6. Booking Service validates token before allowing seat selection
```

**Queue Entry:**
```redis
LPUSH queue:event:<event_id> <user_id>:<timestamp>:<session_id>
INCR  queue:event:<event_id>:total_count
```

**Position broadcast (fan-out challenge):**
- 14M WebSocket connections → cannot push to each individually
- Solution: Segment-based notification
  - Group users into position buckets (0–1K, 1K–5K, 5K–20K, etc.)
  - Broadcast estimated wait time per bucket via Kafka → WebSocket gateway
  - Individual position not shown (only band); reduces notification volume 14M → ~1K bucket updates

**Anti-gaming:**
- One queue slot per verified account (phone/email verified)
- Bot detection at queue join: device fingerprint + CAPTCHA for suspicious patterns
- IP rate limit: max 5 join attempts per IP per event

---

### 5.4 Payment & Idempotency

**Problem:** At 50K transactions/sec, network retries must never double-charge.

**Idempotency Key:** `SHA256(user_id + event_id + seat_ids + session_id)`
- Sent as header: `Idempotency-Key: <hash>`
- Payment Service stores result by key in Redis (TTL: 24h)
- On duplicate request: return cached result, skip charge

**Two-Phase Commit Avoidance (Outbox Pattern):**
```
BEGIN TRANSACTION
  INSERT INTO tickets (ticket_id, seat_id, user_id, status='PENDING_PAYMENT')
  INSERT INTO outbox (event='PAYMENT_REQUESTED', payload={...})
COMMIT

-- Outbox relay publishes to Kafka → Payment Service → charges card
-- On success: Kafka event PAYMENT_CHARGED → Ticket Service marks CONFIRMED
-- On failure: PAYMENT_FAILED → seat released, user notified
```

---

### 5.5 Ticket Generation & Validation

**Ticket = Signed QR Code**

```
ticket_payload = {
  ticket_id: UUID,
  event_id: UUID,
  seat_id: UUID,
  user_id: UUID,
  valid_from: ISO8601,
  valid_until: ISO8601
}
signature = HMAC_SHA256(ticket_payload, SECRET_KEY)
qr_data = base64(ticket_payload + signature)
```

**Gate Validation (offline-capable):**
- Scanner app pre-loads event's public signing key + bloom filter of valid ticket IDs
- Scan → decode QR → verify HMAC signature → check bloom filter for revocation
- If offline: local validation; sync revocations when connectivity restored
- If online: call Ticket Service API → check `ticket_scans` table for duplicates (prevent ticket sharing)

```sql
CREATE TABLE ticket_scans (
    scan_id    UUID PRIMARY KEY,
    ticket_id  UUID NOT NULL,
    gate_id    VARCHAR(50) NOT NULL,
    scanned_at TIMESTAMPTZ NOT NULL,
    result     VARCHAR(20) NOT NULL,  -- VALID | DUPLICATE | REVOKED | INVALID
    UNIQUE (ticket_id)  -- only one successful scan per ticket
);
```

---

### 5.6 Event & Search Service

**Event Discovery:**
- Elasticsearch index: event name, artist, venue, city, date, category, price range
- Geo-search: "events near me within 50 miles" → geo_distance query
- Faceted filters: date range, category, price, availability
- Typeahead: artist/venue name prefix search (Redis autocomplete with sorted sets)

**Event Creation (Organizer flow):**
- Organizer uploads venue SVG/JSON map → Seat Service generates seat rows
- Pricing tiers configured (Floor: $300, Pit: $200, GA: $100)
- On-sale datetime set → Scheduler Service triggers queue open + CDN cache warm

**CDN Cache Warming:**
- 30 min before on-sale: pre-render seat map, event details → push to CDN edge nodes
- Reduces origin load during the first wave of traffic

---

### 5.7 Database Sharding Strategy

| Table | Sharding Key | Rationale |
|---|---|---|
| `events` | `event_id` | Even distribution; queries by event_id |
| `seats` | `event_id` | All seat ops scoped to one event → collocate |
| `seat_holds` | `event_id` | Same as seats; avoids cross-shard joins |
| `tickets` | `user_id` | "My tickets" queries are user-scoped |
| `ticket_scans` | `ticket_id` | Scan lookup is by ticket_id |

- Hot events (Taylor Swift) → dedicated shard to prevent noisy-neighbor
- Shard routing via consistent hashing ring; shard map in ZooKeeper

---

### 5.8 Failure Scenarios

| Failure | Mitigation |
|---|---|
| Redis node fails during hold | Redis Sentinel/Cluster failover < 30s; in-flight holds re-validated against DB on recovery |
| Payment timeout | Idempotency key prevents double charge; seat hold extended 2 min, user shown "retrying" |
| Kafka consumer lag during on-sale | Auto-scale consumers; seat release events processed FIFO; stale holds expire via TTL regardless |
| Queue service crash | Queue position stored in Redis Cluster (replicated); Redis Sentinel + persistent AOF |
| Ticket forged / QR modified | HMAC signature verification at gate catches any tampering |
| DB primary fails | PostgreSQL Patroni auto-failover to replica < 30s; brief read-only mode |

---

## 6. Trade-offs Discussion

### 6.1 Seat Hold: Redis SET NX vs Pessimistic DB Lock vs Optimistic Lock

**Problem:** Two users race to book the same seat at 50K booking attempts/sec. Must be atomic with zero double-bookings.

| Approach | Throughput | Latency | Consistency | Complexity |
|----------|-----------|---------|-------------|------------|
| **Redis SET NX (current)** | 100K ops/sec | <5ms | Strong (single node) | Medium (warm-up, AOF) |
| **PostgreSQL SELECT FOR UPDATE** | ~3K ops/sec | 20–50ms | Strongly consistent | Low |
| **Optimistic Lock (version++)** | ~10K ops/sec | 15–30ms | Strong (retry on conflict) | Low–Medium |
| **Distributed Lock (Redlock)** | ~5K ops/sec | 10–20ms | Strong (majority quorum) | High |

**Decision: Redis SET NX as fast path, PostgreSQL as source of truth**
```
Trade-off accepted:
- Redis crash mid-hold: hold state lost; seat appears available again
- Mitigation: Redis AOF persistence (fsync every second) limits loss to 1s
- PostgreSQL write-behind via Kafka captures all SOLD state durably
- P(Redis crash) × P(seat double-sold in 1s window) ≈ 0.0001%

Why not PostgreSQL locks?
- 3K ops/sec too low for 50K peak demand
- Lock contention on hot rows (Taylor Swift seat A1) → cascading timeouts
- Redis handles hot row contention naturally (single-threaded, O(1) SET NX)

Why not Redlock?
- Requires majority quorum (3 of 5 Redis nodes) → 3× network RTT per lock
- At 50K/sec: 150K network calls/sec for locking alone → too expensive
- Added failure modes: partial quorum, clock skew
```

---

### 6.2 Virtual Queue: Redis INCR vs Kafka Log vs Database Queue

**Problem:** 14M users attempting simultaneous queue entry. Must maintain FIFO ordering, handle 14M WebSocket connections, and be crash-resilient.

| Approach | Ordering | Throughput | Crash Recovery | Fan-out |
|----------|----------|-----------|----------------|---------|
| **Redis INCR + sorted set (current)** | FIFO (atomic counter) | 500K ops/sec | Redis AOF (1s loss) | Kafka fan-out |
| **Kafka partition log** | Partition-level FIFO | Unlimited | Kafka (0 loss) | Native consumers |
| **PostgreSQL table** | Strict FIFO (serial) | ~5K rows/sec | Durable | Polling needed |

**Decision: Redis INCR for position assignment, Kafka for fan-out**
```
Why not Kafka as primary queue store?
- Kafka offsets are partition-based, not globally ordered
- 14M users across multiple partitions = no global FIFO guarantee
- Re-ordering risk if partitions have unequal lag

Why not PostgreSQL?
- 14M inserts at on-sale = queue filling at 1M+ rows/sec peak → too slow
- Single table becomes bottleneck immediately

Hybrid chosen:
- Redis INCR: atomic counter → globally unique, monotonically increasing position
- Kafka: fan-out bucket position updates to 14M WebSocket connections
- Bucket grouping (0–1K, 1K–5K...) reduces 14M individual pushes → ~1K Kafka messages

Trade-off: Redis AOF = at most 1 second of position assignments lost on crash.
Practical impact: ~14K users get re-assigned positions → they move slightly back in queue.
Better than alternative: database queue at 14M concurrent inserts.
```

---

### 6.3 Seat Map Freshness: 5-Second Cache TTL vs Real-Time Push vs No Cache

**Problem:** 250K reads/sec on Taylor Swift seat map. Every cache TTL decision trades freshness for throughput.

| Approach | DB Load | Freshness | UX Impact |
|----------|---------|-----------|-----------|
| **5-second cache TTL (current)** | ~1 req/5s per shard | Stale up to 5s | User sees seat available, clicks, seat is taken → graceful error |
| **Real-time push (WebSocket only)** | 0 (push-driven) | Instant | Best UX; complex infra (250K live socket connections) |
| **No cache (DB direct)** | 250K reads/sec | Instant | DB melts at Taylor Swift scale; non-starter |
| **60-second cache TTL** | Very low | Very stale | 1-min stale map → poor UX, many false-available seats |

**Decision: 5-second TTL cache + WebSocket invalidation for active viewers**
```
5-second TTL chosen because:
- Graceful degradation: "seat taken" at hold phase, not misleading at browse phase
- Reduces DB load: 250K → ~50 reads/sec (1 cache miss per shard per 5s)
- Simple to implement; no WebSocket dependency for cache

WebSocket enhancement:
- Users actively on seat map page receive push invalidations
- Best effort: if WebSocket dropped, falls back to 5-second poll
- ~10% of users have WebSocket open at any moment (rest are browsing)

What happens if cache shows seat available but it's held?
- User clicks seat → hold attempt fails atomically (Redis SET NX fails)
- UI shows: "Sorry, this seat was just taken" → user selects another
- Acceptable UX: consistent with real-world concert ticket buying
```

---

### 6.4 Payment: Synchronous vs Outbox Pattern (Async)

**Problem:** Charging the user's card is slow (200–800ms via Stripe). Should seat be held waiting for payment, or charge async?

| Approach | User Experience | Consistency Risk | Complexity |
|----------|----------------|-----------------|------------|
| **Synchronous charge** | Simple, instant feedback | Seat held during Stripe latency | Low |
| **Outbox pattern / async (current)** | "Processing..." state shown | Seat held in PENDING state | Medium |
| **Optimistic purchase (charge later)** | Fastest UX | Oversell risk if charge fails | High |

**Decision: Outbox pattern with PENDING_PAYMENT state**
```
Why not synchronous?
- Stripe p99 latency: 800ms → user waits nearly 1s staring at spinner
- At 50K/sec: 50K × 800ms = 40K concurrent Stripe connections open at once
- Stripe rate limits would throttle → cascading failures

Why not optimistic (charge after seat assignment)?
- User gets seat but card declines → must re-queue them or release seat
- At 5% decline rate: 2,500 seat releases/sec to reprocess → churn loop

Outbox pattern chosen:
1. BEGIN TX: insert ticket (PENDING_PAYMENT) + outbox row
2. COMMIT: transactional guarantee; seat marked PENDING (not available)
3. Outbox relay: publishes PAYMENT_REQUESTED to Kafka async
4. Payment Service charges card → PAYMENT_CHARGED event
5. Ticket Service: marks ticket CONFIRMED, sends QR
6. On failure: PAYMENT_FAILED → seat released, user notified

Trade-off: User sees "confirming payment..." for 1–3 seconds.
Offset by: zero double-charges, clean failure handling, Stripe decoupled.
```

---

### 6.5 Ticket Validation: Online vs Offline-Capable

**Problem:** Venue gate must scan 20K fans entering in 2 hours = ~2.8 scans/sec/gate (for 200 gates). Internet connectivity at venues is unreliable.

| Approach | Offline Resilience | Duplicate Prevention | Complexity |
|----------|--------------------|---------------------|------------|
| **Online-only API call** | None (gate freezes on outage) | Perfect (DB lookup) | Low |
| **HMAC + Bloom filter (current)** | Full offline capability | 99.99% (Bloom false-positive ~0.01%) | Medium |
| **PKI signed (asymmetric)** | Full offline capability | Same as HMAC | Higher (key distribution) |

**Decision: HMAC-SHA256 with offline bloom filter + online dedup table**
```
HMAC chosen over asymmetric (RSA/ECDSA):
- Symmetric verification is 10× faster (1µs vs 10µs per scan)
- At 200 gates × 2.8 scans/sec: 560 validations/sec → HMAC handles easily
- Key distribution simpler: rotate per-event, push to gate app at setup

Bloom filter for revocations:
- Pre-loaded before event: list of revoked ticket_ids → bloom filter
- False positive rate: 0.01% = 2 wrongly blocked fans per 20K → acceptable
- Bloom filter size: 20K tickets × ~20 bits = 400KB → trivially fits in memory

Online dedup (when connected):
- Prevents ticket sharing: same ticket scanned at two gates simultaneously
- DB UNIQUE constraint on ticket_scans.ticket_id
- Async sync: gate app queues scans locally, flushes when internet restored

Trade-off: Offline mode can't prevent ticket sharing (only catches post-facto).
Mitigated by: venue staff present at gates; fraud tickets revoked pre-event in bloom filter.
```

---

### 6.6 Database Sharding: By event_id vs user_id vs ticket_id

**Problem:** 10B seat records across 500K events. Query patterns are fundamentally different per service.

| Sharding Key | Query Efficiency | Hotspot Risk | Cross-shard Joins |
|-------------|-----------------|-------------|-------------------|
| **event_id (seats, holds)** | Fast for "show all seats for event" | Hot events on one shard | None within event |
| **user_id (tickets)** | Fast for "my tickets" | Power users (venue staff) | Cross-shard for event analytics |
| **ticket_id (scans)** | Fast for scan validation | Even distribution | All queries cross-shard |

**Decision: Event-sharded seats/holds, user-sharded tickets**
```
Seats & holds → shard by event_id:
- All seat ops for one event on one shard (atomic updates within shard)
- No cross-shard JOINs for booking flow
- Risk: Taylor Swift event on one shard → dedicated shard for hot events

Hot event isolation:
- Detect: if projected_demand > 100K concurrent users → dedicated shard
- Assign before on-sale via scheduler
- Prevents noisy-neighbor from impacting other events

Tickets → shard by user_id:
- "My tickets" query hits single shard (user_id range)
- "All tickets for event" (organizer view) → scatter-gather, acceptable for batch
- Consistent hash ring via ZooKeeper → easy rebalance as user base grows

Trade-off: Two different sharding schemes → no single JOIN query spans both.
Event analytics (who bought tickets) done via Kafka event log + data warehouse (Redshift/BigQuery),
not live DB joins. OLAP separated from OLTP.
```

---

### 6.7 Consistency Model Across the System

**Summary of deliberate consistency decisions:**

| Component | Consistency | Rationale |
|-----------|------------|-----------|
| Seat hold (Redis SET NX) | **Strong** (atomic) | Zero double-booking — hard requirement |
| Seat map reads (cached) | **Eventual** (5s stale) | Stale view → graceful hold-time error; acceptable |
| Payment processing (outbox) | **Eventual** (1–3s) | Stripe async; user shown "processing" state |
| Ticket issuance after payment | **Eventually consistent** | Post-payment Kafka event → ticket in seconds |
| Gate scan dedup (online) | **Strong** (DB UNIQUE) | Prevent ticket sharing — legal/financial stake |
| Gate scan dedup (offline) | **Eventual** (sync on reconnect) | Venue connectivity unreliable; risk accepted |
| Queue position (bucket-based) | **Approximate** (bucket, not exact rank) | Exact position for 14M users is unnecessary; bucket is fair |
| Search / discovery | **Eventual** (Elasticsearch lag ~1s) | New events appear within seconds; not safety-critical |

**Key interview insight:** Strong consistency has a throughput cost. At Ticketmaster scale, applying strong consistency everywhere would bottleneck to PostgreSQL's ~3K TPS — one-tenth of the required 50K/sec peak. Each row in the table above represents a deliberate trade-off, not a shortcut.

---

### 6.8 Virtual Queue Fan-out: Exact Position vs Bucket Notification

**Problem:** Sending individual position updates to 14M WebSocket connections is O(14M) per tick.

| Approach | Accuracy | Messages/sec | Infrastructure Cost |
|----------|----------|-------------|---------------------|
| **Individual position push** | Exact | 14M/10s = 1.4M/sec | 1.4M WebSocket frames/sec |
| **Bucket broadcast (current)** | ~±500 position accuracy | ~1K/10s | 1K Kafka messages/10s |
| **Client-side polling** | Exact (on poll) | User-driven (10s interval) | Medium (14M × 0.1/sec = 1.4M req/sec) |

**Decision: Bucket-based broadcast**
```
Bucket example:
- "You are in position 1–1,000: estimated wait < 5 minutes"
- "You are in position 1,001–5,000: estimated wait 5–15 minutes"
- "You are in position 5,001–50,000: estimated wait 15–60 minutes"

Why not exact position?
- 14M updates/10s = 1.4M WebSocket pushes/sec → requires massive gateway fleet
- Users don't meaningfully act differently at position 5,432 vs 5,789
- Buckets convey actionable information (go grab coffee vs stay ready)

Why not polling?
- 14M × 0.1 req/sec = 1.4M HTTP requests/sec for queue position alone
- WebSocket already open → push is cheaper once connection established

Trade-off: User can't see exact position. Perceived fairness maintained because:
- Position within bucket is FIFO
- No bucket skipping (users advance through buckets in order)
- Transparency: bucket boundary and estimated wait shown
```

---

## 7. Follow-Up Topics

### Handling 14M Concurrent Users (Taylor Swift Scale)
- Virtual queue as primary load shedder — only release 5K users/min to booking
- Horizontal scale: Booking Service stateless → auto-scale pods behind ALB
- Redis Cluster: 64 shards, seat keys distributed by `seat_id` hash
- Read traffic: 99% served from CDN + Redis cache; DB sees minimal read traffic
- Chaos test: game day load test at 2× projected peak 1 week before on-sale

### Ticket Transfer
- Sender initiates transfer → generates one-time transfer token (JWT, 1h TTL)
- Recipient clicks link → Ticket Service atomically reassigns `user_id` on ticket
- Original QR invalidated → new QR issued to recipient
- Prevents: same ticket being used by both sender and recipient

### Dynamic Pricing (Platinum Tickets)
- Ticketmaster Platinum: price adjusts based on demand signals
- Demand score = f(search volume, queue depth, social mentions, historical sell-through)
- Price updates at most once per 5 min; stored in Redis with version number
- Buyer sees current price at hold time; price locked for duration of hold (10 min)

### Fraud & Bot Prevention
- Layered: CAPTCHA at queue join, device fingerprint, behavioral analysis (mouse movement patterns)
- Purchase velocity limit: 4 tickets/user/event (enforced in Redis counter)
- Credit card velocity: max 2 orders/card/day across platform
- ML model: score each session; high-score sessions → step-up CAPTCHA or block

### GDPR / Data Privacy
- Ticket holder PII encrypted at rest; key rotation yearly
- After event + 90 days: anonymize ticket records (retain stats, drop PII)
- Right to erasure: delete user account → anonymize ticket history, transfer ownership of active tickets to venue

### Observability
| Signal | Tool |
|---|---|
| Distributed Traces | Jaeger (trace per booking_id) |
| Real-time Metrics | Prometheus + Grafana (seat hold rate, queue depth, payment success %) |
| Log Aggregation | ELK stack |
| Alerting | PagerDuty (double-booking detected → P0, payment failure rate > 1% → P1) |
| Synthetic Tests | Booking canary every 60s across all regions |

---

## Summary

| Component | Technology |
|---|---|
| API Gateway | Envoy + AWS CloudFront |
| Seat Hold Locking | Redis SET NX (Lua atomic scripts) |
| Seat Map Cache | Redis (TTL 5s) + CDN edge cache |
| Virtual Queue | Redis INCR + Kafka fan-out + WebSocket |
| Event / Order DB | PostgreSQL (sharded by event_id) |
| Ticket Storage | PostgreSQL (sharded by user_id) |
| Event Streaming | Apache Kafka |
| Search / Discovery | Elasticsearch + Redis Sorted Sets (autocomplete) |
| Payment | Stripe / Braintree + Outbox pattern |
| QR Ticket | HMAC-SHA256 signed + Bloom filter revocation |
| Notifications | SendGrid (email) + FCM/APNs (push) + Twilio (SMS) |
| Monitoring | Prometheus + Grafana + Jaeger + PagerDuty |
