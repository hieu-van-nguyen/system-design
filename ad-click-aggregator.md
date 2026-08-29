# System Design: Ad Click Aggregator (Meta/Facebook)

> Context: Facebook serves ~10M ads. Advertisers need real-time and historical metrics — clicks, impressions, CTR, spend — aggregated by ad/campaign/time window. The system must be accurate, handle billions of events/day, and surface data to advertisers within minutes.

---

## 1. Functional Requirements

**Core (In Scope)**
- Record every ad click and impression event (user, ad, timestamp, device, geo)
- Aggregate metrics in near real-time (< 1 min latency): clicks, impressions, CTR, spend
- Serve aggregated metrics to advertisers via dashboard and API
- Support flexible query dimensions: by ad, campaign, advertiser, date range, geo, device
- Support time-window granularities: 1-min, 5-min, 1-hour, 1-day
- Detect and deduplicate fraudulent/duplicate clicks
- Support reprocessing historical data (backfill after bug fix)
- Deliver accurate billing data (clicks billed to advertiser account)

**Out of Scope**
- Ad serving / auction system (determines which ad to show)
- Ad targeting / ML ranking
- Advertiser budget pacing
- User-level attribution (conversion tracking)

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Throughput | **1M click events/sec** peak; 500B events/day |
| Aggregation Latency | Metrics available to advertisers within **< 1 minute** (near real-time) |
| Query Latency | Dashboard aggregation queries < **1 second** p99 |
| Accuracy | **< 0.01% error** for billing metrics; slightly relaxed for real-time display |
| Exactly-Once | No double-counting of clicks for billing |
| Availability | 99.99% for ingestion pipeline; 99.9% for query serving |
| Durability | Zero event loss after acknowledgment |
| Late Events | Handle events arriving up to **24 hours** late (mobile offline, network delay) |
| Scale | 10M ads; 10K advertisers; petabyte-scale historical storage |

---

## 3. Back of Envelope Calculation

**Event Volume**
- Facebook: ~2B users/day; avg 250 ad impressions/user = **500B impressions/day**
- Click-through rate ~0.1% → **500M clicks/day**
- Combined events: ~1T events/day → **~11.5M events/sec** peak (assume 3× avg at peak)
- Simplified for this design: **1M events/sec** sustained

**Storage**
- Raw click event: ~500 bytes (user_id, ad_id, timestamp, IP, device, geo, session)
- 500M clicks/day × 500 bytes = **250 GB/day** raw click data
- 500B impressions/day × 200 bytes = **100 TB/day** → store sampled (1:100) or aggregated only
- Aggregated row: ~200 bytes; 10M ads × 1440 min/day = 14.4B rows → **2.9 TB/day** pre-aggregated
- Cold storage retention: 2 years → **~2 PB** (compressed ~5:1 → 400 TB)

**Aggregation**
- 1-min window × 10M ads = 10M aggregation buckets to update per minute
- At 1M events/sec: each event touches 1 aggregation bucket → **1M bucket updates/sec**
- Redis: 10M keys × 100 bytes = **1 GB** in-memory per 1-min window (very manageable)

**Query**
- 10K advertisers × avg 100 queries/min = **~17 queries/sec** (analytical, not OLTP)

---

## 4. High-Level Design

```
                     Ad Served to User
                           │
                           ▼ click / impression
┌──────────────────────────────────────────────────┐
│                 Click Collector                   │
│         (edge servers, geo-distributed)           │
│    validates, deduplicates, enriches event        │
└──────────────────────┬───────────────────────────┘
                       │ Kafka (raw_clicks topic)
                       ▼
┌──────────────────────────────────────────────────┐
│              Apache Kafka                         │
│   Partitioned by ad_id (hot ad distribution)     │
│   Topics: raw_clicks, raw_impressions             │
└──────┬─────────────────────────┬─────────────────┘
       │                         │
       ▼ (real-time path)        ▼ (batch path)
┌─────────────────┐     ┌───────────────────────┐
│  Stream         │     │   Batch Layer          │
│  Processor      │     │   (Apache Spark)       │
│  (Apache Flink) │     │   Hourly / Daily jobs  │
│  1-min windows  │     │   Source of truth      │
└──────┬──────────┘     └───────────┬────────────┘
       │                            │
       ▼                            ▼
┌─────────────────┐     ┌───────────────────────┐
│  Real-time      │     │  Data Warehouse        │
│  Aggregation    │     │  (ClickHouse / Druid)  │
│  Store          │     │  Petabyte-scale OLAP   │
│  (Redis + OLAP) │     └───────────────────────┘
└──────┬──────────┘                 │
       └──────────────┬─────────────┘
                      ▼
           ┌──────────────────────┐
           │  Query Service       │
           │  (Aggregation API)   │
           │  Advertisers /       │
           │  Billing System      │
           └──────────────────────┘
```

### Kappa Architecture Decision

We use **Kappa Architecture** (stream-only) rather than Lambda (stream + batch separate):
- Single pipeline: Kafka + Flink handles both real-time and reprocessing (replay from Kafka / S3)
- Simpler: no dual code paths to maintain
- Reprocessing: replay raw events through same Flink job → overwrites aggregated results
- Trade-off: requires longer Kafka retention (7 days) or S3 as event log for full reprocessing

### Core Components

| Component | Responsibility |
|---|---|
| **Click Collector** | Edge ingestion: validate, enrich, deduplicate, push to Kafka |
| **Apache Kafka** | Durable event log; partitioned by `ad_id` for ordering |
| **Apache Flink** | Stateful stream processing; windowed aggregation; exactly-once |
| **Redis** | Real-time aggregation buffer (1-min / 5-min windows) |
| **ClickHouse / Apache Druid** | OLAP store for sub-second analytical queries |
| **S3 / HDFS** | Raw event archive; source for backfill and audit |
| **Query Service** | Unified API; merges real-time (Redis) + historical (OLAP) results |
| **Fraud Detection** | Async pipeline; flags invalid clicks; compensating adjustments |

---

## 5. Deep Dive

### 5.1 Click Collector — Edge Ingestion

**Responsibilities:**
1. **Validate** event: ad_id exists, user_id valid, timestamp within ±1h of server time
2. **Enrich**: resolve IP → geo (city/country), normalize user agent → device type
3. **Deduplicate** (first pass): bloom filter per ad per 5-min window — fast drop of obvious dupes
4. **Acknowledge** client immediately after Kafka write (async processing downstream)

**Deduplication Strategy:**
```
Client sends click with: client_event_id = SHA256(user_id + ad_id + session_id + page_load_ts)

Collector:
  1. Check Redis bloom filter: key = "bloom:<ad_id>:<5min_bucket>"
     - If present → likely duplicate → drop (with small false-positive rate ~0.1%)
     - If absent → add to bloom filter, forward to Kafka
  2. Bloom filter per ad per 5-min window, TTL = 30 min
  3. True deduplication (billing-grade) done downstream in Flink with exact set
```

**Kafka Message Schema:**
```json
{
  "event_id": "uuid-v4",
  "event_type": "CLICK | IMPRESSION",
  "ad_id": "ad_12345",
  "campaign_id": "camp_67890",
  "advertiser_id": "adv_111",
  "user_id": "u_222",         // hashed for privacy
  "timestamp": 1723843200000, // epoch ms (event time, not server time)
  "server_timestamp": 1723843201500,
  "device": "mobile_ios",
  "geo_country": "US",
  "geo_city": "New York",
  "ip_hash": "sha256_of_ip"
}
```

---

### 5.2 Kafka Partitioning Strategy

**Problem:** Aggregate by `ad_id` → need events for same ad on same partition (ordering). But hot ads (Super Bowl ad) get millions of clicks/min → single partition becomes bottleneck.

**Solution: Two-Tier Partitioning**

```
Normal ads:   partition_key = ad_id
              → all events for ad go to same partition
              → Flink consumer processes in order per partition

Hot ads:      partition_key = ad_id + random_suffix (0-9)
              → fan-out to 10 partitions
              → Flink aggregates per sub-partition, then merges in final step
              → Hot ad detection: if ad's events/sec > threshold → switch to hot mode
```

**Hot Ad Detection:**
```
Collector tracks events/sec per ad_id in Redis counter (1-min window)
If count > 10K events/min → mark ad as "hot" in Redis (TTL 10 min)
Collector checks hot flag → applies suffix sharding
```

**Kafka Config:**
- Topics: `raw_clicks` (1000 partitions), `raw_impressions` (1000 partitions)
- Retention: 7 days (for reprocessing); raw events also archived to S3 within 1h
- Replication factor: 3; `acks=all` for durability

---

### 5.3 Flink Stream Processing — Windowed Aggregation

**Exactly-Once Semantics:**
- Flink checkpoint to S3 every 60s (barriers aligned across partitions)
- Kafka consumer offset committed only after checkpoint complete
- Output sink (ClickHouse): idempotent upsert by `(ad_id, window_start, window_end)`
- Guarantees: no double-counting, no data loss across Flink restarts

**Window Types:**
```
Tumbling Window (1-min): Non-overlapping; [0:00, 1:00), [1:00, 2:00)
  → Used for real-time dashboard

Tumbling Window (1-hour, 1-day): For historical rollups
  → Computed by aggregating 1-min windows (hierarchical rollup)

Session Window: Future use — user engagement per session
```

**Flink Job (pseudocode):**
```java
DataStream<ClickEvent> clicks = env
    .addSource(new FlinkKafkaConsumer<>("raw_clicks", schema, props))
    .assignTimestampsAndWatermarks(
        WatermarkStrategy
            .<ClickEvent>forBoundedOutOfOrderness(Duration.ofMinutes(5))
            .withTimestampAssigner((e, ts) -> e.getTimestamp())
    );

clicks
    .keyBy(ClickEvent::getAdId)
    .window(TumblingEventTimeWindows.of(Time.minutes(1)))
    .allowedLateness(Duration.ofHours(1))    // accept late events up to 1h
    .sideOutputLateData(lateOutputTag)       // route very late to separate topic
    .aggregate(new ClickAggregator())        // count, sum spend
    .addSink(new ClickHouseSink());          // upsert by (ad_id, window_start)
```

**ClickAggregator output:**
```json
{
  "ad_id": "ad_12345",
  "campaign_id": "camp_67890",
  "window_start": "2026-08-16T19:00:00Z",
  "window_end": "2026-08-16T19:01:00Z",
  "click_count": 1523,
  "impression_count": 48200,
  "ctr": 0.0316,
  "spend_cents": 91380,
  "unique_users": 1498,   // HyperLogLog estimate
  "by_device": {"mobile": 1100, "desktop": 423},
  "by_country": {"US": 890, "UK": 320, "CA": 313}
}
```

---

### 5.4 Handling Late Events

**Problem:** Mobile users click an ad offline; event arrives 12 hours later. Aggregation window already closed and committed to billing.

**Strategy: Watermark + Allowed Lateness + Compensating Adjustment**

```
Watermark = max(event_timestamp seen) - 5 min slack
  → Flink considers events within 5 min of watermark as on-time

Allowed Lateness = 1 hour:
  → Window stays open for 1h after watermark passes window end
  → Late events trigger window re-computation → UPSERT to ClickHouse
  → Downstream billing reads latest value (idempotent upsert)

Very Late Events (> 1 hour, up to 24h):
  → Routed to side output (late_clicks Kafka topic)
  → Separate Flink job processes late_clicks
  → Inserts compensating adjustment records:
     { ad_id, window, click_count_delta: +5, adjustment_reason: "late_arrival" }
  → Billing service applies adjustments in next billing cycle

Events > 24h old:
  → Rejected; logged for fraud analysis
  → Client shown as acknowledged (don't retry)
```

---

### 5.5 Fraud Detection

**Problem:** Click farms, bots, and competitors clicking ads to drain budgets.

**Detection Pipeline (async, not in critical path):**

```
raw_clicks → Flink fraud job (parallel to aggregation job)
    │
    ├── Rule-based filters (fast, synchronous):
    │     - Same user_id + ad_id within 10 min → duplicate
    │     - > 50 clicks/min from same IP → bot
    │     - Click from datacenter IP range → invalid
    │
    └── ML scoring (async, 5-min lag):
          - Feature vector: click velocity, geo anomaly, device fingerprint, user history
          - Model: gradient boosted trees → fraud_score 0-1
          - If score > 0.9 → mark INVALID; compensating credit to advertiser
```

**Invalid Click Handling:**
- Invalid clicks published to `invalid_clicks` topic
- Aggregation pipeline reads both valid and raw counts
- Billing uses valid-only count; advertiser dashboard shows both (transparency)
- Credits issued daily for confirmed invalid clicks

---

### 5.6 Query Service — Unified View

**Problem:** Advertisers query "clicks for campaign X last 7 days, by day, by device." Data spans real-time store (Redis) and historical OLAP (ClickHouse).

**Merge Strategy:**
```
Query: campaign_67890, last 7 days, by day

Query Service:
  1. Historical data (> 5 min ago) → ClickHouse query
     SELECT date, SUM(click_count), SUM(impression_count)
     FROM ad_metrics_1min
     WHERE campaign_id='camp_67890'
       AND window_start >= NOW() - INTERVAL 7 DAYS
       AND window_start < NOW() - INTERVAL 5 MINUTES
     GROUP BY date

  2. Real-time data (last 5 min) → Redis
     HGET agg:ad:<ad_id>:<current_1min_window> click_count

  3. Merge and return unified result
```

**ClickHouse Schema:**
```sql
CREATE TABLE ad_metrics_1min (
    ad_id           String,
    campaign_id     String,
    advertiser_id   String,
    window_start    DateTime,
    window_end      DateTime,
    click_count     UInt64,
    impression_count UInt64,
    spend_cents     UInt64,
    unique_users_hll AggregateFunction(uniq, UInt64),  -- HyperLogLog for unique users
    by_device       Map(String, UInt64),
    by_country      Map(String, UInt64),
    is_adjusted     UInt8 DEFAULT 0  -- 1 if late-event adjustment applied
) ENGINE = ReplacingMergeTree(window_start)  -- idempotent upserts
PARTITION BY toYYYYMM(window_start)
ORDER BY (advertiser_id, campaign_id, ad_id, window_start);

-- Pre-aggregated rollups (materialized views)
CREATE MATERIALIZED VIEW ad_metrics_hourly
ENGINE = SummingMergeTree
PARTITION BY toYYYYMM(window_start)
ORDER BY (advertiser_id, campaign_id, ad_id, window_start)
AS SELECT
    ad_id, campaign_id, advertiser_id,
    toStartOfHour(window_start) AS window_start,
    toStartOfHour(window_end)   AS window_end,
    sum(click_count)     AS click_count,
    sum(impression_count) AS impression_count,
    sum(spend_cents)     AS spend_cents
FROM ad_metrics_1min
GROUP BY ad_id, campaign_id, advertiser_id, window_start, window_end;
```

**Query Result Caching:**
- Dashboard queries cached in Redis: key = `query:<hash(params)>`, TTL = 60s
- Invalidated on new data write for the queried time range

---

### 5.7 Real-Time Aggregation Buffer (Redis)

**Structure per 1-min window:**
```
Key:   agg:<ad_id>:<window_start_epoch_min>
Type:  Redis Hash
Fields:
  click_count     → HINCRBY (atomic increment)
  impression_count → HINCRBY
  spend_cents     → HINCRBY
  device:mobile   → HINCRBY
  device:desktop  → HINCRBY
  country:US      → HINCRBY
TTL:   10 minutes (window + buffer for late events)
```

**Flink writes to Redis (real-time path):**
```
On each processed event → HINCRBY agg:<ad_id>:<window> click_count 1
Query Service reads Redis for last 5 min of data
Flink periodically flushes Redis aggregates to ClickHouse (every 60s)
```

**Unique user count (HyperLogLog):**
```
PFADD unique:<ad_id>:<window> <user_id_hash>
PFCOUNT unique:<ad_id>:<window>  → ~0.81% error rate, uses only 12KB per counter
```

---

### 5.8 Backfill / Reprocessing

**Scenario:** Bug in Flink aggregation job double-counts mobile clicks for 3 hours. Fix deployed. Must reprocess.

**Kappa Reprocessing Flow:**
1. Identify affected time window: `2026-08-16T09:00 → 12:00`
2. Read raw events from S3 (Kafka archived to S3 via Kafka Connect → S3 Sink)
3. Deploy fixed Flink job reading from S3 (not Kafka) for affected window
4. Output written to ClickHouse with `REPLACE` / `ReplacingMergeTree` → overwrites bad rows
5. Redis real-time buffer: TTL expired → no action needed
6. Billing system notified: regenerate invoices for affected window

```
S3 raw events (partitioned by date/hour):
  s3://events/clicks/year=2026/month=08/day=16/hour=09/part-*.parquet
  s3://events/clicks/year=2026/month=08/day=16/hour=10/...
  s3://events/clicks/year=2026/month=08/day=16/hour=11/...

Flink reprocessing job reads S3 → same aggregation logic → ClickHouse upsert
```

---

### 5.9 Data Model Summary

```
raw_clicks (S3 + Kafka, immutable log)
    │
    ▼
ad_metrics_1min (ClickHouse, ReplacingMergeTree — idempotent)
    │
    ├── ad_metrics_hourly (ClickHouse materialized view)
    └── ad_metrics_daily  (ClickHouse materialized view)

billing_adjustments (PostgreSQL)
  → late-event and fraud compensating entries
  → joined with ad_metrics at billing time
```

---

## 6. Trade-offs Discussion

### 6.1 Kappa vs Lambda Architecture

**Problem:** We need both real-time metrics (< 1 min) and accurate historical aggregations. Do we maintain two separate pipelines or one unified stream?

| Dimension | Lambda (stream + batch) | Kappa (stream only, current) |
|-----------|------------------------|------------------------------|
| Code paths | Two (Flink + Spark) | One (Flink) |
| Accuracy | Batch = source of truth; stream = approximate | Stream = truth; replay from S3 for corrections |
| Reprocessing | Swap batch output | Replay raw events through same Flink job |
| Operational overhead | High (two clusters to manage) | Medium (one Flink cluster) |
| Latency | Batch: hours; stream: seconds | Stream: seconds; reprocess: hours (same) |
| Storage | Duplicate (stream state + batch output) | Single raw log (S3) + stream state |

**Decision: Kappa**
```
Why Lambda was considered:
- Batch layer (Spark) is extremely accurate for billing reconciliation
- Historically Flink exactly-once was harder to guarantee

Why Kappa wins:
- Flink exactly-once checkpointing is production-proven (2024+)
- Single codebase: bug fixes applied once, reprocessing uses same fixed job
- Lambda's dual-layer reconciliation adds latency to billing cycles
- Savings: ~40% infrastructure cost (no separate Spark cluster for batch)

When Lambda would win:
- If aggregation logic is fundamentally different for real-time vs. historical
  (e.g., real-time uses approximate counts, historical uses exact joins)
- At 100× our scale where Flink reprocessing of raw events is too slow
```

---

### 6.2 Kafka Partitioning: by ad_id vs by advertiser_id vs random

**Problem:** At 1M events/sec across 10M ads, partitioning strategy determines whether aggregation is possible without cross-partition state.

| Partitioning Key | Aggregation Efficiency | Hot Partition Risk | Ordering |
|-----------------|----------------------|-------------------|---------|
| **ad_id (current, with hot-ad sharding)** | High (same ad → same partition) | Super Bowl ad → single bottleneck | Per-ad |
| **advertiser_id** | Medium (need per-ad grouping within partition) | Big advertisers monopolize partition | Per-advertiser |
| **random (round-robin)** | Low (must shuffle to aggregate per ad) | None (perfect balance) | None |
| **campaign_id** | Medium | Hot campaigns | Per-campaign |

**Decision: ad_id partitioning with hot-ad fan-out (10 sub-partitions)**
```
Why ad_id?
- Flink keyBy(ad_id) processes all events for one ad on one task
- No shuffle needed for aggregation → low latency, no network overhead
- Flink checkpoints per-key state (click counts) without distributed join

Hot ad problem (Super Bowl):
- One ad: 500K events/sec → single partition at 1 GB/s → bottleneck
- Solution: ad_id + random_suffix(0-9) → 10 partitions
- Flink aggregates 10 sub-streams → final merge step
- Hot ad detection via Redis counter: events/sec per ad in 1-min window

Trade-off: Hot-ad fan-out adds one aggregation layer and code complexity.
Justified because: <0.01% of ads are "hot" at any time, but they represent
~20% of total event volume → partitioning pressure is real.
```

---

### 6.3 Deduplication: Bloom Filter (Probabilistic) vs Exact Set vs Idempotency Key

**Problem:** Same click event delivered multiple times (network retry, client-side double-fire). Billing requires < 0.01% error. 1M events/sec to deduplicate.

| Approach | Accuracy | Memory | Throughput | False Positive Risk |
|----------|----------|--------|-----------|---------------------|
| **Bloom filter (fast path, current)** | ~99.9% (0.1% false positive) | 12 MB per window | 1M/sec | Yes (drops valid events at 0.1% rate) |
| **Redis exact set (SADD)** | 100% | ~50 GB per day window | ~200K/sec | None |
| **Flink state (exact, in-pipeline)** | 100% | ~10 GB per Flink cluster | ~500K/sec | None |
| **Idempotency key + DB lookup** | 100% | DB storage | ~50K/sec | None |

**Decision: Bloom filter at edge (fast path) + Flink exact dedup (billing path)**
```
Two-tier approach:
Tier 1 — Bloom filter (Click Collector, edge):
  - Drops obvious duplicates (retry storms, client double-fire)
  - 0.1% false positive rate: drops 1 in 1000 legitimate clicks
  - Acceptable for impression metrics (approximate is fine)
  - NOT used for billing — billing tier handles separately

Tier 2 — Flink exact dedup (within pipeline):
  - event_id key in Flink keyed state (RocksDB backend)
  - Window: 24h (max late arrival)
  - State size: ~10M events × 50 bytes = 500 MB per Flink task
  - Guarantees < 0.01% billing error

Why not exact set (Redis SADD) at edge?
- 1M events/sec × 24h window × 50 bytes/event_id = 4.3 TB Redis memory
- Redis Cluster would need 50+ nodes just for dedup keys → prohibitive
- Network RTT to Redis from edge server: 5-10ms → adds latency to every event

False positive cost of bloom filter:
- 0.1% of 500M clicks/day = 500K valid clicks dropped
- These are impression clicks (not billing) → acceptable UX loss
- Billing tier catches them via Kafka replay → no revenue impact
```

---

### 6.4 Windowing: Tumbling vs Sliding vs Session Windows

**Problem:** Advertisers want metrics at 1-min, 5-min, 1-hour granularities. Should we use tumbling or sliding windows? Should we pre-aggregate or compute on query?

| Window Type | Use Case | State Size | Overlap | Late Event Handling |
|-------------|----------|------------|---------|---------------------|
| **Tumbling (current)** | Fixed buckets: 1-min, 1-hr, 1-day | 1× | None | Allowed lateness + side output |
| **Sliding** | "Last N minutes" rolling average | N× (1 window per slide step) | High | Complex watermark management |
| **Session** | Per-user engagement burst | Unbounded | Dynamic | Gap timeout required |

**Decision: Tumbling windows at 1-min granularity, roll up to coarser on query**
```
Why tumbling?
- Non-overlapping → each event belongs to exactly one window
- State size: 10M ads × 1 bucket at a time = manageable
- Late event: one window re-opens, not cascading re-computation across overlapping windows

Why not sliding (e.g., "last 15 minutes")?
- Sliding(15 min, 1 min): 15 active windows per key simultaneously
- State: 15× larger → 150M buckets × 100 bytes = 15 GB per task
- Alternatively: computed at query time by summing last 15 tumbling 1-min buckets
  → Much cheaper; computed in ClickHouse in <100ms

Hierarchical rollup:
1 min (Flink) → stored in ClickHouse
1 hour (ClickHouse materialized view) → aggregates 60 × 1-min rows
1 day (ClickHouse materialized view) → aggregates 24 × 1-hour rows

Advertiser asks for 7-day total → sum 7 × daily rows = 7 row scan
Advertiser asks for last 3 hours → sum 180 × 1-min rows = 180 row scan
Both < 100ms in ClickHouse with ORDER BY (ad_id, window_start)
```

---

### 6.5 Real-time Store: Redis vs Flink State Only vs ClickHouse Direct Write

**Problem:** Last 5 minutes of data need to be queryable in < 200ms while Flink is still aggregating. Where does the live window live?

| Approach | Latency | Consistency | Complexity |
|----------|---------|-------------|------------|
| **Redis HINCRBY (current)** | <5ms reads | Eventual (Flink writes async) | Medium |
| **Flink queryable state** | <50ms (experimental) | Strong (in-pipeline) | High (unstable API) |
| **ClickHouse direct write (every event)** | 100–500ms | Strong | Low (but high write load) |
| **No real-time store (poll ClickHouse)** | 1–5s (flush interval) | Strong | Low |

**Decision: Redis as real-time buffer, ClickHouse for history**
```
Why not ClickHouse for every event?
- ClickHouse optimized for batch inserts (millions of rows at once)
- Per-event inserts: write amplification on MergeTree → slow merges, high I/O
- At 1M events/sec: 1M small inserts/sec would degrade ClickHouse severely
- Correct pattern: Flink batches → bulk insert every 60s

Why not Flink queryable state?
- API is experimental in Flink; production use discouraged
- State backend (RocksDB) not optimized for point queries
- Would require maintaining a query server per Flink task → complex topology

Redis chosen because:
- HINCRBY is O(1) and atomic → 1M increments/sec per node (handles our load)
- Reads for last-5-min window: ~100 HGET calls per query → <5ms
- TTL auto-expires old windows → self-cleaning, no manual eviction
- Flink bulk-flushes Redis aggregates to ClickHouse every 60s

Trade-off: Redis is not the source of truth. If Redis node fails:
- Flink re-processes from Kafka checkpoint (60s of data max)
- Last 5-min window unavailable for ~60s (dashboard shows "updating")
- Acceptable given 99.9% availability SLA for query serving
```

---

### 6.6 Late Event Handling: Allowed Lateness vs Compensating Adjustments vs Reject

**Problem:** Mobile click events arrive 2–24 hours late. Billing window already closed. How do we handle without double-counting or losing revenue?

| Strategy | Accuracy | Billing Complexity | User Impact |
|----------|----------|-------------------|-------------|
| **Reject all late events (> window close)** | Undercount revenue | Simple | Advertisers underpay |
| **Allowed lateness + re-open window (current for < 1h)** | High | Medium (idempotent upsert) | Minimal |
| **Compensating adjustment records (> 1h, current)** | Exact | Medium (daily reconciliation) | Delayed credit |
| **Large allowed lateness (24h window stays open)** | Exact | Complex (state explosion) | None |

**Decision: Three-tier late event strategy**
```
Tier 1 — On-time (< 5 min late):
  Watermark absorbs: events processed normally in their window

Tier 2 — Mildly late (5 min – 1 hour):
  allowedLateness(1h): window stays open, re-computes on each late event
  ClickHouse: ReplacingMergeTree upserts updated row
  Billing: reads latest row value → no extra logic needed
  State cost: 1h × 10M ads × 100 bytes = 1 GB per task (acceptable)

Tier 3 — Very late (1h – 24h):
  Side output → late_clicks Kafka topic
  Separate Flink job: aggregates late events by (ad_id, original_window)
  Inserts adjustment records: { ad_id, window, delta_clicks: +N, reason: "late_mobile" }
  Billing system applies adjustments in next daily cycle

Tier 4 — Ancient (> 24h):
  Reject: timestamp validation at Click Collector
  Log for fraud analysis: legitimate events rarely arrive this late

Trade-off of Tier 3 approach:
- Advertisers get credits/debits in next billing cycle, not immediately
- Delay: up to 24h for late-event reconciliation
- Acceptable because: billing cycles are monthly; 24h adjustment within cycle
- Alternative (keep window open 24h): Flink state = 24h × 10M ads × 100 bytes = 24 GB
  per Flink task × many tasks → state backend overload, slow checkpoints
```

---

### 6.7 Fraud Detection: In-Pipeline (Synchronous) vs Async Side Pipeline

**Problem:** Invalid clicks must not reach billing. But fraud detection (ML scoring) takes 5–10ms and runs async. Should fraud detection block the click or run independently?

| Approach | Latency Impact | Billing Accuracy | Missed Fraud |
|----------|---------------|-----------------|--------------|
| **Synchronous (block on fraud score)** | +5–10ms/event = 10× latency spike | Perfect (fraud never counted) | None |
| **Async side pipeline (current)** | 0 (no latency added) | Good (credits issued after detection) | ~5-min window |
| **Rule-based only (no ML)** | 0 | Moderate (misses sophisticated fraud) | Significant |
| **Post-hoc batch fraud sweep** | 0 | High (full ML on all events) | Hours of lag |

**Decision: Async side pipeline for ML scoring; synchronous for rule-based**
```
Why not synchronous ML scoring?
- 1M events/sec × 10ms ML latency = pipeline would require 10K parallel ML workers
- ML model inference adds tail latency (P99: 50ms) → stalls Flink checkpoints
- Kafka consumer lag would spike under ML backpressure

Hybrid chosen:
Synchronous (Click Collector, zero latency cost):
  - Same IP > 50 clicks/min → drop immediately (stateless rule, O(1))
  - Datacenter IP → drop (IP blocklist in memory)
  - Timestamp too far in future/past → drop
  These catch ~80% of bot traffic without ML

Async ML pipeline (parallel Flink job, 5-min lag):
  - Features: user history, click velocity, device fingerprint, geo anomaly
  - Output: invalid_click_ids to compensation topic
  - Billing: subtract invalid clicks in next reconciliation pass

Trade-off: 5-minute window where fraud clicks are counted before detection.
Financial exposure: 5 min × advertiser's CPM → typically $0–$50 per fraud event.
Compensating credit issued within 24h → advertisers made whole.
Accepted because: synchronous ML would 10× our infrastructure cost.
```

---

### 6.8 Query Serving: Pre-Aggregate vs On-Demand Aggregation vs Materialized Views

**Problem:** Advertisers run diverse queries: "last 7 days by country," "last hour by device," "yesterday by campaign." Pre-compute everything vs. compute on query.

| Approach | Query Latency | Storage Cost | Flexibility | Freshness |
|----------|--------------|-------------|-------------|-----------|
| **Pre-aggregate all dimensions (cube)** | <10ms | Exponential (2^dimensions) | Low (only pre-computed combos) | Near-real-time |
| **On-demand ClickHouse scan** | 100ms–2s | Only raw 1-min rows | High (any dimension combo) | Near-real-time |
| **Materialized views (selected rollups, current)** | <100ms | Medium (selective pre-computation) | Medium | Near-real-time |
| **Redis query cache (current, on top of above)** | <5ms | Small (TTL 60s) | Same as underlying | 60s stale |

**Decision: Selective materialized views + Redis query cache**
```
Why not full pre-aggregation (OLAP cube)?
- Dimensions: ad_id × campaign × advertiser × device × country × date
- 6 dimensions × 10 cardinalities each = 10^6 combinations
- 10M ads × 10^6 = 10^13 pre-computed cells → petabytes just for aggregations
- Impossible at this scale

Why not pure on-demand ClickHouse?
- "Last 7 days by device for campaign 67890" → 7 × 1440 = 10,080 rows scanned
- With ClickHouse columnar + ORDER BY optimization: ~100ms
- But 10K advertisers × 100 queries/min = 1,000 concurrent ClickHouse queries
- At complex queries: tail latency climbs to 2–5s → violates 1s SLA

Materialized views chosen for high-traffic patterns:
- ad_metrics_hourly (aggregates 1-min → 1-hour): most common query range
- ad_metrics_daily (aggregates 1-hour → 1-day): billing and long-range queries
- These cover ~80% of query patterns

Redis query cache (60s TTL):
- Same advertiser opens dashboard → multiple near-identical queries
- Cache hit rate: ~70% (same query within 60s is common for dashboards)
- Reduces ClickHouse load by 70% during peak dashboard viewing hours

Trade-off: 60s stale data in dashboard.
Acceptable because: real-time metrics (< 5 min) come from Redis aggregation buffer,
not ClickHouse. The 60s cache applies to historical ranges (> 5 min ago)
where 60s staleness is imperceptible to advertisers.
```

---

### 6.9 Consistency Model Across the System

**Deliberate consistency decisions per component:**

| Component | Consistency | Rationale |
|-----------|------------|-----------|
| Click ingestion (Kafka acks=all) | **Durable at-least-once** | Zero event loss after ack; duplicates handled downstream |
| Bloom filter dedup (edge) | **Approximate** (0.1% false positive) | 0.1% valid clicks dropped acceptable for speed |
| Flink aggregation (exactly-once) | **Strong** (idempotent upsert) | Billing requires < 0.01% error |
| Redis real-time buffer | **Eventual** (Flink writes async) | 60s flush interval; dashboard latency acceptable |
| ClickHouse historical store | **Eventually consistent** (ReplacingMergeTree) | Background merge may serve old rows briefly |
| Fraud adjustments | **Eventual** (24h lag) | Compensating credits; monthly billing cycle absorbs |
| Query cache (Redis, 60s TTL) | **Stale** (intentional) | Historical range; 60s lag imperceptible |
| Billing final reconciliation | **Strongly consistent** (PostgreSQL) | Financial records require ACID; batch nightly |

**Key interview insight:** Billing accuracy (< 0.01% error) and real-time latency (< 1 min) are seemingly contradictory requirements. The solution is layered consistency: approximate fast path for display, exactly-once pipeline for counting, compensating adjustments for late/fraud corrections. Each layer has a different consistency contract, and billing is the only layer that requires true exactness — reconciled offline where ACID is affordable.

---

## 7. Follow-Up Topics

### Exactly-Once vs. At-Least-Once Trade-Off
- Flink with checkpointing gives **exactly-once within the pipeline**
- External sinks (ClickHouse): use **idempotent upsert** — effectively exactly-once end-to-end
- Trade-off: checkpoint interval (60s) = max data loss on unrecoverable failure
- Billing uses batch reconciliation over ClickHouse + PostgreSQL adjustments = true exactness

### Privacy (GDPR / User-Level Data)
- Raw events store `hashed_user_id` (SHA256 + salt), not raw user_id
- IP stored as hashed; geo derived at collection time (IP never persisted)
- User deletion request → all raw events with that user_id hash deleted from S3 within 30 days
- Aggregated metrics are anonymous (user_id not present) → no deletion needed there

### Advertiser-Level Multi-Tenancy
- Each advertiser only sees their own data; enforced at Query Service (JWT scoped to advertiser_id)
- ClickHouse partition by `advertiser_id` → query pruning
- Rate limit per advertiser: 100 API calls/min (Redis token bucket)

### Approximate vs. Exact Counts
| Metric | Approach | Error |
|---|---|---|
| Click count | Exact (HINCRBY + ClickHouse SUM) | 0% |
| Unique users | HyperLogLog (PFADD) | ~0.81% |
| Impression count | Exact | 0% |
| CTR | Derived (clicks / impressions) | 0% |
| Spend | Exact (billing-grade) | 0% |
- Unique users: HyperLogLog saves 99%+ memory vs. exact set; error acceptable for analytics

### Monitoring & Alerting

| Metric | Alert Threshold |
|---|---|
| Kafka consumer lag | > 100K messages → P1 |
| Flink checkpoint failure | > 2 consecutive → P0 |
| Click count anomaly | > 3σ from historical baseline for any ad → fraud alert |
| Pipeline end-to-end latency | > 5 min → P1 |
| ClickHouse query latency p99 | > 2s → P2 |
| Aggregation error rate | > 0.001% billing discrepancy → P0 |

### Comparison: Lambda vs. Kappa for This System

| | Lambda | Kappa |
|---|---|---|
| Batch layer | Separate Spark batch jobs | No separate batch; Flink reprocesses |
| Real-time layer | Flink / Storm | Flink |
| Code duplication | High (2 codebases) | Low (1 Flink job) |
| Reprocessing | Swap batch results | Replay from S3/Kafka |
| Complexity | High | Medium |
| **Verdict** | Overkill | **Preferred for this system** |

---

## Summary

| Component | Technology |
|---|---|
| Edge Ingestion | Custom Click Collector (Go/Java) on geo-distributed edge nodes |
| Deduplication (fast) | Redis Bloom Filter |
| Event Bus | Apache Kafka (1000 partitions, 7-day retention) |
| Stream Processing | Apache Flink (exactly-once, event-time windowing) |
| Real-time Buffer | Redis (HINCRBY, PFADD HyperLogLog) |
| OLAP Store | ClickHouse (ReplacingMergeTree, materialized rollups) |
| Cold / Archive Storage | AWS S3 (Parquet, partitioned by date/hour) |
| Batch Reprocessing | Flink reading from S3 |
| Query API | Go/Java service; merges Redis + ClickHouse |
| Fraud Detection | Flink side pipeline + ML scoring service |
| Monitoring | Prometheus + Grafana + PagerDuty |
| Tracing | Jaeger (trace per event_id through pipeline) |
