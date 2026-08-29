# System Design: Amazon E-Commerce Platform

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard

---

## Table of Contents

1. [Clarifying Questions](#1-clarifying-questions)
2. [Functional Requirements](#2-functional-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Back-of-Envelope Estimation](#4-back-of-envelope-estimation)
5. [High-Level Design](#5-high-level-design)
6. [Deep Dive](#6-deep-dive)
   - 6.1 Product Catalog & Search
   - 6.2 Cart & Session Management
   - 6.3 Checkout & Order Pipeline
   - 6.4 Payment Processing
   - 6.5 Inventory Management
   - 6.6 Recommendation Engine
   - 6.7 Notification System
7. [Trade-offs Discussion](#7-trade-offs-discussion)
8. [Data Models](#8-data-models)
9. [Follow-Up Questions](#9-follow-up-questions)
10. [Interview Summary Card](#10-interview-summary-card)

---

## 1. Clarifying Questions

Before drawing anything, ask the interviewer:

```
"Are we designing the full Amazon platform or a specific component?"
→ Full e-commerce platform (catalog, cart, checkout, order, payment)

"What's the geographic scope?"
→ Global — US, EU, APAC

"Do we need third-party seller (Marketplace) support?"
→ Yes — both 1P (Amazon direct) and 3P (Marketplace) sellers

"Do we need recommendations / personalization?"
→ Yes, but can be simplified

"Should I include AWS infrastructure details?"
→ Design technology-agnostic; mention AWS where it adds insight

"Prime membership / streaming / AWS?"
→ Out of scope — focus on core e-commerce
```

---

## 2. Functional Requirements

### Core (Must Have)

| # | Requirement | Notes |
|---|-------------|-------|
| FR-1 | **Browse & Search** | Search by keyword, filter by category/price/rating/Prime-eligible |
| FR-2 | **Product Detail Page** | Title, description, images, price, availability, reviews, seller info |
| FR-3 | **Shopping Cart** | Add/remove/update items; persist across sessions and devices |
| FR-4 | **Checkout** | Address selection, shipping method, coupon/gift card, order summary |
| FR-5 | **Payment** | Credit card, debit card, Amazon Pay, gift cards, COD |
| FR-6 | **Order Management** | Place order, view orders, track shipment, cancel/return |
| FR-7 | **Inventory** | Real-time stock check; prevent overselling |
| FR-8 | **User Accounts** | Register, login, address book, payment methods, order history |

### Secondary (Should Have)

| # | Requirement |
|---|-------------|
| FR-9 | Product recommendations (collaborative filtering + "customers also bought") |
| FR-10 | Reviews & ratings (write review, helpful votes, verified purchase badge) |
| FR-11 | Wishlist / Save for Later |
| FR-12 | Notifications (order confirmation, shipping update, delivery) via email + push |
| FR-13 | Seller dashboard (inventory upload, order fulfillment, analytics) |

### Out of Scope

- AWS cloud services, Prime Video, Alexa, Kindle
- Logistics & warehouse management (handled by separate fulfillment system)
- Fraud detection (treated as a downstream service)
- A/B testing platform

---

## 3. Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| **Availability** | 99.99% (52 min/year downtime) | Every minute of downtime ≈ $220K lost revenue |
| **Read latency** | Product page p99 < 200ms globally | Amazon research: 100ms latency = 1% revenue loss |
| **Write latency** | Checkout p99 < 500ms | Acceptable for transactional flows |
| **Throughput** | 1M orders/day; 500M page views/day | Black Friday: 5–10× peak |
| **Consistency** | Strong for inventory/payment; eventual for catalog/recommendations | Overselling = catastrophic; stale search results = tolerable |
| **Durability** | Zero order loss, zero payment loss | ACID transactions for financial data |
| **Scalability** | Horizontal scaling at every tier | Black Friday traffic spikes need elastic scale |
| **Search** | Sub-200ms full-text search with faceting | Elasticsearch / OpenSearch at scale |

---

## 4. Back-of-Envelope Estimation

### Traffic

```
Daily Active Users (DAU):   300 million
Page views/day:             500 million (avg 1.7 views/user)
Searches/day:               200 million
Orders/day:                 1 million
Peak multiplier (Black Friday): 10×

QPS (average):
  Product page reads:     500M / 86,400 ≈ 5,800 reads/sec
  Search queries:         200M / 86,400 ≈ 2,300 queries/sec
  Cart updates:            50M / 86,400 ≈   580 writes/sec
  Order placements:         1M / 86,400 ≈    12 writes/sec (100× peak = 1,200)
  Payment transactions:     1M / 86,400 ≈    12 TPS (100× peak = 1,200)

Peak QPS (Black Friday):
  Product pages:  58,000 reads/sec
  Orders:          1,200 writes/sec (covered by checkout service scaling)
```

### Storage

```
Products:
  300 million SKUs × 2 KB metadata = 600 GB
  Product images:  300M × 5 images × 500 KB = 750 TB → CDN + S3

Orders:
  1M orders/day × 365 × 5 years = 1.825 billion orders
  Avg order size: 2 KB (items, shipping, payment ref)
  Total: 1.825B × 2 KB = 3.65 TB

Users:
  200 million users × 1 KB profile = 200 GB

Reviews:
  500 million reviews × 1 KB = 500 GB

Inventory events (append-only):
  10M inventory changes/day × 200 bytes × 5 years = 3.65 TB

Search index:
  300M products × 1 KB indexed fields = 300 GB → Elasticsearch cluster
```

### Bandwidth

```
Product page: avg 500 KB (HTML + images via CDN)
  5,800 req/sec × 500 KB = 2.9 GB/s outbound (served ~90% from CDN)

Images: served from CloudFront CDN
  ~50% cache hit rate for long-tail products

Payment: negligible (small payloads, 12 TPS)
```

---

## 5. High-Level Design

### Architecture: Domain-Driven Microservices

```
                              ┌─────────────────────────────────┐
                              │         API Gateway              │
                              │  (Auth, Rate Limit, Routing)     │
                              └───────────────┬─────────────────┘
                                              │
              ┌───────────────────────────────┼───────────────────────────────┐
              │                               │                               │
              ▼                               ▼                               ▼
    ┌──────────────────┐          ┌──────────────────┐           ┌──────────────────┐
    │  Product Service  │          │   Cart Service    │           │  Order Service   │
    │  (Catalog, Search)│          │  (Redis + Dynamo) │           │  (PostgreSQL)    │
    └────────┬─────────┘          └────────┬─────────┘           └────────┬─────────┘
             │                             │                               │
             │                    ┌────────▼─────────┐           ┌────────▼─────────┐
             │                    │  Checkout Service │           │ Payment Service  │
             │                    │  (Orchestrator)   │           │  (PCI DSS zone)  │
             │                    └────────┬─────────┘           └──────────────────┘
             │                             │
    ┌────────▼─────────┐         ┌────────▼─────────┐           ┌──────────────────┐
    │ Inventory Service │         │Notification Svc   │           │Recommendation Svc│
    │  (DynamoDB)       │         │(Kafka + FCM/APNs) │           │  (ML Platform)   │
    └──────────────────┘         └──────────────────┘           └──────────────────┘
```

### Request Flow — Product Detail Page

```
User Browser
    │
    ├──► CDN (CloudFront)
    │      ├── Static assets (JS, CSS, images): HIT → serve from edge
    │      └── HTML: MISS → origin
    │
    ▼
API Gateway (Kong / AWS API GW)
    │  Authenticate JWT, rate limit, route
    │
    ▼
Product Service (read path)
    │
    ├──► L1: In-process cache (Caffeine, 30-sec TTL) → HIT 40%
    ├──► L2: Redis cache (1-hour TTL) → HIT 50%
    └──► L3: DynamoDB (product catalog) → HIT 10%
    │
    ▼
Aggregation: product data + inventory availability + top reviews + recommendation
    │
    ▼
Response → HTML rendered (SSR) or JSON (SPA)
```

### Request Flow — Order Placement (Happy Path)

```
Client → POST /checkout/orders
    │
    ▼
Checkout Service (orchestrator)
    ├── 1. Validate cart (CartService.get)
    ├── 2. Reserve inventory (InventoryService.reserve — SYNCHRONOUS)
    ├── 3. Calculate totals (tax, shipping, coupons)
    ├── 4. Initiate payment (PaymentService.charge — SYNCHRONOUS)
    ├── 5. Create order record (OrderService.create — SYNCHRONOUS)
    ├── 6. Release inventory reservation → confirm deduction
    └── 7. Publish OrderCreated event → Kafka
                │
                ├── NotificationService (email + push)
                ├── InventoryService (finalize deduction)
                ├── FulfillmentService (trigger pick-pack-ship)
                └── RecommendationService (update user history)
```

---

## 6. Deep Dive

### 6.1 Product Catalog & Search

#### Catalog Storage

```
300M SKUs — two different access patterns:

1. Point lookup (product detail page):
   Tool: DynamoDB
   Key: product_id (partition key)
   Attributes: title, description, category_path, brand, attributes JSON
   Reads: 5,800/sec → DynamoDB on-demand handles this trivially
   Cache: Redis (1h TTL) → 90% cache hit rate eliminates most DynamoDB reads

2. Full-text search + faceting (search results, category browse):
   Tool: OpenSearch (Elasticsearch)
   Index: 300M documents × 1 KB = 300 GB → 5-node cluster, RF=2
   Sync: DynamoDB Streams → Lambda → OpenSearch (near-real-time, <5s lag)
```

#### Search Architecture

```
Query: "wireless bluetooth headphones under $100, Prime eligible, 4+ stars"

Client → API Gateway → Search Service
    │
    ▼
Query Parser:
  - Extract intent: "wireless bluetooth headphones"
  - Filters: price ≤ 100, prime_eligible = true, avg_rating ≥ 4.0
  - Spelling correction (ElasticSearch fuzziness)
  - Synonym expansion: "earbuds" ↔ "earphones" ↔ "headphones"

OpenSearch Query:
  bool:
    must: multi_match("wireless bluetooth headphones", fields: [title^3, description^1])
    filter:
      - range: { price: { lte: 100 } }
      - term:  { prime_eligible: true }
      - range: { avg_rating: { gte: 4.0 } }
  sort:
    - _score (relevance)
    - sales_rank (popularity signal)

Result: 50 products, with facets (brand counts, price histogram, avg rating)
Latency target: p99 < 100ms
```

#### Search Ranking Signals

```
Final score = 0.4 × text_relevance
            + 0.3 × sales_velocity (units sold last 7 days)
            + 0.2 × conversion_rate (clicks that became purchases)
            + 0.1 × avg_rating × review_count_log

Real-time signals (updated hourly via Kafka → Flink → OpenSearch):
  - Click-through rate
  - Add-to-cart rate
  - Purchase rate per impression
```

---

### 6.2 Cart & Session Management

#### Why Redis for Cart?

```
Cart properties:
  - Ephemeral (users abandon carts — 70% abandonment rate)
  - High read/write: users update cart frequently
  - Must survive browser close (persistent, not session-only)
  - Cross-device sync: mobile app + desktop must see same cart
  - Relatively small: avg 3 items × 200 bytes = 600 bytes/cart

Redis data model:
  Key:   cart:{user_id}
  Value: Hash
    item:{product_id} → { qty: 2, price_at_add: 29.99, added_at: "..." }
  TTL: 30 days (reset on activity)

Operations:
  HSET  cart:user123 item:B0001 '{"qty":2,"price":29.99}' — O(1)
  HGET  cart:user123 item:B0001 — O(1)
  HDEL  cart:user123 item:B0001 — O(1)
  HGETALL cart:user123 — O(N items, N≤100 for practical carts)
```

#### Cart Persistence (Durability)

```
Problem: Redis is in-memory — if cluster fails, carts are lost.
Solution: Redis persistence + DynamoDB backup

Write path:
  1. Write to Redis (primary, fast)
  2. Async write to DynamoDB cart_backups table (within 5 seconds)

Read path:
  1. Try Redis → HIT (95% of cases)
  2. MISS → Read from DynamoDB → Warm Redis

Cart merge (user logs in after adding items as guest):
  guest_cart = Redis.HGETALL(cart:{session_id})
  user_cart = Redis.HGETALL(cart:{user_id})
  merged = merge_strategy(user_cart, guest_cart)
  # Strategy: keep higher quantity per item; keep user_cart price for items in both
  Redis.HMSET(cart:{user_id}, merged)
  Redis.DEL(cart:{session_id})
```

---

### 6.3 Checkout & Order Pipeline

#### The Checkout Orchestrator Pattern

Checkout is the most critical flow — it must be **exactly-once**: no double charges, no lost orders.

```
Pattern: Saga with Orchestration (not choreography)
  The Checkout Service is the orchestrator — it calls each downstream service
  in sequence and handles compensation (rollback) on failure.

CheckoutService.placeOrder(cart, payment_token, address):

  Step 1: VALIDATE
    cart = CartService.get(user_id)
    Assert cart is not empty, all items in-stock

  Step 2: RESERVE INVENTORY
    reservation_id = InventoryService.reserve(cart.items)
    // Inventory held for 15 minutes
    On failure → return "Item out of stock", no charge

  Step 3: CALCULATE TOTALS
    subtotal = sum(item.price × qty)
    tax = TaxService.calculate(subtotal, address)
    shipping = ShippingService.quote(cart.items, address)
    total = subtotal + tax + shipping - coupons

  Step 4: CHARGE PAYMENT
    payment_result = PaymentService.charge(payment_token, total, idempotency_key)
    On failure → InventoryService.release(reservation_id)
                 return "Payment failed"

  Step 5: CREATE ORDER
    order = OrderService.create({
        user_id, items, payment_id: payment_result.id,
        address, total, status: CONFIRMED
    })
    On failure → PaymentService.refund(payment_result.id)
                 InventoryService.release(reservation_id)

  Step 6: COMMIT INVENTORY
    InventoryService.commit(reservation_id, order.id)

  Step 7: PUBLISH EVENT
    Kafka.publish("order.created", { order_id, user_id, items, total })

  Return: { order_id, estimated_delivery }
```

#### Idempotency — Preventing Double Charges

```
Problem: Client retries (network timeout) could charge twice.

Solution: Idempotency key

Client generates: idempotency_key = UUID (per checkout attempt)
  → Sent in header: Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

CheckoutService:
  1. Check Redis: GET idempotent:{idempotency_key}
     → HIT: Return cached result (same order, no re-processing)
     → MISS: Process normally

  2. After processing: Redis.SETEX idempotent:{key} 86400 {result}

PaymentService uses its own idempotency key (Stripe supports this natively).
Stripe ensures: same idempotency_key → same payment result, never double-charged.
```

---

### 6.4 Payment Processing

#### PCI DSS Compliance Zone

```
Payment data (card numbers, CVVs) must NEVER touch Amazon's application servers.

Architecture:
  Client (browser) → Stripe.js / Braintree SDK → tokenizes card client-side
                      ↓
                    Payment Token (non-sensitive) → Amazon servers
                      ↓
                    PaymentService → Stripe API (charge token)

Amazon never sees raw card data → PCI DSS scope reduced to SAQ-A (simplest level)
```

#### Payment Flow

```
1. User enters card → Stripe.js creates token T1 (card never hits Amazon)
2. Client → POST /checkout { payment_token: T1, amount: $127.43 }
3. CheckoutService → PaymentService.charge(T1, 12743, "USD", idempotency_key)
4. PaymentService → Stripe API: charges.create(amount, currency, source, idempotency_key)
5. Stripe → Acquirer Bank → Card Network → Issuing Bank
6. Authorization response: { status: "succeeded", charge_id: "ch_..." }
7. PaymentService stores: { charge_id, amount, status, order_id } in payment_records
8. Returns success to CheckoutService

Failure handling:
  - Insufficient funds → 402 Payment Required → user prompted for another card
  - Card declined → 402 with decline reason
  - Stripe timeout → Retry with same idempotency_key (safe — Stripe idempotent)
  - Network timeout → CheckoutService queries Stripe for charge status before deciding
```

#### Refund Flow

```
OrderService.cancelOrder(order_id):
  payment = PaymentService.getByOrder(order_id)
  Stripe.refunds.create(charge_id: payment.charge_id, amount: payment.amount)
  PaymentService.updateStatus(payment.id, REFUNDED)
  InventoryService.restock(order.items)
  Kafka.publish("order.cancelled", { order_id })
  NotificationService → email "Your order has been cancelled, refund in 3-5 days"
```

---

### 6.5 Inventory Management

#### The Overselling Problem

```
Scenario (without proper locking):
  Product: AirPods Pro, qty_available = 1

  User A: checks availability → 1 in stock ✓
  User B: checks availability → 1 in stock ✓
  User A: places order → inventory = 0
  User B: places order → inventory = -1 ← OVERSOLD!
```

#### Solution 1 — Optimistic Locking (Low Contention)

```sql
-- PostgreSQL with version column
UPDATE inventory
SET quantity = quantity - :ordered_qty,
    version = version + 1
WHERE product_id = :product_id
  AND quantity >= :ordered_qty
  AND version = :expected_version;

-- If 0 rows updated → version conflict → retry or return out-of-stock
-- Works well for low-contention products (most products)
```

#### Solution 2 — Redis Atomic Decrement (High Contention / Flash Sales)

```lua
-- Lua script (atomic in Redis):
local qty = redis.call('GET', KEYS[1])
if tonumber(qty) >= tonumber(ARGV[1]) then
    return redis.call('DECRBY', KEYS[1], ARGV[1])
else
    return -1  -- out of stock
end

-- Usage:
result = redis.eval(lua_script, ["inventory:product_B001"], [ordered_qty])
if result == -1: return "Out of stock"

-- Redis inventory is source of truth during flash sale
-- Async: Kafka event → DB inventory sync
```

#### Inventory Reservation System

```
Reserve:  inventory:B001:available -= ordered_qty
          inventory:B001:reserved  += ordered_qty
          TTL on reservation: 15 minutes

Commit:   inventory:B001:reserved  -= ordered_qty
          (on successful order placement)

Release:  inventory:B001:available += ordered_qty
          inventory:B001:reserved  -= ordered_qty
          (on checkout failure or reservation timeout)

States:
  available → reserved → committed (sold)
                       ↘ released (back to available)
```

---

### 6.6 Recommendation Engine

#### "Customers Also Bought" (Item-Based Collaborative Filtering)

```
Offline batch job (runs nightly, Apache Spark):

1. Build co-purchase matrix:
   For each order, generate pairs: (A,B), (A,C), (B,C) for items A,B,C
   Aggregate: count co-purchases across all orders

   co_purchase[A][B] = # of orders containing both A and B

2. Normalize by item popularity:
   similarity(A,B) = co_purchase[A][B] / sqrt(popularity[A] × popularity[B])
   (Cosine similarity prevents popular items dominating)

3. For each item, store top-20 similar items:
   Redis: ZADD reco:similar:B001 0.95 B002 0.87 B003 ...

4. At product detail page time:
   similar = Redis.ZREVRANGE("reco:similar:{product_id}", 0, 9)
   → Return top-10 "Customers also bought" items

Scale: 300M products × 20 neighbors × 8 bytes = 48 GB → fits in Redis cluster
Refresh: nightly Spark job processes prior 90 days of orders
```

#### "Recommended For You" (User-Based, Real-Time)

```
Online (real-time signals via Kafka):
  UserClickedProduct → Flink → update user interest vector
  UserPurchasedProduct → boost weight 5× vs click

Offline (Matrix Factorization via Spark MLlib):
  User-item rating matrix → ALS decomposition
  → User embedding vector (50 dimensions)
  → Item embedding vector (50 dimensions)
  → similarity(user, item) = dot product of embeddings

Serving:
  User embedding stored in Redis (updated nightly)
  At homepage: approximate nearest neighbor (FAISS or Redis VSS)
  → Top-20 recommended products in <10ms
```

---

### 6.7 Notification System

#### Notification Types & Channels

| Event | Email | Push | SMS |
|-------|-------|------|-----|
| Order Confirmed | ✅ | ✅ | Optional |
| Order Shipped | ✅ | ✅ | ✅ |
| Out for Delivery | ❌ | ✅ | ✅ |
| Delivered | ✅ | ✅ | ❌ |
| Return Approved | ✅ | ✅ | ❌ |

#### Architecture

```
Kafka topic: "notifications"
  Producers: OrderService, FulfillmentService, PaymentService

Notification Consumer (Flink / custom consumer):
  1. Consume event from Kafka
  2. Fetch user preferences (email opt-out, push token, SMS opt-in)
  3. Render template (Handlebars / Mustache)
  4. Route to correct channel:
     Email → Amazon SES (100K emails/sec capacity)
     Push  → FCM (Android) / APNs (iOS) via Firebase Admin SDK
     SMS   → Twilio / Amazon SNS

Deduplication:
  Redis: SET notif:{user_id}:{order_id}:{event_type} 1 EX 86400 NX
  If SET returns 0 (already exists) → skip (already sent)

Retry:
  Failed deliveries → DLQ (Dead Letter Queue)
  Retry 3× with exponential backoff (1s, 5s, 25s)
  After 3 failures → alert on-call, store in failed_notifications table
```

---

## 7. Trade-offs Discussion

### 7.1 Checkout Saga: Orchestration vs Choreography

| Approach | Pros | Cons | Fit |
|----------|------|------|-----|
| **Orchestration** (chosen) | Single place to trace order state; easy rollback sequencing; clear compensation logic | Orchestrator is a single point of failure; service coupling through orchestrator | ✅ Checkout (stateful, sequential, financially critical) |
| **Choreography** | Fully decoupled; services react to events; no SPOF | Distributed state hard to trace; compensations fan-out; "invisible" ordering | ❌ Checkout flow (too complex for event-driven compensation at 1,200 TPS) |
| **Hybrid** | Choreography for post-order events (notification, fulfillment) | Complexity of two patterns | ✅ Used: orchestration within checkout, choreography after `order.created` event |

**Decision**: CheckoutService orchestrates the 7-step saga synchronously. After `OrderCreated` is published to Kafka, downstream services (fulfillment, notification, recommendation) operate choreo-style — no return path needed, failures are idempotent retries.

**Why not choreography for checkout?**
At 1,200 TPS on Black Friday, compensating a failed payment requires: release inventory → cancel payment → notify user. With choreography, each of those is a separate event, each can fail independently, and the system has no single place to determine "did the compensation actually complete?" The orchestrator pattern gives atomic rollback auditability that fintech regulators expect.

---

### 7.2 Inventory Locking: Optimistic vs Redis Atomic vs Pessimistic

```
Flash sale scenario: 500 units of PS5, 50,000 concurrent buyers

Option A — Pessimistic locking (SELECT FOR UPDATE):
  Each checkout acquires a row-level lock on the inventory row.
  PostgreSQL max lock holders: limited by connection pool (~500)
  50,000 concurrent requests → 49,500 queued → connection exhaustion → timeout cascade
  ❌ Not viable above ~500 concurrent buyers

Option B — Optimistic locking (version column + CAS):
  No locks held; CAS retry on conflict.
  Retry rate at 50K concurrency ≈ 99.9% collision → thundering herd
  Each failed CAS → application-layer retry → 3–5 retries per buyer average
  Effective TPS: ~10K retries/sec → PostgreSQL CPU saturation
  ❌ Degrades badly under high contention flash sale loads

Option C — Redis atomic Lua script (chosen for flash sales):
  DECRBY_IF_POSITIVE Lua: atomic, single-threaded per slot, no locks
  Redis throughput: 100K ops/sec per node; 50K concurrent → no queuing
  15-minute reservation TTL prevents dangling holds
  Async DB sync via Kafka → eventual consistency with DB
  ✅ Handles 50K concurrent buyers without lock contention

Option D — Virtual waiting room (Amazon uses this for Prime Day):
  Queue buyers before they hit inventory; dequeue N at a time equal to remaining stock
  Eliminates race entirely; user sees "You're #4,521 in line"
  ✅ Better UX for extreme demand; more complex to implement
```

**Chosen**: Redis Lua for contention hotspots + optimistic locking for all other products.
**Reconciliation**: Background job every 5 minutes compares Redis counters to DB quantities and corrects drift (Redis can lose in-flight state on crash despite AOF persistence).

---

### 7.3 Cart Storage: Redis vs DynamoDB vs PostgreSQL

| Property | Redis | DynamoDB | PostgreSQL |
|----------|-------|----------|------------|
| Read latency | Sub-ms (in-memory) | ~5ms | ~10ms |
| Write throughput | 500K ops/sec | 40K WCU/sec | ~10K/sec |
| Durability | AOF/RDB (eventual) | 99.999% (multi-AZ) | WAL (ACID) |
| Cost at scale | High memory cost | Pay-per-request scales cheaply | Fixed cluster cost |
| TTL support | Native TTL | DynamoDB TTL | Manual cleanup job |
| Cross-device sync | ✅ (shared key) | ✅ | ✅ |

**Decision**: Redis as primary (sub-ms reads, TTL, atomic HSET), DynamoDB as durable backup.

**Math**: 300M users × 30-day cart TTL × 600 bytes avg cart = 180 GB active cart data → fits in a 256 GB Redis cluster with room for peak. DynamoDB backup cost: 180 GB × $0.25/GB-month = $45/month; negligible for durability insurance.

**Key interview insight**: Never store carts in PostgreSQL. Cart reads are ~10× more frequent than order writes; SQL joins and ACID overhead add unnecessary latency. The "durability" argument for SQL doesn't apply — carts are inherently ephemeral, and a lost cart (on Redis crash) is far cheaper than a lost order.

---

### 7.4 Product Catalog: DynamoDB vs PostgreSQL vs MongoDB

| Criterion | DynamoDB | PostgreSQL | MongoDB |
|-----------|----------|------------|---------|
| Query model | Key-value + GSI | Relational, joins | Document, flexible schema |
| Schema flexibility | Schemaless per item | Rigid (ALTER TABLE) | Flexible per document |
| Read scaling | 58K RPS with DAX | ~5K RPS (read replicas) | ~20K RPS (sharded) |
| Full-text search | ❌ (no native) | pg_tsvector (limited) | Atlas Search (limited) |
| Managed | ✅ serverless | ❌ needs DBA ops | ❌ needs ops |
| Black Friday elastic scale | ✅ on-demand mode | ❌ pre-provisioned | ❌ pre-sharded |

**Decision**: DynamoDB for catalog + OpenSearch for search.

**Why not one database for both?**
DynamoDB excels at key-value point lookups (product detail page: 58K/sec on Black Friday). OpenSearch excels at relevance-ranked full-text with faceting. Trying to make PostgreSQL do both at 300M SKUs requires a 20+ node cluster, custom FTS tuning, and still can't match DynamoDB's elastic scale. Two purpose-built tools outperform one general-purpose tool at this scale.

**ADR**: Accept eventual consistency (≤5s) between DynamoDB and OpenSearch via DynamoDB Streams → Lambda → OpenSearch. Stale search results showing a product that just went out-of-stock is acceptable; the inventory check at checkout catches it.

---

### 7.5 Search Ranking: OpenSearch vs Algolia vs Custom ML Ranking

| Option | Latency | Relevance Quality | Cost at 300M SKUs | Customization |
|--------|---------|------------------|--------------------|---------------|
| **OpenSearch** (chosen) | p99 < 100ms | Good (BM25 + custom scoring) | ~$50K/month (managed) | Full control |
| Algolia | p99 < 10ms | Excellent (tuned) | ~$500K/month at Amazon scale | Limited ML hooks |
| Custom ML ranking (LambdaRank) | p99 100–200ms (inference overhead) | Best | R&D + infra investment | Total control |
| Hybrid (OpenSearch + ML reranker) | p99 80–150ms (2-stage) | Best practical | OpenSearch + GPU inference fleet | ✅ Amazon actual approach |

**Decision for interview**: OpenSearch with business signals (click-through rate, purchase rate, sales velocity) weighted into BM25 score. Mention that at Amazon's real scale, a two-stage approach (OpenSearch retrieves top-100, ML reranker produces final top-20) is used — but implementing a full LambdaRank model is out of scope for the interview.

**Algolia rejection rationale**: $500K/month vs $50K/month at 300M SKUs × 2.3K QPS. More importantly, Algolia's ranking black-box limits the ability to inject real-time purchase signals that differentiate Amazon's search quality.

---

### 7.6 Payment Integration: Stripe-Hosted vs In-House Payment Stack

| Option | PCI Scope | Development Cost | Control | Fraud Detection |
|--------|-----------|-----------------|---------|-----------------|
| **Stripe-hosted tokenization** (chosen) | SAQ-A (simplest) | Low | Limited | Stripe Radar (ML) |
| Braintree / Adyen | SAQ-A-EP | Medium | Medium | Configurable rules |
| In-house (card vault + acquirer direct) | PCI DSS Level 1 (most complex) | $5M+ / year | Total | Full custom |

**Decision**: Stripe for tokenization + authorization; Amazon Pay as branded overlay. Raw card data never touches Amazon servers — PCI scope reduced to SAQ-A.

**Why not in-house?**
PCI DSS Level 1 compliance requires annual QSA audit ($100K+/year), network segmentation, HSM-backed key storage, dedicated PCI-scoped VLAN, quarterly ASV scans. Stripe handles all of this; Amazon's core competency is retail, not card scheme integration. Exception: Amazon Pay itself (for merchants accepting Amazon Pay on third-party sites) does operate a full-scale payment processor — but that is out of scope for the e-commerce platform design.

**Idempotency critical detail**: Every payment call includes a client-generated `idempotency_key` (UUID). On network timeout, the client retries with the **same key** — Stripe returns the original charge result without re-charging. This is the single most important API contract in the payment flow.

---

### 7.7 Order Sharding: user_id vs order_id vs Region-Based

```
Problem: 1.825 billion orders over 5 years → single PostgreSQL node is insufficient

Option A — Shard by user_id:
  Pro: All of a user's order history on one shard (ORDER BY created_at is local)
  Con: Power users (frequent buyers) create hot shards
  Con: "Top 1K Amazon buyers" could saturate a single shard
  Usage: Best for user-facing "my orders" queries

Option B — Shard by order_id (hash):
  Pro: Perfectly uniform distribution even for power buyers
  Con: A single user's orders scatter across all shards
  Con: "List my last 50 orders" requires scatter-gather query across all N shards
  Usage: Good for operational queries (fraud, fulfillment) by order_id

Option C — Shard by region (geo):
  Pro: Data sovereignty (GDPR requires EU orders in EU)
  Con: US power buyers still create within-region hot shards
  Usage: Required for compliance; combine with user_id sub-sharding

Option D — Shard by (region, user_id hash) [chosen]:
  region → routes to correct geo cluster (GDPR compliance)
  user_id hash within region → uniform distribution across shards in that region
  "My orders" query → hits single shard in user's home region
  Fulfillment query by order_id → encode region + shard in order_id format:
    order_id = {region_code}{user_shard_id}{timestamp}{sequence}
    e.g., USE4-0047-20240115-00001
```

**Why not scatter-gather?** At 1,200 peak orders/sec on Black Friday, a single user's "my orders" page could take 50ms × 8 shards = 400ms if scatter-gather. With user_id-based sharding, it's 50ms to a single shard. The "list my orders" SLA is directly impacted by sharding choice.

---

### 7.8 Recommendation Engine: Batch Collaborative Filtering vs Real-Time

| Approach | Latency to serve | Personalization freshness | Infrastructure | Accuracy |
|----------|-----------------|--------------------------|----------------|----------|
| **Batch CF (nightly Spark)** | Sub-ms (Redis lookup) | Stale up to 24h | Spark cluster (batch) | Good |
| Real-time streaming (Flink + online ML) | ~50ms (inference) | Minutes | Flink + GPU inference | Better |
| **Hybrid** (chosen) | Sub-ms to 50ms | Minutes for clicks, 24h for purchases | Both | Best |
| Two-tower deep learning (YouTube/Netflix approach) | ~100ms (ANN search) | Hours (retraining) | GPU cluster + FAISS | State-of-art |

**Decision**: Batch item-item CF (Spark, nightly) for "Customers also bought" (stable signal). Real-time Flink pipeline updates user interest vectors within minutes for "Recommended for you". ALS matrix factorization refreshed nightly for homepage recommendations.

**Key tradeoff**: A user who just bought a baby stroller should immediately stop seeing baby stroller ads. The real-time Flink pipeline updates their interest vector within 2 minutes (Kafka lag + Flink processing + Redis write). The nightly ALS batch job would show strollers for up to 24h — unacceptable for negative examples. **Real-time streaming is non-negotiable for negative signal suppression.**

**Cold start problem**: New users with no history → use demographic-based recommendations (age group, location, recent signups similar cohort) until 3+ purchases provide sufficient signal for collaborative filtering.

---

### 7.9 Consistency Model Matrix

| Component | Model | Justification |
|-----------|-------|---------------|
| Inventory (checkout commit) | **Strong (serializable)** | Overselling causes real financial loss and fulfillment nightmares |
| Inventory (catalog availability badge) | **Eventual (≤5s)** | "1 left in stock!" being 5s stale is tolerable; read from Redis not DB |
| Payment records | **Strong (ACID)** | Regulatory requirement; double charges or lost payments are unacceptable |
| Order status | **Strong → Eventual** | Creation is strong (exactly-once); downstream status updates (shipped, delivered) are eventual |
| Product catalog | **Eventual (≤5s)** | Price/description 5s stale: tolerable. CDN serves stale for 1h: acceptable for images |
| Search index | **Eventual (≤30s)** | OpenSearch sync lag; new products taking 30s to appear is acceptable |
| Cart | **Eventual (within-session strong)** | Within one browser session, cart must be consistent. Cross-device sync can lag ~1s |
| User sessions / auth | **Strong (read-your-writes)** | User must see their own login; token invalidation must propagate immediately |
| Recommendations | **Eventual (minutes to 24h)** | Stale recommendations acceptable; negative signal suppression needs minutes-level |
| Notifications | **At-least-once delivery** | Duplicate "Your order shipped" is acceptable; missed notification is not |

**Key insight for interviewers**: Strong consistency everywhere would require every read to hit the primary database. At 58,000 product page reads/sec, that's impossible. The differentiation is **financial data = strong, user-facing catalog data = eventual with bounded staleness**. Articulating this boundary cleanly is what separates senior from junior system design candidates.

---

## 8. Data Models

### Product

```sql
-- DynamoDB (primary catalog store)
{
  "product_id":    "B08N5WRWNW",           -- partition key
  "title":         "Apple AirPods Pro",
  "brand":         "Apple",
  "category_path": "Electronics > Audio > Earbuds",
  "price":         249.00,
  "currency":      "USD",
  "images":        ["s3://amzn-img/B08N.../main.jpg", ...],
  "attributes":    { "color": "White", "connectivity": "Bluetooth 5.0" },
  "avg_rating":    4.7,
  "review_count":  85432,
  "prime_eligible": true,
  "seller_id":     "AMAZON",
  "created_at":    "2020-10-13T00:00:00Z",
  "updated_at":    "2024-01-15T10:30:00Z"
}
```

### Order

```sql
-- PostgreSQL (ACID transactions required)
CREATE TABLE orders (
    order_id        UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID         NOT NULL,
    status          TEXT         NOT NULL CHECK (status IN (
                      'PENDING','CONFIRMED','PROCESSING','SHIPPED','DELIVERED',
                      'CANCELLED','RETURN_REQUESTED','RETURNED')),
    items           JSONB        NOT NULL,  -- [{product_id, qty, price, seller_id}]
    shipping_addr   JSONB        NOT NULL,
    subtotal        NUMERIC(10,2) NOT NULL,
    tax             NUMERIC(10,2) NOT NULL,
    shipping_fee    NUMERIC(10,2) NOT NULL,
    discount        NUMERIC(10,2) DEFAULT 0,
    total           NUMERIC(10,2) NOT NULL,
    payment_id      TEXT,                   -- Stripe charge ID
    estimated_delivery DATE,
    tracking_number TEXT,
    created_at      TIMESTAMPTZ  DEFAULT NOW(),
    updated_at      TIMESTAMPTZ  DEFAULT NOW()
);

CREATE INDEX idx_orders_user     ON orders (user_id, created_at DESC);
CREATE INDEX idx_orders_status   ON orders (status) WHERE status NOT IN ('DELIVERED','CANCELLED');
CREATE INDEX idx_orders_tracking ON orders (tracking_number) WHERE tracking_number IS NOT NULL;
```

### Inventory

```sql
-- PostgreSQL (ACID for inventory transactions)
CREATE TABLE inventory (
    product_id      TEXT         PRIMARY KEY,
    warehouse_id    TEXT         NOT NULL,
    qty_available   INTEGER      NOT NULL CHECK (qty_available >= 0),
    qty_reserved    INTEGER      NOT NULL DEFAULT 0 CHECK (qty_reserved >= 0),
    qty_sold        INTEGER      NOT NULL DEFAULT 0,
    version         BIGINT       NOT NULL DEFAULT 0,  -- optimistic lock
    updated_at      TIMESTAMPTZ  DEFAULT NOW()
);

CREATE TABLE inventory_reservations (
    reservation_id  UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID,
    product_id      TEXT         NOT NULL,
    qty             INTEGER      NOT NULL,
    status          TEXT         NOT NULL DEFAULT 'RESERVED',
    expires_at      TIMESTAMPTZ  NOT NULL DEFAULT NOW() + INTERVAL '15 minutes',
    created_at      TIMESTAMPTZ  DEFAULT NOW()
);
```

---

## 9. Follow-Up Questions

### Q1: How do you handle Black Friday traffic spikes (10× normal)?

```
Pre-scaling (not reactive):
  - Historical data: Black Friday QPS peaks are predictable
  - Pre-scale 3 weeks before: double capacity across all services
  - Load test at 200% expected peak before the event

Caching as shock absorber:
  - Product catalog: 99%+ cache hit rate on hot items (Apple, Samsung)
  - CDN absorbs 90% of static asset traffic
  - Search result caching for top 10K queries (5-min TTL)

Graceful degradation:
  - If InventoryService is overwhelmed → show "Limited availability" without exact count
  - If RecommendationService is slow → show popular items (static list fallback)
  - Circuit breaker (Hystrix/Resilience4j) prevents cascade failures

Queue buffering for orders:
  - At extreme load, CheckoutService → Kafka → OrderWorker (async processing)
  - User sees "Order is being processed" → push notification when confirmed
  - Prevents order table being hammered directly at peak
```

### Q2: How do you prevent flash sale overselling?

```
Three-layer inventory defense:

Layer 1 — Redis atomic counter (pre-sale):
  redis.eval(LUA_DECRBY_IF_POSITIVE, ["inv:B001"], [1])
  Atomic, no locks, handles 100K TPS

Layer 2 — Database optimistic locking (order commit):
  UPDATE inventory SET qty = qty - 1, version = version + 1
  WHERE product_id = 'B001' AND qty > 0 AND version = :v
  → CAS semantics at DB level

Layer 3 — Reconciliation job (post-event):
  Compare Redis count vs DB count
  Adjust any discrepancy (Redis can drift on crash without persistence)
```

### Q3: How do you design the order search for customer service reps?

```
Amazon CS reps need: search orders by email, phone, order ID, product name, date range

Solution: Elasticsearch for order search
  - OrderCreated event → Kafka → Flink → Elasticsearch index
  - Index fields: user_email, user_phone, order_id, product names, status, date
  - Full-text search on product names, exact match on order_id/email
  - CS-only endpoint with additional auth (not customer-facing)
  - Near-real-time (< 30s lag from order creation to searchable)
```

### Q4: How do you handle multi-region active-active?

```
Region pairing: US-East + US-West + EU-West + APAC-Southeast

Data strategy:
  User data:    User's home region is source of truth; read-replicas in other regions
  Product catalog: Eventually consistent globally (DynamoDB Global Tables, RF=3)
  Orders:       Region-local PostgreSQL cluster (order_id encodes region)
  Sessions/Cart: Redis with global replication (ElastiCache Global Datastore)

Routing: Route53 latency-based routing → nearest healthy region
Failover: If US-East fails → Route53 health check fails → traffic to US-West
         Recovery time: <60 seconds (DNS TTL = 30s)

Conflict resolution:
  Inventory is region-local (each region has its own allocation pool)
  → No cross-region inventory conflicts
  → US allocation ≠ EU allocation (managed by supply chain system)
```

### Q5: How do you ensure GDPR compliance?

```
Data minimization:
  - Payment data: tokenized via Stripe (Amazon never stores raw card numbers)
  - PII in orders: encrypted at rest (AWS KMS), column-level encryption

Right to be forgotten:
  - user.deleted_at = NOW() → anonymize: email → deleted_user_{hash}@amazon.com
  - S3 objects (profile photos): deleted immediately
  - Orders: pseudonymized (user_id → anonymous_id, address hashed)
  - Audit log: kept for 7 years (legal requirement) with anonymized user reference
  - Search index: Elasticsearch delete by query on user_id

Data portability:
  - /api/v1/users/me/export → async job → ZIP of all user data → email download link
```

### Q6: How would you add real-time bidding for ad placements on search results?

```
Sponsored product ads (Amazon Advertising):

Ad auction (per search query, <20ms budget):
  1. Search Service identifies ad slots (top 3 positions)
  2. Async RPC to Ad Service: AuctionRequest { query, user_id, slot_count }
  3. Ad Service: fetch eligible ads from pre-indexed bid cache (Redis)
  4. Run second-price auction: winner pays second-highest-bid + 1 cent
  5. Return winning ad creatives with tracking pixel URLs

Billing:
  Win → Kafka event → ClickService (deduct from advertiser budget)
  Click → Kafka event → ClickService (charge CPC, log for analytics)
  Budget exhausted → Ad Service removes ads from active bid pool
```

---

## 10. Interview Summary Card

> Use this to guide your 45-minute answer structure

| Minute | Action |
|--------|--------|
| 0–5 | Ask clarifying questions, agree on scope |
| 5–10 | State functional + non-functional requirements |
| 10–15 | Back-of-envelope estimation (QPS, storage) |
| 15–25 | High-level architecture diagram + component overview |
| 25–40 | Deep dive on 2–3 components (interviewer-guided) |
| 40–45 | Bottlenecks, scaling, trade-offs, follow-ups |

### Key Numbers to Memorize

```
300M DAU
5,800 product page reads/sec (normal), 58K (Black Friday)
12 orders/sec (normal), 1,200 (Black Friday)
300M SKUs, 750 TB product images, 3.65 TB orders (5 years)
```

### Technology Choices Summary

| Component | Technology | Why |
|-----------|-----------|-----|
| Product catalog | DynamoDB | Key-value, massive read scale, managed |
| Search | OpenSearch | Full-text + faceting + ranking |
| Cart | Redis | Sub-ms reads, TTL, atomic operations |
| Orders | PostgreSQL | ACID, complex queries, joins |
| Inventory | PostgreSQL + Redis | ACID for correctness, Redis for flash sales |
| Events | Kafka | Durable, replay, fan-out |
| Images | S3 + CloudFront | Cheap object storage, global CDN |
| Payments | Stripe | PCI DSS, idempotency keys, global acquiring |
| Push/Email | FCM/APNs + SES | Managed, high deliverability |

### Trade-Offs to Articulate

```
Consistency vs Availability:
  Inventory:  CP (strong) — overselling destroys trust
  Catalog:    AP (eventual) — 5-second stale price is acceptable
  Cart:       AP (eventual) — cart divergence is tolerable, rarely noticed

Synchronous vs Asynchronous:
  Checkout saga: sync for inventory + payment (must confirm before order)
  Notifications: async via Kafka (user doesn't wait for email to send)
  Recommendations: async (stale recommendations acceptable)

SQL vs NoSQL:
  Orders:  PostgreSQL — complex queries, ACID, moderate scale
  Catalog: DynamoDB — simple key lookups, extreme scale, no joins needed
  Inventory: PostgreSQL — ACID critical; Redis for contention hotspots
```

---

*co-authored-by: wibey jetbrains plugin (wibey.walmart.com/code)*
