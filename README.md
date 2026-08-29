# System Design

FAANG-level system design interview guides. Each document covers functional requirements, non-functional requirements, back-of-envelope estimation, high-level design, deep dives, and follow-up questions.

## Difficulty Guide

| Level | What to Expect | Typical Interview Stage |
|-------|---------------|------------------------|
| 🟢 **Easy** | Single hard problem, well-defined scope, straightforward scale | Entry-level / L3–L4 screening |
| 🟡 **Medium** | 2–3 interacting hard problems, distributed systems patterns required | Mid-level / L4–L5 onsite |
| 🔴 **Hard** | Extreme scale, celebrity/viral edge cases, multiple bifurcated designs, novel algorithms | Senior / L5–L7 onsite |

---

## 🟢 Easy

| System | File | Key Hard Problem | Key Topics |
|--------|------|-----------------|------------|
| **TinyURL** | [tiny-url.md](./tiny-url.md) | Hash collision + redirect latency | URL shortening, base62 encoding, Cassandra, Redis cache-aside, 301 vs 302, hash collisions, expiry/TTL, rate limiting |
| **Rate Limiter** | [rate-limiter.md](./rate-limiter.md) | Distributed quota across replicas | 5 algorithms (Token Bucket, Sliding Window Counter/Log, Fixed Window, Leaky Bucket), multi-dimensional rules, Redis INCR atomicity, distributed quota, fail-open, dynamic rule propagation, GraphQL cost-based limiting |
| **Parking Garage** | [parking-garage.md](./parking-garage.md) | Atomic spot assignment + offline gate hardware | Spot assignment (Redis atomic queue), offline-capable gate hardware, LPR, IoT sensor pipeline, dynamic pricing, reservations, multi-tenant, fee engine |
| **Elevator System** | [elevator-system.md](./elevator-system.md) | LLD state machine + fleet-scale HLD | Dual design: LLD (LOOK algorithm, state machine, Strategy/Observer patterns, OOP in Python) + HLD (1M elevator fleet, IoT ingestion via Kafka + Flink, predictive maintenance ML, staged OTA rollout, InfluxDB time-series, technician dispatch) |
| **Weather App** | [weather-app.md](./weather-app.md) | Sensor ingestion + geospatial interpolation | IoT sensor ingestion (MQTT/Kafka), TimescaleDB time-series, Flink stream aggregation, IDW/Kriging interpolation, S2 cell heat map tiles, geospatial indexing, anomaly detection, alert CEP |

---

## 🟡 Medium

| System | File | Key Hard Problems | Key Topics |
|--------|------|-----------------|------------|
| **Netflix** | [netflix.md](./netflix.md) | Transcoding pipeline + CDN + recommendations | Transcoding pipeline, adaptive bitrate (DASH/HLS), Open Connect CDN, DRM + Widevine, Cassandra watch history, two-tower recommendations, chaos engineering, AV1 codec economics |
| **Uber** | [uber.md](./uber.md) | Geo-matching + real-time tracking + surge pricing | Ride matching (Redis GEO + distributed lock), driver location at 100K writes/sec, WebSocket real-time tracking, H3 hexagonal surge pricing, trip state machine, Cassandra location history, multi-region city sharding |
| **Build System** | [build-system.md](./build-system.md) | DAG scheduling + worker isolation + incremental builds | Git webhook ingestion, pipeline DAG, worker scheduling, Firecracker isolation, log streaming, artifact store, retry system, incremental builds, monorepo change detection |
| **Job Scheduler** | [job-scheduler.md](./job-scheduler.md) | Thundering herd + exactly-once execution | Cron + one-time jobs, `FOR UPDATE SKIP LOCKED` claim, shard-based scheduling, fencing tokens, outbox pattern, thundering herd, retry backoff, sub-second scheduling, dependency fan-in |
| **Restaurant Reservation** | [restaurant-reservation.md](./restaurant-reservation.md) | Distributed slot locking + waitlist fan-out | Slot modeling (table × time × turn duration), Redis distributed lock (3-phase hold→confirm), two-layer search (Elasticsearch + availability cache), table merging algorithm, waitlist Kafka fan-out, no-show enforcement, real-time floor WebSocket, city-sharded PostgreSQL |
| **Ad Click Aggregator** | [ad-click-aggregator.md](./ad-click-aggregator.md) | Exactly-once billing + hot ad partitions | Kappa architecture (Flink + Kafka), 1M events/sec ingestion, event-time windowing, watermarks + late event compensation, hot ad partitioning, Redis HyperLogLog unique users, ClickHouse OLAP (ReplacingMergeTree), bloom filter dedup, exactly-once billing, S3 backfill reprocessing |
| **Subway Ticket System** | [subway-ticket-system.md](./subway-ticket-system.md) | Offline payment + ticket forgery prevention | Payment idempotency (outbox pattern), offline kiosk (EMV floor limit + SQLite sync), HMAC-signed QR tickets (HSM), bloom filter turnstile validation (< 300ms), PCI-DSS isolation, daily financial reconciliation, ticket forgery prevention, Apple/Google Wallet integration |
| **Booking.com** | [booking.md](./booking.md) | Inventory locking + overbooking tolerance + dynamic pricing | Hotel search (Elasticsearch + Redis cache 20% hit rate), inventory management (optimistic locking + overbooking 0.05% tolerance), booking saga pattern (async payment reconciliation), dynamic pricing (occupancy/demand/season multipliers), distributed transactions with Saga pattern, anti-fraud review system (ML scoring), 100k QPS search, 10k QPS bookings, 1M+ properties, multi-region deployment, GDPR/CCPA compliance |
| **Google Drive** | [google-drive-system-design.md](./google-drive-system-design.md) | Content-defined chunking + delta sync + conflict resolution | Content-defined chunking (Rabin fingerprinting, avg 4 MB), delta sync (95%+ bandwidth savings on edits), global chunk dedup (SHA-256 content-addressed, 30-50% storage reduction), conflict copy resolution (parent_version_id divergence detection), WebSocket real-time sync (50M concurrent), file versioning as chunk-hash snapshots (not full copies), presigned S3 uploads, Elasticsearch full-text search with permission-aware filtering, async virus scanning, GDPR erasure pipeline |

---

## 🔴 Hard

| System | File | Key Hard Problems | Key Topics |
|--------|------|-----------------|------------|
| **WhatsApp** | [whatsapp.md](./whatsapp.md) | E2EE at scale + group fan-out + multi-device sync | WebSocket at scale, Signal Protocol (X3DH + Double Ratchet), Kafka fan-out, group messaging, presence, media upload, push notifications, multi-device E2EE |
| **Ticketmaster** | [ticketmaster.md](./ticketmaster.md) | 14M concurrent users on flash sale + atomic multi-seat hold | Seat hold (Redis SET NX + Lua atomic multi-seat), virtual queue (14M users, bucket fan-out), seat map read scaling (250K/sec cached), HMAC-signed QR tickets, outbox pattern payments, dynamic pricing, bot prevention, event-sharded PostgreSQL |
| **Smart Delivery System** | [smart-delivery-system.md](./smart-delivery-system.md) | VRP route optimization + Saga choreography + real-time tracking | Choreography Saga (Kafka), atomic inventory reservation (Redis Lua), FC assignment scoring, VRP route optimization (OR-Tools), drone dispatch, real-time tracking (WebSocket + Redis Pub/Sub), SLA breach detection, Symbotic robot integration |
| **GM Car Tracking** | [gm_car_tracking.md](./gm_car_tracking.md) | 500K events/sec IoT ingestion + OTA rollout + predictive ML | IoT telemetry ingestion (500k events/sec, MQTT over TLS), multi-region architecture (GDPR/CCPA data residency), OTA updates (staged canary rollout, differential updates, dual partitions), command & control (at-least-once delivery, idempotency, 30s latency), real-time anomaly detection (Kafka Streams + Flink), predictive maintenance ML (XGBoost RUL prediction), time-series data (InfluxDB hot + Glacier archive), security (mutual auth, replay prevention, rate limiting), 10M vehicles, 20M users, 99.99% SLA |
| **Amazon** | [amazon-system-design.md](./amazon-system-design.md) | Flash sale inventory + checkout saga + multi-region active-active | Microservices catalog (DynamoDB + OpenSearch), cart (Redis HASH + DynamoDB backup), checkout saga with idempotency, PCI DSS payment tokenization (Stripe), inventory reservation (optimistic lock + Redis Lua for flash sales), collaborative filtering recommendations, Kafka notification fan-out, Black Friday scaling, multi-region active-active, GDPR |
| **Twitter** | [twitter-system-design.md](./twitter-system-design.md) | Celebrity fan-out bifurcation + trending at 1M tweets/sec | Hybrid fan-out (write for normal users, read for celebrities ≥1M followers), Snowflake IDs, Cassandra tweet storage, Redis sorted-set timeline cache (1.28 TB for 200M users), Kafka fan-out pipeline, Earlybird search (near-real-time Elasticsearch), Count-Min Sketch trending (Flink sliding window), S3+CloudFront media (HLS adaptive bitrate), FCM/APNs notifications, algorithmic "For You" timeline (two-tower neural network) |
| **Instagram** | [instagram-system-design.md](./instagram-system-design.md) | 868K CDN req/sec + celebrity fan-out math + pHash dedup + 4.56 EB | S3 presigned upload (36 GB/s bypassed), multi-tier CDN (868K req/sec, 99% cache hit), hybrid fan-out (celebrity threshold 1M followers), pHash dedup, WebP/AVIF compression, HLS adaptive bitrate video (GPU transcoding cluster), Stories (Redis TTL + S3 lifecycle), Explore two-stage ranking (FAISS ANN + neural ranker), Reels recommendation (two-tower, watch-percentage signal), 2B MAU at 4.56 EB 5-year storage |
| **YouTube** | [youtube-system-design.md](./youtube-system-design.md) | DAG transcoding + Content ID fingerprinting + watch-time rec + 54 EB | Transcoding DAG (60 parallel segments per video → 5 min vs hours), multi-codec strategy (H.264/VP9/AV1 — 50% bandwidth savings), DASH adaptive bitrate, Google Global Cache (GGC co-located at ISPs), two-tower DNN recommendation (watch-time regression, not CTR), candidate generation via ScaNN ANN over 800M videos, Content ID fingerprinting (5.76T fingerprints), Bigtable watch history, 500 hrs/min upload → 54 EB 5-year storage |

---

## Structure

Each document follows this format:

1. **Functional Requirements** — core features and explicit out-of-scope boundaries
2. **Non-Functional Requirements** — availability, latency, consistency, durability, scale targets
3. **Back-of-Envelope Estimation** — QPS, storage, bandwidth, connection math
4. **High-Level Design** — architecture diagram, component responsibilities, core API
5. **Deep Dive** — component-level design decisions with tradeoffs (DB schema, caching strategy, encryption, fan-out models, failure modes)
6. **Trade-offs Discussion** — quantified comparison of alternative approaches with when to switch
7. **Follow-Up Questions** — common interviewer probes with structured answers
