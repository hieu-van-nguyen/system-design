# System Design: Netflix (Video Streaming Platform)

> FAANG-level interview guide — video upload, transcoding pipeline, adaptive bitrate streaming, CDN, and personalization at scale.
> Think: Netflix, YouTube, Disney+, Prime Video.

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | Users can **browse and search** the content catalog (movies, series, documentaries) |
| FR-2 | Users can **stream video** on any device (TV, mobile, browser, game console) |
| FR-3 | Streaming adapts to **network conditions** (adaptive bitrate — 240p → 4K HDR) |
| FR-4 | Content owners can **upload raw video**; platform transcodes it to multiple formats |
| FR-5 | Users can **resume playback** from where they left off (watch history) |
| FR-6 | Support **multiple audio tracks** (languages, audio description) and **subtitles** |
| FR-7 | **Personalized homepage**: recommendations based on viewing history |
| FR-8 | Support **multiple user profiles** per account (up to 5 per household) |
| FR-9 | **Download for offline** viewing (mobile, limited content) |
| FR-10 | DRM (**Digital Rights Management**) — prevent unauthorized redistribution |

**Out of scope:** Live streaming, social features, user-generated content, creator monetization.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 300M subscribers; 200M DAU; 1B+ hours streamed/day |
| **Availability** | 99.99% — every second of downtime costs ~$15K in lost revenue |
| **Latency** | Video playback starts within **2 seconds** of pressing Play (p99) |
| **Throughput** | Peak: 15% of internet traffic globally during prime time |
| **Quality** | Adaptive bitrate: never buffer if bandwidth > 0.5 Mbps; 4K at 25 Mbps |
| **Consistency** | Watch position consistent across devices within **30 seconds** |
| **Durability** | Raw video masters stored permanently; transcoded files retained indefinitely |
| **Security** | All video encrypted (DRM); no direct S3 URL exposure; token-authenticated streams |
| **Cost** | CDN egress is the #1 cost driver — minimize with caching, compression, edge compute |

---

## 3. Back-of-Envelope Estimation

### Streaming Traffic

```
DAU                          = 200M users
Avg concurrent viewers       = 200M × 15% (prime time) = 30M concurrent streams
Avg bitrate (mixed devices)  = 5 Mbps (blend of 1080p + 4K + mobile)

Total egress bandwidth       = 30M × 5 Mbps = 150 Tbps
(Netflix's actual peak: ~200 Tbps — this is ~75% of real traffic ✓)

Per-stream:
  2-hour movie at 1080p (5 Mbps) = 5 Mbps × 7,200s / 8 = 4.5 GB
  2-hour movie at 4K (25 Mbps)   = 25 Mbps × 7,200s / 8 = 22.5 GB
  Avg per movie (blended)        ≈ 7 GB
```

### Storage

```
Content catalog:
  Active titles              = 15,000 movies + 5,000 series × 10 episodes = 65,000 titles
  Formats per title          = 20 (4K/1080p/720p/480p/360p × 4 codecs + HDR variants)
  Avg encoded size/format    = 3 GB per 2-hour film
  Total catalog storage      = 65,000 × 20 × 3 GB ≈ 3.9 PB (encoded)

Raw masters (cold storage):
  Avg raw master size        = 100 GB per title
  65,000 × 100 GB            = 6.5 PB (in Amazon Glacier / S3 cold)

Upload pipeline (new content):
  New titles/week            = 100
  100 × 100 GB               = 10 TB raw upload/week → transcoding pipeline
```

### CDN & Caching

```
Cache hit rate target        = 95% (popular content served from edge)
Unique content requested/day = 20% of catalog (long tail is cold)
Hot content (top 1,000)      = serves 80% of all streams → pre-positioned at all PoPs

CDN PoPs globally            = 1,000+
Per-PoP storage (avg)        = 200 TB (hot titles, multiple formats)
Total CDN edge storage       = 1,000 × 200 TB = 200 PB
```

### Transcoding

```
New upload: 100 GB raw → 20 output formats × 3 GB = 60 GB total output
Transcoding time (parallel): 100 GB × 20 profiles on 50-worker cluster ≈ 4 hours
Cost: ~$50–200 per title (GPU-accelerated encoding at scale)
```

---

## 4. High-Level Design

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                              CLIENTS                                             │
│   Smart TV  │  iOS/Android  │  Browser (DASH/HLS)  │  Game Console              │
└──────┬───────────────┬──────────────────┬────────────────────────────────────────┘
       │               │                  │
       │   Control plane (API calls)       │    Data plane (video bytes)
       │               │                  │
┌──────▼───────────────▼──────┐  ┌────────▼────────────────────────────────────┐
│     API Gateway / CDN        │  │           VIDEO CDN (Open Connect)          │
│  (CloudFront / custom Nginx) │  │                                             │
└──────┬───────────────────────┘  │  ┌──────────┐  ┌──────────┐  ┌──────────┐ │
       │                          │  │  PoP     │  │  PoP     │  │  PoP     │ │
       │                          │  │ (edge)   │  │ (edge)   │  │ (edge)   │ │
       │                          │  │ 200 TB   │  │ 200 TB   │  │ 200 TB   │ │
       │                          │  └────┬─────┘  └────┬─────┘  └────┬─────┘ │
       │                          └───────┼──────────────┼──────────────┼───────┘
       │                                  │              │              │
       │                          ┌───────▼──────────────▼──────────────▼───────┐
       │                          │         Origin Storage (S3)                  │
       │                          │         Transcoded video files               │
       │                          └──────────────────────────────────────────────┘
       │
┌──────▼───────────────────────────────────────────────────────────────────────┐
│                         MICROSERVICES                                         │
│                                                                               │
│  ┌──────────────┐  ┌───────────────┐  ┌────────────────┐  ┌──────────────┐  │
│  │ User/Auth    │  │  Catalog/      │  │  Playback      │  │ Recommendation│  │
│  │ Service      │  │  Metadata Svc  │  │  Service       │  │ Service      │  │
│  └──────────────┘  └───────────────┘  └────────┬───────┘  └──────────────┘  │
│                                                 │                             │
│  ┌──────────────┐  ┌───────────────┐  ┌────────▼───────┐  ┌──────────────┐  │
│  │ Watch History│  │  Search       │  │  Streaming     │  │ Billing/     │  │
│  │ Service      │  │  Service      │  │  Session Svc   │  │ Subscription │  │
│  └──────────────┘  └───────────────┘  └────────────────┘  └──────────────┘  │
└──────────────────────────────────────────────────────────────────────────────┘
       │
┌──────▼───────────────────────────────────────────────────────────────────────┐
│                    CONTENT INGESTION PIPELINE                                 │
│                                                                               │
│  Studio Upload → S3 (raw)  →  Transcoding Farm  →  Quality Check  →  CDN     │
│                              (AWS MediaConvert /                    Pre-push  │
│                               custom FFmpeg workers)                          │
└──────────────────────────────────────────────────────────────────────────────┘
```

### Core API

```
// Playback
GET  /v1/titles/{titleId}/manifest        → MPD (DASH) or M3U8 (HLS) manifest
GET  /v1/titles/{titleId}/playback-info   → { streamUrl, drmLicense, audioTracks, subtitles }
POST /v1/playback/heartbeat               → { titleId, profileId, positionMs, bandwidth }
POST /v1/playback/stop                    → { titleId, profileId, positionMs }

// Catalog
GET  /v1/titles?genre=thriller&page=1     → TitleCard[]
GET  /v1/titles/{titleId}                 → TitleDetail (metadata, cast, ratings)
GET  /v1/search?q=inception               → SearchResult[]

// Recommendations
GET  /v1/profiles/{profileId}/homepage    → Row[] (each row: category + TitleCard[])
GET  /v1/profiles/{profileId}/continue-watching → TitleCard[] with positionMs

// User
POST /v1/auth/login
GET  /v1/accounts/{id}/profiles
POST /v1/profiles/{profileId}/ratings     → { titleId, rating: thumbs_up | thumbs_down }
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: CDN Strategy — Own CDN (Open Connect) vs. Third-Party (CloudFront / Akamai)

| Approach | Latency | Cost at Scale | Control | Time to Deploy |
|----------|---------|--------------|---------|---------------|
| Third-party CDN (CloudFront, Akamai) | Good (30–80ms) | Very high egress fees | Low | Zero |
| **Own CDN — Open Connect (Recommended)** | Excellent (< 10ms) | Near-zero transit | Full | Years |
| Hybrid (own + third-party fallback) | Excellent + Good | Reduced | High | Moderate |

```
Why Netflix built its own CDN (Open Connect) — the billion-dollar decision:

At Netflix's scale (150 Tbps peak), CDN egress cost is existential:
  AWS CloudFront egress: ~$0.009/GB (enterprise negotiated)
  150 Tbps × 3600s/hr × 3 prime-time hours = 202,500 TB/day
  Daily egress cost at CloudFront rates: 202,500,000 GB × $0.009 = $1.8M/day
  Annual: ~$650M/year just in CDN fees

Open Connect eliminates this:
  Netflix installs physical OCA appliances inside ISP networks (AT&T, Comcast, etc.)
  ISP value: Netflix traffic never leaves ISP's network → less transit cost for ISP
  Netflix value: zero internet transit fees (Netflix pays hardware, not bandwidth)
  70% of Netflix traffic served from embedded OCAs → 70% of $650M saved

Latency advantage:
  OCA inside ISP → subscriber's router is 1-2 hops away → < 10ms RTT
  CloudFront PoP: 20-80ms (depends on PoP proximity)
  For video: lower latency = faster segment fetch = larger buffer = fewer quality dips

Control advantage:
  Custom cache eviction (time-shifted, popularity-aware pre-positioning)
  Custom failure detection and fallback routing
  Netflix proprietary ABR signals fed back to CDN → cache warming on predicted demand

Third-party CDN as fallback:
  OCAs don't cover every ISP (small ISPs, rare geographies)
  Netflix maintains CloudFront as Tier 3 fallback (< 5% of traffic)
  New markets: launch with CloudFront → install OCAs as subscriber count justifies it

Decision: Own CDN for scale. Third-party as fallback and for new market launch.
  In interview: "Own CDN is only viable above ~$50M/year in CDN spend. Below that,
  CloudFront is the right answer — you're paying for infrastructure you can't fill."
```

---

### Trade-Off 2: Codec Strategy — H.264 vs. H.265/HEVC vs. AV1

| Codec | Compression vs H.264 | Encode Speed | Device Support | License Cost |
|-------|---------------------|-------------|---------------|-------------|
| **H.264 (AVC)** | Baseline | Fast | 99.9% of devices | Moderate |
| H.265 (HEVC) | 40% better | Medium | ~70% (Apple, newer TV) | Expensive |
| **AV1 (Recommended for new content)** | 30–50% better | 10× slower | 60%+ (growing fast) | Free (open) |
| VP9 | 30–40% better | Slow | Chrome, Android, WebOS | Free |

```
The codec trade-off is fundamentally about encode cost vs. delivery cost:

H.264 (keep forever as universal fallback):
  Supported on every device manufactured since 2010
  Hardware decode chips in all TVs, phones, gaming consoles
  Without H.264, Netflix cannot stream to millions of older devices
  Never drop H.264 — always encode it alongside better codecs

H.265/HEVC:
  Better compression than H.264 but complex patent pool (HEVC Advance + Via)
  License cost per device: $0.20-2.00 → significant for 300M subscribers
  Apple-native (HLS with HEVC works well on iOS/tvOS)
  Netflix uses HEVC for 4K HDR on Apple devices (hardware decode required for 4K)

AV1 (Netflix's long-term bet):
  Developed by AOMedia (Google, Netflix, Amazon, Mozilla, Intel — royalty-free)
  30-50% bandwidth savings vs. H.264 → at 150 Tbps peak:
    30% savings = 45 Tbps reduction = ~$195M/year CDN cost saved (at $0.009/GB)
  Encode is 10× slower: 2-hour movie at H.264: 30 min → AV1: 5 hours
    → Only viable for offline transcoding pipeline (not live)
    → Acceptable trade-off: encode once, deliver millions of times

  Device support growing: Android TV, Smart TVs, Chrome, Edge, Firefox, PS5/Xbox Series X
  Current Netflix AV1 adoption: ~30% of streams (growing as device support increases)

Tiered codec strategy:
  New content: encode AV1 + H.264 immediately; H.265 for Apple
  Old catalog: batch re-encode to AV1 during off-peak (save long-term costs)
  Delivery: client advertises supported codecs → Playback Service selects best

Decision: Tiered strategy with AV1 as primary for capable devices, H.264 as universal
  fallback, H.265 for Apple ecosystem. Never encode only one codec.
  The interview insight: "Codec choice is a cost optimization problem first."
```

---

### Trade-Off 3: Streaming Protocol — DASH vs. HLS vs. Proprietary

| Protocol | Open Standard | Adaptive | Apple Support | Latency |
|----------|--------------|---------|--------------|---------|
| **DASH (MPD)** | ✅ ISO standard | ✅ | ❌ Native (JS only) | Low |
| **HLS (M3U8)** | ✅ Apple RFC | ✅ | ✅ Native | Low |
| Proprietary (Smooth Streaming) | ❌ | ✅ | ❌ | Low |
| WebRTC | ✅ | ❌ | Partial | Ultra-low |

```
Why Netflix uses DASH + HLS (dual protocol, not one):

Apple locked HLS:
  iOS, tvOS, Safari: ONLY support HLS natively
  DASH on Apple: possible via Media Source Extensions (MSE) in JavaScript
  MSE on Safari: historically limited; iOS Safari MSE unreliable
  → Must generate HLS manifests for Apple ecosystem (no choice)

DASH for everything else:
  Android, Smart TVs, game consoles, Chromecast, browsers (Chrome/Firefox/Edge)
  DASH is more flexible: supports multiple DRM systems in one manifest
  (Widevine + PlayReady in same DASH MPD via ContentProtection element)
  HLS DRM: only FairPlay → separate manifest needed for multi-DRM

Practical implementation:
  One source file → two manifests generated from same segment files
  HLS .m3u8 → references .ts or .fmp4 segments (fmp4 = common segments)
  DASH .mpd → references same .fmp4 segments
  Trick: use fragmented MP4 (ISO-BMFF) as container — readable by both HLS and DASH
  → One set of segment files, two manifests → halves CDN storage vs. separate files

WebRTC (rejected for VOD):
  Designed for real-time (video conferencing), not VOD
  No CDN caching of WebRTC streams (peer-to-peer)
  Complex: STUN/TURN, ICE negotiation adds 500ms+ overhead to start
  For streaming: HTTP-based ABR (DASH/HLS) is strictly better for CDN-cacheable content

Decision: DASH for non-Apple, HLS for Apple, fMP4 shared segment files.
  Mention the fMP4 trick — it shows deep understanding of the protocol layer.
```

---

### Trade-Off 4: Watch History Database — Cassandra vs. PostgreSQL vs. DynamoDB

| Approach | Write Throughput | Profile-Scoped Reads | Multi-Region | Complexity |
|----------|-----------------|---------------------|-------------|-----------|
| PostgreSQL | 50K writes/sec (with sharding) | ✅ Rich SQL | Complex | Low |
| **Cassandra (Recommended)** | 1M+ writes/sec | ✅ Partition key | ✅ Native | Medium |
| DynamoDB | 1M+ writes/sec | ✅ Partition key | ✅ Global Tables | Low |
| Redis (cache only) | 1M+ writes/sec | ✅ Fast | ❌ | Low |

```
Why watch history is the hardest DB problem on Netflix:

Write volume:
  200M DAU × avg 2 sessions × 4 heartbeats/min × 60 min = 96B writes/day
  = 1.1M writes/sec peak (top of prime time)

  PostgreSQL at 1.1M writes/sec: requires massive sharding (100+ shards)
  Each shard needs primary + replicas → ops complexity explodes
  Even with sharding, cross-shard fan-out for "continue watching" query is expensive

  Cassandra is designed for exactly this:
    Write path: commit log (sequential write) + memtable → extremely fast
    1.1M writes/sec on a 20-node cluster: well within capacity
    Linear scale: add nodes → proportional throughput increase

Access pattern — profile-scoped reads:
  Query: "All titles this profile is watching, sorted by last updated"
  Cassandra partition key = profile_id → all data for a profile on same nodes
  Clustering key = updated_at DESC → sorted at storage layer (no ORDER BY in memory)
  Read: single partition scan → < 5ms for 1,000 items

Cross-device resume:
  Device A writes heartbeat → Cassandra (async replication to all replicas)
  Device B reads resume position → Cassandra (quorum read = latest data)
  Consistency level: LOCAL_QUORUM (2 of 3 replicas in same region)
  Cross-region: EACH_QUORUM or eventual (< 5s lag between regions)

DynamoDB (credible alternative):
  Same partition key = profile_id, sort key = updated_at → same access pattern
  Advantage: fully managed (no ops), DAX for caching, global tables for multi-region
  Disadvantage: vendor lock-in, complex data modeling for secondary access patterns
  Netflix uses Cassandra (self-hosted) for control and cost at their scale

PostgreSQL (rejected at this scale):
  1.1M writes/sec requires connection pooling + extensive sharding
  "Continue watching" query: JOIN + ORDER BY + LIMIT across sharded writes → complex
  Time-series data with high cardinality (profileId × titleId) not PostgreSQL's strength

Decision: Cassandra with partition key = profile_id.
  The 1.1M writes/sec figure is the key to unlocking why — mention it immediately.
```

---

### Trade-Off 5: Transcoding — Sequential vs. Parallel Chunk-Based vs. Cloud-Native

| Approach | Time for 2-hr Film | Cost | Failure Handling | Flexibility |
|----------|-------------------|------|-----------------|------------|
| Sequential (one machine) | 4–8 hours | Low | Restart from scratch | Low |
| **Parallel chunk-based (Recommended)** | 20–30 min | Medium | Retry failed chunks | High |
| Cloud-native (AWS MediaConvert) | 1–2 hours | Variable | Managed | Low |
| Client-side encoding | Not applicable | Zero | User's problem | N/A |

```
Why sequential encoding fails at Netflix scale:

100 new titles/week × 1 machine per title × 4 hours each:
  Need: 100 × 4h / 168h/week = 2.4 dedicated machines continuously busy
  But 20 output profiles × 1 encoder per profile = 20 machines per title simultaneously
  → 100 × 20 = 2,000 machines running all week → expensive and underutilized off-peak

Parallel chunk-based encoding (Netflix's approach):
  Split: 2-hour movie → 60 chunks of 2 minutes each (GOP-aligned)
  Parallelize: 60 chunks × 20 profiles = 1,200 independent tasks
  Each task: ~100 seconds of wall-clock time on a GPU worker
  Total elapsed: ~20–30 minutes (limited by stitch-and-merge overhead)
  Worker fleet: GPU-enabled spot instances (70% cost reduction vs. on-demand)

GOP-aligned splitting (critical detail):
  GOP = Group of Pictures: I-frame + P-frames + B-frames
  Split in the middle of a GOP → decoder cannot start from that point
  Must split at I-frame boundaries (keyframes)
  FFmpeg: -ss {timestamp} -c copy → finds nearest keyframe and cuts cleanly
  Stitching: ffmpeg concat demuxer → combines chunks without re-encoding

Failure handling:
  Each chunk task is independent → chunk failure = retry that chunk only
  No need to restart entire 4-hour encode on a single encoding failure
  Orchestrator (Temporal): tracks task state; retries with exponential backoff
  Worker crash: Temporal marks task as failed → dispatch to another worker

AWS MediaConvert (cloud-native option):
  Fully managed; less operational burden
  Limitations: fixed profiles (less optimization per-title), black-box quality control
  Netflix needs custom per-title quality optimization (Dynamic Optimizer) → own encoding
  MediaConvert viable for: startups, teams without encoding engineering expertise

Decision: Parallel chunk-based GPU encoding on spot instances.
  The interview key: explain GOP-aligned splitting — it demonstrates encoding knowledge.
  Calculate: 60 chunks × 20 profiles = 1,200 tasks → makes "20 minutes" credible.
```

---

### Trade-Off 6: Recommendation Approach — Collaborative Filtering vs. Content-Based vs. Two-Tower Neural

| Approach | Cold Start | Serendipity | Compute | Accuracy |
|----------|-----------|-------------|---------|---------|
| Collaborative filtering (matrix factorization) | ❌ Poor | High | Medium | Good |
| Content-based (metadata matching) | ✅ Good | ❌ Low | Low | Medium |
| **Two-tower neural + ranking model (Recommended)** | ✅ Good | High | High | Excellent |
| Simple popularity ranking | N/A | ❌ None | Zero | Poor |

```
Why simple collaborative filtering fails at Netflix scale:

Classic matrix factorization (SVD):
  Users × Titles matrix → factor into user embedding × title embedding
  Problem 1: 300M users × 15K titles = 4.5 trillion matrix entries (too sparse)
  Problem 2: cold start — new user has no history → what do you recommend?
  Problem 3: no context signals — same user, different recommendations for 
    "Sunday morning" vs. "Friday night at 11pm" → needs context features

Two-tower neural network (Netflix's approach):
  User tower:  encodes {viewing history, ratings, demographics, device, time_of_day}
               → 128-dim user embedding
  Item tower:  encodes {genre, director, cast, mood, release_year, avg_rating}
               → 128-dim item embedding
  Score:       dot_product(user_vec, item_vec) → relevance score

  Cold start solution:
    New user: user tower uses demographic + device features (even without history)
    New title: item tower uses metadata → can be recommended immediately

  ANN retrieval (Faiss/ScaNN):
    15K titles → 15K item embeddings pre-computed, stored in vector index
    At query time: compute user embedding → ANN search → top 1,000 in < 50ms
    Without ANN: scoring all 15K titles × dot product: ~1ms (small catalog)
    At YouTube scale (800M videos): ANN is essential; at Netflix (15K titles): still useful

Ranking model (second stage):
  Takes top 1,000 candidates from recall
  Scores each with gradient boosted trees (GBDT) using hundreds of features:
    P(play | user, title, context)
    P(complete | user, title)  ← watch-completion rate, not just click rate
    Business rules: promote content nearing license expiry
  → Returns top 75 per homepage row

Why watch-completion rate matters more than CTR:
  CTR optimizes for clicks (thumbnail clickbait) → user plays, disappointed, churns
  Completion rate optimizes for satisfaction → user finishes, trusts recommendations
  Netflix explicitly avoids CTR as primary signal — they saw the degradation

Artwork personalization (extra credit):
  Same movie, different thumbnail per user segment (A/B tested)
  User watches action movies: show action scene
  User watches romance: show lead actors in romantic pose
  Small engagement lift (2-3%) × 300M users = millions of additional streams

Decision: Two-tower recall + GBDT ranking. Offline batch (every 4h) + real-time context re-ranking.
  Mention watch-completion vs. CTR distinction — it's the most interesting insight.
```

---

### Trade-Off 7: Playback Start Latency — Pre-Buffer vs. Lowest-Quality Fast-Start vs. Predictive Pre-fetch

| Strategy | Time to First Frame | User Experience | Bandwidth Waste | Complexity |
|----------|--------------------|-----------------|-----------------|-----------| 
| Wait for full buffer before play | 30s+ | ❌ Terrible | Zero | Zero |
| **Lowest quality fast-start → ramp (Recommended)** | < 2s | ✅ Good | Low | Low |
| Predictive pre-fetch (before user presses Play) | < 500ms | ✅ Excellent | Medium-High | High |
| Download full file first | Minutes | ❌ | Zero during play | Low |

```
The 2-second SLO (most important UX metric for streaming):

Research: every 1 additional second of startup delay → ~X% abandonment rate
Netflix target: < 2 seconds from "Play" press to first frame rendered

Lowest quality fast-start (Netflix's primary approach):
  1. Play pressed → immediately fetch first 3 segments at 360p (lowest quality)
     360p at 500 Kbps: 4-second segment = 250 KB → download in ~100ms on broadband
     Player renders first frame from first downloaded segment → < 500ms
  2. ABR algorithm ramps quality as buffer fills:
     Buffer reaches 10s ahead → upgrade to 720p
     Buffer reaches 20s ahead → upgrade to 1080p
     Buffer reaches 30s ahead → upgrade to 4K (if supported)
  3. User sees quality improve in first 30 seconds (seamless, almost unnoticeable)

Why not start at highest quality:
  4K first segment at 25 Mbps: 4-second segment = 12.5 MB
  On average 50 Mbps broadband: 12.5 MB / 50 Mbps = 2 seconds just for first segment
  → First frame appears at 2+ seconds (barely meeting SLO on fast connections)
  → On slower connections (10 Mbps): 10 seconds for first 4K segment → terrible UX

Predictive pre-fetch (experimental, used by some platforms):
  Machine learning predicts: "Given user's browse history and hover time, they will
  play this title in the next 10 seconds with 80% confidence"
  → Begin pre-downloading segments before user presses Play
  → Playback appears instant (< 500ms) because segments already in device cache
  Trade-offs:
    Bandwidth wasted when prediction is wrong (80% accuracy = 20% wasted pre-fetch)
    At 200M sessions/day × 20% misprediction × 4 segments × 250 KB = 40 TB/day wasted
    Netflix uses predictive pre-fetch selectively (next episode auto-play) not for browse

Auto-play next episode (pre-fetch justified):
  When episode is within 30 seconds of ending: pre-fetch first 30s of next episode
  Prediction confidence: ~95% (user watching episode likely to watch next)
  Bandwidth waste: < 5% (high confidence) → justified

Decision: Lowest quality fast-start + ABR ramp as primary.
  Predictive pre-fetch only for auto-play-next-episode (high-confidence scenarios).
  The 2-second SLO is driven by starting at the lowest viable quality, not by buffering.
```

---

## 6. Deep Dive

### 6.1 Video Upload & Ingestion Pipeline

```
Studio/Content team uploads raw master file:

Step 1 — Upload (large file, reliable):
  - Chunked multipart upload to S3 via presigned URLs
  - Each chunk: 100 MB; parallel upload of N chunks simultaneously
  - Client-side SHA256 checksum per chunk → S3 verifies integrity
  - On all chunks uploaded → trigger ingestion pipeline (S3 event → SQS → Pipeline Orchestrator)

Step 2 — Validation:
  - Probe video file: resolution, frame rate, codec, duration, audio channels
  - Validate against content contract (min 4K master for theatrical releases)
  - Virus scan
  - Reject: corrupt files, unsupported codecs, missing audio tracks

Step 3 — Transcoding (most compute-intensive):
  → See Section 5.2

Step 4 — Quality Assurance:
  - Automated QC: black frames, audio sync drift, artifacting, subtitles timing
  - Perceptual quality score (VMAF) must exceed threshold per profile
  - Failed profiles re-encoded with adjusted parameters

Step 5 — Metadata ingestion:
  - Title metadata: synopsis, cast, genres, ratings, release year
  - Artwork: thumbnail images (multiple aspect ratios for different screens)
  - Audio tracks and subtitle tracks registered in Catalog DB

Step 6 — CDN pre-positioning:
  - Popular/anticipated titles pushed to all PoPs before release
  - Long-tail titles: lazy load on first request (cache miss → origin → PoP → client)
```

---

### 6.2 Transcoding Pipeline (Encoding Farm)

A single raw 4K 2-hour master → 20+ output profiles.

```
Output profiles matrix:

Resolution  │ Bitrate    │ Codec    │ HDR    │ Use Case
────────────┼────────────┼──────────┼────────┼─────────────────
4K (2160p)  │ 15–25 Mbps │ AV1/H.265│ HDR10  │ 4K TV
1080p       │  4–8 Mbps  │ AV1/H.264│ SDR    │ HD TV, desktop
720p        │  2–4 Mbps  │ H.264    │ SDR    │ Laptop, tablet
480p        │  1–2 Mbps  │ H.264    │ SDR    │ Mobile (LTE)
360p        │  0.3–1 Mbps│ H.264    │ SDR    │ Mobile (3G)
240p        │  0.2–0.5   │ H.264    │ SDR    │ Very poor network

× Audio: AAC 5.1, AAC Stereo, Dolby Atmos (per language track)
× Subtitles: WebVTT / TTML per language (separate from video)

Total output files per 2-hour film: ~20 video + 30 audio + 40 subtitle = ~90 files
```

**Parallel transcoding architecture:**

```
Pipeline Orchestrator (Temporal / custom):
  1. Split raw video into 2-minute chunks (GOP-aligned — no mid-GOP splits)
     → 60 chunks for 2-hour movie
  2. For each (chunk, profile) pair → create transcoding task
     → 60 chunks × 20 profiles = 1,200 parallel tasks
  3. Dispatch to Transcoding Worker Pool (GPU-enabled EC2 instances)
  4. Each worker: FFmpeg / AV1 encoder with hardware acceleration (NVENC, Intel QSV)
  5. Output chunks uploaded to S3
  6. Orchestrator: stitches chunks back into single file per profile (ffmpeg concat)
  7. Generates DASH/HLS manifest referencing segment files

Total wall-clock time: 4 hours → with 50 workers → ~5 minutes of compute per worker
Actual elapsed (parallelized): ~20–30 minutes per title

Codec choice rationale:
  AV1: 30% better compression than H.264 at same quality → massive CDN savings
       Slower to encode (10× vs H.264) → acceptable for offline pipeline
  H.264: Universal device support → always kept as fallback
  H.265/HEVC: Middle ground; supported on Apple devices
```

---

### 6.3 Adaptive Bitrate Streaming (ABR)

Netflix uses **DASH** (Dynamic Adaptive Streaming over HTTP) on most devices and **HLS** on Apple devices.

```
How ABR works:

1. Client fetches manifest (MPD/M3U8):
   <Period>
     <AdaptationSet mimeType="video/mp4">
       <Representation id="1" bandwidth="500000"  width="640"  height="360"  codecs="avc1"/>
       <Representation id="2" bandwidth="2000000" width="1280" height="720"  codecs="avc1"/>
       <Representation id="3" bandwidth="8000000" width="1920" height="1080" codecs="avc1"/>
       <Representation id="4" bandwidth="20000000" width="3840" height="2160" codecs="hvc1"/>
       <!-- Each Representation lists segment URLs -->
     </AdaptationSet>
   </Period>

2. Client downloads segments (typically 4-6 seconds each):
   /segment/movie123/1080p/seg001.mp4
   /segment/movie123/1080p/seg002.mp4

3. ABR algorithm (client-side) decides next segment quality:
   Input signals:
     - Measured download speed of last segment
     - Current buffer occupancy (seconds buffered ahead)
     - Historical bandwidth estimate (EWMA)

   Buffer-based algorithm (Netflix Bola):
     If buffer_level > HIGH_WATERMARK (30s) → upgrade quality
     If buffer_level < LOW_WATERMARK (5s)  → downgrade quality
     Target: keep buffer at 15–20s ahead
     Hysteresis: don't switch quality more than once per segment

4. Segment download:
   CDN serves segment from edge cache (cache hit → <50ms)
   Cache miss → origin fetch → S3 presigned URL → serve + cache at edge

Quality switching (seamless):
  - DASH media presentation boundary: switch at any segment boundary
  - No re-buffering on quality change (next segment starts in new quality)
  - Player pre-buffers next 3 segments to absorb brief bandwidth drops
```

---

### 6.4 CDN Architecture — Netflix Open Connect

Netflix built its own CDN (Open Connect) embedded inside ISP networks.

```
Three-tier architecture:

Tier 1 — ISP-embedded Appliances (OCA — Open Connect Appliance):
  - Physical servers inside ISP data centers (AT&T, Comcast, Deutsche Telekom...)
  - 100+ TB SSD storage each
  - Serve directly to subscriber — zero internet transit cost for ISP
  - Cache hit: delivery at <10ms latency
  - ~70% of Netflix traffic served from embedded OCAs

Tier 2 — IXP PoPs (Internet Exchange Points):
  - Larger PoPs at major internet exchange points (Equinix, DE-CIX, AMS-IX)
  - Serve ISPs that don't have embedded appliances
  - Cache miss falls back to Tier 2

Tier 3 — Origin (AWS S3):
  - Master copy of all transcoded files
  - Served only on Tier 2 cache miss (rare for popular content)

Cache fill strategy (proactive, not reactive):
  - Netflix analyzes viewing patterns → predicts next day's popular content
  - Off-peak (2am–6am): pushes predicted popular titles to all OCAs
  - No reactive cache miss for popular content during prime time

Client-side CDN selection:
  Client requests: GET /v1/titles/{id}/playback-info
  Playback Service returns: { streamBaseUrl: "https://ipv4-c001-ord001.ip.netflix.com/..." }
  URL encodes: which OCA cluster, which PoP, client's ASN
  Selection factors: client IP → ASN → ISP → nearest OCA with content
```

---

### 6.5 Playback Session & DRM

```
Playback flow (full sequence):

1. Client → Playback Service: GET /v1/titles/{id}/playback-info
   Auth: Bearer JWT (session token)

2. Playback Service:
   a. Validate subscription tier (4K requires Premium plan)
   b. Check concurrent stream limit (Standard: 2 streams, Premium: 4 streams)
      → Query Streaming Session Service: count active sessions for accountId
      → If at limit → reject 429 "Max streams reached"
   c. Select best OCA for client (based on IP geolocation + ASN + OCA health)
   d. Generate DRM license token (Widevine for Android/Chrome, FairPlay for Apple, PlayReady for MS)
   e. Return: { manifestUrl, licenseUrl, sessionId, selectedOCA }

3. Client → OCA: GET manifestUrl → download MPD/M3U8

4. Client → DRM License Server: POST licenseUrl { challenge }
   → Returns encrypted content key
   → Client decrypts segments using content key (in hardware TEE — Trusted Execution Environment)

5. Client → OCA: fetch video segments (ABR-driven)

DRM key hierarchy:
  Content Key (CEK) → encrypts video segments (AES-128-CTR)
  License Key       → encrypts CEK (asymmetric, device-specific)
  Device certificate → hardware-bound (cannot be extracted from TEE)

  Server never sends raw CEK to client — always encrypted to device's hardware key
  Widevine L1 (hardware): highest security → required for 4K
  Widevine L3 (software): browser streaming → max 1080p

Concurrent stream enforcement:
  Streaming Session Service (Redis):
    Key: streams:{accountId} → Set of { sessionId, device, startedAt }
    SADD on play start, SREM on stop
    TTL per session: 4h (heartbeat must refresh every 60s)
    Heartbeat miss for 90s → session auto-expired (handles abrupt device shutdown)
```

---

### 6.6 Watch History & Resume Playback

```
Heartbeat (every 30 seconds during playback):
  POST /v1/playback/heartbeat
  { titleId, profileId, positionMs, sessionId, bitrateKbps, bufferHealthMs }

Processing:
  → Write to Kafka: playback-events (partition by profileId)
  → Watch History Service consumes:
      UPSERT watch_progress SET position_ms=$pos, updated_at=NOW()
      WHERE profile_id=$pid AND title_id=$tid

Resume query:
  GET /v1/profiles/{profileId}/continue-watching
  → SELECT title_id, position_ms, duration_ms FROM watch_progress
     WHERE profile_id=$pid AND position_ms / duration_ms BETWEEN 0.02 AND 0.95
     ORDER BY updated_at DESC LIMIT 20

Watch history DB:
  Primary: Cassandra (profileId as partition key → fast writes, fast profile-scoped reads)
  Schema:
    partition key: profile_id
    clustering key: updated_at DESC
    columns: title_id, position_ms, duration_ms, completed, device_type

  Write volume: 200M DAU × avg 2 sessions × 4 heartbeats/min × 60 min = 96B writes/day
  → 1.1M writes/sec peak → Cassandra handles this easily (designed for write-heavy)

Cross-device sync:
  Device A pauses at 1:23:45 → heartbeat written → Cassandra updated
  Device B opens app → reads watch_progress → resumes from 1:23:45
  Eventual consistency (Cassandra): < 1s lag (within same region), < 5s cross-region
```

---

### 6.7 Recommendation Engine

```
Netflix's recommendation stack (simplified):

Data inputs:
  - Viewing history (titles watched, % completed, ratings, rewatches)
  - Search queries
  - Browsing behavior (hover time on tiles, scroll depth)
  - Time of day, device type, day of week
  - Similar users' behavior (collaborative filtering)
  - Content metadata (genre, director, cast, mood)

Algorithm layers:

Layer 1 — Candidate generation (recall):
  Two-Tower Neural Network:
    User tower:    user embedding from history → 128-dim vector
    Item tower:    title embedding from metadata → 128-dim vector
    Score = dot_product(user_vec, item_vec)
  ANN (Approximate Nearest Neighbor) search: Faiss / ScaNN
  → Returns top 1,000 candidate titles from 15,000 catalog in < 50ms

Layer 2 — Ranking (precision):
  Gradient Boosted Trees / Deep NN with features:
    - P(play | user, title, context)         ← main signal
    - P(complete | user, title)              ← quality signal
    - Diversity penalty (don't recommend 5 similar thrillers)
    - Novelty boost (surface unseen content)
    - Business rules (promote licensed content nearing expiry)
  → Scores top 1,000 → returns top 75 per row

Layer 3 — Row assembly (homepage):
  Homepage = ordered list of rows (categories):
    "Continue Watching", "Top 10 in US", "Because you watched Squid Game",
    "Critically Acclaimed Films", "New Releases", "Action Thrillers"
  Row ordering itself is personalized (ML model predicts which rows → most engagement)

Layer 4 — Artwork personalization:
  Same title, different thumbnail per user:
  User A (watches rom-coms) → sees lead actress prominently in Stranger Things artwork
  User B (watches horror)   → sees dark, eerie shot of same title
  A/B test winner per user segment

Pre-computation vs real-time:
  Recommendations computed offline (batch, every 4 hours) per profile
  Stored in Redis: rec:{profileId} → { rows: [...] }  TTL: 6h
  Real-time re-ranking at request time (< 100ms) using current context
  (time of day, device, recent events in session)
```

---

### 6.8 Search

```
Search architecture:

Index:
  Elasticsearch cluster (title text, cast, synopsis, tags, genres)
  Index updated on catalog change event (Kafka: catalog-events → Elasticsearch consumer)

Query processing:
  1. Query understanding: "action movies 2024" → { genre: action, year: 2024 }
  2. Spell correction + synonym expansion: "scifi" → "sci-fi"
  3. Elasticsearch query: multi-field match (title^3, synopsis^1, cast^2, tags^2)
  4. Re-rank by: BM25 score × personalization_score × popularity_score
  5. Return top 20 results with thumbnails

Autocomplete:
  Redis Sorted Set: title prefixes sorted by popularity
  Key: autocomplete:{prefix}  e.g., autocomplete:inc → {"Inception": 0.98, "Incognito": 0.2}
  ZREVRANGEBYSCORE → top 5 suggestions in < 5ms

Typo tolerance:
  Elasticsearch fuzzy matching (Levenshtein distance ≤ 2)
  "Starnger Things" → "Stranger Things" ✓
```

---

### 6.9 Data Pipeline & Analytics

```
Event stream (Kafka):
  Topics:
    playback-events      → 1.1M events/sec at peak
    catalog-events       → low volume (new titles, metadata updates)
    user-events          → clicks, searches, ratings
    billing-events       → subscription changes

  Consumers:
    Watch History Service   (Cassandra writes)
    Recommendation Engine   (feature store updates)
    Analytics              (ClickHouse / Apache Druid)
    Data Science Platform  (Spark jobs on S3 data lake)
    Fraud Detection        (real-time anomaly on account sharing)

Data lake (S3 + Spark):
  Raw events → S3 (Parquet, partitioned by date + event type)
  Spark jobs: nightly batch → train recommendation models, compute engagement metrics
  Hive metastore → query via Presto/Trino

Real-time analytics (ClickHouse):
  - Current concurrent viewers per title
  - Bitrate distribution per PoP (CDN health)
  - Buffering ratio per ISP (quality of experience monitoring)
  - A/B test metrics (real-time experiment tracking)
```

---

### 6.10 Fault Tolerance & Chaos Engineering

```
Netflix invented Chaos Engineering (Chaos Monkey, Simian Army):

Chaos Monkey: randomly kills production EC2 instances
  → Forces services to be resilient to instance failure by design

Key resilience patterns:

1. Hystrix / Resilience4j circuit breakers:
   If Recommendation Service fails → serve cached/stale recommendations (fallback)
   If Watch History fails → still allow playback (graceful degradation)

2. Fallback hierarchy (Playback Service):
   Primary:  personalized OCA selection
   Fallback: nearest IXP PoP
   Last:     AWS CloudFront global CDN
   Never:    block playback (streaming is the product)

3. Cell-based architecture:
   Production split into independent "cells" (zones)
   Cell failure → only N% of users affected (bulkhead pattern)
   Canary deployments: 1% → 5% → 25% → 100% traffic shift

4. Multi-region active-active:
   Primary regions: us-east-1, eu-west-1, ap-southeast-1
   Traffic routing: GeoDNS + latency-based routing
   Data replication: Cassandra multi-region (async, RF=3 per region)
   RTO: < 5 min (auto-failover via Route53 health checks)
   RPO: < 30 sec (async replication lag)

5. CDN failover:
   If OCA appliance down → client retries with backup OCA URL
   Playback Service provides ordered list of 3 OCA candidates
   Client-side: if primary fails after 2s → immediately try secondary
```

---

## 7. Data Flow Summary

### User Presses Play

```
1. Client → API Gateway → Playback Service:
   GET /v1/titles/tt0816692/playback-info (JWT auth)

2. Playback Service:
   a. Validate account (active subscription, right tier for 4K)
   b. Check stream count: Redis SCARD streams:{accountId} < limit
   c. Geo-locate client IP → select OCA cluster (e.g., oca-comcast-chicago-01)
   d. Generate DRM token (signed, 4h TTL)
   e. Return { manifestUrl, licenseUrl, sessionId, ocaEndpoint }

3. Client → OCA: GET manifest (MPD)
   → OCA cache HIT (popular title) → returns in < 20ms

4. Client → DRM License Server: POST license challenge
   → Returns encrypted content key for this device

5. Client → OCA: GET first 3 segments at lowest quality (fast start)
   → OCA: cache hit → serves immediately
   → Player: begins playback within 2 seconds ✓

6. ABR kicks in:
   → Measures download speed of each segment
   → Upgrades quality every 3–5 segments as buffer fills
   → After 30s: client streaming 4K if bandwidth supports it

7. Heartbeat every 30s:
   POST /v1/playback/heartbeat { positionMs, bitrateKbps, bufferHealthMs }
   → Watch History: UPSERT watch_progress in Cassandra
   → Analytics: publish to Kafka playback-events
```

### New Title Goes Live

```
1. Studio → S3 raw upload (chunked, 100 GB master)
2. S3 event → SQS → Pipeline Orchestrator (Temporal workflow)
3. Orchestrator:
   a. Validate file (codec, resolution, duration)
   b. Chunk into 2-min segments (60 chunks)
   c. Dispatch 1,200 tasks (60 × 20 profiles) to GPU transcoding fleet
   d. Monitor progress; retry failed tasks
   e. Stitch output chunks per profile
   f. Run VMAF quality gate
   g. Generate DASH + HLS manifests
   h. Write to S3 (origin)
4. Catalog Service: INSERT title metadata (DB + Elasticsearch index)
5. CDN pre-push:
   If high-profile release → push to all OCAs during off-peak window
   Else → lazy cache fill on first request
6. Recommendation Engine: new title added to candidate pool
7. Homepage: title appears in "New Releases" row for eligible profiles
```

---

## 8. Follow-Up Questions

### Q1: How do you handle a viral moment (sudden 10× spike in one title)?

```
Scenario: major sports event or cultural moment makes one title suddenly 10× more popular.

CDN layer (absorbs most load):
  - Popular title already cached at OCAs → CDN handles spike transparently
  - OCA capacity auto-scales (Netflix pre-provisions for 20% headroom)
  - If OCA storage full: LRU eviction of least-popular content + pull from IXP PoP

Origin layer (S3):
  - S3 is designed for massive throughput; not a bottleneck for reads
  - Presigned URL cache: CDN caches the redirect → S3 not hit per-request

API layer (Playback Service):
  - Auto-scales horizontally (stateless pods behind ALB)
  - Redis for stream count check: scales horizontally (sharded cluster)
  - Circuit breakers: non-critical calls (recommendations) shed load first

Rate limiting:
  - Per-account: max 4 concurrent streams (already enforced)
  - Global: API Gateway throttles at 99th percentile to protect downstream
```

---

### Q2: How does Netflix detect and prevent account sharing?
```
Signals:
  - Multiple concurrent streams from different geographic regions (IP geolocation)
  - Streaming from > N unique IP addresses in 30 days
  - Household detection: home IP vs travel IP patterns
  - Device fingerprint diversity (> 10 unique devices in 30 days → flag)

ML model:
  Features: { unique_IPs_30d, unique_ASNs_7d, concurrent_geo_distance_km,
              device_count, browser_fingerprint_diversity, ... }
  Binary classifier: household vs account-share
  Confidence threshold → trigger enforcement flow

Enforcement (graduated):
  Low confidence → log, monitor
  Medium → in-app prompt ("Confirm your home location")
  High → require re-auth on non-home devices, or buy "Extra Member" add-on
  Definitive → lock account, prompt plan upgrade

Privacy constraints:
  IP addresses hashed before storage (GDPR)
  Household definition uses ISP/ASN not raw IP
```

---

### Q3: How would you design the A/B testing infrastructure for Netflix?
```
Experiment service:
  - Every user assigned to experiment cohort on account creation
    experiment_assignments: { accountId → { experimentId: variantId } }
  - Stored in Zookeeper/etcd (low latency reads for real-time allocation)

Feature flags:
  Client fetches assignment at app launch:
  GET /v1/experiments/assignments → { "homepage_layout": "v2", "thumbnail_algo": "b" }

Metric collection:
  Experiment variant tagged on every Kafka event: { eventType, experimentId, variantId, ... }
  ClickHouse aggregates metrics per variant: CTR, play rate, completion rate, retention

Statistical analysis:
  - Sequential testing (continuous monitoring without p-value inflation)
  - Minimum detectable effect: 0.5% change in play rate
  - Decision: 95% confidence + practical significance → ship or kill experiment

Guardrail metrics:
  Experiments must not increase: buffering ratio, error rate, p99 latency
  Automatic kill switch if guardrail violated during experiment
```

---

### Q4: How do you optimize CDN cost (egress is #1 expense)?
```
Cost levers (in order of impact):

1. Better codecs (AV1 vs H.264):
   AV1 saves 30–50% bandwidth → proportional reduction in CDN egress cost
   Trade-off: AV1 encode is 10× slower (offline pipeline, acceptable)
   Netflix AV1 adoption: 30% of streams (growing as device support increases)

2. Per-title optimized encoding (Dynamic Optimizer):
   Instead of fixed bitrate ladder, analyze each title's complexity:
   - Low complexity (animated, static scenes): encode at lower bitrate, same quality
   - High complexity (fast action, grain film): allocate more bitrate
   Per-shot quality optimization → avg 20% bandwidth reduction vs fixed ladder

3. CDN cache hit rate maximization:
   - Pre-positioning popular content → 95% cache hit rate → 95% of S3 egress eliminated
   - OCA embedded at ISP → zero internet transit cost (Netflix pays OCA hardware, ISP saves bandwidth)

4. Segment size tuning:
   Larger segments (10s vs 4s) → fewer HTTP requests → better CDN cache efficiency
   Trade-off: slower ABR adaptation on quality switches

5. Thumbnail compression:
   AVIF format for artwork images → 50% smaller than JPEG at same quality
   Millions of thumbnail loads/day → significant bandwidth saving
```

---

### Q5: How do you design the download-for-offline feature?
```
Download flow:
  1. User taps "Download" on title
  2. Client → Playback Service: GET /v1/titles/{id}/download-info
     Checks: download allowed for this title (licensing), user's subscription tier
  3. Returns: { downloadUrl, licenseToken, expiresAt, maxPlays }

  4. Client downloads segments (background, WiFi-preferred):
     Same segments as streaming — but saved to local storage
     Download quality: user-configurable (lower for storage, higher for quality)

  5. DRM for offline:
     Offline license: time-limited (e.g., 30 days from download, 48h from first play)
     Stored in device's hardware TEE — cannot be copied between devices
     License server contacted when online to check revocation list

  6. Expiry enforcement:
     Client checks license validity before each play
     If license expired → show "Download expired, re-download or stream online"
     On app launch: sync expiry status with server (in case account cancelled)

Storage management:
  Client tracks: downloads list, storage used per download
  User can delete individual downloads or "delete all"
  Automatic cleanup: downloads not played in 30 days purged

Licensing constraints:
  Not all titles downloadable (some studio licenses exclude download)
  Geographic restrictions: download in US, cannot play in EU (check IP on play)
```

---

### Q6: How does Netflix handle global content licensing (different catalog per country)?
```
Geo-restriction enforcement:

Client identification:
  IP → MaxMind GeoIP → country code
  VPN detection: known VPN/proxy IP ranges → flag as "unknown geo"

Catalog per region:
  Each title has: { availableCountries: ["US", "GB", "DE", ...] }
  Stored in Catalog DB, indexed for fast country-filtered queries

Homepage / Search / Recommendations:
  All queries parameterized by country:
  SELECT * FROM titles WHERE $country = ANY(available_countries)

Playback enforcement:
  Playback Service re-checks IP country at play time (not just at browse time)
  If VPN detected and country can't be confirmed → restrict to global catalog only

Content metadata localization:
  Titles table: metadata stored per locale (title, synopsis, artwork)
  Served via i18n CDN layer: /api/titles?locale=de-DE
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Video protocol | DASH (most) + HLS (Apple) | Widest device compatibility; open standard |
| Codec | AV1 primary, H.264 fallback | 30–50% bandwidth savings; H.264 for old devices |
| CDN | Own CDN (Open Connect) embedded in ISPs | Zero transit cost; 10ms delivery; 70% of traffic served by OCAs |
| ABR algorithm | BOLA (buffer-based) | Maximizes quality while preventing rebuffering |
| Watch history DB | Cassandra | Write-heavy (1.1M writes/sec); partition by profileId; multi-region native |
| Transcoding | Parallel chunk-based on GPU fleet | 4h → 20min; cost-efficient at scale |
| Recommendations | Two-tower recall + GBDT ranking | Industry-standard; decouples candidate generation from ranking |
| DRM | Widevine L1 (hardware TEE) for 4K | Hardware-enforced; cannot be extracted; required by studios for 4K licensing |
| Playback start | Pre-fetch lowest quality + ABR ramp | 2s start time (stream immediately, quality improves as buffer fills) |
| Fault tolerance | Chaos Engineering + fallback OCAs | Resilience tested in production; graceful degradation before hard failure |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 50–60 minutes.*
