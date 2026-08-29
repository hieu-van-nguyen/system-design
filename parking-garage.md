# System Design: Parking Garage System

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Medium-Hard  
> Core challenges: **Atomic spot assignment without double-booking · Gate offline-first operation · IoT sensor reconciliation at 168K events/sec**

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | Vehicles enter the garage and are issued a **parking ticket** (physical or digital) |
| FR-2 | System tracks **real-time availability** of spots by floor, zone, and vehicle type |
| FR-3 | Vehicles exit and are charged based on **duration parked** (hourly/flat rate) |
| FR-4 | Support multiple **vehicle types**: motorcycle, compact, regular, oversized (EV, handicap) |
| FR-5 | **Payment processing** at exit: cash, card, mobile pay, pre-paid validation |
| FR-6 | Support **multiple garages** managed under one platform (multi-tenant) |
| FR-7 | Operators can view **occupancy dashboards** and revenue reports in real time |
| FR-8 | (Optional) **Spot reservation** in advance (up to 24h) via mobile app |
| FR-9 | (Optional) **License plate recognition (LPR)** for ticketless entry/exit |
| FR-10 | (Optional) Navigate driver to nearest **available spot** (indoor wayfinding) |

**Out of scope:** Valet management, EV charging billing, parking citations, street parking.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 10,000 garages; avg 500 spots/garage = 5M total spots globally |
| **Availability** | 99.99% — gate failures must not block vehicles (offline-capable hardware) |
| **Latency** | Gate open/close p99 < 500ms; spot availability update p99 < 2s |
| **Consistency** | Strong consistency for spot assignment (no double-booking); eventual for dashboards |
| **Throughput** | Peak: 1,000 vehicles entering/exiting per garage per hour across all garages |
| **Durability** | Payment records retained 7 years (regulatory); ticket records 90 days |
| **Offline mode** | Gate hardware operates autonomously if cloud connectivity is lost |
| **Security** | PCI-DSS compliance for payment; tamper-proof ticket IDs |

---

## 3. Back-of-Envelope Estimation

### Traffic

```
Garages                  = 10,000
Avg spots per garage     = 500
Total spots              = 5,000,000

Turnover rate            = 4 sessions/spot/day (avg 2-hour stay)
Total sessions/day       = 5M × 4 = 20M sessions/day
Sessions/sec (avg)       = 20M / 86,400 ≈ 231/sec
Peak (morning rush, 5×)  ≈ 1,155 sessions/sec (entry OR exit events)

Spot status updates/day  = 20M × 2 (entry + exit) = 40M updates/day
Updates/sec (avg)        = 463/sec
```

### Storage

```
Per session record:
  ticket_id (UUID)       = 16 bytes
  plate / vehicle_id     = 20 bytes
  spot_id                = 8 bytes
  entry/exit timestamps  = 16 bytes
  fee, payment_id        = 20 bytes
  metadata               = 20 bytes
  Total                  ≈ 100 bytes/session

90-day session storage   = 20M/day × 90 × 100 bytes ≈ 180 GB
7-year payment archive   = 20M/day × 365 × 7 × 100 bytes ≈ 5.1 TB

Spot state (live):
  5M spots × 50 bytes    = 250 MB  → fits entirely in Redis

Reservation table (optional):
  10% of sessions reserved × 5M × 50B ≈ 25 MB/day active
```

### Revenue

```
Avg fee/session          = $8
Daily revenue            = 20M × $8 = $160M/day (platform-wide)
Peak payment TPS         = 1,155/sec → payment gateway must handle ~1,200 TPS
```

### Hardware Events (IoT)

```
Sensors per garage       = 500 spots × 1 sensor + entry/exit gates × 2
Heartbeat per sensor     = every 30s
Sensor events/sec        = (500 + 4) × 10,000 / 30 ≈ 168,000 msg/sec → IoT ingestion layer
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────────────┐
│                         CLIENTS                                          │
│  Mobile App (driver)  │  Gate Terminal (kiosk)  │  Operator Dashboard    │
└──────┬───────────────────────┬──────────────────────────┬───────────────┘
       │ HTTPS/REST            │ MQTT / local API          │ HTTPS
       │                       │                           │
┌──────▼───────────────────────▼───────────────────────────▼──────────────┐
│                        API Gateway / Load Balancer                       │
└──────┬───────────────────────┬───────────────────────────┬──────────────┘
       │                       │                           │
┌──────▼──────┐   ┌────────────▼──────────┐   ┌───────────▼────────────┐
│  Parking    │   │   Gate / IoT Service   │   │   Reporting Service    │
│  Service    │   │   (entry/exit events)  │   │   (dashboards, revenue)│
│  (core)     │   └────────────┬───────────┘   └───────────┬────────────┘
└──────┬───────┘               │                           │
       │               ┌───────▼──────────┐       ┌───────▼──────────┐
       │               │   IoT Message    │       │  Analytics Store  │
       │               │   Queue (Kafka)  │       │  (ClickHouse /    │
       │               └───────┬──────────┘       │   BigQuery)       │
       │                       │                  └──────────────────┘
┌──────▼───────────────────────▼──────────────┐
│              Spot Availability Service        │
│   (real-time spot state, Redis + DB)         │
└──────┬──────────────────────────────────────┘
       │
┌──────▼──────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│   Primary DB    │    │   Redis Cache        │    │   Payment Service   │
│   (PostgreSQL / │    │   (spot state,       │    │   (Stripe / Braintree│
│    CockroachDB) │    │    session cache)    │    │    + PCI vault)     │
└─────────────────┘    └─────────────────────┘    └─────────────────────┘
       │
┌──────▼──────────┐
│ Notification    │
│ Service         │
│ (SMS/Push/Email)│
└─────────────────┘
```

### Core API

```
// Entry
POST /v1/garages/{garageId}/entry
Body: { plateNumber?, vehicleType }
Response 201: { ticketId, spotId, floor, zone, entryTime, qrCode }

// Exit / Payment
POST /v1/tickets/{ticketId}/exit
Body: { paymentMethod }
Response 200: { fee, duration, receiptId, exitAuthorized: true }

// Spot Availability
GET /v1/garages/{garageId}/availability
Response: { total, available, byType: { compact, regular, oversized, motorcycle } }

// Reservation (optional)
POST /v1/garages/{garageId}/reservations
Body: { vehicleType, startTime, endTime, plateNumber }
Response 201: { reservationId, spotId, confirmationCode }

// Operator
GET /v1/garages/{garageId}/dashboard
Response: { occupancy%, revenue, entryRate, avgDuration, alerts[] }
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Spot Assignment — Redis Atomic Queue vs. SQL SELECT FOR UPDATE vs. Optimistic Lock

| Approach | Concurrency Safety | Latency | Complexity |
|----------|-------------------|---------|------------|
| SQL `SELECT FOR UPDATE` | ✅ Serialized | High — lock wait at peak | Low |
| SQL optimistic lock (version check) | ✅ Detect conflicts | Medium — retry on conflict | Medium |
| **Redis LPOP (atomic queue)** | ✅ Atomic by nature | < 1ms | Medium |
| Application-level distributed lock | ✅ If implemented correctly | Medium | High |

```
Why Redis LPOP wins for spot assignment:

SQL SELECT FOR UPDATE problem:
  At peak (1,155 sessions/sec), many vehicles enter simultaneously
  Multiple threads compete for the same rows:
    Thread A: SELECT id FROM spots WHERE status='available' LIMIT 1 FOR UPDATE
    Thread B: waits for Thread A's lock → queuing delay
    Thread C: waits for Thread B...
  → Lock queue builds up → p99 latency spikes → gates slow at worst possible moment

Optimistic locking problem:
  Thread A and Thread B both read spot S1 at version=5
  Both try: UPDATE spots SET status='occupied', version=6 WHERE id=S1 AND version=5
  One succeeds; one gets 0 rows → retry → finds another spot → double work

Redis LPOP solution:
  Pre-load available spots into Redis list on garage boot:
    RPUSH spots:available:{garageId}:regular S1 S2 S3 ... S450

  On vehicle entry (atomic, no lock needed):
    spot_id = LPOP spots:available:{garageId}:regular
    → Redis single-threaded command processing: only ONE caller gets S1
    → No race condition possible — Redis serializes all LPOPs
    → Latency: < 0.1ms (memory operation, no disk I/O)

  Write to PostgreSQL after claiming spot (non-blocking to the gate):
    INSERT INTO sessions (spot_id, ...) → DB write happens after gate opens

Fallback when Redis is down:
  PostgreSQL: SELECT id FROM spots WHERE status='available' LIMIT 1 FOR UPDATE SKIP LOCKED
  SKIP LOCKED: skip rows locked by other transactions → no blocking wait
  Slower (~5ms vs ~0.1ms) but correct — Redis failure degrades gracefully

Decision: Redis LPOP as primary (fast, atomic, no DB lock).
  PostgreSQL SKIP LOCKED as fallback. State both in interview.
```

---

### Trade-Off 2: Gate Offline Mode — Local Autonomy vs. Cloud-Only

| Approach | Availability | Consistency | Hardware Cost |
|----------|-------------|-------------|---------------|
| Cloud-only (gate requires connectivity) | Low — any outage blocks vehicles | Strong | Low (thin client) |
| **Local SQLite + edge agent (Recommended)** | High — works offline | Eventual (reconcile on reconnect) | Medium |
| Full P2P (gates sync with each other) | Highest | Complex conflict resolution | High |

```
The core tension: availability vs. consistency at the physical barrier

Cloud-only failure scenario:
  AWS us-east-1 has a 20-minute partial outage
  → All 10,000 garages globally cannot issue tickets
  → Vehicles queue outside → driver anger → news coverage
  → This is a catastrophic UX failure for a physical infrastructure product

Local edge agent approach:
  Gate terminal = embedded Linux (Raspberry Pi / Jetson Nano)
  Local SQLite: stores rate config, active sessions, spot state
  Synced from cloud: rate config (daily), reservation list (every 15 min)

  Offline entry:
    Issue ticket from LOCAL counter: {garageId}-LOCAL-{sequence}
    Record in local SQLite: session, entry_time, spot_id (local assignment)
    Gate opens immediately — no cloud round-trip

  Offline exit:
    Look up session in local SQLite
    Calculate fee using cached rate config
    Accept cash OR store encrypted card details → charge on cloud reconnect
    Gate opens

  Sync on reconnect:
    Terminal sends buffered entry/exit events with timestamps
    Cloud applies them (idempotent by local event_id + timestamp)
    Cloud reconciles spot state, returns delta
    If spot double-assigned offline: flag for operator review (rare, manageable)

Consistency sacrifice:
  During offline period: cloud dashboard shows stale occupancy
  After reconnect: dashboard corrects within seconds
  Real-world assessment: operators accept 20 minutes of stale dashboard
                         to avoid vehicles being blocked

Decision: Local SQLite edge agent. Availability beats consistency for physical barriers.
  "The gate defaulting to fail-open is better than failing-closed" — say this explicitly.
```

---

### Trade-Off 3: Spot State Source of Truth — IoT Sensors vs. Transaction Log vs. Hybrid

| Approach | Accuracy | Infrastructure Cost | Complexity |
|----------|----------|---------------------|------------|
| Transaction log only (entry/exit events) | Medium — no ground truth | Low (no sensors) | Low |
| **IoT sensors per spot (Recommended)** | High — ground truth | High (~$50/sensor × 5M = $250M) | High |
| Camera-based overhead counting | High | Medium (per floor) | Medium |

```
Transaction log only:
  Spot status = "occupied" after entry event, "available" after exit event
  Pros: no hardware cost beyond gates
  Cons:
    Vehicle parks without ticket (tailgate through open gate) → spot shows available but is occupied
    Ticket issued but vehicle never parks → spot shows occupied but is available
    System state drifts from reality over hours → "ghost occupancy"

IoT sensor per spot (ultrasonic or magnetic):
  Each spot: one sensor → MQTT heartbeat every 60s + state-change event
  Ground truth: sensor says "occupied" regardless of ticket state
  Reconciliation: Flink stream processor:
    sensor=occupied AND session=active   → normal, no action
    sensor=occupied AND session=inactive → vehicle without ticket → alert operator
    sensor=vacant   AND session=active   → vehicle left without exit scan → grace 5 min → alert
  Cost reality: $50/sensor × 5M spots = $250M hardware cost
    → Only justified for premium garages, airports, hospitals
    → Mid-tier: per-row or per-floor counting cameras (cheaper, less granular)

Hybrid approach (recommended for interview answer):
  Sensors at entry/exit lanes (must-have): prevent tailgating, enforce payment
  Sensors per spot: premium garages only (airports, hospitals)
  Camera overhead counting: per-floor display boards (cost-effective middle ground)
  Transaction log: primary source of truth; sensors as reconciliation signal

Decision: Hybrid — transaction log primary + entry/exit lane sensors mandatory
  + per-spot sensors as optional upgrade tier. Scale sensor density to business value.
```

---

### Trade-Off 4: Consistency Model — Strong vs. Eventual for Spot Availability

| Dimension | Strong Consistency | Eventual Consistency |
|-----------|-------------------|---------------------|
| Spot assignment | **Must be strong** — no double-booking | Unacceptable |
| Availability count (dashboard) | Unnecessary | ✅ Acceptable — ±2 spots fine |
| Reservation creation | **Must be strong** — no double-sell | Unacceptable |
| Occupancy display on LED signs | Unnecessary | ✅ 3s staleness fine |

```
The key insight: different parts of the system need DIFFERENT consistency levels

Strong consistency (where required):
  Spot assignment: Redis LPOP is strongly consistent (single-threaded command)
  Reservation booking: Redis SET NX (atomic) → only one winner
  Payment recording: PostgreSQL ACID transaction (cannot lose payment record)

Eventual consistency (where acceptable):
  Dashboard occupancy count: operator sees "142 of 500 spots available"
    If it says 143 for 2 seconds: no one is harmed
    Implementation: Redis HINCRBY (fast, may drift) + periodic DB reconciliation
  LED floor signs: "Floor 3: 12 available" can lag 3 seconds — acceptable
  Analytics/revenue reports: 5-minute lag is fine for business reporting

Practical CAP analysis:
  Parking garage is a CP system for assignment (consistency + partition tolerance)
  Redis Cluster: if partition occurs → writes to minority partition blocked
    → Some garages may show "temporarily unavailable" rather than risk double-booking
  This is correct: a double-booked spot causes physical real-world conflict
    (two drivers fight over the same space) → much worse than a temporary outage

Decision: Strong consistency for assignment and payment.
  Eventual for dashboard, LED signs, analytics.
  Explicitly separate these two categories in your interview answer.
```

---

### Trade-Off 5: Reservation Approach — Full Pre-Booking vs. Walk-In Buffer vs. No Reservations

| Approach | Revenue Predictability | Utilization | UX Complexity |
|----------|----------------------|-------------|---------------|
| No reservations (walk-in only) | Low — unpredictable fill | Variable | Simple |
| Full pre-booking (all spots reservable) | High | Risk: no-shows leave gaps | Complex |
| **Buffer model: 90% reservable + 10% walk-in** | High | Best — buffer absorbs no-shows | Medium |

```
Full pre-booking problem (airline-style):
  All 500 spots sold in advance for Saturday 2-4pm
  10% no-show rate → 50 empty spots while walk-in drivers turned away
  Operator unhappy: revenue lost + bad walk-in UX

No reservations problem:
  Popular garage near stadium for Saturday game
  All 500 spots fill in first 10 minutes
  Drivers arrive expecting a spot → turned away → anger

Buffer model (recommended):
  90% of spots reservable (450)
  10% of spots permanently walk-in only (50)

  No-show protection:
    Release reserved spot 30 min after reservation start if no vehicle detected
    Released spot returns to walk-in pool immediately
    → Utilization stays high even with no-shows

  Overbooking (like airlines):
    Reservations can be oversold up to 110% of reservable spots
    Statistical model: 10% no-show rate → sell 110% for ~100% fill
    If overbooked and all show up: offer refund + redirect to nearby garage
    → Rare edge case, manageable operationally

Spot type allocation for reservations:
  Not all spot types need same reservation ratio
  Handicap spots: 0% reservable (first-come by need)
  EV charging: 100% reservable (scarce, high demand predictability)
  Regular: 90/10 split (default)

Decision: Buffer model (90/10 split) with 30-min no-show release.
  Mention overbooking as an option for revenue optimization.
```

---

### Trade-Off 6: Primary Database — PostgreSQL vs. CockroachDB vs. Cassandra

| Criterion | PostgreSQL | CockroachDB | Cassandra |
|-----------|-----------|-------------|-----------|
| ACID transactions | ✅ Full | ✅ Distributed ACID | ❌ LWT only |
| Horizontal write scale | ❌ (manual sharding) | ✅ Native | ✅ Native |
| JSONB / flexible schema | ✅ Native | ✅ Native | ❌ |
| Multi-region active-active | ❌ Complex | ✅ Native | ✅ Native |
| Operational complexity | Low | Medium | High |
| Row-level security (multi-tenancy) | ✅ Native | ✅ Native | ❌ |

```
Why not Cassandra for core parking data:
  Reservations need ACID: "reserve spot S for user U, deduct inventory, charge card"
    → 3-table operation requiring atomicity → Cassandra LWT is limited and slow
  Rate config is JSONB: Cassandra has no native JSONB → work-around is messy
  Complex queries: "show all active sessions for garage G in last 4 hours"
    → Cassandra requires knowing partition key; ad-hoc queries are painful
  Multi-tenancy RLS: PostgreSQL row-level security is perfect; Cassandra has no equivalent

PostgreSQL (single region):
  Works well for initial scale (10K garages, 231 sessions/sec avg)
  Start here: operationally simple, full SQL, JSONB, RLS
  Limitation: single primary bottleneck at very high write volume
  Mitigation: PgBouncer connection pooling + read replicas for dashboards

CockroachDB (multi-region at scale):
  Same SQL interface as PostgreSQL → easy migration
  Native geo-distribution: session data for US garage stored in US region
  Distributed transactions: works across 10 regions transparently
  Cost: 3-5× more expensive than PostgreSQL per unit of compute
  Recommended when: >100K garages globally, strict data residency requirements

Decision: PostgreSQL to start (simpler, cheaper).
  Migrate to CockroachDB when multi-region and scale require it.
  Cassandra for IoT sensor event storage only (high-volume append-only events).
```

---

### Trade-Off 7: Multi-Tenancy — Shared Database vs. Schema-per-Tenant vs. DB-per-Tenant

| Model | Isolation | Cost | Operational Overhead |
|-------|-----------|------|---------------------|
| Shared DB, row-level security | Medium (logical) | Lowest | One cluster to manage |
| Schema-per-tenant (PostgreSQL schemas) | Medium-high | Low | Schema migration complexity |
| **DB-per-tenant (enterprise option)** | High (physical) | Highest | Many clusters |

```
Shared DB with row-level security (recommended for most tenants):
  All garage operators share one PostgreSQL cluster
  Every table has garage_id column
  PostgreSQL RLS policy:
    CREATE POLICY tenant_isolation ON sessions
      USING (garage_id = current_setting('app.current_garage_id')::UUID);
  Application layer: SET app.current_garage_id = '{garageId}' per connection
  → Impossible for Tenant A's query to accidentally return Tenant B's data

  Cost efficiency: 10,000 garage operators share ~20 PostgreSQL nodes
  vs. 10,000 separate DB instances → 500× cost reduction

Schema-per-tenant:
  Each tenant gets a PostgreSQL schema (namespace) within the same DB
  Pros: easier to take individual tenant snapshots, migrate, or export data
  Cons: PostgreSQL limits ~100 schemas per DB before performance degrades
        Not practical for 10K tenants

DB-per-tenant (enterprise):
  Large airport operators, hospital systems: dedicated PostgreSQL cluster
  Pros: complete isolation, dedicated resources, no "noisy neighbor" risk
  Cons: $5,000/month per dedicated cluster × 1,000 enterprise tenants = $5M/month
  Use case: enterprise contract with SLA > 99.99%, HIPAA compliance requirement

Decision: Shared DB + RLS for 95% of tenants (small/mid garage operators).
  Dedicated DB for enterprise tenants (airports, hospitals) willing to pay premium.
  Mention this tiered model — it's the standard SaaS multi-tenancy answer.
```

---

## 6. Deep Dive

### 6.1 Spot Assignment Algorithm

When a vehicle enters, the system must assign a spot — this is a **critical section** (no double-assignment):

```
Assignment priority:
  1. Match vehicle type to compatible spot size
     (motorcycle → any spot; compact → compact/regular; oversized → oversized only)
  2. Prefer nearest available spot to entry (minimize driver travel)
  3. Respect reserved spots (blocked until reservation window ±15 min)

Algorithm:
  1. Redis LMPOP from spot_available_queue:{garageId}:{vehicleType}
     → O(1) pop from pre-sorted list of available spot IDs
  2. If queue empty → check next-larger spot type (upsell logic)
  3. Assign spot: UPDATE spots SET status='occupied', ticket_id=? WHERE id=?
  4. Write session record → return ticket

Why Redis queue (not SQL SELECT FOR UPDATE):
  - SQL SELECT FOR UPDATE at high concurrency causes lock contention
  - Redis LPOP is atomic — zero race conditions, microsecond latency
  - Spot IDs pre-loaded into Redis on garage boot; updated on entry/exit events
```

**Spot compatibility matrix:**

```
Vehicle Type     │ Motorcycle │ Compact │ Regular │ Oversized
─────────────────┼────────────┼─────────┼─────────┼──────────
Motorcycle spot  │     ✓      │         │         │
Compact spot     │     ✓      │    ✓    │         │
Regular spot     │     ✓      │    ✓    │    ✓    │
Oversized spot   │     ✓      │    ✓    │    ✓    │    ✓
```

---

### 6.2 Database Schema

```sql
-- Garages
CREATE TABLE garages (
  id            UUID PRIMARY KEY,
  name          TEXT,
  address       TEXT,
  total_floors  INT,
  timezone      TEXT,
  rate_config   JSONB,   -- { hourly: 3.00, daily_max: 25.00, overnight: 15.00 }
  created_at    TIMESTAMPTZ
);

-- Spots
CREATE TABLE spots (
  id            UUID PRIMARY KEY,
  garage_id     UUID REFERENCES garages(id),
  floor         SMALLINT,
  zone          CHAR(1),         -- A, B, C...
  spot_number   TEXT,            -- A-101
  spot_type     spot_type_enum,  -- motorcycle, compact, regular, oversized
  features      TEXT[],          -- ['ev_charging', 'handicap', 'reserved']
  status        spot_status_enum -- available, occupied, reserved, maintenance
);

CREATE INDEX idx_spots_garage_status ON spots(garage_id, status, spot_type);

-- Sessions (parking tickets)
CREATE TABLE sessions (
  id            UUID PRIMARY KEY,
  garage_id     UUID REFERENCES garages(id),
  spot_id       UUID REFERENCES spots(id),
  plate_number  TEXT,
  vehicle_type  spot_type_enum,
  entry_time    TIMESTAMPTZ NOT NULL,
  exit_time     TIMESTAMPTZ,
  duration_min  INT GENERATED ALWAYS AS
                  (EXTRACT(EPOCH FROM exit_time - entry_time)/60) STORED,
  fee_cents     INT,
  payment_id    TEXT,
  status        session_status_enum  -- active, completed, voided
);

CREATE INDEX idx_sessions_garage_active ON sessions(garage_id) WHERE status = 'active';
CREATE INDEX idx_sessions_plate ON sessions(plate_number, entry_time DESC);

-- Reservations
CREATE TABLE reservations (
  id              UUID PRIMARY KEY,
  garage_id       UUID REFERENCES garages(id),
  spot_id         UUID,
  user_id         UUID,
  vehicle_type    spot_type_enum,
  plate_number    TEXT,
  start_time      TIMESTAMPTZ,
  end_time        TIMESTAMPTZ,
  status          TEXT,           -- confirmed, active, completed, cancelled
  confirmation_code TEXT UNIQUE
);
```

---

### 6.3 Fee Calculation Engine

```
Rate config is stored as JSONB per garage (flexible, operator-configurable):

{
  "type": "tiered",
  "currency": "USD",
  "tiers": [
    { "upTo": 60,   "rate": 3.00  },   // first hour: $3
    { "upTo": 120,  "rate": 2.50  },   // second hour: $2.50
    { "upTo": 1440, "rate": 1.50  }    // each additional hour: $1.50
  ],
  "dailyMax": 2500,                    // $25.00 cap
  "overnight": { "from": "22:00", "to": "06:00", "flat": 1500 },
  "grace": 15,                         // 15 min free grace period
  "validation": {
    "MERCHANT_X": { "discountPct": 50, "maxMinutes": 120 }
  }
}

Fee calculation (pseudocode):
  duration = exit_time - entry_time (in minutes)
  if duration <= grace → fee = 0
  else:
    net_duration = duration - grace
    fee = sum of tiered rates for net_duration
    fee = min(fee, dailyMax)
    if overnight_period(entry_time, exit_time) → apply overnight flat rate
    if validation_code present → apply merchant discount
```

**Key design choice:** Fee config in JSONB (not hardcoded) → operators change rates without deployment.

---

### 6.4 Real-Time Spot Availability (Redis)

```
Redis data structures per garage:

Spot availability queues (LMPOP-safe):
  Key: spots:available:{garageId}:compact     → List of spotIds
  Key: spots:available:{garageId}:regular     → List of spotIds
  Key: spots:available:{garageId}:oversized   → List of spotIds
  Key: spots:available:{garageId}:motorcycle  → List of spotIds

Spot state (for display/dashboard):
  Key: garage:state:{garageId}
  Type: Hash
  Fields: { total, available_compact, available_regular, available_oversized, available_motorcycle }

Occupancy counter:
  Key: garage:occupancy:{garageId} → INT (INCR on entry, DECR on exit)

Session cache (fast exit lookup):
  Key: session:{ticketId}
  Value: { spotId, entryTime, vehicleType, garageId }
  TTL: 24h (evict stale sessions)

On vehicle ENTRY:
  LPOP spots:available:{garageId}:{type}   → assigns spot atomically
  HINCRBY garage:state:{garageId} available_{type} -1
  INCR garage:occupancy:{garageId}
  HSET session:{ticketId} ...

On vehicle EXIT:
  RPUSH spots:available:{garageId}:{type} {spotId}   → returns spot to pool
  HINCRBY garage:state:{garageId} available_{type} +1
  DECR garage:occupancy:{garageId}
  DEL session:{ticketId}
```

---

### 6.5 Gate Hardware & Offline Mode

Gate terminals are embedded Linux devices with **local state** — cloud outage must not block traffic:

```
Gate Terminal Architecture:
  ┌──────────────────────────────────────┐
  │          Gate Terminal               │
  │  ┌─────────────┐  ┌───────────────┐ │
  │  │ Local SQLite │  │ Camera / LPR  │ │
  │  │ (sessions,   │  │ module        │ │
  │  │  spot state) │  └───────────────┘ │
  │  └─────────────┘  ┌───────────────┐ │
  │  ┌─────────────┐  │ Payment       │ │
  │  │ Edge Agent  │  │ Terminal      │ │
  │  │ (sync mgr)  │  └───────────────┘ │
  │  └──────┬──────┘                    │
  └─────────┼────────────────────────── ┘
            │ MQTT / HTTPS (when online)
            ▼
       Cloud Backend

Offline mode behavior:
  - Entry: issue ticket from local counter (format: GARAGE_ID-LOCAL-SEQ)
  - Exit:  calculate fee locally using cached rate config (synced daily)
  - Payment: accept cash or defer card (store encrypted card data, charge on reconnect)
  - Spot state: maintained locally; reconciled with cloud on reconnect

Sync protocol (reconnect):
  1. Terminal sends buffered events (ENTRY/EXIT) with timestamps
  2. Cloud applies events in timestamp order (idempotent via event_id)
  3. Cloud reconciles spot state; sends delta to terminal
  4. Conflicts (same spot double-assigned offline): flag for operator review
```

---

### 6.6 License Plate Recognition (LPR) — Ticketless Entry

```
Entry flow with LPR:
  1. Camera captures plate at entry gate
  2. On-device ML model (YOLO-based) reads plate → plate_number string (< 200ms)
  3. Cloud lookup: GET /v1/vehicles/{plateNumber}/active-reservation
     a. Pre-registered → assign reserved spot, open gate
     b. Known member → open gate (monthly pass)
     c. Unknown → issue new session (fall back to ticket)

Exit flow with LPR:
  1. Camera reads plate
  2. Lookup active session by plate_number
  3. Calculate fee → charge stored payment method
  4. Open gate

Accuracy handling:
  - LPR confidence score < 0.90 → fallback to manual ticket scan
  - Ambiguous characters (0/O, 1/I/l) → phonetic normalization before lookup
  - Multi-angle cameras (front + rear) → vote on best read
  - Edge case: rental cars, plates obscured → operator override kiosk
```

---

### 6.7 Reservation System

```
Reservation flow:
  1. User selects garage, date/time, vehicle type via app
  2. GET /v1/garages/{id}/availability?start=...&end=... → available count
  3. POST /v1/garages/{id}/reservations → tentative hold (5-min lock)
  4. Payment captured → reservation confirmed
  5. Spot soft-locked: removed from available queue for [start-15min, end+15min]

Double-booking prevention:
  - Distributed lock: Redis SET reservation:lock:{garageId}:{spotType}:{slot} NX EX 10
  - Only one reservation write can succeed per (garage, spot_type, time_slot)
  - Slot granularity: 30-minute blocks

Reservation vs walk-in priority:
  - 10% of spots kept as "walk-in buffer" (never reservable)
  - Reservation window opens 24h in advance (configurable per garage)

No-show handling:
  - Spot released if vehicle not detected within 30 min of reservation start
  - Partial refund policy configurable per garage
```

---

### 6.8 Pricing & Rate Limiting

```
Dynamic pricing (surge):
  - If occupancy > 85% → surge multiplier kicks in (1.25×, 1.5×, 2×)
  - Communicated to driver via app before entry
  - Surge state stored in Redis: garage:surge:{garageId} = 1.5
  - Recalculated every 5 minutes by Pricing Service

Operator rate controls:
  - Event pricing (sports game, concert): scheduled rate override with time window
  - Validation codes: merchants issue codes reducing parking fee (malls, hotels)
  - Monthly passes: stored in User DB; checked at entry gate

Rate limiting (API abuse):
  - Availability queries: 60/min per IP (prevent competitor scraping)
  - Reservation creation: 10/min per user (prevent spot hoarding)
  - LPR lookups: authenticated only (gate terminals use service credentials)
```

---

### 6.9 IoT Sensor Integration

Each parking spot optionally has an **ultrasonic or magnetic sensor** for ground truth:

```
Sensor event pipeline:
  Sensor → MQTT broker (Mosquitto / AWS IoT Core)
         → Kafka topic: sensor-events
         → Stream processor (Flink)
         → Reconcile with session state
         → Alert if mismatch (sensor says occupied, DB says available)

Sensor message:
  { garageId, spotId, status: "occupied"|"vacant", timestamp, batteryLevel }

Heartbeat: every 60s (detect dead sensors)

Conflict resolution:
  - Sensor = occupied, DB = available  → alert operator (vehicle without ticket)
  - Sensor = vacant, DB = occupied      → grace period 5 min, then alert (drove away without paying?)
  - Sensor offline > 15 min            → maintenance alert

Floor display boards:
  - Flink aggregates per-floor counts → publishes to display topic
  - LED signs updated in < 3s of state change
```

---

### 6.10 Multi-Tenant Architecture

```
Tenant isolation model:
  - Each garage operator is a tenant (garageOwnerId)
  - Data isolation: all tables partitioned by garage_id
  - No cross-tenant data leakage in queries (row-level security in PostgreSQL)
  - Rate config, branding, pricing rules scoped per tenant

Deployment model:
  - Single shared cluster (cheaper at scale)
  - Dedicated cluster option for enterprise operators (SLA, compliance)
  - Redis namespaced by garageId (keys prefixed with gid:{uuid}:)

Billing:
  - Platform takes % per transaction OR flat SaaS fee
  - Revenue reporting per operator via isolated analytics partition
```

---

### 6.11 Failure Modes & Reliability

| Failure | Impact | Mitigation |
|---------|--------|------------|
| Cloud API down | Gates can't process new tickets | Offline mode: local SQLite + local fee calc |
| Redis down | Spot assignment falls back to DB | PostgreSQL `SELECT FOR UPDATE SKIP LOCKED` as fallback |
| Payment gateway down | Vehicles can't pay at exit | Store payment intent; charge on gateway recovery; allow exit (trust + plate capture) |
| Database primary down | Writes fail | CockroachDB (multi-primary) or PostgreSQL HA with failover < 30s |
| Kafka broker down | Sensor events buffered | MQTT broker retains messages; Kafka RF=3 |
| Gate hardware failure | Physical barrier broken | Gate defaults to OPEN (fail-open policy) — revenue loss preferred over blocking vehicles |
| LPR camera failure | Ticketless entry unavailable | Fallback to physical ticket/QR code automatically |

---

## 7. Data Flow Summary

### Vehicle Entry

```
Vehicle arrives at entry gate
  → Camera reads plate (LPR) OR driver presses button
  → Gate Terminal → API Gateway → Parking Service
  → Spot Availability Service:
      Redis LPOP spots:available:{garageId}:{type}  ← atomic spot claim
  → Write session to PostgreSQL
  → Update Redis: INCR occupancy, DECR available counter
  → Return: ticketId, spotId, QR code
  → Gate opens (barrier lifts)
  → Sensor event published to Kafka (background)
```

### Vehicle Exit

```
Driver scans ticket / plate read at exit gate
  → Gate Terminal → API Gateway → Parking Service
  → Redis GET session:{ticketId}  ← spot + entry time (cache hit)
  → Fee Calculation Engine (rate config JSONB)
  → Payment Service (charge stored/presented payment method)
  → Write session exit: PostgreSQL UPDATE sessions SET exit_time, fee, payment_id
  → Spot Availability Service:
      Redis RPUSH spots:available:{garageId}:{type} {spotId}
      DECR occupancy counter
  → Receipt generated → push notification to driver
  → Gate opens
```

---

## 8. Follow-Up Questions

### Q1: How do you handle a vehicle that doesn't pay and tailgates out?
```
Detection layers:
  1. Sensor: spot shows vacant but session still active → alert after 10 min
  2. LPR at exit: capture plate of every vehicle exiting (matched or not)
  3. Unmatched exit plate → create "drive-off incident" record
  4. Incident logged with plate + timestamp + camera image
  5. Operator can issue invoice by mail (plate → DMV lookup in some jurisdictions)
  6. Repeat offenders → blocklist → gate stays closed, security alert

Gate design: exit lane sensors detect if a second vehicle follows without separate ticket
```

---

### Q2: How do you scale to 1M garages?
```
Bottlenecks:
  1. Redis: 1M garages × ~50 spot queues ≈ 50M Redis keys
     → Shard Redis by garageId (consistent hashing)
     → Each Redis cluster handles ~100K garages
     → 10 Redis clusters total

  2. PostgreSQL: 1M garages × 20M sessions/day = petabyte-scale
     → CockroachDB (geo-distributed, horizontally sharded)
     → Partition sessions table by garage_id range

  3. API layer: stateless, auto-scaled behind ALB; no bottleneck

  4. IoT ingestion: 1M garages × 500 sensors = 500M sensors
     → AWS IoT Core or Azure IoT Hub at that scale
     → Kafka partitions = number of garages (or grouped)

  5. Gate terminals: communicate regionally (data stays in region for latency)
     → GeoDNS routes to nearest regional backend
```

---

### Q3: How would you design a "find me a spot" feature (indoor navigation)?
```
Graph model:
  - Garage floor map stored as graph: nodes = spots/intersections, edges = lanes
  - Edge weights = travel time (estimated)

Spot assignment with proximity:
  - Modify assignment algorithm: instead of any available spot,
    BFS/Dijkstra from entry gate → return nearest available spot
  - Recompute only on entry (not real-time navigation)

Turn-by-turn guidance:
  - AR overlay via phone camera (Apple ARKit / ARCore)
  - Waypoints: Entry gate → Floor elevator → Zone → Spot
  - BLE beacons or UWB anchors for indoor positioning (GPS doesn't work indoors)
  - Alternative: painted floor arrows + LED indicators per spot (simpler, no app required)
```

---

### Q4: How do you handle monthly pass holders?
```
Monthly pass record:
  { userId, garageId, vehicleType, plateNumber, startDate, endDate, passType }

Entry:
  - LPR reads plate → check passes table (indexed on plateNumber + garageId)
  - Valid pass → gate opens immediately, no fee
  - Dedicated spot vs open pool: configurable per pass tier

Pass management:
  - Auto-renew via stored payment method (subscription billing)
  - Pass count capped per garage (don't oversell → waitlist logic)
  - QR code fallback if LPR fails

Capacity planning:
  - Max monthly passes = floor(totalSpots × 1.2) — slight oversell acceptable
    (not all pass holders present simultaneously — similar to airline overbooking)
```

---

### Q5: How do you handle revenue reconciliation across cash, card, and mobile?
```
Payment audit trail:
  sessions.payment_id → payments table:
  { id, session_id, amount, currency, method, gateway_txn_id, status, created_at }

Reconciliation job (nightly):
  1. Query all completed sessions for the day
  2. Match session.fee_cents against payment.amount
  3. Flag discrepancies: orphan payments, sessions with no payment, amount mismatch
  4. Cross-check with payment gateway settlement report (Stripe/Braintree)
  5. Generate operator revenue report (net of platform fee)

Cash handling:
  - Cashier enters collected amount manually → cash_collection event
  - Reconciled against cash sessions for the shift
  - Variance report flagged to manager if > $10 discrepancy
```

---

### Q6: How would you design pricing for an event (e.g., concert at nearby stadium)?
```
Event pricing flow:
  1. Operator creates event rate override via dashboard:
     { startTime, endTime, flatRate: 25.00, preBookOnly: true }

  2. Stored in rate_overrides table (highest priority in fee engine):
     { garageId, name, priority, validFrom, validUntil, rateConfig }

  3. Fee engine checks overrides first (priority order), falls back to standard rate

  4. Advance reservation surge:
     - 70% of spots pre-sold at event rate online
     - 30% reserved for walk-ins at walk-in event rate (higher)
     - Once pre-sold allocation exhausted → mark garage "reservation full" in discovery API

  5. Real-time demand signal:
     - If 90% occupied AND event ongoing → surge multiplier applied to remaining walk-in spots
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Spot assignment | Redis LPOP (atomic queue) | Zero race conditions; microsecond latency vs SQL lock contention |
| Primary database | PostgreSQL / CockroachDB | Strong consistency for sessions; JSONB for flexible rate configs |
| Spot state | Redis Hash + List | Sub-millisecond reads; entire state (5M spots) fits in 250 MB |
| Fee config | JSONB in DB | Operator configures rates without code deployments |
| Gate offline mode | Local SQLite + Edge Agent | Availability > consistency at physical barrier |
| IoT ingestion | MQTT → Kafka → Flink | Decoupled; handles 168K sensor events/sec; replayable |
| LPR | On-device ML + cloud fallback | Low latency; degrades gracefully to ticket-based entry |
| Multi-tenancy | Shared DB, row-level security | Cost-efficient; tenant isolation via PostgreSQL RLS |
| Payment | PCI-scoped vault service | Isolates card data from core app; simplifies compliance scope |
| Reservation lock | Redis SET NX EX | Distributed lock prevents double-booking without DB transaction overhead |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 45–60 minutes.*
