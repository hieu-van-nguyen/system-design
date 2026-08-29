# System Design: Restaurant Reservation System (OpenTable)

> Context: A platform where diners can discover restaurants, check real-time table availability, and book reservations. Restaurants manage their floor plans, time slots, and guest history. Think OpenTable at scale.

---

## 1. Functional Requirements

**Core (In Scope)**
- Diners can search restaurants by location, cuisine, date, time, and party size
- Diners can view real-time table availability for a given restaurant/date/time/party
- Diners can book, modify, and cancel reservations
- Restaurants can manage their floor plans, table configurations, and operating hours
- Restaurants can define availability rules (blackout dates, special event slots, max covers/hour)
- Diners receive confirmation and reminder notifications (email/SMS)
- Restaurants receive booking notifications and can confirm/cancel
- No-show tracking and diner rating/review after visit
- Waitlist support when fully booked
- Walk-in table management for restaurant hosts

**Out of Scope**
- Payment processing / deposits (separate service)
- Loyalty/rewards programs
- Food ordering or delivery
- Point-of-sale integration

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Availability | 99.99% — booking platform must be up during dinner rush |
| Search Latency | Restaurant search results < **200ms** p99 |
| Booking Latency | Reservation confirmation < **500ms** p99 |
| Consistency | **Strong** for slot reservation — zero double-booking of same table/time |
| Scale | 10M restaurants globally, 5M reservations/day |
| Read/Write Ratio | ~100:1 (heavy browse vs. actual booking) |
| Concurrency | Popular restaurants: hundreds of simultaneous booking attempts for same slot |
| Durability | No reservation loss after confirmation |
| Idempotency | Duplicate booking submissions return existing reservation (safe retries) |

---

## 3. Back of Envelope Calculation

**Scale Assumptions**
- 10M restaurants; 500K actively using the platform
- 5M reservations/day → **~58/sec** average; **500/sec** peak (6–8 PM dinner rush)
- Avg 3.5 diners/reservation → 17.5M covers/day
- Search QPS: 100× booking QPS = **5,800 searches/sec** peak
- Each restaurant: avg 20 tables × 6 slots/table/day × 2 seatings = 240 bookable slots/day

**Storage**
- Restaurant record: ~5 KB; 10M restaurants = **50 GB**
- Reservation record: ~1 KB; 5M/day × 365 = 1.8B/year → **1.8 TB/year**
- Availability slots (hot data): 500K restaurants × 240 slots × 200 bytes = **24 GB** (fits in memory)
- Reviews: ~500 bytes × 20M reviews = **10 GB**

**Cache**
- Availability cache: 24 GB fits in Redis Cluster comfortably
- Top 1% restaurants (5K) get 90% of traffic → warm these aggressively

**Notifications**
- 5M reservations × 3 notifications each (confirm + reminder D-1 + reminder H-2) = **15M/day** = 174/sec

---

## 4. High-Level Design

```
┌────────────────────────────────────────────┐
│       Diner App / Restaurant Dashboard     │
│          (Web / iOS / Android)             │
└──────────────────┬─────────────────────────┘
                   │ HTTPS
                   ▼
┌──────────────────────────────────────────────────┐
│           API Gateway + CDN                      │
│  (auth, rate-limit, geo-routing, static assets)  │
└────────┬─────────────────┬───────────────────────┘
         │                 │
         ▼                 ▼
┌────────────────┐  ┌──────────────────────────┐
│  Search        │  │   Reservation Service    │
│  Service       │  │  (book/modify/cancel)    │
│  (ES + Redis)  │  └──────────┬───────────────┘
└────────┬───────┘             │
         │                     ▼
         │          ┌──────────────────────┐
         │          │  Availability        │
         │          │  Service             │
         │          │  (slot management)   │
         │          └──────────┬───────────┘
         │                     │
         ▼                     ▼
┌─────────────────────────────────────────┐
│            Apache Kafka                 │
│  (ReservationCreated, SlotReleased,     │
│   NoShowMarked, WaitlistTriggered)      │
└──────┬──────────────┬────────────────┬──┘
       │              │                │
       ▼              ▼                ▼
┌──────────┐  ┌───────────────┐ ┌────────────────┐
│Notif.    │  │  Restaurant   │ │  Analytics /   │
│Service   │  │  Dashboard    │ │  Reporting Svc │
│(email/   │  │  WebSocket    │ │                │
│SMS/push) │  │  (live floor) │ └────────────────┘
└──────────┘  └───────────────┘

Data Layer:
  PostgreSQL — reservations, restaurants, users
  Redis      — availability slots (hot), distributed locks, search cache
  Elasticsearch — restaurant search & discovery
```

### Core Services

| Service | Responsibility |
|---|---|
| **Search Service** | Full-text + geo search; availability-aware filtering |
| **Availability Service** | Manages bookable slots per restaurant; enforces capacity rules |
| **Reservation Service** | Book / modify / cancel; orchestrates lock → write → notify |
| **Notification Service** | Confirmation, reminders, cancellation alerts |
| **Restaurant Management Service** | Floor plan config, operating hours, blackout rules |
| **Waitlist Service** | Queue diners when fully booked; auto-notify on cancellation |
| **Review Service** | Post-visit ratings; no-show tracking |

---

## 5. Deep Dive

### 5.1 Availability Modeling

**Problem:** What does "available" mean? It's not just "table exists" — it's the intersection of:
- Table is physically available (not already booked in overlapping time window)
- Party size fits the table (or can be merged)
- Restaurant is open (operating hours, no blackout)
- Restaurant hasn't hit max covers for that hour
- Seating duration policy allows the slot (e.g., 90-min turns)

**Slot Model:**

```sql
-- A slot = one bookable unit of time for one table configuration
CREATE TABLE availability_slots (
    slot_id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    restaurant_id  UUID NOT NULL,
    table_id       UUID NOT NULL,
    date           DATE NOT NULL,
    start_time     TIME NOT NULL,
    end_time       TIME NOT NULL,        -- start + turn_duration
    min_party      SMALLINT NOT NULL,
    max_party      SMALLINT NOT NULL,
    status         VARCHAR(20) NOT NULL DEFAULT 'OPEN',  -- OPEN | HELD | BOOKED | BLOCKED
    version        BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (slot_id),
    UNIQUE (table_id, date, start_time)  -- no overlapping bookings per table
);

CREATE INDEX idx_slots_restaurant_date 
    ON availability_slots(restaurant_id, date, status, start_time);
```

**Table Configuration:**
```sql
CREATE TABLE tables (
    table_id      UUID PRIMARY KEY,
    restaurant_id UUID NOT NULL,
    table_number  VARCHAR(10) NOT NULL,
    min_capacity  SMALLINT NOT NULL,
    max_capacity  SMALLINT NOT NULL,
    section       VARCHAR(30),           -- indoor, patio, bar, private
    combinable    BOOLEAN DEFAULT FALSE  -- can merge with adjacent table
);
```

**Slot Generation:**
- Nightly batch job generates slots for the next 90 days per restaurant
- Config inputs: open days, open hours, turn duration (90 min), slot interval (15 min), tables
- Slots written to PostgreSQL; hot window (next 14 days) pre-loaded into Redis

---

### 5.2 Booking — Lock, Reserve, Confirm

**Problem:** Two diners simultaneously try to book the same 7:30 PM table for Saturday. Must guarantee only one succeeds.

**Three-Phase Flow:**

```
Phase 1 — SEARCH (read, cached)
  Diner searches → Availability Service returns open slots from Redis

Phase 2 — HOLD (write, atomic, 5-minute TTL)
  Diner selects slot → acquire distributed lock on slot
  slot.status → HELD; Redis SET NX with 300s TTL

Phase 3 — CONFIRM (write, transactional)
  Diner submits name/phone → Reservation Service
  → PostgreSQL: INSERT reservation + UPDATE slot status=BOOKED (single transaction)
  → Redis: update slot cache
  → Kafka: publish ReservationCreated
  → If hold expired or slot taken → return conflict error
```

**Redis distributed lock (SETNX):**
```lua
-- Atomic hold: succeeds only if slot is OPEN
local key = "slot:hold:" .. ARGV[1]   -- slot_id
local owner = ARGV[2]                  -- user_id:session_id
local result = redis.call('SET', key, owner, 'NX', 'EX', 300)
if result == false then
  return 0  -- slot already held or booked
end
-- Update availability cache
redis.call('HSET', 'slot:status:' .. ARGV[1], 'status', 'HELD')
return 1
```

**PostgreSQL confirm (optimistic locking):**
```sql
BEGIN;

-- Claim slot with optimistic lock
UPDATE availability_slots
   SET status = 'BOOKED', version = version + 1
 WHERE slot_id = $1
   AND status = 'HELD'          -- only if still held (not expired/stolen)
   AND version = $2;            -- optimistic: reject if concurrent update

-- If 0 rows updated → rollback, return CONFLICT

-- Create reservation
INSERT INTO reservations (
    reservation_id, restaurant_id, table_id, slot_id,
    user_id, party_size, status, confirmation_code
) VALUES (
    gen_random_uuid(), $3, $4, $1,
    $5, $6, 'CONFIRMED', $7
);

COMMIT;
```

---

### 5.3 Search & Discovery

**Problem:** Diner searches "Italian restaurant, NYC, Saturday 7 PM, party of 4." Must return restaurants with actual availability — not just open restaurants.

**Two-Layer Search:**

```
Layer 1 — Elasticsearch (restaurant discovery)
  Filter: city=NYC, cuisine=Italian, rating≥4.0, price_tier≤$$
  Returns: top 50 matching restaurants by relevance score

Layer 2 — Availability Service (slot check)
  For each of 50 restaurants: check Redis for open slots on Sat 7 PM ± 90 min, party ≥ 4
  Returns: restaurants WITH available slots + nearest available times
  Filter out: fully booked restaurants
```

**Availability Cache Structure (Redis Hash):**
```
Key:   avail:<restaurant_id>:<date>
Value: Hash of { "<time>:<table_id>": "OPEN|HELD|BOOKED", ... }
TTL:   300 seconds (5 min)

On slot change → invalidate key → next read re-hydrates from DB
```

**Alternative times feature:**
- If 7:00 PM not available → suggest 6:30 PM or 7:30 PM (within ±90 min window)
- Implemented in Availability Service: scan nearby slots after primary slot fails

**Elasticsearch Index Mapping (key fields):**
```json
{
  "restaurant_id": "keyword",
  "name": "text",
  "cuisine": "keyword",
  "location": "geo_point",
  "city": "keyword",
  "rating": "float",
  "price_tier": "integer",
  "features": "keyword",        // outdoor, parking, private_dining
  "has_availability_today": "boolean",  // updated hourly
  "popularity_score": "float"
}
```

---

### 5.4 Table Merging & Party Size Optimization

**Problem:** Party of 6 requests a table, but restaurant only has 4-top and 2-top tables. Should we suggest a merge?

**Algorithm:**
1. Query available tables where `max_capacity >= party_size` → direct fit preferred
2. If none: find adjacent combinable table pairs where `sum(max_capacity) >= party_size`
3. Score merged options by: total capacity waste, section preference, proximity
4. If no merge possible → offer waitlist or alternative time

**Table Graph (adjacency):**
```sql
CREATE TABLE table_adjacency (
    table_a UUID REFERENCES tables(table_id),
    table_b UUID REFERENCES tables(table_id),
    PRIMARY KEY (table_a, table_b)
);
```
- Merge holds both slots atomically (Lua multi-lock, all-or-nothing)

---

### 5.5 Waitlist

**Problem:** Restaurant is fully booked. Diner wants next available slot.

**Flow:**
1. Diner joins waitlist → `waitlist_entries` row created with desired time window + party size
2. On cancellation → `SlotReleased` Kafka event published
3. Waitlist Service consumer: query waitlist entries matching released slot criteria
4. Notify first matching diner (FIFO): "A table opened up — you have 15 min to book"
5. Send exclusive booking link with pre-filled slot + short-lived token
6. If diner doesn't book in 15 min → notify next in queue

```sql
CREATE TABLE waitlist_entries (
    entry_id        UUID PRIMARY KEY,
    restaurant_id   UUID NOT NULL,
    user_id         UUID NOT NULL,
    desired_date    DATE NOT NULL,
    earliest_time   TIME NOT NULL,
    latest_time     TIME NOT NULL,
    party_size      SMALLINT NOT NULL,
    status          VARCHAR(20) DEFAULT 'WAITING',  -- WAITING | NOTIFIED | BOOKED | EXPIRED
    notified_at     TIMESTAMPTZ,
    expires_at      TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    INDEX idx_waitlist_lookup (restaurant_id, desired_date, status, party_size)
);
```

---

### 5.6 No-Show Handling

**Problem:** Diners who don't show up waste restaurant capacity.

**Flow:**
1. Host marks no-show in dashboard at T+15min after reservation time
2. `NoShowMarked` event → Review Service: increment user's no-show count
3. No-show thresholds:
   - 1 no-show: warning email
   - 2 no-shows / 6 months: require credit card to book
   - 3 no-shows / 6 months: suspend booking ability (30 days)
4. Slot immediately released → Waitlist Service notified → offer to waitlist

**Automatic reminder cadence (reduce no-shows):**
- T-24h: reminder + "Still coming? Confirm or cancel"
- T-2h: final reminder with directions
- Confirm link updates `reservation.diner_confirmed = true` → reduces no-show probability

---

### 5.7 Restaurant Dashboard (Real-Time Floor View)

**Problem:** Restaurant host needs live view of which tables are occupied, which are being set up, expected arrivals in next 30 min.

**Solution: WebSocket + Server-Sent Events**

```
Reservation Service → Kafka (ReservationCreated, DinerArrived, TableFreed)
                           │
                    Kafka Consumer (Restaurant Dashboard Service)
                           │
                    WebSocket push to restaurant's active dashboard session
```

**Floor state broadcasted:**
```json
{
  "restaurant_id": "uuid",
  "timestamp": "2026-08-16T19:30:00Z",
  "tables": [
    {"table_id": "t1", "status": "OCCUPIED", "party": 4, "seated_at": "18:45", "est_done": "20:15"},
    {"table_id": "t2", "status": "RESERVED", "arrival_in": "15min", "party": 2},
    {"table_id": "t3", "status": "AVAILABLE"},
    {"table_id": "t4", "status": "BEING_SET"}
  ],
  "upcoming_30min": [...]
}
```

---

### 5.8 Reservation Modify / Cancel

**Modify:**
- Changing time/date → release old slot → acquire new slot (two-phase, Lua atomic swap)
- If new slot unavailable → keep original; return error
- Changing party size → check if same table still fits; else reassign table

**Cancel:**
- `reservation.status = CANCELLED`; `slot.status = OPEN`
- Publish `SlotReleased` to Kafka → Waitlist Service acts
- Cancellation policy: configurable per restaurant (free if > 24h; penalty if < 2h)

**Idempotency:**
- Cancel idempotency key: `SHA256(reservation_id + "cancel")` stored in Redis 24h
- Duplicate cancel calls return same result; no double-slot-release

---

### 5.9 Multi-Region & Geo-Routing

- Restaurants are city-local: reservation data sharded by `restaurant_id` → city cluster
- Diner profile / history: global user service with async replication
- API Gateway routes by user's geo to nearest regional cluster (latency win for search)
- Cross-region reads: diner's reservation history read from home region; write-follows-request
- Failover: if primary region down → redirect to standby with read-only mode (browse not book)

---

## 6. Trade-offs Discussion

### 6.1 Slot Locking: Redis SET NX vs PostgreSQL SELECT FOR UPDATE vs Optimistic Lock

**Problem:** Two diners simultaneously book the same 7:30 PM Saturday table. 500 concurrent booking attempts/sec peak. Must be atomic with zero double-bookings.

| Approach | Throughput | Latency | Consistency | Failure Risk |
|----------|-----------|---------|-------------|-------------|
| **Redis SET NX + PG optimistic (current)** | 50K ops/sec | <10ms | Strong (two-phase) | Redis crash loses hold state |
| **PostgreSQL SELECT FOR UPDATE** | ~3K ops/sec | 20–60ms | Strongly consistent | Lock contention on hot slots |
| **Optimistic lock only (PG version++)** | ~10K ops/sec | 15–30ms | Strong (retry on conflict) | Retry storms under contention |
| **Distributed Redlock (3 nodes)** | ~5K ops/sec | 10–20ms | Strong (quorum) | Clock skew, partial quorum |

**Decision: Redis SET NX as fast path + PostgreSQL optimistic lock as safety net**
```
Two-phase rationale:
- Redis SET NX: 5-minute hold at sub-millisecond speed
  → Blocks concurrent diners immediately with no DB round-trip
- PostgreSQL UPDATE WHERE version=$v: enforces single writer at commit
  → Even if Redis hold is somehow bypassed, DB rejects the second write

Why not PostgreSQL FOR UPDATE alone?
- At 500 booking/sec peak × 60ms lock hold = 30 concurrent lock holders per slot
- A single restaurant's popular Saturday 7 PM slot → hundreds of diners waiting
- Lock queue grows → cascading timeouts → booking failures spike
- Not acceptable for 99.99% availability SLA

Redis crash scenario:
- Hold lost: slot appears OPEN again; second diner can acquire hold
- PostgreSQL optimistic lock catches conflict at commit → only one INSERT succeeds
- P(Redis crash) × P(two diners holding simultaneously) ≈ negligible
- AOF fsync: at most 1-second hold state loss on crash
```

---

### 6.2 Availability Storage: Redis Cache vs Direct PostgreSQL vs In-Memory Service

**Problem:** 5,800 search QPS; each search checks availability for 50 restaurants × multiple time slots = 290K slot lookups/sec. 24 GB of hot slot data.

| Approach | Read Throughput | Freshness | Memory Cost | Complexity |
|----------|----------------|-----------|-------------|------------|
| **Redis Cluster (current)** | 1M+ ops/sec | 5 min stale | 24 GB (Redis) | Medium |
| **PostgreSQL direct** | ~10K reads/sec | Real-time | DB IOPS | Low |
| **In-memory service (per region)** | 10M+ ops/sec | 0ms (local) | 24 GB (RAM) | High (sync) |
| **Per-request DB query + short cache** | ~100K/sec | 30s stale | Minimal | Low |

**Decision: Redis Cluster with 5-minute TTL + publish-on-change invalidation**
```
Why not in-memory service?
- 500K restaurants × 90-day window × multiple tables = 24 GB per service instance
- Horizontal scaling means duplicating 24 GB per pod (100 pods = 2.4 TB RAM)
- Redis centralizes this: 24 GB shared across all pods, much more efficient
- Sync protocol between pods adds distributed state complexity

Why 5-minute TTL vs shorter?
- Restaurant searches are overwhelmingly browse (100:1 read:write ratio)
- 5-minute stale availability is acceptable: diner sees "available," clicks,
  hold attempt fails atomically → "Sorry, just taken" → user selects alternative time
- Shorter TTL (30s): 10× more cache misses → 10× more DB reads → more load
- Longer TTL (60 min): too stale; popular Saturday slots sell out in minutes

Invalidation on change:
- When slot status changes (HELD/BOOKED/RELEASED): Kafka event → cache invalidator
- Cache invalidator calls Redis DEL on affected key → next read re-hydrates from DB
- Hot key problem: one wildly popular restaurant invalidating every 5s?
  → Dedicated Redis slot per restaurant_id; no fan-out to unrelated keys
```

---

### 6.3 Search Architecture: Elasticsearch + Availability vs Unified DB Query

**Problem:** "Italian restaurants in NYC, Sat 7 PM, party of 4, with availability" — a join between text/geo search and real-time availability.

| Approach | Freshness | Query Latency | Complexity | DB Load |
|----------|-----------|--------------|------------|---------|
| **ES for discovery + Redis for availability (current)** | ES: 1 min lag; Redis: 5 min | <200ms combined | Medium | Low |
| **Single PostgreSQL query (JOIN)** | Real-time | 500ms–2s | Low | High (full table scan) |
| **ES with denormalized availability** | 1-hour lag (batch index) | <50ms | Low | Minimal |
| **Availability pre-filtered in ES** | 5 min (from Redis writes) | <100ms | Medium | Low |

**Decision: Two-layer (ES discovery → Redis availability check)**
```
Why not single PostgreSQL JOIN?
- 500K restaurants × 240 slots × 5,800 QPS = 696M row evaluations/sec
- Even with indexes, no RDBMS handles this as read traffic at 200ms p99
- PostgreSQL is optimized for writes (bookings), not search fan-outs

Why ES doesn't embed availability?
- Availability changes every few seconds (holds expire, bookings happen)
- ES refresh interval minimum: 1 second (with performance impact)
- Re-indexing 500K restaurants on every slot change = prohibitive write amplification
- `has_availability_today` boolean in ES updated hourly — coarse filter only

Two-layer flow:
1. ES: "give me top 50 Italian restaurants in NYC, rating≥4, has_availability_today=true"
   → 50 restaurant IDs in <50ms
2. Redis: for each of 50, check slot availability for Sat 7PM ± 90min, party≥4
   → 50 × O(1) hash lookups in <10ms
3. Filter to only restaurants with open slots → return with nearest available time

Total latency: <200ms p99; DB untouched during search
```

---

### 6.4 Hold Duration: 5 Minutes vs 10 Minutes vs No Hold

**Problem:** How long should a diner's slot hold last? Long enough to complete booking, short enough to not block other diners.

| Hold Duration | Diner Completion Rate | Slots Blocked Needlessly | UX Pressure |
|--------------|----------------------|------------------------|------------|
| **No hold** | 50% (race on submit) | 0 | High anxiety |
| **2 minutes** | 65% (rushed) | Low | Moderate pressure |
| **5 minutes (current)** | 85% | Moderate | Comfortable |
| **10 minutes** | 90% | High (popular slots locked out) | Relaxed |
| **15 minutes** | 92% | Very high | No urgency |

**Decision: 5-minute hold with countdown UX**
```
Why not 10+ minutes?
- Popular Saturday slots at top restaurants: if 50 diners each hold for 10 min
  before the slot auto-releases → slot appears booked for 500 minutes of real time
  even if none of them convert → restaurant loses revenue
- At 5 min: hold chain = 250 minutes (still significant, but manageable)
- Restaurant operators complained (via OpenTable data) about 10-min holds

Why not 2 minutes?
- Mobile diners filling in name/phone/party details: 2 min too short if on bad connection
- Abandonment rate spikes above 30%

5 min with UX countdown:
- Timer shown prominently in UI ("Your table is held for 4:23")
- At 1 min: warning + "complete booking to keep this table"
- On expiry: graceful "Hold expired — find another time?" flow
```

---

### 6.5 Waitlist Fan-Out: FIFO vs Broadcast vs Priority Queue

**Problem:** A cancellation opens a slot. 50 waitlisted diners want it. How do we notify fairly while ensuring the slot actually gets booked?

| Approach | Fairness | Conversion Rate | Complexity |
|----------|----------|----------------|------------|
| **FIFO single notify (current)** | High (strict queue) | Medium (15-min window; may fail) | Medium |
| **Broadcast to all waitlisted** | Low (first-click wins → bots win) | High (someone definitely books) | Low |
| **Priority queue (party size match)** | Medium (smaller parties preferred) | High | Medium |
| **Silent hold → best match** | Medium | Highest (automatic) | High |

**Decision: FIFO single notify with 15-minute exclusive window**
```
Why not broadcast?
- Broadcasting to 50 diners creates a race condition identical to general sale
- Bot-like behavior: scripts click fastest → unfair to human users
- 49 diners notified and disappointed = poor UX at scale

Why not silent hold → auto-book for first waiter?
- Diners may no longer want the slot (plans changed)
- Auto-booking without confirmation → chargebacks, disputes
- Restaurants prefer confirmed intent before blocking table

FIFO + 15-min window:
- First waiter gets exclusive booking link (pre-holds slot via token)
- If no action in 15 min: slot released; next waiter notified
- Cascade stops if slot gets booked at any point

Trade-off: Slot may be "offline" for up to 15 min × waitlist depth if no one converts.
Mitigation: track conversion rate per restaurant; if < 30%, broaden to next 3 waitlist
entries simultaneously (tunable per restaurant tier).
```

---

### 6.6 Slot Generation: Batch Pre-generation vs On-Demand Computation

**Problem:** Do we pre-generate all bookable slots for the next 90 days (PostgreSQL rows), or compute availability dynamically at query time?

| Approach | Query Speed | Storage | Config Flexibility | Edge Cases |
|----------|------------|---------|-------------------|------------|
| **Pre-generated rows (current)** | Fast (index scan) | 500K × 240 slots = 120M rows | Changes require slot regeneration | Holiday/blackout easy to block |
| **On-demand computation** | Slower (rule evaluation) | Zero slot rows | Fully flexible | Complex overlapping-rule logic |
| **Hybrid (pre-gen + override rules)** | Fast | 120M rows + rule table | Flexible overrides | Medium complexity |

**Decision: Pre-generated rows with override rule layer**
```
Why pre-generate?
- Availability query = simple index scan on (restaurant_id, date, status)
- On-demand computation: evaluate open_hours, turn_duration, blackouts, capacity rules
  per query → O(rules) per restaurant × 50 restaurants per search × 5,800 QPS = millions
  of rule evaluations/sec → CPU intensive, hard to cache

Pre-generation trade-offs:
- Nightly batch generates slots 90 days out (cron job, low-priority)
- Config change (restaurant adds Monday hours) → regenerate future slots for that restaurant
  → idempotent: DELETE slots WHERE date > today AND restaurant_id = X → INSERT new

Override rule layer:
- Blackout dates, private events: status = BLOCKED (no regen needed)
- Special pricing: added as slot-level attribute
- Emergency close (snow day): batch UPDATE status=BLOCKED for all slots that day

Storage: 500K restaurants × 240 slots/day × 90 days × 200 bytes = 2.2 TB
Well within PostgreSQL at this scale (sharded by restaurant_id → city cluster).
```

---

### 6.7 Consistency Model Across the System

**Deliberate consistency decisions per component:**

| Component | Consistency | Rationale |
|-----------|------------|-----------|
| Slot hold (Redis SET NX) | **Strong** (atomic) | Zero double-hold — core SLA |
| Slot confirm (PG optimistic) | **Strong** (version check) | Belt-and-suspenders; no double-booking |
| Availability cache (Redis TTL) | **Eventual** (5 min stale) | 100:1 read:write; stale-then-fail-gracefully |
| Search results (Elasticsearch) | **Eventual** (~1 min lag) | Discovery not booking; stale results OK |
| `has_availability_today` in ES | **Eventual** (hourly update) | Coarse pre-filter; false positives handled in Layer 2 |
| Waitlist notification | **At-least-once** (Kafka) | Missing a notification = lost booking opportunity |
| Reservation cancel + slot release | **Atomic** (single PG TX) | Both must succeed or neither; idempotent key prevents double-release |
| No-show count update | **Eventual** (Kafka async) | Behavioral tracking; 1-event lag has no revenue impact |
| Restaurant dashboard (WebSocket) | **Eventual** (Kafka push) | Live floor view; 1–2s lag acceptable for host UX |

**Key interview insight:** The read:write ratio of 100:1 fundamentally drives the architecture. Serving availability reads from PostgreSQL at 5,800 QPS × 50 restaurants × multiple slots = 290K slot reads/sec would require a DB fleet 30× the size of what Redis + a single-digit write rate demands. Eventual consistency on reads is not a compromise here — it's the economically rational choice that enables the system to exist at this scale.

---

### 6.8 Sharding: By restaurant_id vs city vs user_id

**Problem:** 10M restaurants globally, 5M reservations/day. Where do we shard?

| Sharding Key | Query Locality | Hotspot Risk | Cross-shard Queries |
|-------------|---------------|-------------|---------------------|
| **restaurant_id (current)** | All slot ops for one restaurant on one shard | Hot restaurants on one shard | User's reservation history |
| **city** | Geographic locality (most queries are city-scoped) | NYC shard overwhelmed | Inter-city bookings (rare) |
| **user_id** | "My reservations" local | Power users | All restaurant availability queries cross-shard |
| **date** | Time-range queries fast | Today's date = hot shard | All other queries cross-shard |

**Decision: restaurant_id sharding for slots/reservations; user_id for user profile/history**
```
Restaurant-sharded slots:
- All slot, hold, and booking operations for a restaurant are on one shard
- Enables atomic operations without cross-shard coordination
- Hot restaurant problem: top 1% of restaurants (5K) get 90% of traffic
  → Dedicated shards for top restaurants (identified by booking volume metric)
  → Shard map updated dynamically in ZooKeeper as popularity shifts

City-level clustering:
- Restaurant_id consistent-hashed within city cluster
- US clusters: US-EAST (NYC, DC, Miami), US-WEST (LA, SF, Seattle), US-CENTRAL (Chicago, Dallas)
- EU clusters: EU-WEST, EU-CENTRAL
- API Gateway routes diner request to city cluster based on searched city

User profile → global user service:
- Reservation history replicated asynchronously (eventual consistency)
- User's past restaurant list, preferences, no-show count
- Read from nearest regional replica; writes go to home region

Trade-off: "User's all reservations" query → scatter-gather across restaurant-sharded DB
Mitigation: Kafka event log → user_id-partitioned reservation topic → materialized view
in a separate user-centric read DB (event sourcing pattern).
```

---

## 7. Follow-Up Topics

### Handling Valentine's Day Spike (10× normal)
- Pre-scale Reservation Service pods and Redis cluster 48h before
- Rate-limit per restaurant: max 50 concurrent booking sessions (queue excess)
- Slot cache TTL reduced to 60s (fresher data during high contention)
- Circuit breaker: if DB write latency > 1s, fail fast with "try again" vs. hanging requests

### Group Reservations / Private Dining
- Party > 12 → flag as large group → route to restaurant's events inbox (manual confirmation)
- Private dining room: modeled as a "table" with room_capacity; separate slot pool
- Deposit required → Payment Service integration at booking time

### International Timezone Handling
- All times stored in UTC in DB; converted to restaurant's local timezone at query time
- Restaurant's timezone stored in restaurant record (IANA zone, e.g., `America/New_York`)
- "7:30 PM Saturday at Nobu NYC" → stored as `2026-08-16T23:30:00Z`

### Review & Reputation System
- Review eligible only after confirmed reservation with `diner_arrived = true`
- Rating: 1–5 stars + text; aspect ratings (food, service, ambiance, value)
- Aggregate score = Bayesian average (min 10 reviews for full weight)
- Fraud: flag reviews from same IP/device burst; auto-hold for moderation

### API Design (Core Endpoints)

```
# Search
GET /v1/restaurants?city=NYC&cuisine=italian&date=2026-08-16&time=19:30&party=4

# Get availability
GET /v1/restaurants/{id}/availability?date=2026-08-16&party=4

# Hold a slot
POST /v1/slots/{slot_id}/hold
Body: { "user_id": "...", "session_id": "..." }

# Confirm reservation
POST /v1/reservations
Body: { "slot_id": "...", "party_size": 4, "contact": {...}, "special_requests": "..." }

# Modify
PATCH /v1/reservations/{id}
Body: { "new_slot_id": "...", "party_size": 5 }

# Cancel
DELETE /v1/reservations/{id}

# Join waitlist
POST /v1/restaurants/{id}/waitlist
Body: { "date": "...", "earliest": "19:00", "latest": "21:00", "party": 4 }
```

### Observability

| Signal | Tool |
|---|---|
| Distributed Tracing | Jaeger (trace per reservation_id) |
| Metrics | Prometheus + Grafana (booking success rate, slot hold contention, search latency) |
| Alerting | PagerDuty (double-booking detected → P0; booking success rate < 95% → P1) |
| Logs | ELK stack |
| Synthetic Tests | Booking canary per region every 5 min |

---

## Summary

| Component | Technology |
|---|---|
| API Gateway | Envoy / Kong |
| Search & Discovery | Elasticsearch + Redis (availability cache) |
| Slot Locking | Redis SET NX (Lua atomic) |
| Reservation DB | PostgreSQL (sharded by restaurant_id → city) |
| Availability Cache | Redis Cluster (24 GB hot window, 5-min TTL) |
| Event Streaming | Apache Kafka |
| Real-time Floor View | WebSocket + Kafka consumers |
| Notifications | SendGrid (email) + Twilio (SMS) + FCM/APNs (push) |
| Waitlist | PostgreSQL + Kafka consumer |
| Monitoring | Prometheus + Grafana + Jaeger + PagerDuty |
