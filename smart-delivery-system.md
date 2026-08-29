# System Design: Smart Delivery System (Walmart)

> Context: A user places an order on Walmart.com / app. The system automatically orchestrates fulfillment — inventory reservation, picking, packing, routing, last-mile dispatch, and real-time delivery tracking — with zero manual intervention.

---

## 1. Functional Requirements

**Core (In Scope)**
- Users can place orders for physical goods (grocery, general merchandise)
- System automatically reserves inventory from the nearest fulfillment center (FC) or store
- System assigns orders to pickers → packers → carriers automatically
- Real-time order tracking: from placement → picked → packed → in transit → delivered
- Support multiple delivery modes: same-day, next-day, scheduled, curbside pickup
- Delivery route optimization for last-mile carriers (human drivers + autonomous vehicles / drones)
- Delivery confirmation (photo, signature, PIN)
- Notifications at each stage (SMS/push)
- Handle failed deliveries (recipient absent, access issues) → reattempt or return flow

**Out of Scope**
- Payment processing (handled upstream by checkout service)
- Returns and reverse logistics (separate system)
- Walmart+ membership / subscription management
- Seller fulfillment (marketplace third-party sellers)

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Availability | 99.99% (< 52 min downtime/year); delivery tracking is customer-facing |
| Order Processing Latency | Inventory reservation and FC assignment < **500ms** p99 |
| Tracking Update Latency | Carrier location visible to customer within **5 seconds** of GPS ping |
| Scale | 10M orders/day peak (Black Friday); 100K concurrent active deliveries |
| Consistency | **Strong** for inventory reservation (no oversell); **eventual** for tracking/status updates |
| Durability | Zero order loss; every state transition persisted before ACK |
| Geo Distribution | US-wide; city-level routing for last-mile; FC data locality |
| Idempotency | All external calls (carrier dispatch, payment capture hooks) idempotent |
| Observability | End-to-end tracing per order; SLA breach alerting per delivery promise |

---

## 3. Back of Envelope Calculation

**Scale Assumptions**
- 10M orders/day → ~116 orders/sec (steady); 500/sec peak (Black Friday)
- Avg 3 items/order → 30M item reservations/day
- 500K active deliveries at peak (order placed but not yet delivered)
- 200K delivery drivers active at peak
- Driver GPS ping every 5s → 200K × 0.2 = **40K location writes/sec**

**Order Storage**
- Order record: ~2 KB
- 10M/day × 2 KB = **20 GB/day** → ~7 TB/year (hot); archive older to cold storage

**Tracking Events**
- 10 state transitions/order × 10M orders = 100M events/day
- Each event: ~500 bytes → **50 GB/day** of tracking events

**Inventory Writes**
- 30M reservation writes/day → **350 writes/sec** average; 1,500/sec peak
- Inventory per SKU record: ~200 bytes; 100M SKUs → **20 GB** base inventory dataset

**Notifications**
- ~5 notifications/order × 10M = 50M notifications/day → **580/sec** average

---

## 4. High-Level Design

```
┌──────────────────────────────────────┐
│           Customer Apps              │
│   (web / iOS / Android / kiosk)      │
└──────────────┬───────────────────────┘
               │ HTTPS / WebSocket
               ▼
┌──────────────────────────────────────┐
│         API Gateway / BFF            │
│   (auth, rate-limit, routing)        │
└──────┬───────────┬────────────┬──────┘
       │           │            │
       ▼           ▼            ▼
┌──────────┐ ┌──────────┐ ┌──────────────┐
│  Order   │ │Inventory │ │  Tracking    │
│ Service  │ │ Service  │ │  Service     │
└────┬─────┘ └────┬─────┘ └──────┬───────┘
     │             │              │
     └──────┬───────┘             │
            ▼                     ▼
    ┌───────────────┐    ┌────────────────────┐
    │  Apache Kafka │    │  Redis (Geo+PubSub) │
    │  (Event Bus)  │    │  + WebSocket GW     │
    └───────┬───────┘    └────────────────────┘
            │
   ┌────────┼──────────────────────────────────┐
   ▼        ▼                ▼                 ▼
┌──────┐ ┌──────────┐ ┌──────────────┐ ┌────────────────┐
│ FC   │ │ Routing  │ │ Dispatch     │ │ Notification   │
│Mgmt  │ │ Service  │ │ Service      │ │ Service        │
│Svc   │ │(OSRM/OR) │ │(driver/drone)│ │(SMS/FCM/APNs)  │
└──┬───┘ └──────────┘ └──────┬───────┘ └────────────────┘
   │                          │
   ▼                          ▼
┌──────────────┐      ┌───────────────────┐
│  PostgreSQL  │      │  Driver/Carrier    │
│  (orders,    │      │  App (WebSocket)   │
│  inventory)  │      └───────────────────┘
└──────────────┘
```

### Core Services

| Service | Responsibility |
|---|---|
| **Order Service** | Accepts orders, orchestrates fulfillment pipeline via events |
| **Inventory Service** | Reserve/release stock, manage FC-level inventory counts |
| **FC Management Service** | Assigns FC/store to order, manages picker/packer queues |
| **Routing Service** | Computes optimal delivery routes (batch + real-time) |
| **Dispatch Service** | Assigns carriers/drones, manages last-mile leg |
| **Tracking Service** | Ingests location pings, publishes updates to customers |
| **Notification Service** | Sends SMS/push at each delivery milestone |
| **SLA Monitor** | Watches delivery promises; escalates at-risk deliveries |

---

## 5. Deep Dive

### 5.1 Order Orchestration — Saga Pattern

Placing an order triggers a distributed transaction across multiple services. We use a **Choreography-based Saga** via Kafka to avoid tight coupling:

```
ORDER_PLACED
    │
    ▼ (Inventory Service listens)
INVENTORY_RESERVED  ──────► (failure) ──► ORDER_CANCELLED + INVENTORY_ROLLBACK
    │
    ▼ (FC Mgmt Service listens)
FC_ASSIGNED (nearest FC with stock)
    │
    ▼ (FC Mgmt assigns picker)
PICK_QUEUED → PICKING_STARTED → PICKED
    │
    ▼
PACK_QUEUED → PACKING_STARTED → PACKED
    │
    ▼ (Dispatch Service listens)
CARRIER_ASSIGNED
    │
    ▼
OUT_FOR_DELIVERY → DELIVERED / FAILED_ATTEMPT
```

Each event is published to Kafka. Services consume and react. Compensating transactions (rollback steps) are pre-defined for each failure case.

**Why Choreography over Orchestration?**
- No central orchestrator SPOF
- Services independently scalable and deployable
- Trade-off: harder to visualize flow → mitigated with distributed tracing (Jaeger)

---

### 5.2 Inventory Reservation (No Oversell)

**Problem:** 1,500 concurrent reservation writes/sec across 100M SKUs. Must be atomic — two customers cannot reserve the last unit of the same item.

**Solution: Optimistic Locking + Redis Atomic Decrement**

**Step 1 — Check availability (Redis, fast path):**
```
GET inventory:<fc_id>:<sku_id>  → available_qty
```

**Step 2 — Reserve (atomic DECRBY, reject if < 0):**
```lua
-- Lua script (atomic in Redis)
local qty = tonumber(redis.call('GET', KEYS[1]))
if qty == nil or qty < tonumber(ARGV[1]) then
  return -1  -- insufficient stock
end
return redis.call('DECRBY', KEYS[1], ARGV[1])
```

**Step 3 — Persist asynchronously to PostgreSQL** via Kafka consumer (write-behind cache)

**Step 4 — Reconciliation job** runs every 60s to sync Redis ↔ PostgreSQL counts

**Oversell Safety Net:**
- If Redis and DB diverge (Redis crash mid-write): PostgreSQL is source of truth
- On Redis cold start: pre-warm from PostgreSQL before accepting traffic
- Hard floor: `available_qty` never goes negative (Lua guard)

```sql
CREATE TABLE inventory (
    fc_id          UUID NOT NULL,
    sku_id         UUID NOT NULL,
    available_qty  INT NOT NULL CHECK (available_qty >= 0),
    reserved_qty   INT NOT NULL DEFAULT 0,
    version        BIGINT NOT NULL DEFAULT 0,  -- optimistic lock
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (fc_id, sku_id)
);
```

---

### 5.3 Fulfillment Center Assignment

**Problem:** Given an order's delivery address, find the best FC to fulfill from.

**Algorithm:**
1. Identify candidate FCs within configurable radius (e.g., 50 miles)
2. Filter: FC has all items in stock (cross-SKU check)
3. Score: `score = α × distance + β × (1 - stock_confidence) + γ × fc_load_factor`
4. Select minimum-score FC; break ties by distance

**FC Load Factor:** ratio of `active_pick_jobs / fc_capacity`
- Prevents hot FC overload during peak (store adjacent to high-demand zip)
- Cached in Redis, updated every 30s

**Split Orders:**
- If no single FC has all items → split order into sub-orders per FC
- Each sub-order has independent tracking; merged view shown to customer
- Trigger only if same-day promise can still be met

---

### 5.4 Picker / Packer Queue (Warehouse Automation)

**Picker Queue**
- Orders arrive in FC Management Service as `PICK_QUEUED` events
- Picker app (handheld scanner) polls or receives pushed job
- Assignment: shortest distance walk path (Traveling Salesman approximation using zone-based greedy)
- Picker scans each item → confirms → `PICKED` event published

**Packing**
- Packer workstation receives `PICKED` event
- System recommends box size (bin packing algorithm: items + dimensions)
- Packer confirms → label printed → `PACKED` event

**Autonomous FC (Walmart's Symbotic robots):**
- Robot retrieval system replaces human pickers
- Orders dispatched to robot controller via FC Management Service API
- Robot status webhooks → translate to same Kafka events (`PICKED`, `PACKED`)

---

### 5.5 Route Optimization (Last-Mile)

**Problem:** 200K drivers, each with 5–20 stops/route. Compute optimal routes in near-real-time as new orders arrive throughout the day.

**Approach: Rolling Horizon Dispatch**

1. **Batch optimization (every 15 min):** Cluster pending orders by geo-cell (H3 hexagons). Run VRP (Vehicle Routing Problem) solver per cluster using Google OR-Tools.
2. **Real-time re-optimization:** As new orders arrive or driver deviates, recalculate affected route segment only (not full re-solve).
3. **Route published** to driver app via WebSocket.

**VRP Constraints:**
- Vehicle capacity (weight + volume)
- Time windows (customer requested delivery slot)
- Driver shift hours
- Traffic-adjusted travel time (HERE / Google Maps Matrix API)

**Drone / Autonomous Vehicle:**
- Drone eligible: order ≤ 5 lbs, delivery point ≤ 10 miles, FAA-approved corridor
- Drone dispatch bypasses human driver queue; goes directly to drone controller API
- Fallback: if drone unavailable → re-queue for driver

---

### 5.6 Real-Time Delivery Tracking

**Driver → Customer location stream:**

```
Driver App (GPS every 5s)
    │
    ▼
Location Service (40K writes/sec)
    │
    ├──► Redis GEO: GEOADD drivers:active <lng> <lat> <driver_id>
    │
    └──► Redis Pub/Sub: PUBLISH delivery:<delivery_id> <lat,lng,eta>
                                │
                        API Gateway (subscribed)
                                │
                         Customer WebSocket
```

**ETA Recalculation:**
- On each GPS ping: recompute ETA using remaining route + current traffic
- If ETA slips > 15 min beyond promise → trigger SLA alert + proactive customer notification

**Delivery Confirmation:**
- Driver app captures: photo (S3), GPS coordinates, timestamp
- If signature required: captured on driver handset → stored in S3
- PIN delivery (contactless): 4-digit PIN sent to customer; driver enters on handset to confirm

---

### 5.7 SLA Monitor — Promise Breach Detection

**Problem:** 10M orders × multiple delivery promise tiers. Detect at-risk deliveries before they breach.

**Approach:**
- On order creation: write promise deadline to `SLA` table
- Scheduled job (every 60s): query deliveries where `estimated_delivery > promised_delivery - 30min` AND status != DELIVERED
- For at-risk orders: escalate → Notification Service (alert customer) + Ops dashboard
- For breached orders: trigger automatic compensation (coupon, refund of delivery fee)

```sql
CREATE TABLE delivery_sla (
    delivery_id       UUID PRIMARY KEY,
    order_id          UUID NOT NULL,
    promised_by       TIMESTAMPTZ NOT NULL,
    current_eta       TIMESTAMPTZ,
    status            VARCHAR(30) NOT NULL,
    breach_alerted_at TIMESTAMPTZ,
    INDEX idx_sla_at_risk (promised_by, status) 
        WHERE status NOT IN ('DELIVERED', 'CANCELLED')
);
```

---

### 5.8 Database Design

**PostgreSQL — Orders**
```sql
CREATE TABLE orders (
    order_id       UUID PRIMARY KEY,
    customer_id    UUID NOT NULL,
    status         VARCHAR(30) NOT NULL,
    delivery_mode  VARCHAR(20) NOT NULL,  -- SAME_DAY, NEXT_DAY, SCHEDULED, CURBSIDE
    delivery_addr  JSONB NOT NULL,
    fc_id          UUID,
    carrier_id     UUID,
    promised_by    TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    version        INT NOT NULL DEFAULT 0
);

CREATE TABLE order_items (
    order_id   UUID REFERENCES orders(order_id),
    sku_id     UUID NOT NULL,
    qty        INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (order_id, sku_id)
);
```

**Cassandra — Tracking Events**
```
CREATE TABLE tracking_events (
    delivery_id  UUID,
    event_time   TIMESTAMP,
    event_type   TEXT,        -- PICKED, PACKED, OUT_FOR_DELIVERY, DELIVERED, etc.
    lat          DOUBLE,
    lng          DOUBLE,
    metadata     TEXT,        -- JSON blob (photo_url, PIN_confirmed, etc.)
    PRIMARY KEY (delivery_id, event_time)
) WITH CLUSTERING ORDER BY (event_time DESC)
  AND default_time_to_live = 7776000;  -- 90 days
```

---

### 5.9 Failure Handling

| Failure Scenario | Mitigation |
|---|---|
| Inventory Service down at order time | Circuit breaker; degrade to async reservation with customer notification |
| FC printer offline (label) | Retry queue with exponential backoff; fallback FC notified |
| Driver app loses connectivity | Buffer GPS pings locally; bulk upload on reconnect |
| Kafka partition lag | Auto-scale consumers; alert if lag > 10K messages |
| Redis GEO node failure | Redis Sentinel failover < 30s; fallback to DB-based geo query |
| Drone fails mid-route | Detect via heartbeat loss; re-queue to human driver automatically |
| Duplicate Kafka events | Idempotency key per event = `(order_id, event_type, version)` |

---

## 6. Trade-offs Discussion

### 6.1 Choreography Saga vs Orchestration Saga

**Problem:** Coordinating distributed transactions across Order, Inventory, FC Management, Dispatch, and Notification services.

| Approach | Pros | Cons |
|----------|------|------|
| **Choreography (Kafka events)** | No SPOF orchestrator; independent service deploy; natural audit trail | Hard to visualize full flow; compensating logic scattered; harder to debug |
| **Orchestration (central workflow engine)** | Central visibility (Temporal, AWS Step Functions); easy rollback logic; linear flow | Orchestrator is a SPOF; tight coupling to workflow DSL; operational overhead |

**Decision: Choreography for steady-state flow + Orchestration for complex compensation**
```
Normal path: Kafka events (choreography)
- ORDER_PLACED → INVENTORY_RESERVED → FC_ASSIGNED → ... → DELIVERED

Complex failure paths: Temporal workflow (orchestration)
- Payment capture fails mid-flight
- FC refuses order after reservation
- Carrier abandons delivery mid-route

Why: Most orders (99%+) follow the happy path — choreography is simpler
and more scalable. Orchestration kicks in only for edge cases where
compensating logic would require 3+ rollback steps.
```

---

### 6.2 Inventory: Redis Cache vs Direct PostgreSQL

**Problem:** 1,500 reservation writes/sec across 100M SKUs — must be atomic with no oversell.

| Approach | Throughput | Latency | Consistency Risk | Complexity |
|----------|-----------|---------|-----------------|------------|
| **Redis Lua (current)** | 100K ops/sec | <5ms | Divergence on crash | Medium (warm-up, sync job) |
| **PostgreSQL SELECT FOR UPDATE** | ~2K ops/sec | 20-50ms | Strongly consistent | Low |
| **Distributed lock (Redlock)** | ~5K ops/sec | 10-20ms | Lock expiry edge cases | High |

**Decision: Redis Lua as fast path with PostgreSQL as source of truth**
```
Trade-off accepted:
- Redis crash can cause reservation count to diverge from PostgreSQL
- Mitigation: 60s reconciliation job + pre-warm on restart
- PostgreSQL hard floor (CHECK constraint) prevents actual oversell
- Acceptable because: P(Redis crash) × P(reconciliation miss) ≈ 0.001%
  Much less harmful than 50ms p99 latency on every reservation
```

---

### 6.3 Real-time Tracking: WebSocket vs Polling vs SSE

**Problem:** Push carrier GPS updates to customer in <5 seconds for 100K concurrent active deliveries.

| Approach | Latency | Server Load | Client Complexity | Firewall Friendly |
|----------|---------|------------|------------------|-------------------|
| **WebSocket (current)** | <1s | Medium (persistent conn) | Medium | Sometimes blocked |
| **Server-Sent Events (SSE)** | <2s | Lower (unidirectional) | Low | Yes |
| **Long Polling** | 5-30s | High (reconnect storm) | Low | Yes |
| **Short Polling (5s)** | 5s | Very High (100K × 12/min) | Lowest | Yes |

**Decision: WebSocket primary, SSE fallback**
```
WebSocket: Used for customers actively viewing tracking page
- ~5% of active deliveries have customer watching live
- 5,000 concurrent WebSocket connections (manageable)

SSE Fallback: For restricted networks (corporate, some mobile carriers)
- Auto-detected via connection negotiation
- 2-3s additional latency acceptable

Short polling: Never — 100K × 12 req/min = 1.2M req/min for tracking alone
```

---

### 6.4 FC Assignment: Nearest FC vs Optimal FC

**Problem:** Assign each order to a fulfillment center that minimizes delivery time while balancing FC load.

| Approach | Accuracy | Latency | Complexity |
|----------|----------|---------|------------|
| **Nearest FC with stock** | 80% optimal | <10ms | Low |
| **Weighted scoring (current)** | 92% optimal | <50ms | Medium |
| **Full LP optimization** | 99% optimal | 500ms–2s | Very High |

**Decision: Weighted scoring with FC load factor**
```
Scoring formula: α(distance) + β(1 - stock_confidence) + γ(fc_load)
Weights tuned offline (A/B tested quarterly)

Trade-off:
- Full LP would solve global optimum but requires knowing all
  pending orders simultaneously (impossible at 500 orders/sec)
- Weighted scoring is greedy per order but fast and 92% optimal
- The 8% gap costs ~$2M/year in sub-optimal routing vs
  ~$10M engineering cost for real-time global LP → not worth it

Exception: During extreme peak (Black Friday), promote load factor
weight (γ) to avoid FC overload at cost of slightly longer routes
```

---

### 6.5 Route Optimization: Batch VRP vs Real-time Greedy

**Problem:** Compute delivery routes for 200K drivers, each with 5-20 stops, with new orders arriving continuously.

| Approach | Route Quality | Compute Cost | Freshness |
|----------|--------------|-------------|-----------|
| **Full VRP re-solve (every 15 min)** | Optimal for batch | High (OR-Tools 15-min windows) | Stale for 15 min |
| **Real-time greedy insertion** | 85% of optimal | Low (O(n²) per order) | Immediate |
| **Hybrid (current)** | 95% of optimal | Medium | 0-15 min lag |

**Decision: Hybrid rolling-horizon dispatch**
```
Batch (every 15 min): Full VRP per geo-cluster via OR-Tools
- Handles 90% of route optimization
- Parallelized by H3 hex cell (100+ clusters simultaneously)

Real-time: Greedy insertion for new orders that arrive mid-route
- New order inserted at cheapest cost position in nearest driver's route
- No full re-solve needed (95% of cases acceptable)

Rollback: If greedy insertion extends driver shift by >30 min → 
         reassign to new driver or defer to next batch cycle

Trade-off: 15 min of stale routing costs ~3% efficiency vs
           real-time full VRP costs 10× compute → batch wins
```

---

### 6.6 Delivery Tracking Storage: Cassandra vs PostgreSQL vs Kafka-only

**Problem:** 100M tracking events/day at 50 GB/day. Queries: "show all events for delivery X" and "show all deliveries for customer Y (last 6 months)."

| Approach | Write Throughput | Query Latency | Operational Cost |
|----------|-----------------|--------------|-----------------|
| **Cassandra (current)** | 1M+ writes/sec | <10ms (by delivery_id) | Medium (tuning needed) |
| **PostgreSQL** | ~10K writes/sec | <50ms | Low |
| **TimescaleDB** | ~100K writes/sec | <20ms | Low-Medium |
| **Kafka + S3 (append-only)** | Unlimited | Minutes (S3 Select) | Low |

**Decision: Cassandra for hot tracking events, S3 Parquet for analytics**
```
Cassandra hot path (90-day TTL):
- Partition key: delivery_id → O(1) lookup by delivery
- Clustering key: event_time DESC → natural sort
- 40K GPS writes/sec well within Cassandra capacity

S3 cold archive (>90 days):
- Parquet files, partitioned by date
- Queried via Athena for analytics/disputes

Trade-off: Cassandra is operationally complex (tuning GC, repairs,
compaction). Alternative (TimescaleDB) would simplify ops but
caps at ~100K writes/sec — adequate now but needs review at 5×
growth (Black Friday 2025 threshold).
```

---

### 6.7 Notification Delivery: Synchronous vs Asynchronous

**Problem:** 580 notifications/sec average, 2,800/sec peak. Delivery must not block the order processing pipeline.

| Approach | Order Pipeline Impact | Delivery Guarantee | Complexity |
|----------|----------------------|-------------------|------------|
| **Synchronous (inline)** | High (SNS latency = order latency) | At-most-once | Low |
| **Kafka + async consumer (current)** | None | At-least-once | Medium |
| **Direct async fire-and-forget** | None | At-most-once | Low |

**Decision: Kafka → Notification Service (async, at-least-once)**
```
Why at-least-once over at-most-once:
- Duplicate "Your order is out for delivery" SMS = acceptable UX cost
- Missing "Your order has been delivered" = unacceptable (customer calls support)

Deduplication on consumer side:
- Track last sent notification_id per (order_id, event_type)
- Idempotent key: sha256(order_id + event_type + delivery_date)
- Suppression window: 60s (won't send same type twice within 1 min)

Trade-off: Duplicates happen <0.1% of time (Kafka retry scenarios).
Customer cost: minor annoyance. Support cost: avoided.
```

---

### 6.8 Consistency Model: Strong vs Eventual

**Summary of consistency decisions across the system:**

| Component | Consistency | Reason |
|-----------|------------|--------|
| Inventory reservation | **Strong** (Redis Lua atomic) | Oversell = direct revenue loss |
| Order status updates | **Eventual** (Kafka async) | 1-2s lag acceptable for UX |
| Driver GPS location | **Eventual** (Redis Pub/Sub) | 5s update cycle by design |
| SLA breach detection | **Eventual** (60s polling job) | False negative costs $5 coupon, not safety-critical |
| Delivery confirmation | **Strong** (synchronous write) | Legal/financial record |
| FC inventory sync | **Eventual** (reconcile 60s) | Short window with Redis floor guard |

**Key insight:** Not all data requires the same consistency guarantee. Applying strong consistency everywhere would require distributed locking, reducing throughput 10-50×. The above table represents deliberate, domain-driven consistency choices — this is the answer interviewers want to hear about trade-offs.

---

## 7. Follow-Up Topics

### Handling Black Friday Scale (10× Spike)
- Pre-scale FC worker pools and Kafka partitions the night before
- Traffic shaping at API Gateway: priority lanes for checkout vs. tracking
- Shed non-critical work: defer SLA breach emails, batch analytics
- Circuit breakers on all downstream calls; fail-open on non-critical services (recommendations)

### Autonomous Delivery (Drones / Robots)
- Drone scheduling = constrained optimization: battery, weather, FAA corridors, package weight
- Fleet management service tracks drone state: `IDLE → DISPATCHED → IN_FLIGHT → LANDED → RETURNING`
- Geo-fencing enforced server-side; drone controller validates before liftoff
- Drone telemetry at 1Hz → same tracking pipeline (Location Service → Redis → WebSocket)

### Multi-Tenant / Marketplace
- Third-party sellers ship from their own warehouses → different carrier APIs
- Adapter pattern: `CarrierAdapter` interface implemented per carrier (FedEx, UPS, OnTrac, Walmart Spark)
- Tracking webhook normalization: carrier-specific events → canonical `TrackingEvent` schema

### Delivery Time Slot Booking
- Slots modeled as inventory: `slot_id → available_capacity`
- Same Redis atomic decrement pattern as product inventory
- Slot windows: 2-hour blocks; displayed in UI with real-time availability

### Privacy & Security
- Customer address encrypted at rest (AES-256); decrypted only within FC Management / Routing services
- Driver app authenticates via mTLS + short-lived JWT
- Delivery photo stored in S3 with pre-signed URL (expires 30 days)
- GDPR/CCPA: customer can request deletion of tracking history

### Observability Stack
| Signal | Tool |
|---|---|
| Distributed Tracing | Jaeger (trace per order_id) |
| Metrics | Prometheus + Grafana |
| Logs | ELK (Elasticsearch + Kibana) |
| Alerting | PagerDuty (SLA breach, inventory sync drift, Kafka lag) |
| Synthetic Monitoring | Canary orders placed every 5 min per region |

---

## Summary

| Component | Technology |
|---|---|
| API Gateway | Envoy / Kong |
| Event Bus | Apache Kafka (partitioned by order_id) |
| Order / Inventory DB | PostgreSQL (sharded by region) |
| Inventory Cache | Redis (Lua atomic scripts) |
| Tracking Events | Apache Cassandra |
| Real-time Location | Redis GEO + Pub/Sub + WebSocket |
| Route Optimization | Google OR-Tools (VRP) + HERE Maps |
| Notifications | AWS SNS → FCM / APNs / Twilio |
| Object Storage | AWS S3 (delivery photos, labels) |
| Monitoring | Prometheus + Grafana + Jaeger |
| Drone Controller | Custom fleet management microservice |
