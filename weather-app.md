# System Design: Weather Monitoring Platform

> FAANG-level interview guide — IoT sensor ingestion, time-series storage, geospatial aggregation, and real-time heat map generation.
> Asked frequently at **Amazon**. Think: Weather.com, Dark Sky, Weather Underground, AWS IoT + QuickSight.

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | **Ingest sensor readings** from distributed weather stations (temperature, humidity, pressure, wind, precipitation) |
| FR-2 | **Query current conditions** for a location (lat/lon or city name) — nearest sensor(s) |
| FR-3 | **Historical data queries**: time-series data for a sensor or region over a date range |
| FR-4 | **Aggregations**: min, max, avg, percentile per sensor / region / time window (hourly, daily, monthly) |
| FR-5 | **Heat map generation**: render temperature (or other metrics) overlaid on a geographic map |
| FR-6 | **Alerts**: notify users when temperature/conditions cross a threshold at their location |
| FR-7 | **Trend analysis**: "warmer than average for this time of year", "wettest March in 10 years" |
| FR-8 | (Optional) **Forecast**: short-range prediction (24–72h) from historical + real-time patterns |
| FR-9 | (Optional) **Station health monitoring**: detect offline/malfunctioning sensors |
| FR-10 | (Optional) **Data quality**: flag and impute anomalous/missing readings |

**Out of scope:** Numerical weather prediction (NWP) models, satellite data ingestion, radar processing.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 1M weather sensors globally; each reports every 30 seconds = **33M readings/sec** |
| **Availability** | 99.99% for read APIs; ingestion can tolerate brief pauses with buffering |
| **Latency** | Current conditions p99 < 100ms; heat map tile p99 < 200ms; historical queries < 2s |
| **Freshness** | Heat map updates within **60 seconds** of new sensor readings |
| **Consistency** | Eventual consistency acceptable; stale data (< 2 min) is fine for UI |
| **Storage** | Raw readings: 2 years online; 10 years cold archive |
| **Throughput** | 33M writes/sec ingest; 100K read QPS peak (consumer app usage) |
| **Accuracy** | Aggregations correct within ±0.1°C; spatial interpolation within ±0.5°C |
| **Geo-coverage** | Global; sensors as sparse as 1/100km² (rural) to 100/km² (urban) |

---

## 3. Back-of-Envelope Estimation

### Sensor Ingestion

```
Sensors                     = 1,000,000
Reporting interval          = 30 seconds
Raw write rate              = 1M / 30 = 33,333 writes/sec ≈ 33K writes/sec
Peak (burst on reconnect)   ≈ 100K writes/sec (e.g., mass reconnect after outage)

Payload per reading:
  sensor_id (8B) + timestamp (8B) + temperature (4B) + humidity (4B) +
  pressure (4B) + wind_speed (4B) + wind_dir (2B) + precipitation (4B) +
  lat (8B) + lon (8B) = ~60 bytes/reading

Raw ingest throughput       = 33K × 60 bytes ≈ 2 MB/sec (trivial network-wise)
```

### Storage

```
Raw readings (2 years online):
  33K reads/sec × 86,400 sec/day × 365 × 2 = 2.08 trillion readings
  × 60 bytes = 125 TB (raw, uncompressed)
  With time-series compression (Gorilla/Zstd): ÷10 = ~12.5 TB (2 years, online)

Aggregated data (pre-computed):
  Hourly avg per sensor: 1M sensors × 8,760 hours/year × 2 years × 60B ≈ 1 TB
  Daily avg per sensor:  1M sensors × 730 days × 2 years × 60B ≈ 88 GB
  Aggregates fit in fast storage easily

Cold archive (10 years):
  12.5 TB/2yr → 62.5 TB (10 years compressed) → S3 Glacier

Heat map tiles:
  Global map divided into zoom levels 0–15
  Tiles per zoom level 10: 2^10 × 2^10 = 1M tiles
  Each tile (PNG): ~5 KB
  Total tiles (all zoom levels): ~2M × 5 KB = 10 GB (regenerated hourly)
```

### Read Traffic

```
DAU (consumer app)          = 50M users
Avg sessions/day            = 3 (morning, midday, evening)
Requests/session            = 5 (current conditions + hourly forecast + map tiles)
Total requests/day          = 50M × 3 × 5 = 750M requests/day
Requests/sec (avg)          = 750M / 86,400 ≈ 8,700/sec
Peak (morning rush, 5×)    ≈ 43,500/sec → round to 50K read RPS
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                     SENSOR LAYER                                                 │
│  Weather Stations (1M)  │  Mobile Crowd-sourced  │  Partner APIs (NOAA, ECMWF)  │
└──────┬────────────────────────────┬──────────────────────────┬───────────────────┘
       │ MQTT / CoAP / HTTPS        │                          │ REST / SFTP
       ▼                            ▼                          ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│                     INGESTION LAYER                                              │
│  ┌──────────────────────┐    ┌──────────────────────────────────────────────┐   │
│  │  MQTT Broker          │    │  Ingestion API (REST/gRPC)                  │   │
│  │  (EMQX / HiveMQ)     │    │  - Auth / device registry check             │   │
│  │  - 33K msg/sec        │    │  - Schema validation                        │   │
│  │  - QoS 1 (at-least-  │    │  - Rate limiting per sensor                 │   │
│  │    once)              │    └──────────────────────────────────────────────┘   │
│  └──────────┬────────────┘                    │                                  │
└─────────────┼──────────────────────────────────┼──────────────────────────────── ┘
              │                                  │
              └───────────────┬──────────────────┘
                              ▼
              ┌───────────────────────────────────┐
              │     Kafka (Message Bus)            │
              │  Topic: sensor-readings            │
              │  Partitions: 256 (by sensor_id)   │
              │  Retention: 7 days                 │
              └───────────────┬───────────────────┘
                              │
          ┌───────────────────┼────────────────────────────┐
          │                   │                            │
          ▼                   ▼                            ▼
┌──────────────────┐ ┌─────────────────────┐  ┌───────────────────────────┐
│  Raw Storage     │ │  Stream Processor   │  │  Alert Engine             │
│  Writer          │ │  (Apache Flink)     │  │  (Flink CEP / Lambda)     │
│  (Time-Series DB)│ │  - Windowed agg     │  │  - Threshold detection    │
│  InfluxDB /      │ │  - Spatial index    │  │  - Notification Service   │
│  TimescaleDB     │ │    update           │  │    (SNS / FCM)            │
└──────────────────┘ └─────────────────────┘  └───────────────────────────┘
                              │
                    ┌─────────┴──────────────┐
                    │                        │
          ┌─────────▼──────────┐  ┌──────────▼──────────┐
          │  Aggregation Store  │  │  Spatial Index       │
          │  (ClickHouse)       │  │  (PostGIS / Redis    │
          │  - Hourly/daily agg │  │   Geo / S2 cells)   │
          │  - Per region agg   │  │  sensor_id → geo     │
          └────────────────────┘  └──────────┬───────────┘
                                             │
                                  ┌──────────▼──────────┐
                                  │  Heat Map Generator  │
                                  │  - IDW Interpolation │
                                  │  - Tile renderer     │
                                  │  - PNG → CDN         │
                                  └──────────┬───────────┘
                                             │
                              ┌──────────────▼────────────────────────────┐
                              │              CDN                           │
                              │  (CloudFront / Fastly)                    │
                              │  Cache heat map tiles (5 min TTL)         │
                              └──────────────┬────────────────────────────┘
                                             │
┌────────────────────────────────────────────▼────────────────────────────────────┐
│                         READ API SERVICE                                        │
│  GET /v1/conditions/{lat}/{lon}           → current conditions                  │
│  GET /v1/sensors/{id}/history            → time-series data                    │
│  GET /v1/heatmap/tiles/{z}/{x}/{y}.png   → map tile                            │
│  GET /v1/regions/{id}/aggregates         → hourly/daily stats                  │
└─────────────────────────────────────────────────────────────────────────────────┘
```

### Core API

```
// Sensor ingestion (IoT device → server)
POST /v1/ingest
{
  "sensorId": "ws-us-chi-0042",
  "timestamp": "2026-08-16T14:30:00Z",
  "lat": 41.8781, "lon": -87.6298,
  "temperature": 28.4,          // Celsius
  "humidity": 62.1,             // %
  "pressure": 1013.2,           // hPa
  "windSpeed": 12.3,            // km/h
  "windDirection": 245,         // degrees
  "precipitation": 0.0          // mm/h
}

// Current conditions (consumer app)
GET /v1/conditions?lat=41.8781&lon=-87.6298&radius=10km
Response: {
  temperature: 28.4, feelsLike: 30.1, humidity: 62,
  description: "Partly Cloudy", windSpeed: 12, windDirection: "WSW",
  station: { id, name, distanceKm: 1.2 }, updatedAt: "2026-08-16T14:30:00Z"
}

// Historical time-series
GET /v1/sensors/{sensorId}/readings?from=2026-08-01&to=2026-08-16&metric=temperature&resolution=hourly
Response: { readings: [{ timestamp, value }] }

// Regional aggregates
GET /v1/regions/chicago/aggregates?metric=temperature&period=daily&from=2026-08-01
Response: { aggregates: [{ date, min, max, avg, p50, p95 }] }

// Heat map tile (XYZ slippy map tiles — used by Leaflet / Mapbox)
GET /v1/heatmap/tiles/10/411/594.png?metric=temperature&time=latest
Response: PNG image (256×256 px), Cache-Control: max-age=300

// Alerts
POST /v1/alerts { userId, lat, lon, metric: "temperature", operator: "gt", threshold: 35 }
DELETE /v1/alerts/{alertId}
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: IoT Ingestion Protocol — MQTT vs. CoAP vs. HTTP vs. gRPC

| Protocol | Connection Model | Overhead | QoS / Reliability | IoT Native |
|----------|----------------|---------|------------------|-----------|
| **MQTT (Recommended)** | Persistent TCP | Very low | ✅ QoS 0/1/2 | ✅ Designed for IoT |
| CoAP | UDP | Minimal | Partial (confirmable) | ✅ |
| HTTP/1.1 | Short-lived | High | ❌ (fire-and-forget) | ❌ |
| gRPC / HTTP/2 | Multiplexed | Medium | ✅ (streaming) | ❌ |

```
Why MQTT wins for weather stations:

Connection cost matters enormously at 1M sensors:
  HTTP: each sensor opens new TCP connection every 30s
    1M sensors / 30s = 33K new TCP handshakes/sec (SYN/SYN-ACK/ACK = 3 round trips)
    TLS handshake: additional 100ms per connection
    Total overhead: 33K × ~200ms = constant connection setup cost
    Compare: MQTT keeps one persistent TLS connection open indefinitely

  MQTT persistent connection:
    1 TCP connection per sensor, lives for months
    Heartbeat (PINGREQ/PINGRESP): lightweight keepalive
    1M sensors = 1M open connections → EMQX handles natively (event-driven, no thread per conn)

QoS levels (critical for weather data):
  QoS 0 (at-most-once): fire and forget — acceptable for non-critical telemetry
  QoS 1 (at-least-once): sensor retries until PUBACK received
    → No lost readings even on intermittent network
    → Duplicates handled by Kafka idempotent producer (de-duplicated on sensor_id + timestamp)
  QoS 2 (exactly-once): complex handshake — overkill for sensor data

CoAP (UDP-based, for constrained devices):
  Designed for microcontrollers with < 64KB RAM
  Weather stations typically have more resources → MQTT is fine
  UDP unreliable: CoAP's confirmable messages are complex re-transmission logic
  Use CoAP only for extremely constrained devices (soil sensors, water level sensors)

HTTP fallback (for non-IoT sources):
  Partner APIs (NOAA, Weather Underground): REST over HTTPS — they don't support MQTT
  Mobile crowd-sourcing: phones don't maintain persistent connections well
  → REST POST /v1/ingest (stateless, simple, good enough for low-volume sources)
  → REST clients publish to same Kafka topic via Ingestion API layer

Decision: MQTT (QoS 1) for dedicated weather stations + REST for all other sources.
  The persistent connection model is the key insight — 1M sensors × HTTP would not scale.
```

---

### Trade-Off 2: Time-Series Database — TimescaleDB vs. InfluxDB vs. Cassandra vs. DynamoDB

| Approach | Write Throughput | SQL Compatibility | Compression | Aggregation | Ops Complexity |
|----------|-----------------|------------------|------------|------------|---------------|
| **TimescaleDB (Recommended)** | 1M+ rows/sec | ✅ Full PostgreSQL | ✅ 10:1 | ✅ Continuous aggregates | Low (PG ecosystem) |
| InfluxDB | 1M+ rows/sec | ❌ Flux/InfluxQL | ✅ | ✅ Tasks | Medium |
| Cassandra | 1M+ rows/sec | ❌ CQL | ❌ Manual | ❌ Limited | High |
| DynamoDB | 1M+ writes/sec | ❌ | ❌ | ❌ | Low (managed) |

```
The key requirement: "query sensor X's temperature from T1 to T2 with hourly averages"

TimescaleDB (built on PostgreSQL):
  Hypertable: automatic time-based partitioning (chunks of 1 day)
    → SELECT ... WHERE time BETWEEN T1 AND T2 → only scans relevant chunks
    → Chunk exclusion: O(1) pruning; no scanning 2 years of data for a 1-day query

  Continuous aggregates (killer feature):
    Pre-computed materialized views refreshed incrementally
    hourly_stats maintained automatically as new data arrives
    Query: SELECT avg_temp FROM hourly_stats WHERE ...
    → Reads from pre-computed rows, not raw 33K rows/sec data
    → 1000× speedup for time-windowed aggregations

  Compression (columnar, per chunk):
    Time-series data compresses 10:1 with delta+RLE (temperature values change slowly)
    125 TB uncompressed → 12.5 TB compressed (2 years online)
    Decompresses transparently at query time

  SQL compatibility:
    All PostgreSQL tools work: psql, JDBC, SQLAlchemy, Grafana
    Complex queries (JOIN with sensor metadata table) are native SQL
    This matters: the team already knows SQL; no new query language

InfluxDB:
  Purpose-built for time-series → excellent write performance
  Flux query language: powerful but alien (not SQL)
  InfluxDB 3.x (Apache Parquet backend): modern, but ecosystem smaller
  No JOIN support natively → sensor metadata must be denormalized into measurement
  Choose InfluxDB if: pure time-series, no relational needs, team comfortable with Flux

Cassandra:
  Excellent write throughput; multi-region native
  Data model: partition key = (sensor_id, year_month); clustering = timestamp
  Range queries: fast per-sensor (partition key scan)
  Aggregations: Cassandra has no windowed aggregation → must do in application code
  Missing: continuous aggregates, compression, SQL
  Choose Cassandra if: multi-region is the primary constraint (weather data is global)

DynamoDB:
  Fully managed; GSI + DynamoDB Streams
  Time-series: partition = sensor_id, sort = timestamp → works for point lookups
  Range scan: fast per-sensor; aggregation: none built-in
  Cost: at 33K writes/sec × 1KB per item = 33,000 WCUs → expensive at this scale
    ($0.00065/WCU × 33,000 = $21.45/sec = $1.85M/month → not viable)

Decision: TimescaleDB. Continuous aggregates + compression + SQL make it the clear winner.
  The continuous aggregates answer is what separates a good answer from a great one.
```

---

### Trade-Off 3: Stream Processor — Flink vs. Kafka Streams vs. Spark Streaming vs. Lambda

| Approach | Stateful Processing | Event-Time Windows | Latency | Ops Complexity |
|----------|--------------------|--------------------|---------|---------------|
| **Apache Flink (Recommended)** | ✅ Native | ✅ Watermarks | < 1s | Medium |
| Kafka Streams | ✅ RocksDB | Partial | < 1s | Low |
| Spark Structured Streaming | ✅ Limited | ✅ | 1–5s (micro-batch) | Medium |
| AWS Lambda (event-driven) | ❌ | ❌ | < 500ms | Very low |

```
Why event-time windowing is critical for weather data:

Problem: sensors on cellular networks in remote areas send readings in bursts
  Sensor offline for 10 minutes → reconnects → sends 20 readings in 2 seconds
  With processing-time windows: all 20 readings land in current window → spike in aggregates
  With event-time windows: readings assigned to the original windows they belong to

  Flink watermark:
    Watermark = max(event_time_seen) - 30s
    Window fires when watermark passes window end time
    Late events (up to 30s late): merged into already-computed result (update aggregate)
    Very late events (>30s): side output for separate handling

  This is why Spark Streaming doesn't work as well:
    Micro-batch (1-5s batches): OK for aggregations, but not for alert detection
    CEP (Complex Event Processing) in Spark: complex, not native
    Flink CEP: built-in, declarative, handles sequences of events elegantly

Kafka Streams (strong alternative for simpler deployments):
  Runs inside application JVM — no separate cluster needed
  Stateful operations backed by RocksDB (embedded, replicated via Kafka)
  Event-time windows: supported but less mature than Flink
  CEP: not built-in (would need custom pattern matching)
  Choose Kafka Streams if: team wants operational simplicity and doesn't need CEP

AWS Lambda (for simple thresholding):
  Can process each Kafka record as it arrives
  No state: can't do windowed aggregations or sensor-to-sensor comparisons
  Cold start: 200ms for the first invocation → alert latency affected
  Alert deduplication: must use external Redis (Lambda is stateless)
  Limited to: simple per-reading threshold checks (not rate-of-change, not CEP)
  Use Lambda for simple alerting at low scale; Flink for all complex processing

Decision: Flink for windowed aggregations, S2 cell updates, and CEP-based alerts.
  Event-time watermarks for late-arriving sensor data is the key differentiator.
  Kafka Streams is a credible simpler alternative if CEP is not required.
```

---

### Trade-Off 4: Heat Map Rendering — Pre-Rendered Tiles vs. Server-Side on Demand vs. Client-Side

| Approach | Scale | Freshness | Customization | CDN Friendly |
|----------|-------|----------|--------------|-------------|
| **Pre-rendered PNG tiles (Recommended)** | ✅ Unlimited CDN | ✅ 5-min refresh | Medium | ✅ Best |
| Server-side on-demand (render at request time) | ❌ O(req × complexity) | ✅ Real-time | High | ❌ No |
| Client-side (WebGL, Canvas) | ✅ Client compute | ✅ Real-time | ✅ Full | N/A (data layer) |
| Vector tiles + client rendering | ✅ CDN | ✅ Real-time | ✅ Full | ✅ |

```
The heat map is the highest-cost feature at scale — get this right:

Pre-rendered PNG tiles (recommended for weather heat maps):
  Heat map generator runs every 5 minutes → renders all visible tiles → uploads to S3
  CDN caches PNG tiles: Cache-Control: max-age=300 (5 minutes)
  Client request: GET /heatmap/temp/z10/411/594.png
    → CDN cache hit: < 20ms, zero server compute per request
    → CDN cache miss: S3 serves static PNG → CDN caches it

  Scale: 50K read RPS × 0 server CPU = scales to any traffic level
  Pre-render cost: ~33 tiles/sec × 200ms render = 6.6 render-seconds/sec → 4 pods sufficient
  Time-bucket URLs: URL = /heatmap/{metric}/{z}/{x}/{y}/{time_bucket}.png
    where time_bucket = floor(unix_now / 300) → same URL for all requests in 5-min window
    → CDN caches by URL; no cache invalidation needed (TTL expires naturally)

Server-side on-demand (rejected for this scale):
  50K RPS × IDW interpolation per request → IDW per tile ≈ 50ms
  50K × 50ms = 2,500 CPU-seconds/sec → requires 2,500+ CPU cores continuously
  Not scalable; every tile request does expensive compute
  Only viable at: < 100 RPS, small map viewport, small sensor dataset

Client-side WebGL rendering (used by Windy.com):
  Server provides: vector data JSON (u, v components or temperature grid) per tile
    Much smaller than PNG: 50×50 float grid = 10KB vs 5KB PNG (comparable)
  Client WebGL: interpolates + colors in GPU → real-time interaction (pan/zoom smooth)
  Advantages: fully interactive, no refresh lag, arbitrary color schemes
  Disadvantages: client GPU required (CPU fallback is slow), complex JS implementation
  Best for: premium desktop experience (weather enthusiast apps)

Vector tiles + Mapbox GL:
  Pre-compute sensor positions + values as vector tile (MVT format) → CDN-cacheable
  Client: Mapbox GL JS applies heat map layer client-side
  Real-time updates: re-fetch vector tile every 60s → client re-renders
  Best for: sparse sensor overlays (not dense interpolated heat maps)

Decision: Pre-rendered PNG tiles for primary heat map (CDN scale + freshness trade-off acceptable).
  Client-side WebGL for premium "Windy-style" animated experience (follow-up feature).
  The time-bucket URL trick for CDN caching — mention it; interviewers love it.
```

---

### Trade-Off 5: Aggregation Store — ClickHouse vs. Apache Druid vs. AWS Redshift vs. BigQuery

| Approach | Write Latency | Query Speed | Real-Time Ingestion | Ops Cost |
|----------|--------------|------------|--------------------|----|
| **ClickHouse (Recommended)** | < 1s (async merge) | Excellent | ✅ | Medium |
| Apache Druid | < 1s (streaming) | Excellent | ✅ | High |
| AWS Redshift | Seconds (S3 COPY) | Good | ❌ Batch | Low |
| BigQuery | Seconds (streaming insert) | Excellent | Partial | Low (managed) |

```
Why ClickHouse for the aggregation store:

The access pattern: "avg temperature per sensor per hour, last 30 days"
  This is a column-oriented scan — the exact workload ClickHouse optimizes for

  Flink writes pre-aggregated rows to ClickHouse (hourly_aggregates table)
  Rate: 1M sensors × 1 row/hour = 16,667 rows/sec to ClickHouse → trivial

  Query: SELECT avg_temp FROM hourly_aggregates WHERE sensor_id IN (...) AND hour >= ...
  ClickHouse: reads only avg_temp column (columnar) → vectorized AVG → result in 50ms
  PostgreSQL same query: reads entire row (all 10 columns) for each row → 10× more I/O

  MergeTree engine:
    Data sorted by (sensor_id, hour) ORDER BY key
    Sparse index: one index entry per 8,192 rows (not per row)
    Range query on (sensor_id, time): index prunes to exact data range
    Late aggregates (Flink re-processes): AggregatingMergeTree merges duplicate keys

  Regional rollups (SummingMergeTree):
    hourly_cell_aggregates: writes per S2 cell per hour
    SummingMergeTree: automatically sums duplicate (cell_id, hour) rows during background merge
    No need for explicit UPDATE — append-only writes, engine handles deduplication

Apache Druid (strong alternative):
  Purpose-built for real-time OLAP with sub-second ingest from Kafka
  Druid Middle Manager: streams from Kafka → immediately queryable
  Advantage over ClickHouse: true real-time (not async merge delay)
  Disadvantage: complex architecture (ZooKeeper, Broker, Coordinator, Historical, Router)
  Operational complexity: 5 different node types to manage
  Choose Druid if: need < 5s query freshness AND team has Druid expertise

Redshift:
  Batch-oriented: COPY from S3 is primary ingest method (not streaming)
  For weather: new hourly aggregates must wait for batch load → 1-hour freshness delay
  SQL: full PostgreSQL-compatible → familiar
  Cost: ~$0.25/node-hour × managed hardware → predictable cost
  Choose Redshift if: batch historical analysis only (not real-time queries)

Decision: ClickHouse for aggregation store. MergeTree for sensor aggregates,
  SummingMergeTree for regional rollups. Druid is the alternative if sub-second
  freshness is a hard requirement for the aggregation store.
```

---

### Trade-Off 6: Geospatial Index — Redis GEO vs. PostGIS vs. S2/H3 vs. Elasticsearch Geo

| Approach | Nearest-N Query | Area Queries | Memory | Update Cost |
|----------|----------------|-------------|--------|------------|
| **Redis GEO (for station lookup)** | ✅ < 1ms | ❌ Limited | 100 MB (1M sensors) | ✅ Low |
| **PostGIS (for complex geo queries)** | ✅ 5–20ms | ✅ Rich | Disk | Medium |
| **S2/H3 cells (for heat map binning)** | ❌ | ✅ Excellent | Redis-cacheable | ✅ Very low |
| Elasticsearch Geo | ✅ | ✅ | High | Medium |

```
Three different geospatial problems need three different solutions:

Problem 1: "Find 5 closest sensors to (lat, lon)"
  Access pattern: triggered on every current-conditions API request (50K RPS)
  Requirement: < 5ms, highly concurrent

  Redis GEO (GEORADIUS):
    GEOADD sensors:active -87.63 41.88 "ws-chi-0042"  (done at sensor registration)
    GEORADIUS sensors:active -87.63 41.88 50 km ASC COUNT 5
    → Sub-millisecond; geohash-based internal representation
    → 1M sensors × ~100 bytes = 100 MB → fits entirely in RAM
    → Single Redis command = zero round trips = minimal latency
    Limitation: no filtering on sensor attributes (status, type) beyond the set itself
    Workaround: separate sets per status: sensors:active, sensors:degraded

  Why not PostGIS for this:
    PostGIS KNN query is excellent (5–20ms) but requires DB round-trip + connection overhead
    At 50K RPS: 50K × 20ms = connection pool saturation → need large PgBouncer pool
    Redis handles 50K concurrent GEORADIUS commands easily (single-threaded, in-memory)

Problem 2: "Find all sensors within a polygon (state/country boundary)"
  Access pattern: administrative region queries (100 RPS, low frequency)
  Requirement: accurate polygon intersection, complex shapes

  PostGIS:
    ST_Within(location, ST_GeomFromText('POLYGON(...)')) → returns all sensors inside
    GIST index on location → fast spatial join
    Handles: arbitrary polygons, point-in-polygon, buffer zones, intersection
    No equivalent in Redis (GEORADIUS is circle-only)

Problem 3: "Aggregate temperature by cell for heat map generation"
  Access pattern: Flink writes per reading; heat map generator reads all cells in viewport
  Requirement: hierarchical (different zoom levels), bulk read/write, not point lookups

  S2 Cell Index:
    cell_id = S2.cellFromLatLon(lat, lon, level=12)  (per reading, at ingest time)
    Redis: SETEX cell:{cellId}:temperature {ewma} EX 7200  (updated by Flink every 30s)
    Heat map: MGET cell:* for cells covering viewport bounding box
    Level hierarchy: zoom in → higher S2 level → smaller cells → more cells → more detail
    S2 advantage over H3: covers poles without distortion (H3 hexagons have issues at poles)

  H3 (Uber's hexagonal grid):
    More uniform cell areas (hexagons vs S2 quadrilaterals)
    Better for density-based analysis (all neighbors equidistant)
    Slight disadvantage: doesn't tile rectangles cleanly → edge cells partially outside viewport

Elasticsearch Geo:
  Supports: geo_distance queries, geo_shape queries, geo_grid aggregations
  More complex than Redis GEO for simple nearest-N
  Useful if: already using ES for search (add geo capabilities to existing cluster)
  Overhead: ES cluster for just geo queries is wasteful

Decision: Three tools for three problems.
  Redis GEO: nearest-station lookup (hot path).
  PostGIS: admin region queries (warm path, low volume).
  S2 cells in Redis: heat map cell aggregation (high write, bulk read).
  Telling an interviewer "one size fits all" would be wrong here.
```

---

### Trade-Off 7: Interpolation Method — IDW vs. Kriging vs. Neural Field

| Method | Computation | Accuracy | Uncertainty Estimates | Real-Time Viable |
|--------|------------|---------|----------------------|-----------------|
| **IDW (Recommended for real-time)** | O(k) per point | Good | ❌ | ✅ < 1ms/point |
| **Kriging (Recommended for batch)** | O(N³) | Excellent | ✅ | ❌ Too slow |
| Neural field (ML interpolation) | O(1) inference | Excellent | ✅ (with uncertainty) | ✅ < 10ms |
| Nearest-neighbor (Voronoi) | O(1) | Poor | ❌ | ✅ |

```
Why interpolation choice directly drives heat map quality:

Nearest-neighbor (Voronoi diagram):
  Each unsensed point gets value of its nearest sensor → hard polygon boundaries
  Temperature heat map looks like a jigsaw puzzle (sharp discontinuities at sensor boundaries)
  Not physically realistic: temperature gradients are smooth
  Only acceptable for: very dense sensor grids (≥1 sensor per km²)

IDW (Inverse Distance Weighting) — recommended for real-time:
  Weight = 1/d^p where d = distance to sensor, p = power (typically 2)
  Each unsensed point = weighted average of k nearest sensors
  Implementation:
    For each grid point P:
      candidates = find_k_nearest(P, k=8, max_dist=50km)
      weights = [1/dist²  for dist in candidate_distances]
      T(P) = sum(T(i) × w(i)) / sum(w(i))

  Speed: with k=8 and pre-indexed sensors → < 1ms per grid point
    256×256 tile = 65,536 points → 65ms per tile → acceptable for 5-min refresh cycle
  "Bull's-eye" artifact: isolated sensor creates circular gradient around it
    Mitigation: increase k (more neighbors) + use minimum bounding search radius

Kriging (Gaussian Process interpolation) — batch only:
  Assumes temperature follows a spatial correlation structure (variogram)
  Models: "sensors 10km apart have temperature correlation of 0.8; 100km apart: 0.2"
  Key advantage: provides confidence interval per point (where data is uncertain)
  Heat map can show: "±0.5°C confidence" in sensor-dense regions, "±3°C" in sparse regions
  O(N³) inversion of covariance matrix: 1,000 sensors per tile → 10⁹ operations → too slow
  
  Practical approximation: local Kriging (50 nearest sensors only → N=50 → O(50³) = 125K)
  Runtime: ~50ms per grid point → too slow for 256×256 real-time tile
  Use for: scientific-quality daily maps, historical analysis (offline batch)

Neural field (ML interpolation — the emerging approach):
  Train: neural network f(lat, lon, elevation, time_of_year) → temperature
  Input: 2 years of sensor data
  Inference: < 10ms for entire tile (batch inference on GPU)
  Accuracy: competitive with Kriging (has seen all spatial patterns during training)
  Uncertainty: can train with Gaussian output head → confidence intervals
  
  Limitations:
    Requires retraining when sensor network changes significantly
    May not generalize to extreme events (novel weather patterns)
    Model interpretability: hard to debug when wrong
  Best for: teams with ML infrastructure already in place

Decision: IDW for real-time tile generation (speed); Kriging for offline scientific
  products (accuracy + uncertainty quantification). Neural field as future upgrade path.
  Mentioning the bull's-eye artifact and how to mitigate it shows implementation depth.
```

---

## 6. Deep Dive

### 6.1 Sensor Ingestion at Scale

```
33K writes/sec across 1M sensors — two ingestion paths:

Path A — IoT native (MQTT, for dedicated weather stations):
  Protocol: MQTT over TLS (port 8883)
  QoS 1 (at-least-once) — sensor retries if no PUBACK received
  Broker: EMQX cluster (horizontally scalable, 1M concurrent connections)
  EMQX → Kafka bridge (built-in plugin): publishes each MQTT message to Kafka

Path B — REST/HTTP (for mobile crowdsourcing, partner APIs, manual stations):
  POST /v1/ingest (standard HTTPS)
  Validation → async publish to Kafka (return 202 Accepted immediately)

Kafka topic: sensor-readings
  Partitions: 256 (shard by sensor_id % 256)
  Replication factor: 3
  Retention: 7 days (allows reprocessing if consumer fails)
  Producer: idempotent (prevents duplicates on retry)

Device authentication:
  Each sensor has: device certificate (X.509) issued at manufacturing
  MQTT connection validated via mutual TLS (mTLS) — no username/password
  Device registry (DynamoDB): { sensorId, cert_thumbprint, status, location, owner }
  Unknown sensors → rejected at broker before reaching Kafka

Malformed / anomalous reading detection (at ingestion):
  Range validation: temperature ∈ [-90°C, 60°C], humidity ∈ [0, 100], etc.
  Out-of-range → route to quarantine topic (sensor-readings-quarantine)
  Valid → route to sensor-readings topic
```

---

### 6.2 Time-Series Storage

```
Requirements:
  - 33K writes/sec sustained
  - Point queries: "temperature at sensor X at time T"
  - Range queries: "temperature at sensor X from T1 to T2"
  - Aggregation: "avg temperature per hour for sensor X"
  - Retention: 2 years online, automatic downsampling + archival

Database choice: TimescaleDB (PostgreSQL extension) or InfluxDB

TimescaleDB schema:

CREATE TABLE sensor_readings (
  sensor_id   TEXT        NOT NULL,
  time        TIMESTAMPTZ NOT NULL,
  temperature REAL,
  humidity    REAL,
  pressure    REAL,
  wind_speed  REAL,
  wind_dir    SMALLINT,
  precipitation REAL
);

-- Hypertable: automatic time-based partitioning (chunks of 1 day)
SELECT create_hypertable('sensor_readings', 'time',
  partitioning_column => 'sensor_id',
  number_partitions => 256);

-- Compression (TimescaleDB native columnar compression)
SELECT add_compression_policy('sensor_readings', INTERVAL '7 days');
-- Compression ratio: ~10:1 for time-series data → 12.5 TB for 2 years

-- Continuous aggregates (pre-computed, auto-maintained)
CREATE MATERIALIZED VIEW hourly_stats
WITH (timescaledb.continuous) AS
SELECT
  sensor_id,
  time_bucket('1 hour', time) AS hour,
  AVG(temperature) AS avg_temp,
  MIN(temperature) AS min_temp,
  MAX(temperature) AS max_temp,
  AVG(humidity)    AS avg_humidity
FROM sensor_readings
GROUP BY sensor_id, hour;

-- Auto-refresh every 5 minutes
SELECT add_continuous_aggregate_policy('hourly_stats', ...);

-- Data retention: 2 years online, then export to S3
SELECT add_retention_policy('sensor_readings', INTERVAL '2 years');
```

**Write path optimization:**

```
Kafka consumer → TimescaleDB writer:
  - Consumer reads in batches of 5,000 records (100ms window)
  - Single COPY command (bulk insert): 5,000 rows in one round-trip
  - 33K/sec → 6.6 batches/sec → trivial for TimescaleDB
  - TimescaleDB inserts: designed for 1M+ rows/sec on modern hardware

Connection pooling: PgBouncer (transaction-mode) between writers and DB
Sharding: shard sensor_readings by sensor_id across 4 TimescaleDB nodes
  (each handles 250K sensors × 33K/4 = 8,250 writes/sec — well within capacity)
```

---

### 6.3 Geospatial Index & Nearest-Station Lookup

```
Problem: "Given lat/lon, find the N closest sensors with fresh data"

Data: 1M sensors with fixed lat/lon (stations don't move)

Option A — PostGIS (PostgreSQL extension):
  CREATE TABLE sensors (
    id      TEXT PRIMARY KEY,
    name    TEXT,
    location GEOGRAPHY(POINT, 4326),  -- WGS84
    status   TEXT
  );
  CREATE INDEX idx_sensor_geo ON sensors USING GIST(location);

  -- Find 5 closest sensors within 50km
  SELECT id, name,
    ST_Distance(location, ST_MakePoint(-87.63, 41.88)::GEOGRAPHY) AS dist_m
  FROM sensors
  WHERE ST_DWithin(location, ST_MakePoint(-87.63, 41.88)::GEOGRAPHY, 50000)
    AND status = 'active'
  ORDER BY dist_m ASC
  LIMIT 5;

  -- KNN index: O(log N) query → milliseconds for 1M sensors ✓

Option B — Redis GEO (for read-hot, low-latency lookups):
  GEOADD sensors:active -87.63 41.88 "ws-chi-0042"
  GEORADIUS sensors:active -87.63 41.88 50 km ASC COUNT 5
  → Sub-millisecond; entire index fits in memory (1M sensors × ~100B = 100 MB)

Option C — Google S2 / H3 cell indexing (for heat map generation):
  S2 hierarchy: each lat/lon maps to nested cell IDs at multiple resolutions
  Cell at level 12 ≈ 3.7 km² — good for sensor binning
  cell_id = S2.cellFromLatLon(lat, lon, level=12)
  Index: { cell_id → [sensorIds] }  (Redis Hash)
  → Lookup sensors in a region = query cells covering that region

Recommended:
  Station lookup API: Redis GEO (fastest, fits in memory)
  Heat map generation: S2/H3 cell aggregation
  Historical queries with geo-filter: PostGIS
```

---

### 6.4 Real-Time Aggregation (Flink Stream Processing)

```
Flink jobs consuming from Kafka: sensor-readings topic

Job 1 — Windowed aggregations (update aggregation store):
  Window: Tumbling 1-minute, 1-hour, 1-day
  Per sensor, per window:
    min(temperature), max(temperature), avg(temperature),
    min(humidity), max(humidity), avg(humidity), ...
  Output → ClickHouse (append new aggregate rows)

  Flink watermark: 30-second event-time watermark (handles late-arriving sensor data)
  Late data (> 30s late): allowed with side output → update aggregate if arrives within 5 min

Job 2 — S2 Cell aggregation (for heat map):
  For each reading:
    cell_id = S2CellId.fromLatLon(lat, lon, LEVEL_12)
    UPSERT cell_stats:{cell_id}:{metric} = EWMA(current_avg, new_value, alpha=0.3)
  Output → Redis (Exponentially Weighted Moving Average per cell)
  Frequency: every 30s (matches sensor reporting interval)
  Redis key TTL: 2 hours (stale cell auto-expires if no sensors report)

Job 3 — Alert detection (Flink CEP — Complex Event Processing):
  Load alert rules from DB: { userId, sensorId OR geofence, metric, operator, threshold }
  Per reading: check if reading matches any alert rule
  If match: publish to Kafka: alert-triggers → Notification Service
  Deduplication: don't re-alert within 1 hour for same (user, alert_rule) pair
    Redis SET alert:sent:{userId}:{alertId} NX EX 3600

Job 4 — Sensor health monitoring:
  Expected: each sensor reports every 30s
  Missing report window: 5 min
  Flink: keyed process function — timer fires if no event for sensor_id in 5 min
  Output → sensor-health topic → mark sensor offline in device registry
  Alert operations team if > 1% of sensors offline simultaneously
```

---

### 6.5 Aggregation Store (ClickHouse)

```
ClickHouse is ideal for:
  - Column-oriented storage (excellent for aggregation queries)
  - Billions of rows at sub-second query speed
  - High write throughput (millions of rows/sec)
  - Time-series aggregation patterns

Schema:

CREATE TABLE hourly_aggregates (
  sensor_id    String,
  hour         DateTime,
  lat          Float32,
  lon          Float32,
  avg_temp     Float32,
  min_temp     Float32,
  max_temp     Float32,
  avg_humidity Float32,
  avg_pressure Float32,
  reading_count UInt32
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (sensor_id, hour);

-- Regional rollup (pre-aggregated by S2 cell)
CREATE TABLE hourly_cell_aggregates (
  cell_id      UInt64,   -- S2 cell ID at level 10
  hour         DateTime,
  avg_temp     Float32,
  min_temp     Float32,
  max_temp     Float32,
  sensor_count UInt32
) ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (cell_id, hour);

Example queries (ClickHouse):

-- Daily temperature range for Chicago sensors (past 30 days)
SELECT toDate(hour) AS day, min(min_temp), max(max_temp), avg(avg_temp)
FROM hourly_aggregates
WHERE sensor_id IN (SELECT id FROM sensors WHERE city='Chicago')
  AND hour >= now() - INTERVAL 30 DAY
GROUP BY day ORDER BY day;
→ ~50ms for 1M-row scan (columnar, vectorized execution)

-- Hottest zip codes in the US right now
SELECT cell_id, avg_temp
FROM hourly_cell_aggregates
WHERE hour = toStartOfHour(now())
ORDER BY avg_temp DESC LIMIT 100;
→ <10ms (recent partition hot in cache)
```

---

### 6.6 Heat Map Generation

The heat map is the most visually compelling feature — and the most technically interesting.

```
Heat map pipeline:

Step 1 — Data collection (real-time, from Redis):
  For target geographic bounding box + zoom level:
    Determine which S2 cells at level 12 overlap the viewport
    Redis MGET cell_stats:{cell_id}:temperature for each cell
    → Returns avg temperature per cell (populated by Flink Job 2)

Step 2 — Spatial interpolation:
  Problem: sensors are not uniformly distributed (dense in cities, sparse in rural)
  A cell may have no sensor → must estimate from neighbors

  Algorithm: Inverse Distance Weighting (IDW)
    For each pixel (or cell) P with no sensor:
      T(P) = Σ [ T(i) / d(P,i)^p ] / Σ [ 1 / d(P,i)^p ]
      where: T(i) = temperature at sensor i
             d(P,i) = distance from P to sensor i
             p = power parameter (typically 2)
      Search radius: 50 km, max 8 nearest sensors

  Alternative: Kriging (geostatistical, more accurate, more compute)
    Use for daily/historical maps; IDW for real-time (lower latency)

Step 3 — Color mapping:
  Temperature range → color gradient:
    -40°C → deep purple
    -20°C → blue
      0°C → cyan
     15°C → green
     25°C → yellow
     35°C → orange
     45°C → deep red
  Apply alpha blending (semi-transparent overlay on map)

Step 4 — Tile rendering:
  Map divided into XYZ slippy tiles (256×256 px each, per Leaflet/Mapbox standard)
  For each tile at zoom Z, tile coordinates (X, Y):
    Compute bounding box in lat/lon
    Run IDW interpolation for grid of 256×256 points in bounding box
    Render PNG with color gradient applied
    Upload PNG to S3 → served via CDN

Step 5 — CDN delivery:
  URL: /heatmap/temperature/z10/411/594.png
  CDN: Cache-Control: max-age=300 (5-minute TTL, matches update frequency)
  Pre-render: top 1,000 most-viewed tiles pre-generated before cache expires
  On-demand: other tiles generated on first request (< 200ms)

Tile generation rate:
  Active tiles at zoom 10: ~10,000 (visible world with data)
  Regeneration: every 5 minutes → 10,000 tiles / 300s = 33 tiles/sec (trivial)
  Workers: 4 tile-renderer pods → each handles ~8 tiles/sec
```

---

### 6.7 Interpolation Deep Dive (IDW vs Kriging)

```
Inverse Distance Weighting (IDW):
  + Simple, fast (<1ms per point)
  + No assumptions about spatial correlation
  - "Bull's eye" effect: isolated sensors create circular artifacts
  - Accuracy degrades in data-sparse regions

Kriging (Gaussian Process Regression):
  + Statistically optimal interpolation
  + Provides confidence intervals (know where data is uncertain)
  + Handles anisotropy (temperature varies differently N-S vs E-W near coasts)
  - Computationally expensive: O(N³) for N control points → impractical for real-time
  - Requires variogram fitting (model how temperature varies with distance)
  Use case: high-resolution historical analysis, scientific products

Recommendation:
  Real-time heat map: IDW (speed priority)
  Daily/weekly aggregate maps: Kriging (accuracy priority, computed offline batch)
  Machine learning option: train a neural network (lat, lon, elevation, time) → temperature
    Using 2 years of readings → interpolation becomes inference (fast, accurate)
```

---

### 6.8 Handling Sparse vs Dense Sensor Coverage

```
Challenge: Chicago has 500 sensors in 600 km², rural Montana has 3 sensors in 380,000 km²

Urban (dense):
  Many sensors per S2 cell → cell average is reliable
  Heat map resolution: high (can show block-level variation)
  IDW: search radius 5 km, 8 nearest sensors → accurate

Rural (sparse):
  0-1 sensors per large area → IDW must interpolate across hundreds of km
  Accuracy degrades → communicate uncertainty to user

Strategies for sparse coverage:

1. Uncertainty overlay:
   Display confidence ring on map: cells with sensor within 10km = full opacity,
   50km = 50% opacity, >100km = hatched / "estimated" label
   User understands where data is real vs interpolated

2. Blended data sources:
   Where sensors are sparse → blend with:
     - NOAA NEXRAD data (government weather grid, 10km resolution)
     - ERA5 reanalysis data (ECMWF, global 31km grid, 1-hour lag)
     - Satellite-derived land surface temperature (MODIS/VIIRS, 1km resolution)
   Sensor data overrides model data within sensor's radius of influence

3. Elevation correction:
   Temperature drops ~6.5°C per 1,000m elevation (standard lapse rate)
   DEM (Digital Elevation Model) added as input to interpolation
   Adjusts for mountain sensors vs valley sensors at same lat/lon

4. Dynamic search radius:
   IDW search radius = f(nearest_sensor_distance)
   Dense area: 5 km radius
   Sparse area: 200 km radius (with uncertainty flag)
```

---

### 6.9 Alert System

```
Alert types:
  - Threshold: temperature > 35°C (heat advisory)
  - Rate of change: temperature drops > 10°C in 1 hour (cold front)
  - Conditions: humidity + temperature → heat index > 40 (dangerous)
  - Comparative: "colder than 10-year average for this date"

Alert storage:
  CREATE TABLE alerts (
    id          UUID PRIMARY KEY,
    user_id     UUID,
    sensor_id   TEXT,            -- specific station OR
    lat         FLOAT, lon FLOAT, radius_km FLOAT,  -- geo-fence
    metric      TEXT,            -- temperature, humidity, wind_speed
    operator    TEXT,            -- gt, lt, rate_of_change
    threshold   FLOAT,
    active      BOOLEAN DEFAULT TRUE,
    last_sent_at TIMESTAMPTZ
  );

Alert evaluation (Flink CEP):
  On each reading from sensor S:
    Load alerts for sensors within radius of S (from Redis cache, TTL 5min)
    For each matching alert: evaluate condition against current reading
    If triggered AND last_sent > 1h ago:
      Publish to Kafka: alert-triggers

Notification Service:
  Consumes alert-triggers
  Looks up user notification preferences (push, SMS, email)
  Sends via:
    Push: FCM (Android) / APNs (iOS)
    SMS: Twilio
    Email: SES

Alert deduplication:
  Redis SET alert:dedup:{userId}:{alertId} = "sent" NX EX 3600
  If SET NX fails → already notified within 1h → skip
```

---

### 6.10 Data Quality & Anomaly Detection

```
Problem: sensors malfunction, reporting -999°C or sudden 50°C spike

Layer 1 — Range validation at ingestion:
  Hard bounds: temperature ∈ [-90, 60], humidity ∈ [0, 100], pressure ∈ [870, 1085]
  Out of range → reject to quarantine topic, mark sensor for inspection

Layer 2 — Temporal consistency check (Flink):
  Rate of change threshold: |T(t) - T(t-1)| > 15°C per 30s → anomalous
  Physical limit: temperature cannot change 15°C in 30s naturally
  → Flag as anomalous, exclude from aggregations, use previous valid reading

Layer 3 — Spatial consistency check (Flink):
  Compare sensor reading to median of nearby sensors (within 20km)
  If deviation > 10°C from neighbor median → flag as suspect
  IDW interpolation from neighbors → imputed value used for heat map

Layer 4 — Historical baseline:
  "Climatological check": compare to same hour/day/month historical stats
  If reading > mean ± 4σ for this location/season → flag (keep but mark uncertain)

Sensor health scoring:
  anomaly_rate = flagged_readings / total_readings (30-day window)
  > 5% anomaly rate → sensor marked DEGRADED (data deprioritized)
  > 20% → sensor marked OFFLINE (excluded from aggregations)
  Automatically resurface to ACTIVE when readings normalize
```

---

### 6.11 Caching Strategy

```
Layer 1 — CDN (heat map tiles):
  Cache-Control: max-age=300 (5 minutes)
  Tile URL is deterministic (z/x/y + metric + time_bucket)
  time_bucket = floor(now / 5min) → same URL for all requests in 5-min window
  Cache invalidation: TTL-based only (tiles regenerated, new URL on next bucket)

Layer 2 — API cache (Redis):
  Current conditions per location:
    Key: conditions:{geohash_6}  (geohash precision 6 ≈ 1.2km × 0.6km)
    TTL: 60s
    On cache miss: query TimescaleDB, populate cache

  Aggregates per sensor:
    Key: agg:{sensorId}:hourly:{date}
    TTL: 5min (recent), 1h (older hours), 24h (past days)

  Nearest sensors:
    Key: nearest:{geohash_5}  (geohash precision 5 ≈ 4.9km × 4.9km)
    Value: [sensorId, distanceKm]
    TTL: 1h (sensors don't move)

Layer 3 — S2 cell temperature (Flink → Redis, for heat map):
  Key: cell:{s2_cell_id}:temperature
  Value: EWMA temperature (float)
  TTL: 2h
  Updated every 30s by Flink stream processor
```

---

## 7. Data Flow Summary

### Sensor Reading → Heat Map Update

```
[Weather station] --(MQTT)-->
  EMQX broker --(Kafka bridge)-->
    Kafka topic: sensor-readings (partition by sensor_id)

[Flink Job 2] consumes:
  → Computes S2 cell for (lat, lon) at level 12
  → EWMA update: Redis SET cell:{cellId}:temperature = new_avg  TTL=2h
  → Latency: sensor send → Redis update ≈ 2–5 seconds

[Heat Map Generator] (triggered every 5 min):
  → Read S2 cell values from Redis for target zoom level
  → Run IDW interpolation on grid
  → Apply color gradient
  → Render PNG tiles → upload to S3
  → CDN invalidation (TTL expires, new URL via time_bucket)
  → Latency: sensor send → tile available at CDN ≈ 5 minutes (within SLO ✓)
```

### User Requests Current Conditions

```
App: GET /v1/conditions?lat=41.88&lon=-87.63

Read API:
  1. Check Redis: conditions:{geohash6} → cache hit?
     → Yes: return cached response (<5ms)
     → No:
       a. Redis GEO: GEORADIUS to find 3 nearest active sensors
       b. TimescaleDB: SELECT latest readings for those 3 sensor_ids
          WHERE time > NOW() - INTERVAL '10 minutes'  -- only fresh data
       c. Pick closest sensor with fresh data
       d. Compute derived fields (feels like, heat index, dew point)
       e. Cache in Redis (TTL=60s)
       f. Return response
  Total: cache miss → p99 < 80ms ✓
```

---

## 8. Follow-Up Questions

### Q1: How do you handle a sensor reporting incorrect data that corrupts the heat map?
```
Defense in depth:

1. At ingestion: hard range check → rejects obviously wrong values (-999, 999)
2. Flink temporal check: flags sudden spikes (>15°C in 30s)
3. Flink spatial check: compares to median of 8 nearest sensors
4. Anomalous sensor automatically downweighted in IDW:
   IDW weight = (1/d²) × quality_score
   quality_score = 1.0 (healthy) → 0.1 (degraded) → 0.0 (excluded)

Recovery:
  Corrupted tile: CDN TTL expires in 5 min → next generation uses corrected data
  Historical aggregates: replay Kafka (7-day retention) with corrected sensor excluded
  Operator alert: if sensor anomaly_rate > 20% → PagerDuty alert to ops team
```

---

### Q2: How do you scale ingestion for 10× sensor growth (10M sensors)?
```
10M sensors × every 30s = 333K writes/sec

Kafka: scale from 256 to 2,560 partitions → linear throughput scaling
EMQX broker: scale from 5 to 50 nodes → handles 10M concurrent MQTT connections
TimescaleDB: scale from 4 to 40 nodes (horizontal sharding)
Flink: scale from 32 to 320 task slots → processing scales linearly

Write batching optimization:
  At 333K/sec, batch size 10,000 → 33 bulk inserts/sec → TimescaleDB handles easily
  TimescaleDB designed for 10M+ writes/sec with proper sharding

S2 cell density:
  10M sensors → urban cells have 100+ sensors per cell
  Upgrade to level 14 cells (≈ 0.25 km²) for urban areas (dynamic resolution)
  Heat map tiles at zoom 15 feasible (street-level resolution in cities)

Redis GEO:
  10M sensors × ~100B = 1 GB → still fits in Redis (scale to cluster if needed)
```

---

### Q3: How do you design the historical trend comparison ("Warmer than average")?
```
Climatological baseline (10-year average):
  Pre-compute per (sensor_id OR cell_id, month, day, hour):
    avg, stddev of temperature over past 10 years
  Stored in ClickHouse: climate_baseline table

  Schema:
    cell_id, month, day_of_year, hour, baseline_avg, baseline_stddev, year_count

  Size: 1M cells × 12 × 31 × 24 = 8.9B rows → ClickHouse handles fine (columnar)

Runtime comparison:
  Current reading: 28.4°C
  Baseline for this cell, Aug 16, 2pm: avg=24.1°C, stddev=2.3°C
  Anomaly = (28.4 - 24.1) / 2.3 = +1.9σ
  User sees: "4.3°C above average for mid-August afternoon"

Trend heat map:
  Separate heat map layer: "departure from normal"
  Color: blue (cooler than normal) → white (normal) → red (warmer than normal)
  Generated same way as temperature map, using anomaly score instead of raw temp
```

---

### Q4: How do you handle sensors in different time zones and reporting delays?
```
Time zone handling:
  All timestamps stored in UTC (standard practice)
  Sensor sends UTC timestamp in payload (GPS clock or NTP-synced)
  UI converts to user's local timezone at display time

Clock drift / out-of-order events:
  Sensor clocks can drift (no internet, no NTP sync for hours)
  Flink watermark: 30-second event-time watermark
  Events arriving 30–300s late: accepted, used to update aggregates
  Events > 5 minutes late: accepted, flagged as late, stored but excluded from real-time heat map
  Events > 24 hours late: stored in quarantine for manual review

Network delay:
  Rural sensors on cellular → intermittent connectivity → batch uploads
  Sensor buffers readings locally (256KB flash) → sends burst on reconnect
  Kafka handles burst gracefully (producer batches, broker absorbs spike)
  TimescaleDB: out-of-order inserts handled (hypertable chunk selection by time)
```

---

### Q5: How would you design a simple forecast feature?
```
Short-range forecast (24–72h):

Approach 1 — Statistical (fast, low cost):
  Historical patterns: "Chicago in August, similar pressure/wind → temperature rises 3°C by 6pm"
  Autoregression + seasonal decomposition (SARIMA model per sensor)
  Train: offline Spark job weekly
  Inference: given last 6h readings → predict next 24h
  Latency: <50ms per prediction (pre-trained model in memory)

Approach 2 — ML (more accurate):
  Features: [lat, lon, elevation, current_temp, humidity, pressure, wind, time_of_day, month]
  LightGBM or LSTM trained on 2 years of sensor + NOAA reanalysis data
  Horizon: 1h, 3h, 6h, 12h, 24h predictions (separate model per horizon)
  Accuracy: MAE ≈ 1.5°C at 24h (competitive with commercial APIs)

Forecast storage:
  Pre-compute forecasts every hour for all 1M sensors
  Store in ClickHouse: forecasts table
  { sensor_id, generated_at, forecast_hour, predicted_temp, confidence_interval }

Blending:
  For short range (< 6h): statistical model (fast, local signal)
  For medium range (6–72h): ML model + NWP model blend
  For > 72h: defer to NOAA/ECMWF API (full numerical weather prediction)
```

---

### Q6: How do you build the heat map for wind (vectors, not scalars)?
```
Wind is a vector quantity (magnitude + direction) — not directly interpolatable like temperature.

Vector decomposition:
  Split wind into components:
    u = wind_speed × cos(wind_direction_radians)  // East-West component
    v = wind_speed × sin(wind_direction_radians)  // North-South component

  IDW interpolate u and v separately (scalar interpolation)
  Reconstruct at each grid point:
    speed = sqrt(u² + v²)
    direction = atan2(v, u)

Visualization options:
  1. Animated particle flow (like Windy.com):
     JavaScript canvas: particles follow interpolated vector field
     Particle velocity ∝ wind speed
     Color ∝ wind speed (blue→green→yellow→red)
     Server provides: vector grid JSON (u,v values at 50×50 grid points per tile)

  2. Arrow map:
     Static arrows on map, scaled by speed
     Color by speed category (calm, light, moderate, strong, storm)

  3. Barb map (meteorological standard):
     Wind barbs: each barb represents 10 knots
     Professional use (aviation, maritime)

Server-side rendering for particles:
  Pre-compute vector grid per zoom level every 5 min
  JSON response (not PNG): { grid: [[u, v], ...], bbox, resolution }
  Client renders particles with WebGL (GPU-accelerated)
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| IoT protocol | MQTT (QoS 1) + REST fallback | MQTT designed for IoT: lightweight, reliable, 1M concurrent connections; REST for non-IoT sources |
| Message bus | Kafka (256 partitions by sensor_id) | Ordered per sensor, replayable, absorbs burst; 7-day retention for reprocessing |
| Time-series DB | TimescaleDB | PostgreSQL-compatible; continuous aggregates; native compression (10:1); hypertable partitioning |
| Aggregation store | ClickHouse | Columnar, vectorized; billions of rows at sub-second; perfect for time-series analytics |
| Stream processing | Apache Flink | Stateful stream processing; event-time watermarks handle out-of-order events; CEP for alerts |
| Geospatial index | Redis GEO + PostGIS + S2 cells | Redis GEO for fast nearest-sensor; PostGIS for complex geo queries; S2 for heat map binning |
| Interpolation | IDW (real-time) + Kriging (batch) | IDW fast enough for 60s refresh; Kriging for accurate scientific products |
| Heat map format | PNG tiles (XYZ slippy map) | Compatible with Leaflet/Mapbox; CDN-cacheable; standard web map protocol |
| Cache | Redis (conditions, cells) + CDN (tiles) | Layered caching: Redis for API responses, CDN for tiles; time-bucket URLs enable TTL-based invalidation |
| Alert dedup | Redis SET NX EX | Atomic, TTL-based; prevents notification spam without distributed coordination |
| Data quality | Multi-layer (range → temporal → spatial) | Defense in depth; corrupted reading impacts heat map for max 5 min (CDN TTL) |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 50–60 minutes.*
