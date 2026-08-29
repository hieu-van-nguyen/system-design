# System Design: Uber (Ride-Sharing Platform)

---

## 1. Functional Requirements

**Core (In Scope)**
- Riders can request a ride from location A to B
- Drivers can go online/offline and accept/reject ride requests
- Real-time matching of riders to nearby available drivers
- Real-time location tracking of driver en route and during trip
- Dynamic fare estimation (surge pricing)
- Trip lifecycle management: request → matching → pickup → in-progress → completed
- Ride history and receipts for both riders and drivers
- Payment processing (card, wallet)
- Ratings/reviews after trip completion

**Out of Scope**
- Uber Eats, Freight, scheduled rides
- Carpooling (UberPool)
- Driver onboarding / background checks

---

## 2. Non-Functional Requirements

| Requirement        | Target                                      |
|--------------------|---------------------------------------------|
| Availability       | 99.99% (< 52 min/year downtime)             |
| Matching Latency   | Driver matched within **1–3 seconds**       |
| Location Update    | Driver GPS update every **4–5 seconds**     |
| Consistency        | **Eventual** for most; **strong** for payments and driver state |
| Scale              | 100M+ riders, 5M+ drivers globally         |
| Geo Distribution   | Multi-region; city-level data locality      |
| Fault Tolerance    | No single point of failure; graceful degradation |

---

## 3. Back of Envelope Calculation

**Scale Assumptions**
- 100M monthly active riders, 5M active drivers
- Peak: 10M concurrent riders, 500K concurrent drivers
- ~10M trips/day → ~116 trips/second peak

**Location Updates**
- 500K active drivers × 1 update/5s = **100K location writes/sec**
- Each update payload: ~100 bytes → **10 MB/s ingress**

**Matching Requests**
- ~116 ride requests/sec globally
- Each match query scans nearby drivers in a geo-radius → needs sub-100ms lookup

**Storage**
- Trip record: ~1 KB
- 10M trips/day × 1 KB = **10 GB/day** → ~3.5 TB/year
- Driver location: ephemeral (in-memory), not persisted long-term

**Bandwidth**
- Rider app polls or streams driver location during trip (1 update/2s × ~1M active trips) = 500K events/sec outbound

---

## 4. High-Level Design

```
┌──────────────┐     HTTPS/WebSocket     ┌─────────────────────┐
│  Rider App   │ ──────────────────────► │   API Gateway /     │
│  Driver App  │ ◄────────────────────── │   Load Balancer     │
└──────────────┘                         └────────┬────────────┘
                                                  │
              ┌───────────────────────────────────┼──────────────────────────────┐
              │                                   │                              │
              ▼                                   ▼                              ▼
   ┌─────────────────────┐          ┌─────────────────────┐       ┌─────────────────────┐
   │   Ride Service      │          │  Location Service   │       │  Matching Service   │
   │ (trip lifecycle)    │          │  (real-time GPS)    │       │  (driver dispatch)  │
   └────────┬────────────┘          └────────┬────────────┘       └────────┬────────────┘
            │                                │                             │
            ▼                                ▼                             ▼
   ┌─────────────────┐           ┌────────────────────┐       ┌────────────────────────┐
   │  PostgreSQL     │           │  Redis (Geo Index) │       │  Kafka (Event Stream)  │
   │  (trip records) │           │  + Cassandra       │       │                        │
   └─────────────────┘           │  (location history)│       └────────────────────────┘
                                 └────────────────────┘
                                                                          │
                  ┌───────────────────┐          ┌───────────────────────┘
                  ▼                   ▼           ▼
        ┌──────────────────┐  ┌──────────────┐  ┌───────────────────┐
        │  Payment Service │  │ Notification │  │  Surge Pricing    │
        │  (Stripe/Braintree)│  │ Service (FCM)│  │  Service          │
        └──────────────────┘  └──────────────┘  └───────────────────┘
```

### Core Services

| Service | Responsibility |
|---|---|
| **Ride Service** | Trip state machine (REQUESTED → MATCHED → PICKUP → IN_PROGRESS → COMPLETED/CANCELLED) |
| **Location Service** | Ingests driver GPS pings, maintains geo-index |
| **Matching Service** | Finds optimal nearby driver for a ride request |
| **Surge Pricing Service** | Computes demand/supply ratio per geo-cell → multiplier |
| **Payment Service** | Charge rider, pay driver, handle refunds |
| **Notification Service** | Push notifications to driver/rider on state changes |
| **ETA Service** | Computes pickup and drop-off ETA using routing engine (OSRM/Google Maps) |

---

## 5. Trade-Off Discussion

### Trade-Off 1: Driver Location Store — Redis GEO vs. PostGIS vs. H3 Cell Index vs. Elasticsearch

| Approach | Query Latency | Write Throughput | Memory | Update Cost |
|----------|--------------|-----------------|--------|------------|
| **Redis GEO (Recommended)** | < 1ms | ✅ 100K/sec | 100 MB (500K drivers) | O(log N) GEOADD |
| PostGIS (PostgreSQL) | 5–20ms | 10K/sec | Disk | Medium |
| H3 cells in Redis Hash | < 1ms | ✅ | ~50 MB | O(1) HSET |
| Elasticsearch Geo | 10–50ms | 5K/sec | High | Medium |

```
Why this is the most critical data structure decision in the system:

The core query: "find all available drivers within 2km of (lat, lon)" runs on EVERY
ride request (116/sec) and EVERY driver location update (100K/sec).
Sub-100ms latency required for matching to complete within 1–3 seconds.

Redis GEO internals (why it works):
  GEOADD: encodes (lat, lon) as a 52-bit geohash → stored as ZSET score
  GEORADIUS: ZRANGEBYSCORE on geohash prefix ranges → O(N + log M) where N = results
  For 500K drivers in one city cluster: index fits entirely in RAM (~100 MB)
  100K GEOADD/sec on a single Redis node: well within capacity (Redis is single-threaded
  but at O(log N) per write → 500K-node ZSET → 19 comparisons per write → trivial)

TTL-based expiry (the elegant driver offline detection):
  No explicit "driver went offline" delete needed
  Each GEOADD refreshes the TTL on the driver's entry
  30s TTL: if no ping in 30s → driver auto-removed from geo index
  No separate cleanup process; Redis handles this natively

City-level sharding:
  One Redis cluster per city: GEOADD drivers:online:chicago ...
  Driver in NYC never appears in Chicago's index → queries stay O(city_drivers) not O(global)
  At 500K global drivers with 100 cities: avg 5K drivers per city → very fast scans

PostGIS (rejected for hot path):
  Excellent for: "find all restaurants within 1km" (low write rate)
  At 100K writes/sec: PostgreSQL connection pool saturation
  Each GEOADD equivalent: UPDATE SET location=... WHERE driver_id=... → 5–20ms
  At 100K writes/sec: 100K × 20ms = 2,000 CPU-seconds/sec → thousands of cores
  Use PostGIS for: driver trip history queries, zone boundary calculations (offline)

H3 cell index (complementary, not replacement):
  S2/H3 cells: divide earth into hexagons at multiple resolutions
  Cell at resolution 8 ≈ 0.74 km² — bin drivers into cells
  Lookup: "drivers in cell X and neighbors" → O(1) HGET
  Advantage over GEORADIUS: exact ring coverage (6 equidistant neighbors, not circular)
  Use H3 for: surge pricing zones, demand heatmaps — not for exact geo queries

Decision: Redis GEO for the matching hot path (sub-ms, in-memory, natural TTL).
  H3 cells for surge pricing computation (batch, coarser granularity, equidistant neighbors).
  PostGIS for offline analytics, zone boundary management.
```

---

### Trade-Off 2: Driver Dispatch Model — Sequential Offer vs. Broadcast Fanout vs. Batched Auction

| Model | Driver Experience | Matching Speed | System Complexity | Used By |
|-------|-----------------|---------------|------------------|---------|
| **Sequential offer (Recommended)** | ✅ Clear, unambiguous | Slower (per-driver timeout) | Low | Uber |
| Broadcast fanout | ❌ Racing, confusion | Fast | Medium | Early Lyft |
| Batched auction | ✅ Optimal matching | Fast | High | Research |

```
The dispatch model determines how the system offers a ride to drivers:

Sequential offer (Uber's actual model):
  1. Score all nearby available drivers → rank by (ETA + acceptance_rate + rating)
  2. Offer to #1: "You have a ride. Accept within 15 seconds."
  3. If rejected or timeout: offer to #2, then #3, ...
  4. If no driver accepts after K candidates: expand search radius → repeat

  Driver UX: driver sees one offer at a time → clear decision, no racing
  Rider experience: may wait 30–60s if top drivers reject (low supply scenario)
  Double-booking prevention: driver locked via Redis SET NX during offer window
    SET driver:{id}:status OFFERED EX 15 NX
    → Only one ride can hold a driver at a time (atomic Redis operation)

  Search radius expansion strategy:
    Round 1: 1km radius, top 3 drivers, 15s each → 45s max
    Round 2: 3km radius, next 3 drivers → +45s
    Round 3: 5km radius, broadcast to all → last resort
    Total maximum matching time: ~2-3 min before "no drivers available"

Broadcast fanout (early Lyft / experimental):
  Broadcast ride request to all nearby drivers simultaneously
  First driver to accept gets the ride
  Race condition: multiple drivers see "Accept" button → one wins, others frustrated
  Driver UX: drivers spam Accept → waste time on rides they don't get
  At scale: thundering herd of driver app updates for every request
  Abandoned: driver retention suffers when false "available ride" notifications increase

Batched auction (academic optimal, complex in practice):
  Collect all ride requests in a 5-second batch window
  Solve bipartite matching problem (Hungarian algorithm) → globally optimal assignment
  Minimizes total passenger wait time across all simultaneous requests

  Problem 1: 5-second delay before matching starts → violates 1–3s SLO
  Problem 2: Hungarian algorithm O(N³) at scale (10K simultaneous requests in a city → impractical)
  Problem 3: drivers assigned without offering → poor driver autonomy (regulatory issues in some markets)

Decision: Sequential offer. The sequential model with Redis NX lock is the correct answer.
  The key insight: driver UX and double-booking prevention are more important than
  theoretical optimality. Mention the radius expansion strategy as the mechanism
  for handling low-supply scenarios.
```

---

### Trade-Off 3: Real-Time Location Delivery to Riders — WebSocket vs. SSE vs. Polling vs. MQTT

| Protocol | Rider App | Battery Impact | Server State | Fallback |
|----------|----------|---------------|-------------|---------|
| **WebSocket (Recommended)** | ✅ Full-duplex | Medium | Stateful connections | SSE / long-poll |
| SSE (Server-Sent Events) | ✅ One-way push | Low | Stateful connections | Long-poll |
| Long-polling | Works everywhere | High | Stateless | Always available |
| MQTT | ✅ Lightweight | Low | Broker needed | N/A |

```
Two different location delivery problems with different requirements:

Driver → Server (GPS pings, 100K writes/sec):
  Driver app sends location every 4–5s
  Requirement: reliable delivery; driver goes offline gracefully
  MQTT (QoS 1): persistent connection, at-least-once delivery, lightweight heartbeat
    Perfect for IoT-style GPS devices: low battery impact, handles reconnection
  HTTP POST fallback: simpler, works everywhere, but 33K connection setups/sec at 100K writes/sec
  Recommendation: MQTT for native apps; HTTPS POST for web/fallback

Server → Rider (driver position during trip, 500K events/sec outbound):
  Rider app needs driver's position to update the map every 2 seconds
  Requirement: low latency, works on mobile (intermittent connectivity), low battery

  WebSocket:
    Full-duplex: rider can send messages (ETA requests, trip updates) while receiving location
    One connection per active trip → 1M open WebSocket connections at peak
    Server memory: ~10KB per connection → 10 GB for 1M trips (manageable)
    Problem: WebSocket connections are stateful → sticky routing or connection registry needed
    API Gateway: knows which pod handles which WebSocket → Redis connection registry
      Key: ws:trip:{tripId} → pod_address
      On driver location update → lookup pod → forward to that pod → fan out to rider WebSocket

  SSE (Server-Sent Events):
    HTTP/2 one-way stream: server pushes events; rider can't send via same connection
    Lower latency than polling; works through HTTP proxies that might block WebSocket
    Less battery drain than WebSocket (half-duplex)
    Simpler reconnect: browser/OS auto-reconnects on disconnect (HTTP standard)
    Good fit for: rider-side (mostly receiving, occasionally sending via REST)

  Long-polling:
    Rider sends GET /location/{tripId} → server holds until new location available → returns
    Zero server state: no persistent connection
    Battery cost: constant request-response cycle → high battery on mobile
    Use only as last-resort fallback when WebSocket and SSE fail

Redis Pub/Sub for fan-out:
  Location Service publishes: PUBLISH location:trip:{tripId} {lat,lng,ts}
  API Gateway pod (holding rider WebSocket) subscribes to that channel
  Driver location → Location Service → Redis Pub/Sub → correct pod → rider WebSocket
  Decouples location ingestion from delivery → horizontal scale of both independently

Decision: Driver uses MQTT (reliable, battery-efficient IoT protocol).
  Rider uses WebSocket + Redis Pub/Sub for fan-out.
  SSE as fallback for environments where WebSocket is blocked.
  The Redis connection registry (tripId → pod) is the key coordination mechanism.
```

---

### Trade-Off 4: Trip State Consistency — Optimistic Locking vs. Saga vs. Event Sourcing

| Approach | Consistency | Complexity | Failure Recovery | Audit Trail |
|----------|------------|-----------|-----------------|------------|
| **Optimistic locking + Kafka events (Recommended)** | ✅ Strong for state | Low | Manual compensation | Kafka log |
| Distributed Saga (Choreography) | Eventual | Medium | Automatic compensation | Event log |
| Event Sourcing (full) | ✅ | High | Replay from log | ✅ Complete |
| 2PC (distributed transaction) | ✅ Exact | Very high | Coordinator SPOF | N/A |

```
The trip state machine problem: multiple services (Ride, Payment, Notification) must
agree on trip state, but they run on different machines with different DBs.

Optimistic locking in PostgreSQL (for trip state):
  trips table has version column (integer)
  State transition: UPDATE trips SET status='MATCHED', driver_id=$1, version=version+1
                    WHERE trip_id=$2 AND status='REQUESTED' AND version=$3
  If 0 rows updated: someone else transitioned this trip already → abort + retry
  No distributed lock needed: PostgreSQL row-level lock during single UPDATE statement

  What this prevents: two Matching Service nodes simultaneously matching the same trip
  to different drivers. Only one UPDATE succeeds (version check fails for the second).

Kafka for event fan-out (not for state management):
  After successful PostgreSQL state transition:
    Publish to Kafka: trip-events { tripId, status: MATCHED, driverId, riderId, ... }
  Notification Service, Payment Service, Analytics consume from Kafka (eventual)
  These consumers are idempotent: processing the same event twice = same result

  The PostgreSQL UPDATE is the source of truth.
  Kafka events are downstream fan-out (eventual consistency is fine for notifications).

What about dual-write between PostgreSQL and Kafka?
  Use outbox pattern:
    INSERT INTO trip_outbox (trip_id, event, published=false) in SAME PostgreSQL transaction
    Separate relay process: reads unpublished, publishes to Kafka, marks published=true
  → Atomicity guaranteed within PostgreSQL; Kafka delivery eventually guaranteed

Saga pattern (for payment compensation):
  Trip completes → charge rider (step 1) → pay driver (step 2)
  If step 2 fails: compensating transaction (refund rider's charge)
  Choreography: services react to each other's Kafka events
  Issue: compensation logic spread across services → hard to reason about

  For Uber: payment failure after trip = simpler to handle synchronously or via retry
  Full saga pattern is over-engineering for payment at Uber's scale (idempotency key = tripId)

Event sourcing (full, rebuilds state from events):
  Every state transition = immutable event appended to log
  Current state = replay of all events from the beginning
  Advantage: perfect audit trail, time-travel debugging, replay for bug fixes
  Disadvantage: complex; query current state requires replaying events (or maintaining snapshot)
  Uber doesn't use full event sourcing for trips (too complex, replay latency for current state)

Decision: PostgreSQL optimistic locking for trip state (strong, simple, no coordinator).
  Kafka + outbox pattern for event fan-out to downstream services.
  The combination gives you strong consistency where it matters (state machine) and
  eventual consistency where it's acceptable (notifications, analytics).
```

---

### Trade-Off 5: Geo Sharding — City-Level vs. Consistent Hash vs. Geohash Range

| Strategy | Data Locality | Rebalancing | Cross-Shard Queries | Regulatory |
|----------|--------------|------------|--------------------|-----------| 
| **City-level sharding (Recommended)** | ✅ Excellent | Manual | Rare (cross-city trips rare) | ✅ |
| Consistent hash (by rider_id/driver_id) | ❌ Global scatter | Automatic | Always needed | ❌ |
| Geohash range sharding | ✅ Good | Complex | Edge-of-boundary | ✅ |

```
Why sharding strategy matters specifically for ride-sharing:

The fundamental insight: a ride NEVER crosses city/region boundaries.
  A driver in NYC will never pick up a rider in London.
  All matching, location tracking, and surge pricing are city-local operations.
  → City is the natural shard boundary. No cross-shard queries for the hot path.

City-level sharding (Uber's actual architecture):
  Each city (or metro area) has:
    Own Redis cluster (driver locations, surge pricing cells)
    Own PostgreSQL cluster (trip records, driver state)
    Own Kafka cluster (trip events)
    Own Cassandra cluster (location history)
  API gateway: routes requests based on rider/driver city (from GPS or account city)

  Advantages:
    Zero cross-shard queries for all real-time operations
    City data sovereignty: GDPR (EU), data residency (India) requirements satisfied
    Independent scaling: NYC cluster can grow without affecting Lagos cluster
    Failure isolation: NYC cluster down → London unaffected (bulkhead pattern)

  Disadvantages:
    Airport edge case: driver crosses city boundary → must transfer to new city's cluster
    Operational overhead: 100+ city clusters to manage
    Driver moves cities permanently: re-registration in new city's cluster

Consistent hashing (by rider_id):
  Hash(rider_id) → shard (0–255)
  A rider's trip could be handled by any shard → driver in same city may be on different shard
  Every matching query: must query ALL shards that have drivers near the rider
  → Fan-out every request to all shards → latency multiplied, network cost multiplied
  → This approach forces cross-shard queries for every single ride request
  Completely wrong for a location-based service where physical proximity defines the shard boundary

Geohash range sharding:
  Divide earth into geohash prefixes (first 4 characters): "dr5r" = lower Manhattan
  All data for that geographic area on one shard
  Issue: city boundaries don't align with geohash boundaries
  Airport between two geohash areas → request must query both shards
  Border areas: 10% of requests may require two-shard queries
  More complex than city-level but more flexible for variable city sizes

Decision: City-level sharding. The natural shard boundary is the same as the
  operational boundary (a ride is a city-local event).
  Mention the airport/city-boundary edge case — it's the interesting failure mode.
  "Driver crosses city boundary → transfer to destination city's cluster."
```

---

### Trade-Off 6: Surge Pricing Geo-Cell Shape — H3 Hexagons vs. Fixed Grid vs. Dynamic Zones

| Cell Shape | Coverage Uniformity | Hierarchy | Implementation | Boundary Artifacts |
|-----------|--------------------|-----------|---------|--------------------|
| Fixed grid (lat/lon rectangles) | ❌ Distorts at poles | ✅ Simple | Very low | High |
| **H3 Hexagons (Recommended)** | ✅ Equidistant neighbors | ✅ Multiple resolutions | Medium | Low |
| S2 cells (Google) | ✅ Good | ✅ Multiple levels | Medium | Low |
| Dynamic zones (ML-defined) | ✅ Best fit | ❌ Variable | High | Low |

```
Why cell shape affects surge pricing accuracy:

Fixed grid (lat/lon rectangles):
  1° lat ≈ 111 km (constant), but 1° lon = 111 km × cos(lat)
  At NYC (40.7°N): 1° lon ≈ 85 km
  At Seattle (47.6°N): 1° lon ≈ 75 km
  Same grid cell size → wildly different physical areas → inconsistent pricing zones
  Edge case: cells in Alaska are tiny (dense pricing zones), cells near equator are huge (coarse pricing)
  Acceptable only for: equatorial cities where distortion is minimal

H3 hexagons (Uber's production choice):
  Hierarchical hexagonal grid at 16 resolutions (0 = global, 15 = ~1 m²)
  Resolution 8: avg area 0.74 km² ≈ 900 m diameter → ideal for surge pricing zones
  All 6 neighbors of a hexagon are equidistant → isotropic demand spreading
  Rectangle: 4 orthogonal + 4 diagonal neighbors at different distances → anisotropic

  Hierarchical aggregation:
    Resolution 10 → Resolution 8 → Resolution 6 (zoom out automatically)
    If a fine-grain cell has too few drivers (< 3): roll up to parent cell
    → Surge pricing granularity adapts to data density

  H3 computation:
    h3 = H3.fromLatLon(lat, lon, resolution=8)  # O(1)
    neighbors = H3.kRing(h3, 1)                 # 6 neighbors + center = 7 cells
    parent = H3.toParent(h3, resolution=6)       # roll up

  Stored in Redis: HSET surge:cells:{h3_id} multiplier {value} EX 60
  Rider's surge: HGET surge:cells:{h3_id of pickup location}

S2 cells (Google's approach):
  Quadrilateral cells projected from cube face → more uniform than lat/lon grid
  S2 cells at level 12 ≈ 3.7 km² (larger than H3 resolution 8)
  Used in Cassandra (compact cell ID as partition key)
  H3 and S2 solve the same problem; H3 has better hexagonal neighbor equidistance

Dynamic zones (ML-defined surge areas):
  Airport, stadium, downtown: surge boundaries follow physical/demand features
  ML model: learn optimal zone boundaries from historical demand patterns
  Advantage: zones match real demand geography (not arbitrary hexagon grid)
  Disadvantage: zones change over time → hard to cache, explain to users, or display on map
  Experimented with by Uber but not production for driver-facing surge display

Decision: H3 hexagons at resolution 8 for surge pricing. Resolution 10 for heatmaps.
  Use parent resolution when cell has < 5 drivers (insufficient data for pricing).
  Hexagons' equidistant neighbors are the key property — drivers at any direction
  from the cell center have equal influence on the zone's supply count.
```

---

### Trade-Off 7: Driver State Management — Redis vs. PostgreSQL vs. In-Memory Service

| Approach | Latency | Durability | Concurrent Access | Failure Recovery |
|----------|---------|-----------|------------------|-----------------|
| **Redis (Recommended for hot state)** | < 1ms | Replication only | ✅ Atomic ops | Brief state loss |
| PostgreSQL (source of truth) | 5–20ms | ✅ WAL | ✅ MVCC | ✅ Full recovery |
| **Redis + PostgreSQL (Recommended)** | < 1ms reads | ✅ | ✅ | ✅ |
| In-memory (single service) | Fastest | ❌ | ❌ No distribution | Restart = lost |

```
Driver state has two tiers with different requirements:

Hot state (millisecond decisions during matching):
  Is this driver available right now?
  Is this driver already offered a ride?
  What is this driver's current location?

  These are read and written on every request (100K writes/sec for location alone).
  PostgreSQL at this rate: cannot sustain (connection pool, WAL write, MVCC overhead).

  Redis (for hot state):
    HSET driver:{id} status AVAILABLE city chicago vehicle_type UberX
    SET driver:{id}:status OFFERED EX 15 NX  // atomic offer lock
    GEOADD drivers:online:{city} {lng} {lat} {driver_id}  // location

    Atomicity: Redis SET NX is the distributed lock for offer state
    TTL: offer expires in 15s automatically → no cleanup process needed
    Cluster: 20 Redis nodes × 100K ops/sec each = 2M ops/sec capacity for driver state

Cold state (audit, history, compliance):
  Driver's trip history, acceptance rate, total earnings, ratings
  Written once per trip (rare) but queried by analytics
  Read latency: seconds acceptable (dashboard, reports)
  → PostgreSQL is correct here (SQL queries, joins, aggregations)

Consistency between Redis and PostgreSQL:
  Trip completion → UPDATE PostgreSQL (authoritative) → async update Redis
  Redis driver state can lag PostgreSQL by seconds during failures
  Recovery: on Redis restart → rebuild hot state from PostgreSQL:
    SELECT driver_id, status, city FROM drivers WHERE status != 'OFFLINE'
    Rebuild Redis from DB → drivers momentarily not matchable → small matching lag

The critical invariant: driver can only accept one ride at a time.
  This is enforced by Redis SET NX (atomic "claim" on the driver's offer slot).
  PostgreSQL is the backup source of truth — but Redis is the gatekeeper for matching.

  If Redis fails: matching pauses (fail safe — better than double-booking a driver).
  If PostgreSQL fails: trips can still be matched (Redis has current state); trip records
  buffered in Kafka outbox → written to PostgreSQL when it recovers.

Decision: Redis for hot driver state (latency-critical matching path).
  PostgreSQL for durable trip records and driver history (audit, reports).
  The two-tier architecture with explicit roles is the production answer.
  Redis failure = matching pauses (correct behavior — no double-booking risk).
```

---

## 6. Deep Dive

### 6.1 Driver Location Service

**Problem:** 500K drivers sending GPS pings every 5s = 100K writes/sec. Must support fast geo-queries ("find all drivers within 2km of point P").

**Solution: Redis GEO**
- `GEOADD drivers:online <lng> <lat> <driver_id>` on each ping
- `GEORADIUS drivers:online <lng> <lat> 2 km` to find nearby drivers
- TTL-based expiry: if no ping for 30s → driver removed from index (went offline)
- Redis cluster sharded by city/region

**Location History (for dispute resolution)**
- Async write to Cassandra via Kafka consumer
- Partition key: `driver_id`, clustering key: `timestamp`
- Retained 30 days; cold storage to S3 after

```
Driver App → WebSocket/HTTP → Location Service → Redis GEO (hot)
                                               ↘ Kafka → Cassandra (history)
```

---

### 6.2 Matching Service (Dispatch Algorithm)

**Problem:** Match rider to best available driver quickly, avoid double-booking.

**Algorithm**
1. Query Redis GEO for available drivers within search radius (start 1km, expand to 3km/5km if none found)
2. Filter by: driver status = `AVAILABLE`, vehicle type matches rider request
3. Score candidates: `score = f(ETA, acceptance_rate, driver_rating)`
4. Offer ride to top-scored driver via push notification (10s accept window)
5. If rejected/timeout → offer to next candidate
6. Lock driver atomically: `SET driver:<id>:status OFFERED EX 10 NX` (Redis SET NX)

**Preventing Double-Booking**
- Driver state transitions are protected by Redis distributed lock
- State machine enforced in Ride Service (PostgreSQL row-level lock on trip creation)

**Dispatch Fanout vs. Sequential Offer**
- Uber uses **sequential offer** (not fanout) to avoid driver confusion
- Configurable per market based on supply density

---

### 6.3 Trip State Machine

```
REQUESTED
    │
    ▼ (match found)
MATCHED ──────────────────────────────┐
    │                                  │ (driver cancels / timeout)
    ▼ (driver arrives)                 ▼
DRIVER_ARRIVED                    CANCELLED
    │
    ▼ (rider boards)
IN_PROGRESS
    │
    ▼ (drop-off confirmed)
COMPLETED
```

- State stored in PostgreSQL with optimistic locking (`version` column)
- Events published to Kafka on each transition → consumed by Notification, Payment, Analytics services

---

### 6.4 Surge Pricing

**Problem:** Balance supply/demand in real-time per geographic zone.

**Approach**
- Divide city into **H3 hexagonal geo-cells** (Uber's actual approach)
- Every 60s: count `active_requests / available_drivers` per cell
- Surge multiplier = function of ratio (e.g., ratio > 2.0 → 1.5x, > 3.0 → 2.0x, capped at ~8x)
- Stored in Redis with 60s TTL
- Rider sees surge estimate at request time; locked in at trip creation

```python
def surge_multiplier(demand: int, supply: int) -> float:
    if supply == 0:
        return MAX_MULTIPLIER
    ratio = demand / supply
    if ratio < 1.2:
        return 1.0
    elif ratio < 2.0:
        return 1.0 + (ratio - 1.0) * 0.5
    else:
        return min(1.5 + (ratio - 2.0) * 0.3, MAX_MULTIPLIER)
```

---

### 6.5 Real-Time Location Tracking (Rider View)

**Problem:** Rider needs to see driver's live position. 1M active trips × 1 update/2s = 500K events/sec outbound.

**Solution: WebSocket + Pub/Sub**
- Rider app opens WebSocket to API Gateway
- Location Service publishes driver location updates to **Redis Pub/Sub** channel `location:<trip_id>`
- API Gateway subscribes and fans out to connected rider WebSocket
- Driver app sends location → Location Service → Redis Pub/Sub → Rider app
- Fallback: long-polling for clients that drop WebSocket

```
Driver → Location Service → Redis Pub/Sub (channel: location:trip_123)
                                    │
                            API Gateway (subscribed)
                                    │
                              Rider WebSocket
```

---

### 6.6 Database Design

**PostgreSQL (Trips)**
```sql
CREATE TABLE trips (
    trip_id       UUID PRIMARY KEY,
    rider_id      UUID NOT NULL,
    driver_id     UUID,
    status        VARCHAR(20) NOT NULL,  -- REQUESTED, MATCHED, IN_PROGRESS, COMPLETED, CANCELLED
    pickup_lat    DECIMAL(10, 7),
    pickup_lng    DECIMAL(10, 7),
    dropoff_lat   DECIMAL(10, 7),
    dropoff_lng   DECIMAL(10, 7),
    fare_estimate DECIMAL(10, 2),
    fare_final    DECIMAL(10, 2),
    surge_multiplier DECIMAL(4, 2) DEFAULT 1.0,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    version       INT NOT NULL DEFAULT 0  -- optimistic locking
);

CREATE INDEX idx_trips_rider    ON trips(rider_id, created_at DESC);
CREATE INDEX idx_trips_driver   ON trips(driver_id, created_at DESC);
CREATE INDEX idx_trips_status   ON trips(status) WHERE status IN ('REQUESTED', 'MATCHED', 'IN_PROGRESS');
```

**Cassandra (Location History)**
```
CREATE TABLE driver_locations (
    driver_id  UUID,
    ts         TIMESTAMP,
    lat        DOUBLE,
    lng        DOUBLE,
    trip_id    UUID,
    PRIMARY KEY (driver_id, ts)
) WITH CLUSTERING ORDER BY (ts DESC)
  AND default_time_to_live = 2592000;  -- 30 days
```

---

### 6.7 Payments

- Rider's payment method tokenized and stored with Stripe/Braintree (never raw card data)
- Fare computed at trip end: `base_fare + (per_minute × duration) + (per_km × distance) × surge`
- Charge initiated by Payment Service on `COMPLETED` event from Kafka
- Driver payout batched daily (ACH/bank transfer)
- Idempotency key = `trip_id` to prevent double charges

---

### 6.8 Data Partitioning & Multi-Region

- Shard by **city/region** — city-level data sovereignty and lower latency
- Each city cluster: independent Redis, Postgres primary + replicas, Cassandra ring
- Global services (auth, payments) centralized with cross-region replication
- Ride requests never cross region (no driver in NYC picks up rider in London)

---

## 7. Follow-Up Topics

### Handling Driver Goes Offline Mid-Trip
- Heartbeat check every 10s from driver app
- If missed 3 consecutive: mark driver as `DISCONNECTED`, alert rider
- If trip `IN_PROGRESS`: hold state, attempt reconnection for 60s before alerting support
- Location history fills gaps via dead-reckoning or last known position

### ETA Accuracy
- Use historical traffic data + real-time traffic (Google Maps Platform / HERE)
- ML model trained on actual trip data per time-of-day, day-of-week, weather
- Continuously recalculate ETA as driver deviates from optimal route

### Preventing Fraud
- GPS spoofing detection: compare reported location vs cell tower / IP geolocation
- Fare fraud: server-side fare calculation (never trust client)
- Rate limiting on ride requests per rider/driver account

### Scalability Bottlenecks
| Bottleneck | Mitigation |
|---|---|
| Redis GEO under write storm | Shard by city; use Redis Cluster |
| Kafka consumer lag | Scale consumer group; increase partitions by city |
| Postgres trip table hot rows | Read replicas for history; archive completed trips to cold storage |
| Matching service CPU | Stateless + horizontal scaling behind load balancer |

### Observability
- Distributed tracing (Jaeger/Zipkin) for request flows across services
- Key metrics: match latency P99, driver GPS lag, payment success rate, trip completion rate
- Alerting: match rate drops > 10% in a city → paging, potential supply issue

### Alternative: Event-Driven State Machine
- Replace synchronous Ride Service calls with event sourcing
- Each state transition is an immutable event appended to Kafka
- Ride Service rebuilds state from event log → audit trail, replay, easier debugging
- Trade-off: eventual consistency requires careful idempotent consumers

---

## 8. Summary

| Component | Technology |
|---|---|
| API Gateway | Nginx / Envoy |
| Driver Location | Redis GEO + Cassandra |
| Real-time updates | WebSocket + Redis Pub/Sub |
| Matching | In-memory computation + Redis distributed lock |
| Trip storage | PostgreSQL (sharded by city) |
| Event streaming | Apache Kafka |
| Geo indexing | H3 hexagonal cells + Redis GEORADIUS |
| Payments | Stripe / Braintree |
| Notifications | FCM (Android) / APNs (iOS) |
| Routing / ETA | OSRM / Google Maps Platform |
| Monitoring | Prometheus + Grafana + Jaeger |
