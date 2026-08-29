# Booking.com System Design - FAANG Interview Level

## Overview
Design a global hotel booking platform similar to Booking.com that handles millions of users searching, comparing, and booking accommodations. The system must support real-time availability, dynamic pricing, and complex search queries across thousands of properties worldwide.

---

## 1. Functional Requirements

### Core Features
1. **Search & Discovery**
   - Search hotels by location, dates, number of guests
   - Filter by price range, rating, amenities, property type
   - Display availability and pricing information
   - Support sorting (price, rating, distance, popularity)
   - Autocomplete for locations (cities, landmarks, addresses)

2. **Inventory Management**
   - Real-time availability tracking for each property
   - Room type management (single, double, suite, etc.)
   - Rate management with dynamic pricing
   - Overbooking rules and cancellation policies

3. **Booking Flow**
   - View property details (photos, descriptions, reviews)
   - Check real-time availability
   - Hold inventory temporarily during checkout
   - Complete booking with payment
   - Confirmation and itinerary management

4. **User Management**
   - User registration and authentication
   - Wishlist/saved properties
   - Booking history
   - Profile management

5. **Reviews & Ratings**
   - Post reviews for completed bookings
   - View guest and hotel ratings
   - Review moderation and anti-fraud measures

6. **Notifications**
   - Booking confirmations
   - Price alerts
   - Review reminders
   - Promotional notifications

---

## 2. Non-Functional Requirements

### Scale Requirements
- **Daily Active Users**: 10+ million
- **Peak QPS**: 100,000+ requests/second during peak hours
- **Data Volume**: 
  - Hotels: 1+ million properties
  - Rooms: 10+ million room instances
  - Bookings: 1+ million/day
  - Reviews: 10+ million/year

### Performance
- Search results: <200ms (p95)
- Hotel detail page: <300ms (p95)
- Checkout process: <500ms (p95)
- API endpoints: <100ms (p95)

### Reliability
- **Availability**: 99.95% uptime SLA
- **Data Consistency**: Strong consistency for financial transactions
- **RTO**: <1 hour
- **RPO**: <5 minutes

### Scalability
- Horizontal scaling for stateless services
- Database sharding strategy
- Cache layer for hot data
- Multi-region deployment

---

## 3. High-Level Design

### Architecture Diagram
```
┌─────────────────────────────────────────────────────────────┐
│                     CDN & Frontend                          │
└────────────────────────┬────────────────────────────────────┘
                         │
┌────────────────────────▼────────────────────────────────────┐
│              Load Balancer (Global)                         │
└────────────────────────┬────────────────────────────────────┘
                         │
        ┌────────────────┼────────────────┐
        │                │                │
┌───────▼────────┐ ┌────▼────────┐ ┌───▼─────────────┐
│  Search API    │ │ Booking API │ │  User API       │
│  Gateway       │ │ Gateway     │ │  Gateway        │
└───────┬────────┘ └────┬────────┘ └───┬─────────────┘
        │                │                │
    ┌───▼────────────────▼────────────────▼────┐
    │     Service Mesh (Istio)                 │
    └───────────────────┬──────────────────────┘
        │                │                │
    ┌───▼──────────┐ ┌──▼─────────┐ ┌───▼────────┐
    │ Search Svcs  │ │ Booking Svc │ │ User Svc   │
    └───┬──────────┘ └──┬─────────┘ └───┬────────┘
        │                │                │
    ┌───▼────────────────▼────────────────▼──────┐
    │        Distributed Cache (Redis)           │
    │  - Search results cache                    │
    │  - Availability cache                      │
    │  - User session data                       │
    └───────────────────┬────────────────────────┘
        │               │               │
    ┌───▼──────────┐ ┌──▼──────────┐ ┌─▼──────────┐
    │ Primary DB   │ │ Replica DB  │ │ Event DB   │
    │ (Bookings)   │ │ (Analytics) │ │ (Changes)  │
    └──────────────┘ └─────────────┘ └────────────┘
        │               │               │
    ┌───▼────────────────▼────────────────▼──────┐
    │     Message Queue (Kafka)                  │
    │  - Booking events                          │
    │  - Availability updates                    │
    │  - Notifications                           │
    └───────────────────┬────────────────────────┘
        │               │
    ┌───▼───────┐   ┌───▼─────────────┐
    │ Analytics │   │ Notification Svc│
    │ Pipeline  │   └──────────────────┘
    └───────────┘
```

### Core Services

#### 1. **Search Service**
- Handles complex search queries with multiple filters
- Uses Elasticsearch/Solr for full-text search capabilities
- Caches popular searches (20% of searches are identical)
- Implements search relevance ranking algorithm
- Handles autocomplete and suggestions

#### 2. **Inventory Service**
- Manages real-time availability data
- Tracks room inventory across all properties
- Implements overbooking logic with predictive cancellation
- Handles rate updates and dynamic pricing
- Provides availability APIs to other services

#### 3. **Booking Service**
- Manages booking lifecycle (creation, confirmation, cancellation)
- Implements distributed transactions using Saga pattern
- Handles payment processing and reconciliation
- Manages inventory hold/release during checkout
- Tracks booking status and state transitions

#### 4. **User Service**
- Authentication and authorization
- Profile management
- Wishlist and saved preferences
- Booking history
- User segmentation for personalization

#### 5. **Review Service**
- Manages guest reviews and ratings
- Implements review moderation
- Provides review analytics
- Handles review authenticity validation

#### 6. **Notification Service**
- Email and SMS notifications
- Push notifications
- Notification scheduling and queueing
- Preference management

---

## 4. Back of Envelope Calculation

### Storage Estimation

**Hotels & Metadata**
```
Hotels: 1.5M × 10KB = 15 GB
Room Types per Hotel: 5 × 1.5M × 5KB = 37.5 GB
Hotel Photos: 1.5M × 100 photos × 500KB = 750 TB (stored in object storage)
```

**Bookings Data**
```
1M bookings/day × 365 days = 365M bookings/year
365M × 5KB per booking = 1.8 TB/year
5 years retention = 9 TB

With sharding across 10 shards: 900 GB per shard
```

**Availability Data**
```
10M room instances × 365 days × 100 bytes = 365 GB
(Compressed with time-series DB: ~36 GB)
```

**User Data**
```
500M registered users × 5KB = 2.5 TB
Session data (10% active): 50M users × 2KB = 100 GB (in Redis)
```

**Reviews**
```
10M reviews/year × 2KB = 20 GB/year
5 years = 100 GB
```

**Total Active Storage: ~30 TB (excluding photos)**
**Total Object Storage: ~750 TB (for photos/images)**

### Compute Estimation

**Search Requests**
```
Peak QPS: 100,000 req/s
Average response time: 200ms
Assuming 500 RPS per search instance:
100,000 / 500 = 200 search instances
```

**Booking Requests**
```
Peak QPS: 10,000 req/s
Average response time: 300ms
Assuming 100 RPS per booking instance:
10,000 / 100 = 100 booking instances
```

**Database Connections**
```
200 search instances × 10 connections = 2,000 connections (read replicas)
100 booking instances × 20 connections = 2,000 connections (primary)
Max connections in pool: 5,000 total
```

### Network Bandwidth

```
Peak QPS: 100,000 req/s
Avg response size: 50KB
Bandwidth: 100,000 × 50KB = 5 GB/s = 40 Gbps
(With compression: 10 Gbps effective)
```

---

## 5. Trade-offs Discussion

### 1. **Consistency vs Availability (Inventory)**

**Problem**: Overselling rooms due to distributed updates

**Solutions**:

| Approach | Pros | Cons |
|----------|------|------|
| **Strict Consistency (Distributed Locks)** | No overselling | Higher latency (500ms+), single point of contention |
| **Eventual Consistency (Event-based)** | High availability, low latency | Possible overselling (< 0.1%), requires compensation logic |
| **Hybrid (Optimistic Locking + Grace Period)** | Balance of both | Complex implementation, requires monitoring |

**Recommendation**: Use **Hybrid approach** with booking holds
- Book with optimistic locking (version numbers)
- 15-minute grace period for checkout completion
- Automatic release on timeout
- Overbooking tolerance: <0.05%

---

### 2. **Search Freshness vs Performance**

**Problem**: Real-time search vs query latency

**Solutions**:

| Approach | Pros | Cons |
|----------|------|------|
| **Real-time Indexing (Search Engine)** | Always fresh, good search features | Higher ingestion latency, complex sync |
| **Batch Updates (Hourly/Daily)** | Simple, predictable | Stale data (up to 1 hour) |
| **Hybrid (Real-time + Cache Invalidation)** | Fresh + performant | More complex |

**Recommendation**: **Hybrid approach**
- Elasticsearch for primary search (updated every 30 seconds)
- Cache popular searches for 5 minutes
- Use cache invalidation on price/availability changes
- Customer impact: 99% within 5 minutes, 100% within 30 minutes

---

### 3. **Monolithic vs Microservices**

**Booking.com Characteristics**:
- High interdependency (search → booking → inventory)
- Complex transactions (distributed commits)
- Monolithic patterns: Single database transaction needed

**Decision**: **Service-Oriented Architecture (hybrid)**
```
- Booking service: Monolith (complex logic, tight consistency)
- Search service: Separate (independent reads)
- Inventory service: Separate (event-driven updates)
- Notification/Review: Separate (async, decoupled)
```

---

### 4. **Database Sharding Strategy**

**Problem**: 1.8 TB of booking data, can't fit in single database

**Options**:

| Sharding Key | Pros | Cons |
|--------------|------|------|
| **User ID** | User data locality | Uneven distribution (power users) |
| **Booking ID** | Even distribution | Cross-shard queries for user bookings |
| **Property ID** | Property locality | Complex analytics queries |
| **Time-based** | Archival friendly | Hot/cold data imbalance |

**Recommendation**: **Composite key: (User ID, Booking ID)**
- Primary shard by user ID (10 shards for 500M users)
- Within shard: sorted by booking ID for range queries
- Handles: "Show my bookings" queries locally
- Analytics: Use read replicas with different sharding for reporting

---

### 5. **Payment Processing Sync**

**Problem**: Booking confirmation before payment settlement

**Solutions**:

| Approach | Pros | Cons |
|----------|------|------|
| **Synchronous** | Transactional integrity | High latency (3-5s), single point of failure |
| **Asynchronous** | High availability | Compensation logic needed, eventual consistency |
| **Two-Phase Commit** | ACID guarantees | Distributed deadlocks risk |

**Recommendation**: **Asynchronous with compensation**
```
1. Create booking (reserved)
2. Async: charge payment
3. On success: confirm booking
4. On failure: refund (automatic) + notify user
5. Retry policy: exponential backoff, max 3 attempts
```

---

### 6. **Real-time Notifications vs Throughput**

**Decision**: Event-driven with eventual delivery
```
- Kafka topics: 1 partition per property for ordering
- Push notifications: fire-and-forget (best effort)
- Email: guaranteed delivery, retry up to 24h
- SMS: immediate attempt, fallback to email
```

---

## 6. Deep Dive: Critical Components

### 6.1 Inventory Management System

```
┌─────────────────────────────────────────────┐
│      Hotel Property                         │
│  (10M room instances)                       │
└────────┬────────────────────────────────────┘
         │
    ┌────▼─────────────────────────────────┐
    │  Inventory Cache (Redis)              │
    │  Key: prop_id:date:room_type          │
    │  Value: {available: 5, held: 2}       │
    │  TTL: 1 minute                        │
    └────┬─────────────────────────────────┘
         │
    ┌────▼─────────────────────────────────┐
    │  Inventory DB (PostgreSQL)            │
    │  Partitioned by date (daily)          │
    │  Indexed by: property_id, date        │
    └────┬─────────────────────────────────┘
         │
    ┌────▼─────────────────────────────────┐
    │  Event Log (Kafka)                    │
    │  Topics: availability-updates         │
    │  1 partition per property (ordering)  │
    └───────────────────────────────────────┘
```

**Booking Flow with Inventory**:
```
1. USER SEARCHES
   - Check cache (availability for search dates)
   - Cache hit rate: 80%
   - Query time: <10ms (cache) vs 200ms (DB)

2. USER CLICKS "CHECK AVAILABILITY"
   - Real-time check against DB with row lock
   - Lock timeout: 5 seconds
   - Fallback to cache if lock timeout

3. USER HOLDS BOOKING (Checkout)
   - Decrement available count
   - Create hold record (expires in 15 min)
   - Update cache immediately (optimistic)
   - Async: write to event log

4. USER COMPLETES PAYMENT
   - Confirm hold → permanent booking
   - Release hold if payment fails
   - Trigger inventory update event

5. PROPERTY UPDATES AVAILABILITY
   - Push update to inventory service
   - Event: {property_id, date, delta}
   - Cache invalidation (publish to Redis pub/sub)
   - All search instances update cache within 1s
```

**Overbooking Logic**:
```
max_available = physical_rooms + predictive_cancellation_forecast
  where predictive_cancellation_forecast = historical_cancellation_rate × bookings_in_period

Example:
- Physical rooms: 50
- Bookings: 45
- Cancellation rate: 10%
- Overbook buffer: 45 × 0.1 = 4.5 ≈ 4
- Max available to sell: 50 + 4 = 54

Risk: 4 overbookings/50 = 8% oversell rate
Mitigation: 
- Buy alternative property inventory (partnerships)
- Pay penalty to overbooking insurance
```

---

### 6.2 Search & Discovery System

```
┌──────────────────────────────────────┐
│    Search Request                    │
│  {location, dates, guests,           │
│   filters, sort}                     │
└────────┬─────────────────────────────┘
         │
    ┌────▼────────────────────────────┐
    │  Query Parser & Validator       │
    │  - Normalize location (geocode) │
    │  - Validate dates               │
    │  - Parse filters                │
    └────┬───────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  Cache Lookup (Redis)                     │
    │  Key: md5(location:dates:filters:sort)    │
    │  Hit rate: 20% (popular searches)         │
    │  TTL: 5 minutes                           │
    └────┬──────────────────────────────────────┘
         │ MISS
    ┌────▼──────────────────────────────────────┐
    │  Elasticsearch Query                      │
    │  Filters:                                 │
    │  - Location (geo-spatial)                 │
    │  - Price range                            │
    │  - Rating (aggregation)                   │
    │  - Amenities (boolean)                    │
    │  - Availability (date ranges)             │
    └────┬──────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  Ranking & Scoring                        │
    │  - TF-IDF score (text relevance)          │
    │  - Popularity score (booked %/month)      │
    │  - Rating score (normalized 0-1)          │
    │  - Distance score (from location)         │
    │  - Price score (normalized)               │
    │  - Conversion lift (ML model)             │
    │                                           │
    │  Final Score = sum(weights × scores)      │
    │  w = [0.2, 0.2, 0.2, 0.1, 0.15, 0.15]    │
    └────┬──────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  Availability Cross-Check                 │
    │  - Query Redis cache for each result      │
    │  - Mark as "low availability" if <2 rooms │
    │  - Filter out unavailable properties      │
    └────┬──────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  Sort & Paginate                          │
    │  - Return top 20 results per page         │
    │  - Support offset pagination              │
    │  - Cache page results                     │
    └────┬──────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  Response Assembly                        │
    │  {                                        │
    │    "properties": [...],                   │
    │    "total_count": 5000,                   │
    │    "filters_applied": {...},              │
    │    "sort_order": "relevance",             │
    │    "page": 1,                             │
    │    "last_updated": timestamp              │
    │  }                                        │
    └───────────────────────────────────────────┘
```

**Search Performance Optimizations**:

1. **Caching Strategy** (80/20 rule)
   ```
   20% of locations: Paris, New York, London, Tokyo, Dubai
   These 20% drive 80% of searches
   → Cache these separately with 24h TTL
   ```

2. **Elasticsearch Tuning**
   ```
   Index structure:
   - Primary shard: by location (country level)
   - Replicas: 2 (for redundancy + read scaling)
   - Refresh interval: 30s (balance freshness vs throughput)
   - Query timeout: 2s (fallback to cache if slow)
   ```

3. **Autocomplete**
   ```
   Prefix tree: trie data structure
   - Popular locations: top 10K cities
   - Stored in Redis with high priority
   - Query: O(k) where k = prefix length
   - Example: "par" → 50 suggestions in <10ms
   ```

---

### 6.3 Booking & Payment System

```
BEGIN BOOKING TRANSACTION
│
├─→ Validate user & payment method
│   └─→ Call payment gateway (3DS verification)
│
├─→ Lock inventory (optimistic lock version)
│   └─→ Check booking table row version
│
├─→ Create booking record
│   {
│     id: UUID
│     user_id: sharded_key
│     property_id: 12345
│     check_in: 2024-01-15
│     check_out: 2024-01-20
│     room_type: "deluxe_double"
│     price: 500.00
│     status: "PENDING_PAYMENT"
│     payment_intent_id: "pi_xxx"
│     hold_expires: NOW + 15 min
│     version: 1
│   }
│
├─→ Async Event: Publish "booking_created"
│   └─→ Kafka topic: bookings
│       └─→ Subscribers: inventory, notifications, analytics
│
├─→ Async: Process Payment
│   ├─→ Call Stripe/PayPal API
│   ├─→ Webhook: Payment confirmed
│   └─→ Update booking status → CONFIRMED
│
└─→ Return immediately with booking reference
    (confirmation happens asynchronously)
```

**Distributed Transaction Pattern (Saga)**:

```
Booking Service
    ↓
    Create booking (PENDING_PAYMENT)
    ↓
    1. PAYMENT_PROCESSING
       ├─ Payment succeeds → 2. INVENTORY_RESERVE
       └─ Payment fails → COMPENSATION: Delete booking, release hold
    ↓
    2. INVENTORY_RESERVE
       ├─ Inventory available → 3. BOOKING_CONFIRMED
       └─ Inventory unavailable → COMPENSATION: Refund payment, delete booking
    ↓
    3. BOOKING_CONFIRMED
       ├─ Send confirmation email
       └─ Update analytics

Each step is idempotent:
- Can retry without side effects
- Uses idempotency keys (booking_id)
```

**Payment Reconciliation**:

```
Challenge: Handle payment processing delays (takes 5-30 minutes)

Solution:
1. Booking created with status: PENDING_PAYMENT
2. User sees: "Payment processing, confirmation coming soon"
3. Async job checks payment status every 5 seconds
4. Once cleared, update booking → CONFIRMED
5. If failed after 24h, automatic cancellation and refund

Idempotency: 
- Key = booking_id
- Payment provider ensures single charge even if retry
```

---

### 6.4 Replica Consistency & Read Replica Lag

```
Primary DB                    
  (Bookings)                  
    ↓ (async replication)     
    ├─→ Replica 1 (lag: 100ms)
    ├─→ Replica 2 (lag: 150ms)
    └─→ Replica 3 (lag: 200ms)

Read routing:
- Write: always to primary
- Read: depends on consistency requirement
  ├─ Strong: read from primary (for recent bookings)
  ├─ Eventual: read from replicas (for analytics/searches)
  └─ Lazy: read from cache first, then primary if miss
```

---

## 7. Follow-up Questions & Answers

### Q1: How do you handle booking cancellations?

**A**: Cancellation Policy System

```
Cancellation Cancellation Scenario
    ↓
Parse property's policy:
├─ Free cancellation up to 7 days
├─ 50% refund if 3-7 days
└─ No refund if <3 days

If user cancels:
1. Check cancellation deadline
2. Calculate refund amount
3. Update booking status → CANCELLED
4. Publish cancellation event
   └─ Trigger: 
      - Refund payment (async)
      - Release inventory (immediate)
      - Send cancellation email
      - Update guest availability for recommendations

Race condition: User cancels while payment processing
- Solution: Idempotent cancellation (idempotency key)
- If booking still PENDING_PAYMENT, deny cancellation
- Force user to wait for payment confirmation first
```

---

### Q2: How do you prevent double-booking?

**A**: Inventory Hold & Pessimistic Locking

```
Method 1: Optimistic Locking (Recommended)
┌──────────────────────────────────────────┐
│ SELECT * FROM inventory                 │
│ WHERE property_id = 123                 │
│ AND date = '2024-01-15'                 │
│ Result: {available: 5, version: 42}     │
└──────────────────────────────────────────┘
              ↓
        User Books
              ↓
┌──────────────────────────────────────────┐
│ UPDATE inventory                         │
│ SET available = available - 1,           │
│     version = 43                         │
│ WHERE property_id = 123                 │
│ AND date = '2024-01-15'                 │
│ AND version = 42                         │
│                                          │
│ If 0 rows updated → retry or fail       │
└──────────────────────────────────────────┘

Method 2: Distributed Lock (Pessimistic)
- Use Redlock or Zookeeper
- Lock duration: 5 seconds
- Prevents concurrent writes to same inventory
- Trade-off: higher latency but guaranteed consistency

Method 3: Booking Hold System (Hybrid)
- When checkout starts: create hold record
- Hold expires after 15 minutes
- During hold: exclude rooms from other searches
- On payment success: convert hold to booking
- On timeout: release hold automatically
```

---

### Q3: How do you scale the search service?

**A**: Multi-Layer Caching & Sharding

```
Layer 1: CDN Cache (CloudFlare)
├─ Popular searches cached at edge
├─ TTL: 5 minutes
├─ Saves 80% of database queries
└─ Geographic cache optimization

Layer 2: Service-Level Cache (Redis)
├─ Key: md5(search_query)
├─ Value: sorted result list (top 100)
├─ TTL: 5 minutes
├─ Cluster: Redis Cluster (16 nodes)
└─ Sharding: consistent hashing

Layer 3: Elasticsearch Shards
├─ Primary shard by: geographic region
│  (Europe, Asia-Pacific, Americas, etc.)
├─ Index size: 50GB per region
├─ Replicas: 2 (for resilience + parallelism)
├─ Bulk refresh: every 30 seconds
└─ Search across multiple shards (fan-out)

Example search distribution:
Request → Load Balancer
         ↓
    Query Parser
         ↓
    Route by location
         ↓
    Paris search → ES shard: Europe
    Tokyo search → ES shard: Asia-Pacific
    (parallel search execution)
```

---

### Q4: How do you handle currency & dynamic pricing?

**A**: Multi-Currency Pricing Engine

```
Pricing Data Model:
┌─────────────────────────────────┐
│ pricing_rules                   │
├─────────────────────────────────┤
│ property_id: 123                │
│ room_type: "deluxe"             │
│ date: 2024-01-15                │
│ base_price: USD 100             │
│ currency: USD                   │
│ occupancy_multiplier: 1.2       │
│ demand_multiplier: 1.5          │
│ season_multiplier: 0.8          │
│ final_price: 100 × 1.2 × 1.5 × 0.8 = $144
└─────────────────────────────────┘

Dynamic Pricing Algorithm:
1. Base price (fixed by property manager)
2. × Occupancy multiplier (% booked)
   └─ High occupancy → higher price
3. × Demand multiplier (user demand for date)
   └─ ML model: predict demand based on:
      - Historical bookings
      - Events in city (concerts, conferences)
      - Weather forecast
      - Competitor pricing
4. × Season multiplier (peak/off-season)
5. × Currency exchange rate
6. Round to nearest $0.99

Price capping:
- Min price: base_price × 0.5
- Max price: base_price × 2.5
- Prevents wild price swings from losing users

Multi-Currency Handling:
1. Store all prices in USD internally
2. Convert to user's currency on display
   └─ Exchange rates updated hourly
3. Round to local currency rules
   └─ USD: $123.99
   └─ JPY: ¥13,500 (no decimals)
   └─ EUR: €117,50
4. Charge in user's currency
   └─ Stripe handles FX conversion
```

---

### Q5: How do you implement the review system?

**A**: Anti-Fraud Review System

```
Post-Review Validation:
┌─────────────────────────────────┐
│ 1. User eligibility             │
├─────────────────────────────────┤
│ ✓ Must have completed booking   │
│ ✓ Check-out date must be in past│
│ ✓ No review already posted      │
│ ✗ Block fraud patterns:         │
│   - Same user: same IP address  │
│   - Bulk reviews: >5 in 1 day   │
│   - Competitor IPs              │
└─────────────────────────────────┘
              ↓
┌─────────────────────────────────┐
│ 2. Content analysis             │
├─────────────────────────────────┤
│ ✓ Text length: 10-1000 chars    │
│ ✓ Sentiment analysis (NLP)      │
│ ✗ Detect spam keywords          │
│ ✗ Detect promotional content    │
│ ✗ Detect hate speech            │
│ ✗ Detect competitor sabotage    │
└─────────────────────────────────┘
              ↓
┌─────────────────────────────────┐
│ 3. Machine learning scoring     │
├─────────────────────────────────┤
│ Inputs:                         │
│ - Review sentiment              │
│ - Reviewer history              │
│ - Property category mismatch    │
│ - Linguistic anomalies          │
│                                 │
│ Score: 0-100 (trustworthiness)  │
│ If score < 30: HOLD FOR REVIEW  │
│ If score < 10: AUTO REJECT      │
└─────────────────────────────────┘
              ↓
    Publish or Queue for Moderation

Review Display Algorithm:
┌─────────────────────────────────┐
│ Reviews to show:                │
├─────────────────────────────────┤
│ 1. Most recent (default sort)   │
│ 2. High ratings (if user seeks) │
│ 3. Low ratings (if user seeks)  │
│ 4. Verified purchase badge      │
│                                 │
│ Hide criteria:                  │
│ - Duplicate reviews (same user) │
│ - Blacklisted reviewers         │
│ - Property disputes (pending)   │
│ - Unusually helpful negative    │
│   (owned by competitor?)        │
└─────────────────────────────────┘

Rating Calculation:
Overall = (verified_reviews_sum / count) × weight_boost
where:
- verified_reviews: reviews from bookings
- weight_boost: favor recent + verified reviews
- decay: older reviews weighted less
- Example:
  (4.2 average from 500 reviews) × 1.1 boost = 4.6 displayed
```

---

### Q6: How do you handle peak traffic (New Year's Eve booking surge)?

**A**: Traffic Management & Auto-Scaling

```
Expected Load During Peak:
Normal: 100k req/s
Peak: 500k req/s (5x surge)

Solution: Multi-Layer Load Shedding

Layer 1: Frontend Rate Limiting
├─ Per IP: 100 req/s
├─ Per user: 50 req/s
├─ Return 429 "Too Many Requests" beyond limits

Layer 2: Queue System (Kafka)
├─ Request queue depth threshold: 10k
├─ Beyond threshold: return "wait" page
├─ Client polls for completion every 5 seconds
├─ Timeout: 5 minutes

Layer 3: Circuit Breaker
├─ If inventory service latency > 500ms:
│  └─ Serve cached results (accept stale data)
├─ If database unavailable:
│  └─ Failover to read replicas
└─ If both down: maintenance page

Auto-Scaling Rules:
┌──────────────────────────────────────┐
│ Metric           Threshold   Action   │
├──────────────────────────────────────┤
│ CPU util         > 70%      +2 pods   │
│ Memory util      > 80%      +1 pod    │
│ Request latency  > 500ms    +3 pods   │
│ Queue depth      > 5k       +5 pods   │
│                                       │
│ Scale down when metrics normalize    │
└──────────────────────────────────────┘

Expected auto-scaling: 100 → 500 instances in 5 minutes

Cache Invalidation During Peak:
├─ Disable cache updates (accept stale data)
├─ Keep cache TTL aggressive: 1 minute
├─ Background: write updates to queue
└─ Post-peak: batch apply updates

Database Connection Management:
├─ Pool size: 10k connections
├─ Max per instance: 20 connections
├─ Overflow: queue to wait list
└─ Timeout: 30 seconds (return error, retry)
```

---

### Q7: How do you ensure data durability & disaster recovery?

**A**: Backup & Replication Strategy

```
Primary Replication:
┌────────────────────┐
│  Primary DB        │
│  (US-EAST)         │
└────────┬───────────┘
         │ (async replication)
    ┌────┴──────────────────────────────┐
    │                                   │
┌───▼──────────┐  ┌─────────────────┐
│ Replica 1    │  │ Replica 2       │
│ (US-EAST)    │  │ (US-WEST)       │
│ lag: 100ms   │  │ lag: 500ms      │
└──────────────┘  └─────────────────┘
         │                   │
    ┌────▼───────────────────▼──────┐
    │  S3 Backup (hourly snapshot)   │
    │  Retention: 30 days            │
    │  Encryption: AES-256           │
    └───────────────────────────────┘

Disaster Recovery:
- RTO: 1 hour (switch to warm standby in US-WEST)
- RPO: 5 minutes (recovery point)

If primary fails:
1. Detect (health check: no heartbeat for 30s)
2. Failover decision (requires quorum approval)
3. Promote replica to primary
4. Update DNS (propagation: 5-10 minutes)
5. Application reconnect (within 30s)

Data Loss Scenario:
- If primary corrupted: restore from S3 backup (5 mins)
- If S3 also corrupted: use historical snapshots
- Maximum acceptable loss: 5 minutes (booking events)
```

---

## Architecture Decision Records (ADRs)

### ADR-1: Why PostgreSQL for bookings instead of NoSQL?

**Decision**: Use PostgreSQL for booking/inventory, Elasticsearch for search

**Rationale**:
- Bookings require ACID transactions (payment safety)
- Foreign key constraints (user ↔ booking ↔ property relationships)
- Complex queries (bookings by date range, user analytics)
- Sharding complexity in NoSQL (handles better in SQL)

**Trade-off**:
- Scalability ceiling: 10M bookings/day sustainable
- Beyond that: consider sharding + NoSQL hybrid

---

### ADR-2: Why eventual consistency for inventory?

**Decision**: Accept ~0.05% overselling rate for high availability

**Rationale**:
- Strong consistency via locks: 99.95% availability, but 5xx latency increases
- Eventual consistency: 99.999% availability, <200ms latency, <0.05% overselling
- Overselling mitigated by: insurance, partner inventory access

**Customer Impact**:
- 999 out of 10,000 bookings: "Sorry, just sold out" message
- Compensation: rebook at competitor + $50 travel credit
- Cost: ~$50k/day vs $5M/day revenue = acceptable

---

### ADR-3: Why Kafka for events, not direct DB writes?

**Decision**: Event-driven architecture with Kafka

**Rationale**:
- Decouples services (booking ↔ inventory ↔ notification)
- Handles backpressure (if notification service slow, booking still completes)
- Replay events for debugging/analytics
- Multiple consumers (inventory update, email, analytics, BI)

---

## Conclusion

This system design demonstrates:

1. **Scale**: 100k+ QPS, 1M+ properties, 500M+ users
2. **Reliability**: 99.95% uptime, disaster recovery
3. **Performance**: <200ms search, <300ms booking
4. **Complexity**: Distributed transactions, inventory management, dynamic pricing
5. **Trade-offs**: Consistency vs availability, freshness vs latency

**Key Takeaways**:
- Cache aggressively (80/20 rule applies to queries)
- Event-driven architecture for loose coupling
- Async processing for non-critical paths
- Accept calculated trade-offs (e.g., 0.05% overselling)
- Monitor and alert on all critical metrics

---

**References**:
- Designing Data-Intensive Applications (Kleppmann)
- System Design Interview (Xu)
- Release It! (Nygard)
- Microservices Patterns (Richardson)
