# System Design: YouTube

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard  
> Core challenges: **500 hours of video uploaded per minute · Transcoding DAG at scale · Two-tower recommendation over 800M videos**

---

## Table of Contents

1. [Clarifying Questions](#1-clarifying-questions)
2. [Functional Requirements](#2-functional-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Back-of-Envelope Estimation](#4-back-of-envelope-estimation)
5. [High-Level Design](#5-high-level-design)
6. [Trade-Off Discussion](#6-trade-off-discussion)
7. [Deep Dive](#7-deep-dive)
   - 7.1 Video Upload Pipeline
   - 7.2 Transcoding at Scale (DAG Architecture)
   - 7.3 Adaptive Bitrate Streaming (DASH)
   - 7.4 CDN & Global Distribution
   - 7.5 Recommendation System (Two-Tower DNN)
   - 7.6 Search
   - 7.7 Engagement (Likes, Comments, Watch History)
8. [Data Models](#8-data-models)
9. [Follow-Up Questions](#9-follow-up-questions)
10. [Interview Summary Card](#10-interview-summary-card)

---

## 1. Clarifying Questions

```
"Are we designing the full YouTube platform?"
→ Yes — upload, playback, search, recommendations, comments

"Do we need YouTube Live / Shorts?"
→ Shorts: include (same pipeline, shorter segments)
  Live: out of scope (very different architecture — RTMP ingest, real-time HLS)

"Do we need ads / monetization?"
→ Out of scope

"Do we need YouTube Studio (analytics for creators)?"
→ Out of scope

"What's the scale?"
→ 2.7B MAU, 50M active creators, 500 hours uploaded/minute

"Subtitles / auto-generated captions?"
→ Yes — include ASR pipeline mention
```

---

## 2. Functional Requirements

### Core (Must Have)

| # | Requirement | Notes |
|---|-------------|-------|
| FR-1 | **Upload video** | Any format (MP4, MOV, AVI, MKV); up to 256 GB; resumable |
| FR-2 | **Stream video** | Adaptive bitrate; global; < 2s startup time |
| FR-3 | **Home feed / recommendations** | Personalized video feed ranked by predicted watch time |
| FR-4 | **Search** | Full-text search by title, description, channel, hashtag |
| FR-5 | **Like / Dislike** | Aggregate counts; user can see liked videos |
| FR-6 | **Comments** | Threaded; @mentions; pinned comments |
| FR-7 | **Subscriptions** | Subscribe to channels; notification on new upload |
| FR-8 | **Watch history** | User's personal watch history; used for recommendations |
| FR-9 | **Video chapters / timestamps** | Creator-defined or auto-detected |
| FR-10 | **Thumbnails** | Creator upload or auto-generated from frames |

### Out of Scope

- YouTube Live, YouTube TV, YouTube Music, Ads, Studio Analytics, Community Posts

---

## 3. Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| **Availability** | 99.99% | 1B hours watched/day — downtime is catastrophic revenue loss |
| **Upload availability** | 99.9% | Creator experience slightly less critical than viewer |
| **Playback startup latency** | p99 < 2 seconds | Industry benchmark for video startup time |
| **Video quality** | Adaptive (144p–4K) | Serve best quality within user's bandwidth |
| **Upload processing** | < 5 min (360p available) | Creator wants quick processing; higher resolutions async |
| **Search latency** | p99 < 500ms | Search must feel instant |
| **Recommendation latency** | p99 < 200ms | Home feed must load before user thinks |
| **Durability** | 11 nines | Videos are creator assets — no data loss acceptable |
| **Scale** | 500 hrs video/min upload; 1B hrs/day watch | YouTube's actual numbers |
| **Read:Write ratio** | ~10,000:1 | Extreme read dominance (1B watch vs 500 hrs upload) |

---

## 4. Back-of-Envelope Estimation

### Upload & Storage

```
Uploads:
  500 hours of video uploaded every minute
  = 500 × 60 = 30,000 minutes of video per minute
  = 500 min/sec of video ingested

  Avg video: 10 minutes, 720p, ~300 MB raw
  Videos per day: (500 × 60) min/min × 1,440 min/day / 10 min/video
                = 4,320,000 videos/day = 50 videos/sec

  Raw ingest bandwidth: 50 videos/sec × 30 MB/sec avg bitrate = 1.5 GB/s inbound

Storage per video (after transcoding into all variants):
  144p:  ~100 MB  (for 10 min)
  360p:  ~300 MB
  480p:  ~500 MB
  720p:  ~1 GB
  1080p: ~2 GB
  1440p: ~4 GB
  4K:    ~8 GB
  Total per 10-min video: ~16 GB (all variants, H.264 + VP9 + AV1 for newer)
  With AV1 (50% compression): total ~11 GB per video

Daily new storage:
  4,320,000 videos/day × 16 GB = 69 PB/day
  (YouTube deletes very-low-quality or policy-violating videos → net growth ~30 PB/day)

5-year cumulative:
  30 PB/day × 365 × 5 = 54.75 EB
  → Tiered: hot (< 30 days, GCS Standard) → warm (GCS Nearline) → cold (GCS Archive)
```

### Viewing & QPS

```
Watch:
  1 billion hours watched per day
  / 86,400 sec/day = 11.57 million concurrent viewers (avg)
  Peak (primetime): 30M concurrent viewers

  Avg video duration watched: ~7 minutes
  Watch sessions/day: 1B hrs × 60 min / 7 min = 8.57B sessions/day
  Watch requests/sec: 8.57B / 86,400 = 99,000 video starts/sec

CDN (video segments):
  1B hours × 3600 sec/hr = 3.6T seconds of video served/day
  At 720p = 2.5 Mbps = 312 KB/sec per viewer
  Daily data served: 3.6T sec × 312 KB = 1.12 EB/day
  Egress bandwidth: 1.12 EB / 86,400 = 13 TB/s peak
  (99%+ absorbed by CDN — origin serves << 1%)

Recommendations:
  2.7B MAU × 5 recommendation loads/day = 13.5B requests/day
  = 156,000 rec requests/sec avg; peak ~500K/sec

Search:
  ~500M searches/day = 5,787 searches/sec

Likes:
  ~5B likes/day = 57,870 likes/sec

Comments:
  ~1B comments/day = 11,574 comments/sec
```

### Infrastructure (rough)

```
Transcoding workers:
  50 videos/sec uploaded, avg 10 min each
  Transcoding time: 1 min of video takes ~1 min CPU time at 360p
  For 10 variants: 10× → 10 CPU-minutes per video-minute
  = 50 × 10 min × 10 = 5,000 CPU-minutes/sec = 300,000 CPU-cores
  (With GPU acceleration: ~10× speedup → 30,000 GPU cores)
  → Auto-scaling pool; YouTube uses TPUs + custom silicon for encoding

Video metadata DB:
  800M videos × 5 KB metadata = 4 TB → fits in sharded PostgreSQL

Watch history:
  2.7B users × 1,000 videos in history × 50 bytes = 135 TB → Bigtable / Cassandra
```

---

## 5. High-Level Design

### Architecture Overview

```
                     ┌──────────────────────────────────┐
                     │           API Gateway             │
                     │   Auth · Rate Limit · gRPC/REST   │
                     └──────────────┬───────────────────┘
                                    │
     ┌──────────────┬───────────────┼────────────────┬──────────────┐
     │              │               │                │              │
     ▼              ▼               ▼                ▼              ▼
┌──────────┐ ┌──────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────────┐
│ Upload   │ │ Video    │  │ Rec Service  │  │ Search   │  │ Engagement   │
│ Service  │ │ Streaming│  │ (home feed)  │  │ Service  │  │ Service      │
└────┬─────┘ │ Service  │  └──────┬───────┘  └──────────┘  │ like/comment │
     │       └────┬─────┘         │                         └──────────────┘
     │            │               │
     ▼            ▼               ▼
┌──────────┐ ┌──────────┐  ┌──────────────┐
│  GCS /   │ │  CDN     │  │ Rec Model    │
│  S3 raw  │ │ (global  │  │ (Candidate   │
│  storage │ │ 300+ PoP)│  │  Gen +       │
└────┬─────┘ └──────────┘  │  Ranking)    │
     │                      └──────────────┘
     ▼
┌──────────────────────────────┐
│        Kafka                  │
│  video.uploaded · processed  │
│  view.event · engagement     │
└──┬───────────┬───────────────┘
   │           │
   ▼           ▼
┌──────────┐ ┌───────────────────┐
│Transcoding│ │ Downstream        │
│ DAG       │ │ consumers:        │
│ Workers   │ │ - Rec trainer     │
└──────────┘ │ - Search indexer  │
             │ - Analytics       │
             │ - Notification svc│
             └───────────────────┘
```

### Core Request Flows

#### Video Upload

```
1. Creator → POST /api/v1/upload/init { title, description, tags, visibility }
   Response: { video_id (Snowflake), upload_url (resumable GCS URL), chunk_size: 256MB }

2. Creator → PUT {upload_url} in 256 MB chunks (resumable upload protocol)
   → Chunks go directly to GCS (raw bucket) — YouTube API servers not in data path
   → If upload fails mid-way: resume from last confirmed chunk offset

3. GCS → Pub/Sub event "video.uploaded.raw" → Kafka → Transcoding Orchestrator

4. Transcoding pipeline (see Deep Dive 7.2):
   → 360p available within 2-3 minutes
   → All resolutions within 10-30 minutes
   → 4K, AV1 encoded: up to several hours (background, lower priority)

5. Each variant uploaded to GCS (processed bucket) → CDN cache warm

6. Video Service: UPDATE video SET status='published', available_qualities=[...] WHERE id=?
   (video becomes watchable after 360p ready)

7. Kafka "video.published" → Search Indexer + Notification Service (subscribers)
```

#### Watch Video

```
1. Client → GET /api/v1/videos/{id}/manifest
   Response: DASH manifest (MPD file) listing all quality tiers + segment URLs

2. Client DASH player:
   a. Reads MPD → knows all available qualities and segment durations (2-10 sec each)
   b. Measures current bandwidth (first segment download time)
   c. Selects quality tier: if bandwidth > 5 Mbps → 1080p; 2.5 Mbps → 720p; etc.
   d. Requests segment URLs: cdn.youtube.com/{video_id}/720p/seg_{n}.mp4

3. CDN serves segments (99%+ from cache):
   HIT: edge node ~5ms
   MISS: origin fetch from GCS → cache at edge → serve ~50ms

4. Player continuously monitors buffer level + bandwidth:
   Buffer > 30s: may upgrade quality
   Buffer < 10s: downgrade quality (prevents stall)
   → Seamless quality switches at segment boundary

5. Client sends viewing events (beacon API, batched every 30s):
   POST /api/v1/events [{ video_id, watch_ms, quality, timestamp }, ...]
   → Kafka "view.event" → analytics + recommendation trainer
```

---

## 6. Trade-offs Discussion

### 6.1 Transcoding Architecture: Monolithic vs Segment-Parallel DAG

| Approach | Wall-clock for 10-min 4K video | Failure impact | Complexity |
|----------|-------------------------------|----------------|------------|
| Single FFmpeg process (serial) | ~2.5 hours | Full re-encode on crash | Low |
| Per-quality parallel (5 processes) | ~30 minutes | Re-encode 1 quality on crash | Medium |
| **Segment-parallel DAG** (chosen) | ~5 minutes | Re-encode 1 segment on crash | High |

```
DAG parallelism math:
  10-minute video → 60 × 10-second segments
  Per-segment transcode time: 360p 3s, 720p 8s, 1080p 15s, 4K 30s, AV1 60s
  Total tasks: 60 segments × 5 qualities = 300 parallel tasks
  Wall-clock time = time of slowest single task = 60s (AV1, one segment)
  vs. sequential: 60 × 60s = 3,600s = 60 minutes for AV1 alone

  With DAG: ALL 300 tasks run simultaneously (bounded by worker pool)
  At 30,000 GPU cores across worker pool: 300 tasks × 60s = 300 GPU-task-seconds
  Wall-clock: 60 seconds (limited by single-task latency, not total work)
  SLA: 360p available < 2 minutes from upload completion ✓

Failure isolation advantage:
  Serial FFmpeg: crash at segment 59/60 → restart from beginning
  DAG: crash at task (seg_59, 4K) → retry only that 1 task
  Retry overhead: 60s vs 60 minutes → 60× faster recovery

Segment boundary artifact prevention:
  Problem: natural keyframes rarely align to exact 10-second marks
    → Cut in the middle of a P-frame → decoder artifact at join
  Solution: FFmpeg -force_key_frames "expr:gte(t,n_forced*10)"
    → Forces I-frame at every 10s boundary
    → DASH player can seek to any segment start without visual glitch

Merge step cost:
  FFmpeg concat demuxer (stream copy, no re-encode):
  2 GB 720p video: ~30s I/O-bound concat — negligible vs transcode savings
```

**Why not just parallelize by quality (not by segment)?** 5 parallel FFmpeg processes still takes 30+ minutes for 4K — 18× worse than segment parallelism. The DAG approach is the only one that meets the "360p in 5 minutes" SLA at YouTube's upload rate of 50 videos/sec.

---

### 6.2 Codec Strategy: H.264 vs VP9 vs AV1 — Encode All Three

```
Codec comparison at same perceived quality (720p reference):
  H.264:   4 Mbps → 1× file size (baseline)
  VP9:     2.5 Mbps → 0.625× file size (40% savings)
  AV1:     2.0 Mbps → 0.5× file size (50% savings)

YouTube's CDN egress: 13 TB/s total
  With AV1 (60% device adoption, 50% compression):
  Savings = 13 TB/s × 60% × 50% = 3.9 TB/s
  At $0.08/GB egress: 3.9 TB/s × 86,400s × $0.08/GB = $27M/day saved by AV1
  Annual: ~$10 billion in CDN bandwidth savings — justifies the encoding investment

Encoding cost trade-off:
  AV1 encoding cost: 20× H.264 at same quality
  50 videos/sec × 10-min avg × AV1 at 20× cost = 20× more GPU-hours
  But: AV1 encoding is one-time; CDN savings are perpetual per view
  Break-even: video with >100 views recoups AV1 encoding cost in CDN savings
  At YouTube's avg 1M views/video: break-even in first 0.01% of views

Serving logic (Content negotiation via Accept header):
  AV1:  Chrome 70+, Firefox 67+, Android 10+ → 60% of traffic
  VP9:  Older Chrome, Edge, Samsung Internet → 25% of traffic
  H.264: iOS Safari, Smart TVs, older Android → 15% of traffic

Encoding priority schedule:
  T+0 to T+5 min:   H.264 (360p, 720p) — availability SLA
  T+5 to T+30 min:  H.264 (1080p, 4K) + VP9 (all qualities)
  T+30 min to T+12h: AV1 (all qualities) — background, spot instances
  Overnight:         Legacy library re-encode to AV1 (lowest priority)
```

**Storage trade-off**: 3 codecs × 7 quality tiers = 21 stored versions per video. At 16 GB per video (H.264 only), that becomes ~32 GB with all codecs. But AV1's 50% compression means the total is ~26 GB — only 62% more storage than H.264 alone, while saving perpetual CDN bandwidth.

---

### 6.3 Recommendation Optimization Target: CTR vs Watch Time vs Satisfaction

| Signal | User Satisfaction | Engagement | Gaming Risk | Rabbit Hole Risk |
|--------|-----------------|-----------|-------------|-----------------|
| CTR (click-through rate) | Low — clickbait | High | Very high | High |
| Like/dislike ratio | Medium | Medium | Medium | Medium |
| **Watch time** (chosen primary) | High | High | Low | **High** |
| Completion rate | High | Medium | Low | Medium |
| Post-watch survey (1-5 stars) | Highest | N/A | None | Low |
| **Composite** (YouTube actual) | Best | High | Low | Low |

```
Watch time optimization problem (2016–2022):
  Pure watch time → "rabbit hole" amplification
  Conspiracy theory video: 25-min watch time (outrage keeps watching)
  Documentary: 8-min watch time (user satisfied, leaves at natural end)
  Optimizing for watch_time alone: conspiracy video scores 3× higher → amplified
  Result: YouTube's 2012-2016 approach contributed to misinformation spread

Composite scoring (YouTube's current approach):
  score = 0.5 × watch_time_minutes
        + 0.2 × P(completion > 90%)
        + 0.2 × satisfaction_score (post-watch 1-5 star)
        + 0.1 × P(share | watched)
        - 0.3 × P(dislike | watched)
        - 0.5 × borderline_content_score  (ML classifier)

Why regression (not binary classification)?
  Binary: "did user click like? yes/no" — 5-minute vs 30-minute watch both = 1
  Regression: "how many minutes did user watch?" — 30-minute watch = 6× signal of 5-minute
  YouTube paper (Covington 2016): regression on watch time produces dramatically
  better recommendations than binary engagement prediction
  → Label is actual watch_time_seconds (continuous), not a click event
```

**Cold start for new videos**: Multi-armed bandit (UCB exploration bonus). New video gets `score = predicted_value + C × sqrt(log(total_views) / video_views)`. As video gets more views, exploration bonus shrinks. This ensures promising new content surfaces without overpowering established videos.

---

### 6.4 CDN Strategy: GGC vs Third-Party CDN vs P2P

| Option | CDN Cost | Latency | Complexity | Privacy |
|--------|---------|---------|------------|---------|
| **GGC (Google Global Cache)** (chosen) | Near-zero (ISP peering) | < 10ms (90%+ users) | Very high (own PoPs) | ✅ No IP leakage |
| CloudFront / Fastly | $0.08/GB × 13 TB/s = enormous | ~20ms | Low | ✅ |
| P2P (WebRTC between viewers) | Near-zero | Variable (peer churn) | Very high | ❌ IP leakage |
| Hybrid CDN + P2P | Medium | Good avg, variable tail | Very high | Partial |

```
GGC economics:
  YouTube pays $0 for transit when ISP hosts GGC boxes on-premises
  ISP benefits: traffic stays local → no transit fees they'd otherwise pay
  YouTube benefits: free bandwidth + lower latency (traffic never leaves ISP network)
  Win-win: peering agreement with mutual benefit

  Third-party CDN cost comparison:
  13 TB/s × $0.05/GB × 86,400s = $56B/year
  GGC peering: $0 in transit + hardware costs (~$1B/year in servers)
  GGC saves ~$55B/year — the single largest cost optimization in YouTube's infrastructure

P2P rejection rationale:
  Long-tail content: "rare documentary from 2009" — viewer populations are sparse
    → Only 3 concurrent viewers worldwide → 0 peers in same ISP → falls back to CDN anyway
  Popular content: same video at same ISP → 1,000 concurrent viewers → P2P could work
    BUT: mobile seeders upload at expense of their data plan → user backlash
    AND: WebRTC IP discovery → viewer A can learn viewer B's IP address
    → Privacy-unacceptable for 2.7B general audience (not a BitTorrent user base)
  Live streaming: P2P has < 5% deployment globally (Twitch experimented, abandoned)
```

**Interview answer**: The GGC/ISP peering model is the key differentiator that allows YouTube to be economically viable at 13 TB/s. Without it, YouTube's CDN bill alone would exceed its revenue. Always mention this in interviews — it demonstrates real-world awareness of how hyperscalers actually operate.

---

### 6.5 Recommendation: Offline-Only vs Real-Time vs Two-Stage Hybrid

```
Scale: 156K recommendation requests/sec at 500ms budget

Option A — Pure real-time (query 800M videos per request):
  ScaNN ANN search over 800M video vectors per user per request
  ScaNN latency: 20ms for top-500 over 800M vectors (excellent, but...)
  800M vectors × 256 dimensions × float32 = 800 GB of embedding index
  Loading into memory for real-time serving: 800 GB × 156K requests/sec
  → Needs 1,000+ GPU servers to serve ANN at this QPS
  → Acceptable compute cost but cold inference for every user is wasteful
  ❌ Too expensive at 156K RPS; no session-level context reuse

Option B — Pure offline (pre-compute full feed per user):
  Nightly Spark job: compute top-20 videos for all 2.7B users
  Storage: 2.7B × 20 video_ids × 8 bytes = 432 GB → fits in Redis
  Read: O(1) Redis GET per recommendation request
  Problem: "trending" video uploaded 3 hours ago → not in any pre-computed feed
  Problem: user's real-time session context (they just watched 3 cooking videos)
           has zero impact on pre-computed recommendations
  ❌ Zero freshness; user context ignored; viral content invisible

Option C — Two-stage (chosen):
  Stage 1 (offline, every 6 hours): ANN retrieval → 500 candidates per user
    Stored in Redis candidates:{user_id} with 6h TTL
    Fresh viral injection: Flink pipeline adds trending videos to ALL candidate pools
  Stage 2 (online, < 100ms): lightweight DNN ranker on 500 candidates
    Uses real-time features: session history (last 3 videos watched), time of day
    Produces ranked top 20

  Freshness injection for new viral content:
    Flink: video_id gains > 10K views in 10 min → "trending" classification
    Trending videos pushed into all user candidate pools (Redis ZADD to candidates sets)
    Lag: viral video appears in recommendations within ~10 minutes
    ✅ Both computational efficiency AND freshness

Two-stage cost comparison:
  Option A: 1,000 GPU servers for ANN serving alone
  Option C: 50 GPU servers for ANN (6h refresh, not real-time) + 200 CPU servers for ranking
  = 4× cheaper infrastructure for equal or better recommendation quality
```

---

### 6.6 Video Availability: Wait for All Qualities vs Progressive Release

```
Upload SLA options:
  Option A: Wait for ALL qualities (360p through 4K + AV1) before publishing
    Average processing: 2-3 hours for 4K + AV1
    Creator uploads at 9 PM → video available at midnight → audience asleep
    → Major creator experience complaint: delayed audience engagement
    ❌ Unacceptable latency for creators with active subscriber bases

  Option B: Release at 360p ready (chosen)
    360p available: ~2-3 minutes after upload
    Progressive quality upgrades: 720p → 1080p → 4K → AV1 over next 2-3 hours
    DASH manifest updated as each quality tier completes:
      Initial manifest: lists 360p only
      After 5 min: updates to include 720p
      Player re-fetches manifest at quality switch → sees new options
    → Creator can notify subscribers immediately; viewers get lowest quality initially

Priority queue for quality processing:
  High-priority creators (>100K subscribers): 720p within 5 minutes
  Standard creators: 720p within 30 minutes
  Background (AV1 re-encode): scheduled for off-peak (2-6 AM local)

Manifest update mechanism:
  Video metadata row: available_qualities[] array updated atomically
  DASH manifest served dynamically (not static) from Video Service:
    SELECT available_qualities WHERE video_id = ? → builds MPD on the fly
  CDN caches manifest with short TTL: max-age=60 (not immutable — it changes!)
  vs. video segments: max-age=31536000, immutable (never change)
```

**Key insight**: Separating manifest TTL (60s, dynamic) from segment TTL (1 year, immutable) is the critical CDN design choice. Manifests must be fresh (qualities change); segments are content-addressed and eternal.

---

### 6.7 Watch History Storage: Bigtable vs Cassandra vs PostgreSQL

```
Scale: 2.7B users × 1,000 videos in history = 2.7T records
       Write: 99K video starts/sec → 99K history writes/sec
       Read: "last 50 watched" at recommendation time → 156K reads/sec

Access patterns:
  Pattern A: "What did user U watch most recently?" → time-ordered range scan by user_id
  Pattern B: "Has user U ever watched video V?" → point lookup (for dedup)
  Pattern C: "All users who watched video V" → reverse lookup (NOT required in hot path)

PostgreSQL analysis:
  2.7T rows: requires 1,000+ shards; cross-shard range scan for "last 50" is scatter-gather
  B-tree index on (user_id, timestamp DESC): ~40 bytes overhead/row × 2.7T = 108 TB index
  Write throughput: 99K writes/sec → ~100 primary shards needed
  ❌ Manageable but expensive; OLTP PostgreSQL not optimized for wide-column access

Cassandra analysis:
  Partition key: user_id, Clustering key: watched_at DESC
  "Last 50 by user": O(1) partition read — perfect for Pattern A
  "Has user watched video V?": ALLOW FILTERING or secondary index — O(full partition)
  Write throughput: native 99K writes/sec across cluster — purpose-built for this
  Memory: TTL per row (delete history older than 1 year) — built-in
  ✅ Good fit, widely used for this pattern

Bigtable analysis (chosen at Google/YouTube):
  Row key: user_id + timestamp (reverse: 9999999999 - timestamp → newest first)
  Column family: watch_events { video_id, duration_ms, quality, device }
  "Last 50": Scan from row_key prefix, LIMIT 50 → O(50) row reads (sequential)
  Write throughput: 1M+ writes/sec per Bigtable cluster
  Integration: native Google Cloud → no cross-cloud latency for YouTube's GCP stack
  ✅ Best fit at YouTube's scale within Google infrastructure

For non-Google designs: Cassandra is the correct answer.
  Bigtable is proprietary; Cassandra provides equivalent semantics with:
  - Same wide-column, time-series access pattern
  - Same horizontal scale-out
  - Open-source, cloud-agnostic
```

---

### 6.8 Consistency Model Matrix

| Component | Model | Justification |
|-----------|-------|---------------|
| Video upload (GCS write + metadata) | **Strong (write quorum)** | Creator's video must not vanish after upload confirmation |
| Transcoding status (available_qualities[]) | **Strong (read-your-writes)** | Creator checking "is my video ready?" must see current state |
| DASH manifest | **Eventual (60s CDN TTL)** | Quality tiers visible within 60s of becoming available; acceptable |
| Video segments (CDN) | **Immutable** | Content-addressed; same URL always = same bytes; no consistency concern |
| View count | **Approximate eventual (60s flush lag)** | "1,234,567 views" vs "1,234,570" — users can't tell; Redis INCR → batch flush |
| Like count | **Approximate eventual (60s)** | Same as view count; 57,870 likes/sec requires async flush |
| Comment creation | **Strong (read-your-writes)** | User posting a comment must immediately see it |
| Comment feed (public) | **Eventual (seconds)** | New comments appearing 2–3s late is unnoticeable |
| Subscription notifications | **At-least-once, eventual** | Duplicate "Channel X uploaded" is tolerable; delay of minutes acceptable |
| Recommendation candidates | **Eventual (6h refresh cycle)** | Pre-computed candidates stale up to 6h; freshness injection handles viral content |
| Watch history (for resume playback) | **Eventual (30s beacon interval)** | Resume from 30s ago is acceptable; exact position non-critical |
| Content ID copyright match | **Eventual (async, non-blocking)** | Video can be watched while Content ID runs; match enforced within minutes |
| Creator revenue / monetization data | **Strong (ACID)** | Financial data requires exact consistency; processed in separate payment system |

**Key insight**: YouTube has three consistency tiers with radically different requirements:
1. **Creator-facing writes** (upload, publish, monetization) → strong consistency; creators make business decisions based on this
2. **Social metrics** (view/like counts, recommendation candidates) → eventual; approximate is fine at billions-per-day scale
3. **Viewer-facing streaming** (CDN segments) → immutability eliminates consistency concerns entirely; same bytes forever

The immutability insight is YouTube's most elegant consistency solution — by making video segments content-addressed and eternal, they reduce the hardest consistency problem to a solved cache coherence problem.

---

## 7. Deep Dive

### 7.1 Video Upload Pipeline

#### Resumable Chunked Upload

```
Problem: 4K video = 10 GB+ — HTTP POST fails on network interruption

Solution: Resumable Upload Protocol (GCS / YouTube's actual protocol)

Upload flow:
  1. Client: POST /upload/init → server returns { upload_id, offset: 0 }
  2. Client: PUT /upload/{upload_id} Content-Range: bytes 0-268435455/total_size
             (256 MB first chunk)
  3. Server: stores chunk in temp GCS bucket → Response: 308 Resume Incomplete
             { Range: bytes 0-268435455 }  (confirms what was received)
  4. Client: PUT /upload/{upload_id} Content-Range: bytes 268435456-...
  5. On interrupt: Client queries PUT /upload/{upload_id} with empty body
     → Server: 308 { Range: bytes 0-N } (tells client where to resume from)
  6. Client: resumes from byte N+1

Server-side chunked storage:
  Each 256 MB chunk written to GCS as an "object component"
  After all chunks received: GCS compose API merges components
  → Atomic: either all chunks arrive or upload_id expires (24h TTL)

Upload validation:
  SHA-256 checksum per chunk verified server-side
  Total file MD5 verified after all chunks received
  → Detects corrupt uploads without redownloading entire file
```

#### Pre-Processing Checks

```
Before entering transcoding queue:
  1. File format validation: parse container headers (MP4/MOV/MKV/AVI)
  2. Codec detection: H.264, HEVC, ProRes, etc.
  3. Duration check: max 12 hours (YouTube Limit)
  4. Resolution: max 8K (7680×4320)
  5. Content safety: PhotoDNA hash for CSAM (mandatory, blocks queue entry)
  6. Copyright scan: Content ID fingerprinting
     → Match against audio/video fingerprint database
     → If match: notify rights holder (can monetize, block, or allow)
     → Runs asynchronously — video can upload while Content ID runs

Content ID fingerprinting:
  Audio: acoustic fingerprint using Shazam-style hashing
    Every 10 seconds: extract 32-bin frequency spectrum hash
    Match in O(log N) against 1B+ fingerprint database (sorted B-tree)
  Video: perceptual hash of keyframes every 5 seconds
    Robust to: re-encoding, slight color grading, aspect ratio crop
  Match threshold: 80% (avoid false positives)
```

---

### 7.2 Transcoding at Scale — DAG Architecture

#### Pipeline Overview

```
Raw Video (GCS)
      │
      ▼
┌─────────────────┐
│  Video Splitter  │  Split into N × 10-second segments
└────────┬────────┘
         │ N segments
         ▼
┌────────────────────────────────────────────────────┐
│                  Segment Queue (Kafka)              │
│  { video_id, segment_n, gcs_path, priority }       │
└──────────┬──────────────────────────┬──────────────┘
           │                          │
    ┌──────▼──────┐            ┌──────▼──────┐
    │ Worker Pool │   ...      │ Worker Pool │
    │  (GPU/CPU)  │            │  (GPU/CPU)  │
    └──────┬──────┘            └──────┬──────┘
           │                          │
    For each segment: transcode to all quality tiers
    360p (CPU), 720p (CPU), 1080p (GPU), 4K (GPU), AV1 (TPU/GPU)
           │
           ▼
┌─────────────────┐
│  Segment Merger  │  FFmpeg concat (stream copy — no re-encode)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Post-processing │
│  - Thumbnail gen │
│  - ASR captions  │
│  - Chapter detect│
└────────┬────────┘
         │
         ▼
  GCS (processed bucket) → CDN
```

#### Worker Architecture

```
Transcoding worker (Kubernetes pod, GPU-enabled):
  Resource: 1× NVIDIA A100 (for GPU-accelerated encodes)
  or:       32 CPU cores (for CPU-only 360p/480p)

  FFmpeg GPU command (H.264 1080p):
    ffmpeg -hwaccel cuda -hwaccel_output_format cuda
           -i {input_segment}
           -vf "scale_cuda=1920:1080"
           -c:v h264_nvenc -preset p4 -b:v 5M
           -c:a aac -b:a 192k
           {output_segment}

  Processing time per 10-second segment:
    360p CPU:  ~3 seconds
    1080p GPU: ~5 seconds
    4K GPU:    ~15 seconds
    AV1 GPU:   ~60 seconds (most compute-intensive)

Priority queue:
  High: videos with > 10K subscribers (creator has audience waiting)
  Medium: standard upload
  Low: AV1 re-encodes of existing library (background)

Auto-scaling:
  Kafka consumer lag > 1,000 messages → add workers (Kubernetes HPA)
  Lag < 100 messages → scale down
  Peak: 30,000 GPU cores active; off-peak: 5,000
  Use spot/preemptible instances (70% cost reduction) — transcoding is interruptible
```

#### Segment Merging

```
After all N segments transcoded at quality Q:
  Input: seg_0_720p.mp4, seg_1_720p.mp4, ..., seg_59_720p.mp4
  Output: final_720p.mp4

  FFmpeg concat demuxer (stream copy — no re-encode):
    file 'seg_0_720p.mp4'
    file 'seg_1_720p.mp4'
    ...
  ffmpeg -f concat -i segments.txt -c copy final_720p.mp4

  Processing time: proportional to file size (I/O bound, not CPU bound)
  A 2 GB 720p video: ~30 seconds to concat

Segment boundary artifacts:
  Problem: if a keyframe isn't at exactly the 10-second boundary,
           the last frame of a segment may not be a keyframe
  Solution: Force keyframe at exactly every 10 seconds during transcode:
    -force_key_frames "expr:gte(t,n_forced*10)"
  → Guarantees clean segment boundaries
  → Also enables DASH segmented playback at exact offsets
```

#### Auto-Generated Captions (ASR Pipeline)

```
After video is transcoded:
  Audio track extracted → sent to ASR service (Whisper / Google Speech-to-Text)
  Transcript returned with word-level timestamps
  Formatted as WebVTT (subtitle file format):
    00:00:01.000 --> 00:00:04.000
    Hello and welcome to this video

  Auto-translated: Google Cloud Translation API → 80+ languages
  Stored in GCS alongside video segments
  Served via: GET /api/v1/videos/{id}/captions?lang=en

Quality note: ASR accuracy ~95%+ for clear English speech
  Creator can upload custom SRT/VTT to override auto-generated
```

---

### 7.3 Adaptive Bitrate Streaming (DASH)

#### DASH Manifest (MPD)

```xml
<!-- Example DASH MPD (Media Presentation Description) -->
<MPD type="static" mediaPresentationDuration="PT10M0S">
  <Period>
    <!-- 360p representation -->
    <AdaptationSet mimeType="video/mp4" codecs="avc1.4d401e">
      <Representation id="360p" bandwidth="800000" width="640" height="360">
        <SegmentTemplate
          initialization="$RepresentationID$/init.mp4"
          media="$RepresentationID$/seg_$Number$.mp4"
          startNumber="1" duration="10000" timescale="1000"/>
      </Representation>
    </AdaptationSet>

    <!-- 720p representation -->
    <AdaptationSet mimeType="video/mp4" codecs="avc1.640028">
      <Representation id="720p" bandwidth="2500000" width="1280" height="720">
        <SegmentTemplate .../>
      </Representation>
    </AdaptationSet>

    <!-- 1080p, 4K representations... -->

    <!-- Audio -->
    <AdaptationSet mimeType="audio/mp4" codecs="mp4a.40.2">
      <Representation id="audio_128k" bandwidth="128000">
        <SegmentTemplate .../>
      </Representation>
    </AdaptationSet>
  </Period>
</MPD>
```

#### DASH Player Adaptive Algorithm

```
YouTube's ABR (Adaptive Bitrate) Algorithm:

State: current_quality, buffer_level (seconds of video buffered)
Goal: maximize quality, minimize stalls

Every 2 seconds (segment boundary):
  bandwidth = last_segment_size / last_segment_download_time
  available_bandwidth = bandwidth × 0.85  (15% safety margin)

  desired_quality = highest quality with bitrate ≤ available_bandwidth

  Buffer-based override:
    IF buffer_level < 5s: downgrade one quality tier (avoid stall)
    IF buffer_level > 25s AND current_quality < max: upgrade one quality tier

  Hysteresis: require 3 consecutive cycles at same level before switching up
              (prevent oscillation on fluctuating connections)

Segment prefetch:
  Always prefetch next 3 segments at current quality
  Buffer target: 30 seconds of video ahead
  → 30s buffer means 30s to detect network degradation before stall
```

---

### 7.4 CDN & Global Distribution

#### Google Global Cache (GGC)

```
YouTube's CDN is not third-party (not CloudFront/Fastly) —
it's Google's own infrastructure co-located inside ISP networks.

Architecture:
  Tier 1: GGC Edge Nodes (inside ISP data centers globally)
    - 10K+ edge nodes across 1,000+ ISPs in 100+ countries
    - Direct peering: ISP to YouTube traffic stays on ISP network
    - Latency: < 10ms for 95% of global users
    - Cache capacity: PB-scale per major ISP node

  Tier 2: Google Point-of-Presence (PoP) in major cities
    - 130+ Google PoPs globally
    - Handles cache misses from Tier 1

  Tier 3: Google data centers (origin)
    - GCS origin: serves < 1% of video requests

Cache strategy for YouTube:
  Popular videos (top 20% of views = 80% of traffic — Pareto):
    → Cached at all Tier 1 nodes worldwide
    → Cache-Control: public, max-age=86400 (1 day; segments are immutable)

  Tail videos (long-tail content, rarely watched):
    → Cached only in regional PoPs or served from origin
    → CDN hit rate for tail: ~50% (vs 99%+ for popular)

Content routing:
  DNS Anycast: YouTube's CDN IP routes to nearest healthy edge node
  Client → DNS resolution → nearest edge PoP IP
  Edge handles request or proxies to origin if not cached
```

#### Cache Warming for New Viral Videos

```
Problem: New video uploaded → goes viral in 1 hour →
         all 30M concurrent viewers hit cold edge nodes → origin overwhelmed

Solution: Proactive cache warming

Viral detection (real-time Flink job):
  Monitor: views/minute per video_id
  Threshold: if > 10,000 views in 1 minute → classify as "trending"
  Trigger: POST cache_warm_request { video_id, quality_tiers, priority: high }

Cache warmer service:
  For each edge node near high-demand geography:
    Prefetch all segments to edge cache
    Priority: 360p and 720p first (most-used qualities)
  Warming complete in < 5 minutes
  → After warming: all subsequent requests served from edge (< 10ms)

Graduated warming (avoid thundering herd on origin):
  Instead of all edges fetching simultaneously → staggered by region
  US-East → US-West → EU → APAC (5-minute stagger)
  Each tier fetches from closer parent tier, not origin
```

---

### 7.5 Recommendation System — Two-Tower DNN

YouTube published the foundational recommendation paper (Covington et al., 2016).

#### Architecture

```
Two-Stage Architecture:

Stage 1: Candidate Generation (retrieves hundreds from millions)
┌─────────────────────────────────────────────────────┐
│                 Candidate Generator                  │
│                                                     │
│  User Tower:              Item Tower:               │
│  - Watch history embeds   - Video ID embed          │
│  - Search query embeds    - Title/tag embed         │
│  - Geographic signal      - View count, age         │
│  - Time-of-day features   - Engagement rate         │
│                                                     │
│  User vector (256-dim) × Item vectors               │
│  → ANN search (ScaNN): top-500 similar videos       │
└─────────────────────────────────────────────────────┘

Stage 2: Ranking (scores hundreds of candidates → top 20)
┌─────────────────────────────────────────────────────┐
│                     Ranker                          │
│  Features: candidate video + user + context         │
│  Outputs:  P(watch > 30s), P(like), P(share)        │
│  Score:    weighted combination (watch_time ×  0.6  │
│            + like_prob × 0.2 + share_prob × 0.2)   │
│  → Top 20 videos ranked by score                    │
└─────────────────────────────────────────────────────┘
```

#### Candidate Generation Detail

```
Offline (hourly Spark + TensorFlow job):
  1. Build user embedding:
     watch_history = MGET video_embed:v1 video_embed:v2 ...  (last 50 videos watched)
     user_vector = mean(watch_history_embeds) + search_embed + demographic_embed
     → 256-dimensional float vector

  2. Approximate Nearest Neighbor search (Google ScaNN):
     Query: user_vector against 800M pre-computed video vectors
     ScaNN ANNS: O(log N) search → top-500 candidates in ~20ms
     Stored in Redis: candidates:{user_id} = [video_id: score, ...]
     TTL: 6 hours

Video embedding (pre-computed offline):
  Input: video title, tags, transcript, thumbnail features
  Model: fine-tuned BERT for text + ResNet for thumbnail visual features
  Output: 256-dim embedding per video
  Stored in: FAISS index on GPU machines (800M × 256 × 4 bytes = 800 GB)
  → ScaNN partitions index across machines for distributed ANN search
```

#### Ranking Detail

```
Online (per recommendation request, ~100ms budget):
  1. Read candidates from Redis (< 1ms)
  2. Fetch features for all 500 candidates:
     - Redis MGET: fresh engagement stats (views, likes — last 24h)
     - Feature store (Redis Hash): pre-computed per-video features
  3. Run ranking model (TensorFlow Serving):
     Input: 500 × feature_vector
     Model: 3-layer DNN, ReLU activation, batch norm
     Output: 500 predicted watch-time scores
     Latency: ~50ms on GPU inference server
  4. Apply diversity rules:
     - Max 3 consecutive videos from same channel
     - Inject "information variety" (different topics)
     - Boost unwatched creators
  5. Return top 20

Training (continuous offline):
  Daily: retrain on 90-day watch history (100B events)
  Platform: Google's internal DistBelief / TensorFlow on TPU pods
  Label: actual watch time (in seconds) — regression, not classification
  Why regression not binary? "Watched 3 minutes" is more signal than "opened/not opened"
```

#### Cold Start Problem

```
New user (no history):
  Fall back to: popularity-based recommendations
    Top videos in user's detected country + language
    Trending videos (last 24h)
  After first 5 videos watched: switch to personalized model
  After 20 videos: model has enough signal for good personalization

New video (no engagement data):
  1. Use content-based features (title, tags, thumbnail quality score)
  2. Random exploration: show to small % of users with similar interests
     "Exploration" pool: 5% of recommendation slots given to new/unknown videos
  3. After 100 views: engagement signals available → full model
  Exploration/exploitation: multi-armed bandit (UCB algorithm)
    Score = predicted_value + exploration_bonus
    exploration_bonus decreases as video gets more views
```

---

### 7.6 Search

#### Architecture

```
Search stack: Elasticsearch (similar to Google's internal "Alexandria")

Indexing:
  Kafka "video.published" → Search Indexer worker → Elasticsearch
  Documents indexed:
    { video_id, title, description, tags, channel_name,
      transcript_text, language, published_at, view_count,
      like_ratio, duration_seconds }

  Index sharded by video_id (uniform distribution)
  500M+ documents → 50-node Elasticsearch cluster

Query pipeline:
  1. Query parse: "python tutorial" → tokens ["python", "tutorial"]
  2. Multi-field search:
     BM25 relevance on title (boost 3×), tags (boost 2×), transcript (boost 1×)
  3. Rerank by engagement signals:
     final_score = bm25_score × (view_count_log × 0.3 + recency × 0.2 + ctr × 0.5)
  4. Filter: duration, upload date, language
  5. Return top-20 with highlight snippets

Autocomplete:
  Redis sorted set: ZADD search_completions {score} "python tutorial"
  Score = search frequency in last 7 days
  On keypress: ZRANGEBYLEX search_completions "[py" "[py\xff" LIMIT 0 10
  → Prefix search in Redis (< 1ms) → suggest top 10 completions

Trending searches:
  Count-Min Sketch + Flink sliding window (1-hour)
  Same architecture as Twitter trending topics
```

---

### 7.7 Engagement — Likes, Comments, Watch History

#### Like / Dislike

```
Scale: 57,870 likes/sec

Like write:
  POST /api/v1/videos/{id}/like
  1. Redis: SADD video_likers:{video_id} {user_id}  (dedup — SET ignores re-like)
            INCR video:{video_id}:likes
  2. Kafka "video.liked" → Cassandra (durable store): async
  3. Recommendation signal: Kafka → online feature store → affects next rec batch

Like count reads:
  GET video:{video_id}:likes from Redis → O(1)
  Flushed to PostgreSQL every 60s (batch job)

Dislike (YouTube removed public counts in 2021):
  Still tracked privately: SADD video_dislikers:{video_id} {user_id}
  Used as negative training signal for recommendations
  Creator can see their own dislike count (YouTube Studio)
```

#### Comments

```
Scale: 11,574 comments/sec

Storage: Cassandra
  Partition key: video_id
  Clustering key: comment_id DESC (newest first)
  Top-level comments fetched with: LIMIT 20 ORDER BY like_count DESC

Comment ranking:
  YouTube uses "top comments" by default (not chronological)
  Ranking = like_count × recency_decay × creator_engagement_bonus
  Creator-replied comments: boosted (creator engagement signal)

Nested replies:
  Flat structure: reply_to_comment_id references parent
  Max nesting: 1 level (YouTube design — avoids deep threads)

Comment safety:
  ML toxicity classifier (Perspective API) on every comment
  Score > 0.9: auto-hold for review
  Keyword filters: channel-configurable blocked words
  Spam: velocity check (> 10 identical comments in 1 min → shadow ban)
```

#### Watch History

```
Scale: 2.7B users × 1,000 videos in history = 2.7T records

Storage: Bigtable (Google's wide-column NoSQL)
  Row key: user_id + reverse_timestamp (most recent first)
  Columns: video_id, watch_duration_ms, quality, device, timestamp

  Read pattern: "last 50 videos user watched" → O(50) Bigtable scan

Watch events pipeline:
  Client beacons (every 30s): { video_id, position_ms, quality }
  → Kafka "view.progress"
  → Flink: detect video completion (> 90% watched)
  → Bigtable: UPSERT watch_history row
  → Redis: UPDATE video:{id}:views counter

Resume playback:
  GET /api/v1/videos/{id}/progress
  → Redis: GET watch_progress:{user_id}:{video_id} = position_ms
  → On app open: resume from last saved position (beacon updates every 30s)
```

---

## 8. Data Models

### Video

```sql
-- PostgreSQL (metadata — needs rich queries, JOINs)
CREATE TABLE videos (
    video_id         BIGINT        PRIMARY KEY,   -- Snowflake ID
    channel_id       BIGINT        NOT NULL,
    title            VARCHAR(100)  NOT NULL,
    description      TEXT,
    tags             TEXT[],
    category         VARCHAR(50),
    language         VARCHAR(10),
    duration_seconds INTEGER,
    status           VARCHAR(20)   DEFAULT 'processing',
                                   -- processing|published|private|deleted
    visibility       VARCHAR(10)   DEFAULT 'public',
                                   -- public|unlisted|private
    available_qualities TEXT[],    -- ['360p','720p','1080p']
    thumbnail_url    TEXT,
    view_count       BIGINT        DEFAULT 0,
    like_count       BIGINT        DEFAULT 0,
    comment_count    BIGINT        DEFAULT 0,
    published_at     TIMESTAMPTZ,
    created_at       TIMESTAMPTZ   DEFAULT NOW()
);

CREATE INDEX idx_videos_channel ON videos (channel_id, published_at DESC);
CREATE INDEX idx_videos_status ON videos (status) WHERE status != 'published';
```

### Channel (User)

```sql
CREATE TABLE channels (
    channel_id       BIGINT        PRIMARY KEY,
    user_id          BIGINT        UNIQUE NOT NULL,
    handle           VARCHAR(30)   UNIQUE NOT NULL,  -- @channelname
    display_name     VARCHAR(100),
    description      TEXT,
    avatar_url       TEXT,
    banner_url       TEXT,
    subscriber_count BIGINT        DEFAULT 0,
    video_count      INTEGER       DEFAULT 0,
    is_verified      BOOLEAN       DEFAULT false,
    created_at       TIMESTAMPTZ   DEFAULT NOW()
);
```

### Segment Manifest (Cassandra)

```sql
-- Cassandra: video segment metadata (high read QPS, simple key lookup)
CREATE TABLE video_segments (
    video_id    BIGINT,
    quality     TEXT,       -- '360p', '720p', '1080p', '4k'
    codec       TEXT,       -- 'h264', 'vp9', 'av1'
    segment_n   INT,
    gcs_path    TEXT,
    cdn_url     TEXT,
    duration_ms INT,
    size_bytes  BIGINT,
    PRIMARY KEY (video_id, quality, codec, segment_n)
);

-- Transcoding job tracking
CREATE TABLE transcode_jobs (
    video_id     BIGINT,
    quality      TEXT,
    codec        TEXT,
    status       TEXT,   -- 'queued'|'processing'|'complete'|'failed'
    worker_id    TEXT,
    started_at   TIMESTAMP,
    completed_at TIMESTAMP,
    error        TEXT,
    PRIMARY KEY  (video_id, quality, codec)
);
```

---

## 9. Follow-Up Questions

### Q1: How do you handle a live event with 100M concurrent viewers (Super Bowl on YouTube)?

```
Scale challenge:
  100M concurrent viewers × 720p (2.5 Mbps) = 250 Tbps total egress
  Beyond any single CDN's capacity — requires careful pre-planning

Live streaming vs. VOD:
  Live: ultra-low latency HLS (LHLS) / CMAF with 2-10 second segments
  Latency: 5-20 seconds behind live (not true real-time — CDN caching requires it)
  vs. VOD: same DASH/HLS, pre-cached segments

Pre-event scaling:
  48 hours before: reserve capacity at all major GGC nodes
  Disable/deprioritize non-essential background jobs
  Pre-position backup ingest servers (RTMP → HLS transcoding)

CDN fan-out for live:
  Origin: single transcoding pipeline → GCS
  GGC Tier 2 (Google PoPs): 130 nodes each pull from origin
  GGC Tier 1 (ISP nodes): each pulls from nearest Tier 2
  → Origin serves only 130 requests/segment; viewers served from ISP cache

  At 2-second segments: 130 Tier-2 fetches per 2 seconds = 65 requests/sec at origin
  vs. 50M req/2sec at viewers — 99.99974% absorbed by CDN

Graceful degradation:
  If CDN misses: serve 360p instead of 1080p → 3× fewer bytes
  If overwhelmed: queue mechanism (virtual waiting room) — max 100M concurrent
```

### Q2: How do you implement Content ID at scale (copyright detection)?

```
Scale: 500 hours of video/minute uploaded = massive fingerprinting workload

Content ID architecture:
  Reference database: 800M+ rights-holder video + audio fingerprints
  Fingerprint types:
    Audio: every 1-second window → frequency spectrum hash (Shazam-style)
    Video: every keyframe (every 2-10 seconds) → perceptual hash

New upload fingerprinting:
  1. Audio extracted → sliding window every 0.5 seconds → fingerprint
  2. Query: exact-match hash lookup in fingerprint DB (hash table, O(1))
  3. For near-matches: Hamming distance search (perceptual similarity)
  4. Match threshold: 80% overlap with reference → claim

Fingerprint DB scale:
  800M videos × 3600 seconds × 2 fingerprints/second
  = 5.76 trillion fingerprints
  At 8 bytes each: 46 TB
  → Sharded hash table across 100 servers: 460 GB per shard
  → LSM-tree (LevelDB) for write-heavy ingestion; memory-mapped for reads

Response to match:
  Rights holder pre-configured policy: block | monetize | track | allow
  → Automatic enforcement (no human needed for clear matches)
  → Appeals: creator disputes claim → rights holder has 30 days to respond
```

### Q3: How do you design YouTube Shorts (TikTok-style vertical video)?

```
Key differences from regular YouTube:
  Max 60 seconds (extended to 3 min in 2023)
  Vertical 9:16 format
  Infinite scroll feed (not search-intent driven)
  Sounds/audio duets (TikTok features)

Upload pipeline differences:
  Same pipeline but no transcoding DAG needed:
    60-sec video → single FFmpeg transcode (no splitting needed)
    Processing time: 30-60 seconds end-to-end
    Creator sees video available immediately after upload

Feed architecture:
  Pure algorithmic (no chronological option)
  Pre-fetch next 10 videos in buffer (user swipes → next loads instantly)
  Recommendation: same two-tower but optimized for:
    - Loop count (does user replay video?)
    - Swipe-away time (how quickly did user skip?)
    - Sound-on engagement

Deduplication:
  Many Shorts re-upload TikTok content
  pHash + audio fingerprint → detect reposts
  Demote (not delete) re-uploaded content in Shorts feed
  Original creator gets attribution if same audio used (sound linking)

Storage optimization:
  Shorts: 60s video = ~50 MB at 720p
  4,320,000 Shorts/day × 50 MB = 216 TB/day (much less than regular uploads)
  Hot cache: most Shorts watched within 48 hours → aggressive CDN caching
```

### Q4: How do you prevent recommendation rabbit holes (misinformation amplification)?

```
Problem: pure watch-time optimization can amplify low-quality or extreme content
  because sensationalized videos often have higher watch time (outrage keeps people watching)

Multi-objective optimization:
  Old: maximize watch_time
  New: maximize weighted(watch_time, satisfaction, quality_score, diversity)
  quality_score: human rater score (1-5) × video category credibility
  satisfaction: post-watch survey ("Was this video satisfying to watch?")

Authoritative source boosting:
  Breaking news, medical, scientific topics:
    → Boost results from authoritative sources (CNN, NHS, WHO)
    → Defined by manual allow-list of trusted channels per topic
  "Information panels" shown with context: "This video discusses [topic].
    Here is information from [WHO, CDC]..."

Borderline content demotion:
  Videos that don't violate policy but are "problematic":
    conspiracy theories, miracle cures, political extremism
  ML classifier assigns "borderline score" (0-1)
  Recommendation score penalty: × (1 - borderline_score × 0.8)
  → Still watchable if user searches, but not algorithmically amplified

Diversity enforcement:
  Max 30% of recommendation slate from any single topic cluster
  After 5 consecutive political videos: inject non-political content
  "Diversification layer" in post-ranking step
```

---

## 10. Interview Summary Card

### Time Allocation (45 min)

| Minute | Focus |
|--------|-------|
| 0–5 | Clarifying questions |
| 5–10 | Requirements (functional + non-functional) |
| 10–15 | Back-of-envelope (uploads, storage, CDN bandwidth) |
| 15–20 | High-level diagram + upload and watch flows |
| 20–35 | Deep dive: Transcoding DAG (most impressive differentiator) |
| 35–42 | Recommendation two-tower DNN |
| 42–45 | Trade-offs + follow-up |

### The Three Key Decisions

```
1. TRANSCODING DAG:
   "A 10-minute video split into 60 × 10-second segments, each segment
    transcoded in parallel across a GPU worker pool. Wall-clock time: 5 minutes
    for all qualities. Sequential transcode would take hours.
    Segment boundaries enforced with forced keyframes so DASH can seek."

2. CODEC STRATEGY:
   "Encode H.264 (immediate — iOS/Smart TV), VP9 (30 min — Chrome/Android),
    AV1 (overnight — 50% compression savings). Serve by device capability
    via Accept header. AV1 saves YouTube ~$hundreds of millions/year in CDN."

3. TWO-TOWER RECOMMENDATION:
   "Separate candidate generation (ANN retrieval, every 6 hours, offline)
    from online ranking (< 100ms per request). Optimize for watch time
    (regression, not binary), not CTR. Cold start: popularity fallback
    + multi-armed bandit for exploration on new videos."
```

### Key Numbers

```
500 hours of video uploaded per minute (= 50 videos/sec)
1 billion hours watched per day → 11.57M concurrent viewers avg
2.7B MAU, 800M total videos
30 PB/day net storage growth → 54.75 EB over 5 years
13 TB/s CDN egress; 99%+ absorbed by GGC edge nodes
57,870 likes/sec → Redis INCR
Transcoding: 30,000 GPU cores, 300 parallel tasks per 10-min video
Candidate generation: 256-dim vectors, 800M videos in FAISS/ScaNN
```

### Technology Choices

| Component | Technology | Why |
|-----------|-----------|-----|
| Raw video storage | GCS (Google Cloud Storage) | Petabyte scale, multipart upload, lifecycle |
| Transcoding | FFmpeg + GPU cluster (NVIDIA A100) | Industry standard, NVENC hardware acceleration |
| DAG orchestration | Apache Airflow / custom (Kafka + workers) | Segment-level parallelism |
| Video segments | GCS → Google Global Cache (GGC) | Co-located with ISPs, free peering |
| Streaming protocol | MPEG-DASH | Codec-agnostic, supports multi-bitrate |
| Video metadata | PostgreSQL (sharded) | Relational, rich queries, mature |
| Watch history | Bigtable (Google's NoSQL) | Wide-column, time-series, 2.7B users |
| Recommendation candidates | FAISS / ScaNN (ANN search) | Billion-scale vector retrieval |
| Ranking model | TensorFlow Serving (DNN on GPU) | Industry-standard ML serving |
| Search | Elasticsearch | Full-text, multi-field BM25 ranking |
| Likes / counters | Redis INCR → PostgreSQL flush | 57K ops/sec, atomic, O(1) |
| Comments | Cassandra | High write, time-ordered per video |
| Content ID | Custom fingerprint hash DB | 5.76T fingerprints, O(1) lookup |

---

*co-authored-by: wibey jetbrains plugin (wibey.walmart.com/code)*
