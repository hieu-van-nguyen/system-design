# System Design: Instagram

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard  
> Core challenges: **Photo/video pipeline at petabyte scale · Fan-out feed generation · CDN for 10B daily media serves**

---

## Table of Contents

1. [Clarifying Questions](#1-clarifying-questions)
2. [Functional Requirements](#2-functional-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Back-of-Envelope Estimation](#4-back-of-envelope-estimation)
5. [High-Level Design](#5-high-level-design)
6. [Trade-Off Discussion](#6-trade-off-discussion)
7. [Deep Dive](#7-deep-dive)
   - 7.1 Photo & Video Upload Pipeline
   - 7.2 Feed Generation (Fan-Out at Scale)
   - 7.3 CDN & Media Delivery
   - 7.4 Social Graph
   - 7.5 Stories (Ephemeral Content)
   - 7.6 Explore / Discovery Page
   - 7.7 Likes, Comments & Reactions
8. [Data Models](#8-data-models)
9. [Follow-Up Questions](#9-follow-up-questions)
10. [Interview Summary Card](#10-interview-summary-card)

---

## 1. Clarifying Questions

```
"Are we designing the full Instagram product?"
→ Core features: photo/video posts, Stories, feed, follow, likes, comments

"Do we need Reels (short-form video)?"
→ Yes — include video upload + adaptive bitrate streaming

"Do we need Direct Messages?"
→ Out of scope (similar to Messenger architecture)

"Do we need the Explore / Discovery page?"
→ Yes — ML-ranked personalized discovery

"Do we need Stories?"
→ Yes — 24-hour ephemeral content

"What's the scale?"
→ 2B MAU, 500M DAU, global product

"Shopping / ads / monetization?"
→ Out of scope
```

---

## 2. Functional Requirements

### Core (Must Have)

| # | Requirement | Notes |
|---|-------------|-------|
| FR-1 | **Upload photo / video** | Multi-photo posts (up to 10), videos up to 90s (feed), 60s (Stories), 15m (Reels) |
| FR-2 | **Home feed** | Ordered stream of posts from followed accounts (chronological or ranked) |
| FR-3 | **Follow / Unfollow** | Asymmetric graph; private accounts require approval |
| FR-4 | **Like / Unlike** | Post-level likes; like count visible |
| FR-5 | **Comment** | Nested replies; @mention support |
| FR-6 | **Stories** | 24-hour ephemeral content; viewable once per viewer |
| FR-7 | **Explore page** | ML-ranked discovery of content from non-followed accounts |
| FR-8 | **Search** | Search by username, hashtag, location |
| FR-9 | **Notifications** | Likes, comments, follows, @mentions |
| FR-10 | **User profile** | Grid of posts, followers/following counts, bio |

### Out of Scope

- Instagram Direct (DMs), Shopping, Ads, IGTV, Live streaming

---

## 3. Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| **Availability** | 99.99% (~52 min downtime/year) | Consumer social — outages are PR events |
| **Feed read latency** | p99 < 300ms | Feed must feel instant |
| **Photo upload latency** | p99 < 2s | Upload confirmation (actual processing async) |
| **Feed consistency** | Eventual (< 30 seconds) | Seeing a post 10s late is imperceptible |
| **Media delivery** | p99 < 200ms globally (CDN) | Images dominate user experience |
| **Read:Write ratio** | ~500:1 | Extremely read-heavy (feed, Explore, profile views) |
| **Scalability** | 10B media serves/day | ~115K images/sec avg, 400K/sec peak |
| **Durability** | 11 nines (S3) | User photos are irreplaceable |
| **Storage growth** | ~5 PB/day (raw) after processing | Video dominates; compression critical |

---

## 4. Back-of-Envelope Estimation

### Users & Content

```
Monthly Active Users (MAU):       2 billion
Daily Active Users (DAU):         500 million
Photos uploaded/day:              100 million
Videos (Reels/Feed) uploaded/day: 20 million
Stories posted/day:               500 million

Avg posts viewed per user/day:    50 posts
Total feed reads/day:             500M × 50 = 25 billion
```

### QPS

```
Write (uploads):
  Photos: 100M / 86,400 = 1,157 uploads/sec (avg)
  Videos: 20M / 86,400 = 231 uploads/sec
  Stories: 500M / 86,400 = 5,787 stories/sec
  Total upload writes: ~7,200/sec avg; peak ~20K/sec

Read (feed + media):
  Feed API calls: 25B / 86,400 = 289K reads/sec (avg); peak ~800K/sec
  Media (CDN) serves: 25B posts × 3 images per carousel/variant
                    = 75B CDN requests/day = 868K requests/sec
  (>99% served from CDN edge — origin sees ~8,680/sec)

Likes:
  4.2B likes/day / 86,400 = 48,600 likes/sec

Comments:
  ~500M comments/day / 86,400 = 5,787 comments/sec

Stories views:
  500M daily story viewers × 6 stories avg = 3B story views/day
  = 34,700 story views/sec
```

### Storage

```
Photos (per upload):
  Original: avg 5 MB (10 MP JPEG)
  Processed variants: thumbnail (50KB) + medium (300KB) + full (1.5MB) + WebP
  Total per photo: ~2 MB stored (after compression + dedupe)
  Daily: 100M × 2 MB = 200 TB/day

Videos:
  Original Reel: avg 200 MB (4K, 60s)
  Transcoded: 360p(10MB) + 720p(30MB) + 1080p(60MB) = 100 MB/video
  Daily: 20M × 100 MB = 2 PB/day

Stories:
  Shorter videos + photos; expire in 24h (but retained 30 days for export)
  Daily new: 500M × 0.5 MB avg = 250 TB/day

Total new storage/day: ~2.5 PB
5-year cumulative: 2.5 PB × 365 × 5 = 4.56 EB (exabytes)
  → Tiered to S3 Glacier after 90 days for old posts

Follow graph edges:
  2B users × 500 avg following × 8 bytes = 8 TB
  → Sharded across Cassandra cluster

Feed cache (Redis):
  500M active users × 50 post_ids × 8 bytes = 200 GB active tier
  Cached for users active in last 7 days: ~200M users
  200M × 50 × 8 bytes = 80 GB → small Redis cluster
```

### Bandwidth

```
Media writes (upload): 7,200 req/sec × 2 MB = 14.4 GB/s inbound
Media reads (CDN origin): 8,680 req/sec × 500 KB = 4.3 GB/s to CDN
Feed API: 289K req/sec × 5 KB response = 1.4 GB/s
Total egress (CDN edges globally): ~500 GB/s peak (distributed across 300+ PoPs)
```

---

## 5. High-Level Design

### Architecture Overview

```
                     ┌──────────────────────────────────┐
                     │           API Gateway             │
                     │  Auth · Rate Limit · Routing      │
                     └──────────────┬───────────────────┘
                                    │
     ┌──────────────────────────────┼────────────────────────────────┐
     │                             │                                │
     ▼                             ▼                                ▼
┌──────────────┐          ┌────────────────┐              ┌─────────────────┐
│ Upload Svc   │          │  Feed Service  │              │  Social Graph   │
│ (write path) │          │  (read path)   │              │  Service        │
└──────┬───────┘          └───────┬────────┘              └─────────────────┘
       │ S3 presigned             │
       │ upload URL               ▼
       ▼                  ┌────────────────┐              ┌─────────────────┐
┌──────────────┐          │  Redis         │              │  Search Service │
│  S3 (origin) │          │  (feed cache   │              │  (Elasticsearch)│
│  raw media   │          │  sorted sets)  │              └─────────────────┘
└──────┬───────┘          └───────┬────────┘
       │ S3 event                 │ cache miss
       ▼                          ▼
┌──────────────┐          ┌────────────────┐              ┌─────────────────┐
│  Media       │          │  Post Store    │              │  Stories Svc    │
│  Processing  │◄─────────│  (Cassandra)   │              │  (Redis TTL +   │
│  Workers     │          └────────────────┘              │  S3)            │
└──────┬───────┘                                          └─────────────────┘
       │ processed variants
       ▼
┌──────────────┐          ┌────────────────┐              ┌─────────────────┐
│  CDN         │          │  Kafka         │              │  Explore Svc    │
│ (CloudFront/ │          │  (fan-out,     │              │  (ML ranking)   │
│  Fastly)     │          │   notif, index)│              └─────────────────┘
└──────────────┘          └───────┬────────┘
                                  │
                    ┌─────────────┼─────────────┐
                    ▼             ▼             ▼
             Fan-out Worker  Notif Svc    Search Indexer
```

### Core API Endpoints

```
# Upload
POST   /api/v1/media/upload/init     → { media_id, s3_presigned_url }
POST   /api/v1/posts                 → { post_id } (after media ready)
POST   /api/v1/stories               → { story_id }

# Feed & Discovery
GET    /api/v1/feed?cursor=&limit=   → { posts[], next_cursor }
GET    /api/v1/explore?cursor=       → { posts[], next_cursor }
GET    /api/v1/users/{id}/posts      → profile grid

# Social
POST   /api/v1/follow/{user_id}
DELETE /api/v1/follow/{user_id}
POST   /api/v1/posts/{id}/like
POST   /api/v1/posts/{id}/comments

# Stories
GET    /api/v1/stories/feed          → { story_rings[] } (ordered by recency)
GET    /api/v1/stories/{id}          → { media_url, expires_at }
```

### Core Request Flows

#### Photo Upload

```
1. App → POST /api/v1/media/upload/init
2. Upload Service: create media_id (Snowflake), generate S3 presigned PUT URL
   Response: { media_id, upload_url, expires_in: 3600 }

3. App → PUT {upload_url} (direct to S3 — bypasses API servers entirely)
   → Zero Instagram server load for 5 MB photo payload

4. S3 → EventBridge → Kafka "media.uploaded" { media_id, user_id, s3_key, type }

5. App → POST /api/v1/posts { caption, media_ids: [media_id], hashtags, location }
   → Post Service: validate media ready, create post_id, write to Cassandra
   → Publish Kafka "post.created" { post_id, user_id, media_ids, timestamp }

6. Kafka consumers (async):
   ├── Media Processor: resize → compress → WebP → store variants → update CDN
   ├── Fan-out Worker: push post_id to followers' feed caches
   ├── Search Indexer: index caption + hashtags in Elasticsearch
   └── Notification Service: notify tagged users

7. Response to app: 201 Created { post_id, permalink }
   (media processing continues async — app shows spinner until media ready event)
```

#### Read Home Feed

```
1. App → GET /api/v1/feed?cursor=null&limit=12
2. Feed Service:
   a. Read feed cache: ZREVRANGE feed:{user_id} 0 11 WITHSCORES
      → Returns [(post_id, score), ...] — score is timestamp
   b. For celebrity followees (>5M followers): merge recent posts at read time
   c. MGET post:{id1} post:{id2} ... (batch hydrate post metadata)
   d. For each post: get media URLs from CDN (constructed, not stored)
      url = "https://cdn.instagram.com/{media_id}/{variant}.webp"
   e. Attach like_count, comment_count, user_liked (Redis SISMEMBER)
3. Return 12 posts with CDN URLs — app fetches media directly from CDN
```

---

## 6. Trade-offs Discussion

### 6.1 Fan-Out Strategy: Write vs Read vs Hybrid

This is the defining architectural decision for any social feed system and the one interviewers probe most deeply.

| Approach | Write Cost | Read Cost | Celebrity Handling | Offline Users |
|----------|-----------|-----------|-------------------|---------------|
| **Fan-out on Write** | 231K Redis writes/sec (normal) | O(1) ZREVRANGE | ❌ Ronaldo 600M followers → 600M writes/post | Wastes writes for inactive timelines |
| **Fan-out on Read** | O(1) Cassandra insert | O(following) — 500 queries/load | ✅ Natural | ✅ No wasted writes |
| **Hybrid** (chosen) | Normal users only | O(1) + O(celebrities) ≈ 16ms total | ✅ Merge at read time | Skip inactive >30 days (30% savings) |

```
Celebrity threshold math:
  Cristiano Ronaldo: 600M followers
  Fan-out on write cost: 600M Redis ZADD ops per post
  At 50K ZADD/sec per Redis node: 600M / 50K = 12,000 seconds = 3.3 HOURS
  → Completely untenable; celebrity must use read-time merge

Instagram threshold set at 5M followers (vs Twitter's 1M):
  Instagram content is sparser (posts/day << tweets/day)
  5M × ZADD / (50K × 100 Redis nodes) = 1 second — borderline acceptable
  Set at 5M to minimize read-time merge overhead for typical celebrity followers

Fan-out on read catastrophic case:
  User follows 500 accounts → 500 Cassandra queries at feed load
  500 queries × 5ms each = 2,500ms → 8× over the 300ms SLA
  → Fan-out on read is simply not viable for dense follow graphs

Zombie optimization:
  Skip fan-out for users inactive > 30 days
  ~30% of Instagram accounts are dormant → saves 69K Redis writes/sec
  Cold rebuild on first login: pull from Cassandra, repopulate feed cache async
```

**Why not pure read (even for celebrities)?** A user following 10 celebrities with 5M+ followers each still only incurs 10 Redis reads at query time (each `ZREVRANGE user_posts:{celeb_id} 0 4` = ~1ms) + k-way merge (~2ms) = ~12ms overhead. Manageable. But if 80% of users follow celebrities, that's 289K Redis reads/sec at peak — still fine for a Redis cluster.

---

### 6.2 Feed Ordering: Chronological vs ML-Ranked vs Hybrid

| Approach | Engagement | Predictability | Cold Start | Complexity |
|----------|-----------|----------------|------------|------------|
| **Chronological** | Lower (miss relevant older posts) | High — users know what to expect | ✅ No model needed | Low |
| **ML-Ranked** (current Instagram "Home") | +30–40% session time | Low — users feel "algorithm hides posts" | ❌ New users see poor rankings | High |
| **Hybrid** (chosen) | Best of both | Medium | Fallback to chron for new users | Medium |

```
Ranked feed scoring pipeline:
  Candidate pool: ~500 post_ids from Redis feed cache (pre-computed fan-out)
  Features per post (fetched from Redis at read time):
    - Temporal: age_hours (exponential decay: score × exp(-0.1 × age))
    - Affinity: Redis GET affinity:{viewer_id}:{author_id} → float [0.0, 3.0]
    - Content type: user's video/photo/carousel preference ratio (last 30 days)
    - Engagement velocity: likes in last hour / total likes (trending boost)
  Model: XGBoost classifier (predict P(engagement) per post)
  Serving: inference in 20ms for 500 candidates → top 12

Cold start handling:
  New user (< 10 posts engaged with): fallback to chronological
  Onboarding: select 5 interest categories → seed interest vector
  After 10 engagements: switch to ML ranking (sufficient signal)

Instagram's actual approach:
  "Following" tab: pure chronological (user demand after 2022 backlash)
  "Home" tab: ML-ranked (maximizes engagement / advertiser value)
  Design mirrors this: expose both ranking modes via API parameter
```

---

### 6.3 Photo Storage: On-the-Fly Transform vs Pre-Generated Variants

| Approach | Storage Cost | Serving Cost at 115K req/sec | Flexibility | CDN Complexity |
|----------|-------------|------------------------------|-------------|----------------|
| On-the-fly (imgix/Thumbor) | 1× (original only) | High CPU: 115K transforms/sec | Unlimited sizes | Simple (one URL pattern) |
| **Pre-generated fixed variants** (chosen) | 4× | Zero CPU: direct S3 serve | Fixed sizes only | Simple (known paths) |
| Pre-generate common + on-demand rare | 2× | Low for 99% of requests | Medium | Medium |

```
Storage math for pre-generated approach:
  Per photo: original (5MB) + thumbnail 150×150 (50KB) + medium 640w (300KB) +
             full 1080w (1.5MB) + HiDPI 1440w (2MB) + WebP versions of each
  Effective stored: ~4 MB per photo (WebP savings offset variant overhead)
  Daily: 100M × 4 MB = 400 TB/day (vs 500 TB raw if on-the-fly)
  Actually cheaper: CDN bandwidth savings dwarf storage costs

On-the-fly rejection math:
  115K CDN requests/sec × 0.01% cache miss rate = 11.5 transform requests/sec to origin
  OK for 11.5/sec — but: transform CPU per image ~50ms on modern CPU
  11.5 req/sec × 0.05s = 0.575 CPU cores needed → trivial
  But: edge resize at CDN (Lambda@Edge) costs $0.60/million invocations
  At 115K req/sec × 1% miss = 1,150 req/sec × 86,400 = 99M invocations/day
  → $59/day for Lambda@Edge vs $0 for pre-generated (already in S3)

Decision: Pre-generate 4 fixed variants. Instagram controls all display surfaces
  (iOS app, Android app, web) — no arbitrary sizes needed. Store originals
  in S3 Glacier for legal hold/download, never serve from origin.

WebP mandatory:
  JPEG 1MB → WebP ~600KB (40% savings)
  At 868K CDN requests/sec × 400KB avg: 347 GB/s egress
  vs 578 GB/s with JPEG → saves $0.02/GB × 231 GB/s = $4.6/sec = $400K/day
```

---

### 6.4 Like Count Consistency: Strong vs Approximate vs Sharded Counter

```
Scale: 4.2B likes/day = 48,600 likes/sec peak to ~500K/sec on viral posts

Option A — PostgreSQL UPDATE per like (row-level lock):
  UPDATE posts SET like_count = like_count + 1 WHERE id = ?
  PostgreSQL row lock throughput: ~5K updates/sec per row
  At 48,600 likes/sec: 9.7× over single-row capacity → deadlock cascade
  Even with connection pooling: lock contention saturates OLTP at scale
  ❌ Fails at peak load on viral posts (500K likes/sec)

Option B — Redis INCR (chosen):
  INCR post:{id}:like_count → atomic, O(1), no locks
  Single Redis node: 1M+ INCR/sec on a single key
  Async flush to Cassandra every 60s via batch job
  Risk: Redis crash → max 60s of likes lost (mitigated by AOF: max 1s loss)
  ✅ Handles 500K likes/sec on viral post with headroom

Option C — Sharded counter (for extreme viral):
  INCR post:{id}:likes:{shard} where shard = rand(0, 15)
  Read: pipeline GET of 16 shards → SUM
  Use when: single Redis key saturated (>1M likes/sec per post)
  At normal 48,600/sec: no need; reserve as scaling escape hatch
  ✅ Available but add complexity only when needed

Option D — HyperLogLog (PFADD / PFCOUNT):
  Good for unique liker cardinality, not total like count
  PFCOUNT error: ±0.81% — "47,382" could show "47,000"
  For "did I like this?": use SISMEMBER on post_likers SET instead
  ❌ Wrong data structure for aggregate count; right for unique count
```

**Interview insight**: "47,382 likes" vs "47,380 likes" — imperceptible to users. The key is **separating the hot write path (Redis) from the durable read path (Cassandra)**. Strong consistency at 48,600 writes/sec would require a DB cluster 10× larger than necessary. Approximate is the deliberate trade-off for social vanity metrics.

---

### 6.5 CDN Architecture: Single vs Multi-Tier vs Custom

| Option | Origin Load at 868K req/sec | Cache Hit Rate | Latency (edge hit) | Cost |
|--------|---------------------------|---------------|-------------------|------|
| Single CloudFront | 86,800 req/sec to S3 (10% miss) | 90% | ~5ms | Medium |
| **CloudFront + Origin Shield** (chosen) | 868 req/sec to S3 (0.1% miss) | 99.9% | ~5ms (edge) / ~25ms (shield) | Medium-low |
| Custom CDN (Meta/Proxygen) | ~8 req/sec (99.999% hit) | 99.999% | ~3ms | Very high (own PoPs) |

```
Origin Shield impact math:
  Without Origin Shield: 868K × 10% miss = 86,800 S3 requests/sec
  With Origin Shield: 868K × 10% edge miss × 1% shield miss = 868 S3 requests/sec
  S3 request pricing: $0.0004 per 1,000 → 86,800/sec × 86,400s × $0.0004/1000 = $2,993/day
                      vs 868/sec × 86,400s × $0.0004/1000 = $29/day
  Origin Shield saves ~$2,964/day on S3 requests alone (before bandwidth savings)

Content-addressed URLs (critical correctness property):
  URL pattern: cdn.instagram.com/v/{media_id}/{variant}.webp
  media_id is a Snowflake ID (encodes upload timestamp)
  Content at this URL is IMMUTABLE (post images never change)
  Cache-Control: public, max-age=31536000, immutable
  → Browser, CDN edge, Origin Shield all cache aggressively and forever
  → On post delete: Instagram soft-deletes in DB; CDN eventually evicts (TTL)
    or explicit invalidation (slow, expensive — use soft delete instead)

Profile photos (mutable) differ:
  URL includes version: cdn.instagram.com/p/{user_id}/v{version}/150.webp
  Cache-Control: max-age=3600 (1 hour)
  On change: new version number → new URL → instant CDN bypass
```

---

### 6.6 Video Transcoding: Pre-Process All Qualities vs On-Demand vs JIT

```
Scale: 20M video uploads/day = 231 uploads/sec
  Average Reel: 60s video at 4K → ~200 MB input

Option A — Pre-transcode all qualities at upload time (chosen):
  Transcode: 360p + 720p + 1080p immediately after upload
  GPU time per video: ~30s (FFmpeg on A100)
  Total: 231 uploads/sec × 30s = 6,930 GPU-seconds/sec = 6,930 GPU cores
  Storage: 100 MB per video (all qualities) × 20M/day = 2 PB/day
  Pros: zero compute at serve time; CDN serves static .ts segments
  ❌ Expensive upfront; stores unused low-quality tiers for high-bandwidth users

Option B — On-demand transcode on first request:
  First 360p request → trigger transcode → cache result
  First 1080p request → trigger higher-quality transcode
  Pros: only transcode what's actually viewed; 80% of Reels never hit 1M views
  Cons: first viewer gets 5-30s latency spike → cold start UX disaster
  ❌ Unacceptable for social video (first viewer expects instant play)

Option C — Tiered transcode (chosen hybrid):
  At upload time: 360p + 720p immediately (90% of viewers)
  Background queue: 1080p within 30 minutes (for high-engagement posts)
  Never: 4K output (bandwidth cost > quality benefit on mobile screens)
  Pros: 33% fewer GPU-hours than Option A; covers 90% of views within seconds

GPU cluster sizing (Option C):
  231 uploads/sec × 20s (2-quality transcode) = 4,620 GPU-seconds/sec
  = 4,620 GPU cores baseline + 3× autoscale for primetime (6–10 PM)
  AWS p3.2xlarge (8 V100 GPUs) at $12.24/hr:
  4,620 cores / 8 = 578 instances × $12.24 = $7,074/hr → $170K/day
  Spot instances (70% savings): ~$51K/day for transcoding alone
```

---

### 6.7 Stories Storage: Redis-First vs Cassandra-First vs Dual-Write

```
Stories constraints:
  24h TTL (hard expiry — not soft delete)
  High write/read during active hours (5,787 stories/sec)
  View receipts: poster sees who viewed, ordered by recency
  Data loss on storage failure: acceptable (ephemeral content)

Option A — Redis-first (chosen):
  HSET story:{story_id} ... + EXPIRE 86400 — native TTL, zero GC overhead
  ZADD user_stories:{user_id} ... + EXPIRE 86400 — sorted set for ring ordering
  SADD seen:{story_id} ... + EXPIRE 172800 — view receipt set
  Pros: sub-ms reads, TTL exactly matches product requirement, no cleanup job
  Cons: Redis restart = story data loss (mitigated by Redis AOF + RDB snapshots)
  Memory: 500M active stories × ~200 bytes metadata = 100 GB → fits single large Redis cluster

Option B — Cassandra-first + TTL column:
  Cassandra supports TTL per row: INSERT ... USING TTL 86400
  Automatic deletion after 24h (no external GC needed)
  Pros: durable (multi-datacenter RF=3)
  Cons: Cassandra TTL triggers compaction overhead; not designed for high-cardinality
    small records; view receipt SET semantics require separate table with complex queries
  ❌ Extra infra complexity for ephemeral content that's OK to lose on failure

Option C — PostgreSQL + background expiry job:
  Cron job: DELETE FROM stories WHERE created_at < NOW() - INTERVAL '24 hours'
  Runs every 5 minutes → stories live up to 24h5m (acceptable)
  Cons: DELETE is expensive at 5,787 stories/sec × 86,400s = 500M rows/day deleted
    PostgreSQL VACUUM required after mass deletes → I/O spikes
  ❌ Maintenance complexity; vacuuming 500M rows/day disrupts OLTP performance

Decision: Redis for hot story metadata (TTL-native), S3 for media (25h lifecycle rule).
  Story loss probability on Redis failure: ~0.01% per day (cluster HA + AOF).
  Ephemeral content loss is acceptable; user just re-records if needed.
```

---

### 6.8 Follow Graph: Dual-Table vs Single-Table vs Graph Database

```
Access patterns require two opposite traversals:
  "Who does user A follow?" → for fan-out, feed building (need full list)
  "Who follows user A?" → for notification, follower count, reverse fan-out
  "Do A and B mutually follow?" → for DM eligibility, close friends

Scale: 2B users × 500 avg following = 1 trillion edges

Option A: Single table sharded by follower_id
  covering: FOLLOWING(follower_id PK, followee_id)
  "Who follows A?" → scatter-gather across all shards → O(shards)
  At 1,000 shards: 1,000 parallel queries → merge up to 600M follower IDs for Ronaldo
  → 600M × 8 bytes = 4.8 GB of data to merge → minutes, not milliseconds
  ❌ Reverse lookup catastrophically slow for celebrities

Option B: Dual denormalized tables (chosen)
  FOLLOWING(follower_id, followee_id) — sharded by follower_id
  FOLLOWERS(followee_id, follower_id) — sharded by followee_id
  Both updated on every follow/unfollow (dual Cassandra BATCH write)
  "Who does A follow?": FOLLOWING shard for A → O(1)
  "Who follows A?": FOLLOWERS shard for A → O(1), even for Ronaldo
  Cost: 2× write amplification → follows are rare (avg 1 follow/day/user vs 50 reads/min)
  Dual-write consistency: 30s saga reconciliation window (acceptable)
  ✅ Both traversals O(1) regardless of follower count

Option C: Graph database (Neo4j, JanusGraph, Amazon Neptune)
  Natural model for mutual-follow queries and 2nd-degree graph traversal
  Scale ceiling: Neo4j Enterprise max ~50B edges (Instagram has 1T edges → 20× limit)
  Horizontal sharding of graph DBs: extremely complex (cross-shard edges break locality)
  ❌ Instagram, Twitter, Facebook all abandoned graph DBs at this scale

Mutual follow query (DM eligibility):
  With dual table: AND EXISTS(SELECT 1 FROM FOLLOWING WHERE follower=B AND followee=A)
  At Cassandra: two O(1) point lookups → ~2ms total
  For fan-out: use FOLLOWERS table only; never read following table in hot path
```

---

### 6.9 Consistency Model Matrix

| Component | Model | Justification |
|-----------|-------|---------------|
| Post upload (S3 + Cassandra write) | **Strong (write quorum)** | User's post must not vanish after 201 response; Cassandra LOCAL_QUORUM |
| Feed cache (Redis) | **Eventual (≤30s)** | Fan-out pipeline takes up to 30s; new post appearing late is imperceptible |
| Like / comment counts | **Approximate eventual (±5, 60s flush lag)** | Social vanity metrics; exact count costs 10× more infra |
| Follow / Unfollow | **Strong (read-your-writes)** | Unfollow must be immediate — critical for harassment/blocking use cases |
| Follow graph dual-write | **Eventual (≤30s saga reconciliation)** | 30s window where FOLLOWING and FOLLOWERS tables may diverge |
| Media (CDN) | **Immutable / no consistency concern** | Content-addressed URLs; same URL always returns same bytes |
| Stories (Redis TTL) | **Eventual with bounded durability** | Story loss on Redis failure (~0.01%/day) acceptable for ephemeral content |
| Stories view receipts | **Eventual (at-least-once)** | "Alice viewed your story" appearing 5s late is fine |
| Explore ranking | **Eventual (4h candidate refresh)** | Explore candidates refreshed hourly; stale recommendations acceptable |
| User profile (bio, avatar) | **Strong (read-your-writes)** | User must immediately see their own edits; others within CDN TTL (1h) |
| Private account access control | **Strong at read time** | Access checked at post serve, not in cache — cannot pre-cache private content |
| Notifications | **At-least-once delivery** | Duplicate "Bob liked your photo" better than missed notification |

**Key insight**: Instagram has two fundamentally different consistency domains:
1. **Identity and access** (follow state, private account status, block list) → strong consistency required; these affect safety and access control
2. **Social metrics and feed** (like counts, feed ordering, explore recommendations) → eventual consistency acceptable; users tolerate seconds-to-hours of lag

Conflating these domains is the most common mistake in social system design interviews. Strong consistency everywhere would push all 48,600 like writes/sec through a serialized DB path — catastrophic at Instagram's scale.

---

## 7. Deep Dive

### 7.1 Photo & Video Upload Pipeline

#### Photo Processing

```
Input: JPEG/HEIC/PNG (avg 5 MB from iPhone camera)

Processing pipeline (async worker, triggered by Kafka "media.uploaded"):

Step 1 — Decode & validate:
  Verify MIME type matches extension (detect MIME magic bytes)
  Reject corrupt images, malicious EXIF (path traversal attacks)
  Strip EXIF metadata (privacy — GPS coordinates, device info)
  Max resolution check: reject > 50 MP (potential decompression bomb)

Step 2 — Content moderation (async, non-blocking to upload):
  PhotoDNA hash: detect CSAM (mandatory, instant reject)
  ML nudity/violence classifier (Google Vision API / internal model)
  If flagged: hold for human review → notify user of policy hold

Step 3 — Resize & compress:
  Generate variants:
    150×150  crop-to-center thumbnail   → WebP quality 80
    640w     width-constrained medium   → WebP quality 85
    1080w    width-constrained full     → WebP quality 90
    1440w    HiDPI variant             → WebP quality 90
    Original JPEG archived as-is (no recompression → no quality loss)

Step 4 — Near-duplicate detection:
  Compute perceptual hash (pHash) of each uploaded image
  Query: SELECT post_id FROM phash_index WHERE hamming_distance(phash, ?) < 5
  → Detect screenshot spam, reposted viral content
  → Flag for reduced distribution (not deleted — just ranked lower)

Step 5 — Store in S3:
  Bucket path: s3://instagram-media/{shard}/{media_id}/{variant}.webp
  Content-Type: image/webp
  Cache-Control: public, max-age=31536000, immutable
  → Immediately invalidate/warm CDN for the new media paths

Timeline: Processing completes in ~3 seconds; user sees post immediately (optimistic UI)
```

#### Video Processing (Reels)

```
Input: MP4/MOV (avg 100 MB, up to 15 minutes for Reels)

Video pipeline is more complex — runs on dedicated GPU transcoding cluster:

Step 1 — Upload (chunked multipart):
  App splits video into 5 MB chunks
  POST /api/v1/media/upload/chunk { chunk_index, total_chunks, data }
  S3 multipart upload API — allows resumable uploads
  Progress shown in app: "Uploading 45%..."

Step 2 — Transcode (FFmpeg on GPU workers):
  Output profiles:
    360p  H.264 (700 Kbps video + 128 Kbps AAC audio)
    720p  H.264 (2.5 Mbps video + 128 Kbps AAC audio)
    1080p H.264 (5 Mbps video + 192 Kbps AAC audio)
    720p  H.265/HEVC (1.2 Mbps — 40% smaller, for newer devices)
  Segmented into 2-second HLS segments (.ts files)
  Generate .m3u8 playlist per quality tier

Step 3 — Thumbnail generation:
  Extract frame at 0s, 1s, 2s → ML model picks most visually interesting
  Generate 150×150 thumbnail, 640w poster image

Step 4 — Audio normalization:
  Loudness normalization to -14 LUFS (prevents loud autoplay videos)

Step 5 — Store in S3 → CDN:
  s3://instagram-video/{media_id}/360p/segment_{n}.ts
  s3://instagram-video/{media_id}/master.m3u8
  App player fetches master.m3u8 → selects quality based on bandwidth

GPU cluster sizing:
  20M video uploads/day × avg 30s transcode time = 600M GPU-seconds/day
  = 6,944 GPU-seconds/sec → 7,000 GPU cores (AWS p3 instances or on-prem A100s)
  Auto-scale up 3× during primetime (6-10 PM)
```

---

### 7.2 Feed Generation — Fan-Out at Scale

#### Fan-Out Worker Design

```
Kafka topic: "post.created"
  Partitions: 2,000 (parallelism for fan-out)
  Consumer group: "feed-fanout-workers"

Fan-out Worker logic (per partition):
  1. Consume post.created event { post_id, user_id, timestamp }
  2. Check poster's follower count:
     - count = Redis GET followers_count:{user_id}
     - If > 5M: skip fan-out (celebrity path)
     - If ≤ 5M: proceed with fan-out
  3. Fetch follower IDs (paginated from Follow Service):
     - Batch size: 10,000 followers per page
     - Cache follower list in worker memory (TTL 30s) to avoid repeat DB hits
  4. For each follower (in batches of 1,000):
     - Redis pipeline:
         ZADD feed:{follower_id} {timestamp_score} {post_id}
         ZREMRANGEBYRANK feed:{follower_id} 0 -501  (keep top 500 only)
  5. Skip inactive followers (last_active > 30 days) — read from Redis SET inactive_users
  6. Acknowledge Kafka after batch complete

Throughput:
  1,157 photo uploads/sec × 200 avg followers = 231,400 feed writes/sec
  With 2,000 partitions × 10 threads each = 20,000 concurrent workers
  Each worker handles ~11.6 feed writes/sec — very manageable

Celeb post handling (fan-out on read):
  No fan-out at write time
  Celeb posts stored in: ZADD user_posts:{celeb_id} {timestamp} {post_id}
  At feed read time: merge top-5 from each celebrity followed
```

#### Feed Cache Architecture

```
Redis Sorted Set per user:
  Key:    feed:{user_id}
  Score:  Unix timestamp (milliseconds) × ranking_boost
          ranking_boost ∈ [0.5, 2.0] — ML model adjusts for relevance
  Member: post_id

Operations:
  Write: ZADD feed:{user_id} {score} {post_id}
  Read:  ZREVRANGEBYSCORE feed:{user_id} +inf {cursor_score} LIMIT 0 12
  Trim:  ZREMRANGEBYRANK feed:{user_id} 0 -501 (async, after each ZADD)

Cache warm-up (user returns after >7 days away):
  Trigger async "feed rebuild" job:
    1. Fetch all followees from Follow Service
    2. For each followee: get last 5 posts from user_posts:{followee_id}
    3. Merge, score, populate feed:{user_id}
  Until rebuild complete: serve directly from Cassandra (slower, one-time)

Ranked feed scoring:
  score = timestamp × affinity_multiplier × content_type_preference
  affinity_multiplier: ML-derived [0.5–3.0] based on past interactions with author
  content_type_preference: user preference for video vs photo vs carousel

  Precomputed affinity scores:
    Redis HASH: affinity:{user_id} → { author_id: score, ... }
    Updated by offline ML job (hourly Spark job on 30-day engagement history)
```

---

### 7.3 CDN & Media Delivery

#### CDN Architecture

```
Global CDN topology:
  300+ edge PoPs (CloudFront / Fastly) — within 20ms of 95% of users
  12 regional Origin Shields (US-East, US-West, EU-West, AP-Southeast, ...)
  S3 origin (3 regions: us-east-1, eu-west-1, ap-southeast-1)

Request path:
  App → Edge PoP (cache HIT: ~5ms, 95% of requests)
       → Origin Shield (cache HIT: ~20ms, additional 4% of requests)
       → S3 origin (~80ms, final 1% of requests)

Cache key: full URL including variant and format
  https://cdn.instagram.com/v/{media_id}/{variant}/{width}.webp

Cache-Control strategy:
  Post images:    max-age=31536000, immutable  (content-addressed — never changes)
  Profile photos: max-age=3600, stale-while-revalidate=86400
  Stories:        max-age=3600, s-maxage=1800  (short TTL — expires soon anyway)
  Video segments: max-age=31536000, immutable

Bandwidth optimization:
  WebP adoption: 30-40% smaller than JPEG at same quality
  AVIF (next-gen): 50% smaller than JPEG — rolling out to Chrome/Safari
  Client hint: App sends viewport size → CDN serves correct variant
    Sec-CH-Viewport-Width: 390 → serve 640w not 1440w

Image on-demand resizing (rare sizes):
  Resize worker (Lambda@Edge):
    On cache miss for non-standard size: fetch 640w from origin → resize → cache
    Handles profile picture circles, Explore grid, notification thumbnails
    Caches result at edge for subsequent requests
```

#### Content Deduplication

```
Problem: 100M uploads/day — many are screenshots of viral content,
         reposts, or identical images uploaded by multiple users

Perceptual hashing (pHash):
  DCT-based hash: 64-bit fingerprint of image content
  Resistant to: JPEG compression, minor crops, brightness adjustments
  Sensitive to: meaningful content changes

dedup_index table (PostgreSQL):
  phash BIGINT, post_id BIGINT, user_id BIGINT
  Index: GiST index on phash for hamming distance queries

On upload:
  1. Compute pHash of uploaded image
  2. SELECT post_id WHERE hamming_distance(phash, ?) < 10
  3. If duplicate found:
     → Option A: link to same S3 object (storage savings — dedupe content)
     → Option B: store separately, flag as repost (distribution penalty)
  4. Instagram uses Option B: dedup at storage AND apply repost signal in Explore ranking

Storage savings from dedup:
  Estimate: 15-20% of uploads are near-duplicates
  At 200 TB/day raw: saves 30-40 TB/day → ~$15K/day at S3 pricing
```

---

### 7.4 Social Graph

#### Graph Scale & Storage

```
Scale:
  2B users
  Avg following: 500 accounts
  Total edges: 2B × 500 = 1 trillion edges
  Storage: 1T × 8 bytes = 8 TB (following table alone)
  Both directions (following + followers): 16 TB

Storage: Cassandra (sharded by user_id)
  Cassandra handles:
    High write throughput (follows/unfollows)
    Linear scale-out with more nodes
    Tunable consistency (QUORUM for accurate follow status, LOCAL_ONE for counts)

Schema:
  following table: partition=follower_id, clustering=followee_id → "who do I follow?"
  followers table: partition=followee_id, clustering=follower_id → "who follows me?"

  Both tables updated synchronously on follow/unfollow (dual write):
    BEGIN BATCH
      INSERT INTO following (follower_id, followee_id, ...) ...
      INSERT INTO followers (followee_id, follower_id, ...) ...
    APPLY BATCH

  Cassandra's LWT (lightweight transactions) for private account follow requests:
    INSERT INTO follow_requests (followee_id, follower_id) IF NOT EXISTS
```

#### Private Accounts

```
Private account flow:
  1. User A follows private User B:
     → INSERT INTO follow_requests (B, A, status='pending')
     → Notify B: "A wants to follow you"

  2. User B approves:
     → INSERT INTO following (A, B) + INSERT INTO followers (B, A)
     → DELETE FROM follow_requests (B, A)
     → Kafka "follow.approved" → fan-out A's feed with B's recent posts

  3. User B rejects:
     → DELETE FROM follow_requests (B, A)
     → A not notified (privacy — Instagram design choice)

Access control:
  Feed Service: before returning post, check if poster is private:
    IF user.is_private AND NOT follows(viewer, poster): skip post
  Enforced at read time — simpler than purging from all caches on privacy change
```

---

### 7.5 Stories — Ephemeral Content

#### Design Requirements

```
Stories characteristics:
  - 24-hour TTL after posting (hard delete from CDN + S3)
  - Viewed stories: show as "seen" to poster (view receipt)
  - Stories ring in feed: ordered by recency, unseen first
  - Close Friends: subset of followers with access control
  - View count: poster sees who viewed (up to 48h)
```

#### Architecture

```
Story Storage:
  Metadata: Redis Hash + TTL (fast TTL enforcement)
    HSET story:{story_id} user_id {uid} media_url {url} created_at {ts} view_count 0
    EXPIRE story:{story_id} 86400   (24 hours)

  Media: S3 with Lifecycle rule (expire objects after 25h)
    s3://instagram-stories/{story_id}/{variant}.webp
    CDN TTL: max-age=3600 (don't over-cache — story could be deleted early)

  User's active stories:
    ZADD user_stories:{user_id} {timestamp} {story_id}
    EXPIRE user_stories:{user_id} 86400

Story feed (home screen rings):
  GET /api/v1/stories/feed:
    1. Fetch following list (cached in Feed Service)
    2. For each followee: SMEMBERS user_stories:{followee_id} (batch MGET)
    3. Filter out expired stories (Redis TTL expired = story gone)
    4. Check viewer's seen status: SISMEMBER seen:{story_id} {viewer_id}
    5. Order: unseen stories first, then seen; within each group: newest poster first
    6. Return "story rings" (one ring per user with active stories)

View receipts:
  POST /api/v1/stories/{id}/view:
    SADD seen:{story_id} {viewer_id}     (poster can check who saw)
    EXPIRE seen:{story_id} 172800        (48h — Instagram shows views for 48h)
    INCR story:{story_id} view_count

Close Friends:
  SADD close_friends:{user_id} {friend_id}
  At post time: story tagged with access_level="close_friends"
  At view time: SISMEMBER close_friends:{story_id}:{poster_id} {viewer_id}
```

---

### 7.6 Explore / Discovery Page

#### Architecture

```
Goal: show personalized content from accounts the user doesn't follow

Two-stage architecture (Candidate Generation + Ranking):

Stage 1 — Candidate Generation (offline, runs every 4 hours):
  Method A: Interest-based retrieval
    User interest vector (from liked posts' hashtags, categories)
    ANN search in FAISS: find top-500 posts similar to user's interest vector
    Embedding model: ResNet-50 for image features, BERT for captions

  Method B: Collaborative filtering
    "Users like you also engaged with..."
    Matrix factorization (Spark ALS) on user×post engagement matrix
    Produces 500 candidates per user

  Method C: Trending in user's geography
    Top 100 posts trending in user's city/country (Count-Min Sketch)
    → Adds diversity, catches zeitgeist

  Combined candidates: deduplicate → 1,000 candidates per user
  Stored in Redis: SET explore_candidates:{user_id} {post_ids_json}
  TTL: 4 hours (refreshed by offline job)

Stage 2 — Real-time Ranking (online, per request):
  Input: 1,000 candidates + real-time context (time of day, device, session length)
  Model: lightweight neural ranker (100ms budget)
  Features per candidate:
    - Content: image embedding, caption sentiment, media type
    - Author: follower count, recent engagement rate, account age
    - User-candidate: topic similarity, past engagement with similar content
    - Temporal: post age, engagement velocity (likes in last hour)
  Output: ranked top 50 → serve top 12 per page

Real-time signal injection (Flink streaming):
  "This post is going viral right now" signal:
    Flink: count likes/comments in sliding 10-minute window
    If post gains >10K likes in 10 min → inject into explore candidates for similar users
    → Breaking viral content surfaces in explore within minutes
```

---

### 7.7 Likes, Comments & Reactions

#### Like System at Scale

```
Scale: 4.2B likes/day = 48,600 likes/sec

Write path:
  POST /api/v1/posts/{id}/like:
    1. Redis: SADD post_likers:{post_id} {user_id}  (idempotent — SET ignores dupe)
               INCR post:{post_id}:like_count
    2. ZADD user_liked:{user_id} {timestamp} {post_id}  (for "liked posts" profile tab)
    3. Publish Kafka "post.liked" { post_id, liker_id, likee_id }

Async (Kafka consumer):
    4. Cassandra: INSERT INTO likes (post_id, user_id, ts) ...  (durable store)
    5. Notification Service: notify post author (with aggregation)

Like count reads:
  Redis: GET post:{post_id}:like_count → O(1), handles 48,600/sec
  Flushed to Cassandra every 60s (periodic batch job)
  On Redis miss: seed from Cassandra, warm cache

"Did I like this?" (client UI state):
  SISMEMBER post_likers:{post_id} {user_id} → boolean
  post_likers SET capped at 1M members per post (trim beyond 1M — exact set not needed)
  For viral posts > 1M likes: switch to Bloom filter (space-efficient, false-positive safe for "like" state)

#### Comment System

Scale: 500M comments/day = 5,787 comments/sec

Comments stored in Cassandra:
  Partition key: post_id
  Clustering key: comment_id DESC (newest first)
  → Efficient "get latest 20 comments for post X"

Nested replies:
  reply_to_comment_id column — single level of nesting (Instagram design)
  Fetch top-level comments → separate query for replies to each (lazy load)

Comment counts in Redis: INCR post:{post_id}:comment_count
```

---

## 8. Data Models

### Post

```sql
-- Cassandra: primary post store
CREATE TABLE posts (
    post_id      BIGINT   PRIMARY KEY,   -- Snowflake (encodes created_at)
    user_id      BIGINT   NOT NULL,
    caption      TEXT,
    media_ids    LIST<TEXT>,             -- ordered list of media_id references
    post_type    TEXT,                  -- 'photo', 'video', 'carousel', 'reel'
    hashtags     LIST<TEXT>,
    location_id  BIGINT,
    alt_text     TEXT,                  -- accessibility
    like_count   BIGINT   DEFAULT 0,
    comment_count BIGINT  DEFAULT 0,
    is_deleted   BOOLEAN  DEFAULT false,
    created_at   TIMESTAMP
);

-- User's post timeline (for profile grid)
CREATE TABLE user_posts (
    user_id   BIGINT,
    post_id   BIGINT,
    PRIMARY KEY (user_id, post_id)
) WITH CLUSTERING ORDER BY (post_id DESC);
```

### Media Object

```sql
-- PostgreSQL: media metadata (small record, rich queries)
CREATE TABLE media (
    media_id     BIGINT         PRIMARY KEY,
    uploader_id  BIGINT         NOT NULL,
    media_type   VARCHAR(10)    NOT NULL,  -- 'photo', 'video'
    status       VARCHAR(20)    DEFAULT 'processing',
                                           -- 'processing'|'ready'|'failed'
    s3_key       TEXT           NOT NULL,
    width_px     INTEGER,
    height_px    INTEGER,
    duration_s   FLOAT,                    -- for video
    file_size_bytes BIGINT,
    phash        BIGINT,                   -- perceptual hash (for dedup)
    created_at   TIMESTAMPTZ    DEFAULT NOW()
);
-- GiST index for hamming distance queries on phash:
CREATE INDEX idx_media_phash ON media USING GIST (phash gist_int8_ops);
```

### User

```sql
-- PostgreSQL
CREATE TABLE users (
    user_id          BIGINT        PRIMARY KEY,
    username         VARCHAR(30)   UNIQUE NOT NULL,
    display_name     VARCHAR(60),
    bio              VARCHAR(150),
    profile_pic_id   BIGINT,              -- FK to media
    is_private       BOOLEAN       DEFAULT false,
    is_verified      BOOLEAN       DEFAULT false,
    follower_count   INTEGER       DEFAULT 0,
    following_count  INTEGER       DEFAULT 0,
    post_count       INTEGER       DEFAULT 0,
    created_at       TIMESTAMPTZ   DEFAULT NOW()
);
CREATE UNIQUE INDEX idx_users_username_lower ON users (LOWER(username));
```

### Follow Graph

```sql
-- Cassandra shard 1: "who do I follow?" (sharded by follower_id)
CREATE TABLE following (
    follower_id  BIGINT,
    followee_id  BIGINT,
    created_at   TIMESTAMP,
    PRIMARY KEY  (follower_id, followee_id)
) WITH CLUSTERING ORDER BY (followee_id ASC);

-- Cassandra shard 2: "who follows me?" (sharded by followee_id)
CREATE TABLE followers (
    followee_id  BIGINT,
    follower_id  BIGINT,
    created_at   TIMESTAMP,
    PRIMARY KEY  (followee_id, follower_id)
) WITH CLUSTERING ORDER BY (follower_id ASC);

-- Pending follow requests (for private accounts)
CREATE TABLE follow_requests (
    followee_id  BIGINT,
    follower_id  BIGINT,
    created_at   TIMESTAMP,
    PRIMARY KEY  (followee_id, follower_id)
);
```

### Story

```
Redis data structures (primary store — TTL-driven):
  HASH  story:{story_id}          → { user_id, media_url, created_at, view_count }
  ZSET  user_stories:{user_id}    → { story_id: timestamp }
  SET   seen:{story_id}           → { viewer_id_1, viewer_id_2, ... }
  SET   close_friends:{user_id}   → { friend_id_1, friend_id_2, ... }

All keys EXPIRE after 24h (stories) or 48h (seen receipts)
S3 lifecycle rule: delete story media after 25h
```

---

## 9. Follow-Up Questions

### Q1: How do you handle a viral Reel (500M views in 24 hours)?

```
CDN absorbs 99%+ of traffic:
  All video segments cached at edge — S3 never sees load spike
  HLS segmented video: each 2-second .ts segment cached independently
  Bitrate adaptation: users on poor networks auto-downgrade to 360p
  → No single origin fetch causes cascade

Hot metadata (like count, comment count):
  Redis INCR handles millions of operations/sec on single key
  If single Redis key becomes too hot (>500K ops/sec):
    → Replicate counter to 10 Redis keys: INCR post:{id}:likes:{shard_n}
    → Query total: SUM(GET post:{id}:likes:0 ... GET post:{id}:likes:9)
    → Redis pipeline: single round trip for all 10 reads

Feed fan-out storm prevention:
  Post goes viral → existing followers already have it in cache
  New followers gained during virality: fan-out handles them normally
  The viral loop is: Explore → more users follow → more fan-out writes
  Rate-limit fan-out: max 10M feed writes per post (cap; beyond = pull model)
```

### Q2: How do you implement Instagram's "Sensitive Content Control"?

```
Content Sensitivity Classification:
  ML model classifies every photo/video at upload time:
    Labels: safe, mild_sensitive, sensitive, violating
    Model: ResNet fine-tuned on Instagram's policy dataset

User content settings (3 levels):
  Standard:    see mild_sensitive, not sensitive
  More:        see sensitive content (adult default: opt-in required)
  Less:        see only safe content

Feed/Explore filtering:
  Post score includes sensitivity_level
  Feed Service checks user content_setting → filters/demotes posts above threshold
  Implemented as: post.sensitivity_level > user.content_threshold → ZREM from feed cache

Violating content:
  Auto-remove (model confidence > 0.99) → immediate soft delete
  Human review queue (confidence 0.90-0.99) → temporary hide pending review
  Appeals process: 72-hour SLA for human review
```

### Q3: How do you scale to 1 billion new photos/day (10× growth)?

```
Bottlenecks at 10× scale and mitigations:

1. Upload ingestion:
   Current: 7,200 uploads/sec → 72,000 uploads/sec
   Mitigation: S3 presigned URL approach already decentralized
   S3 scales linearly → no change needed
   API Gateway: auto-scale → horizontal scaling

2. Media processing (most critical):
   Current: 7,000 GPU cores → 70,000 GPU cores needed
   Mitigation:
     → Spot GPU instances (70% cheaper) for non-urgent processing
     → Queue-based scaling: Kafka lag → Auto Scaling Group
     → Prioritize: user-visible processing (thumbnail) first; archival quality last
     → Progressive upload UX: show blurry preview immediately, full quality in 30s

3. Fan-out:
   Current: 231,400 feed writes/sec → 2.3M writes/sec
   Mitigation:
     → Lower celebrity threshold from 5M → 500K followers
     → Increase Kafka partitions: 2,000 → 20,000
     → Tiered fan-out: high-affinity followers first (you interact with them → they want to see it)
     → Stale feed acceptable: show cached feed, update async (2 seconds late = fine)

4. Storage:
   Current: 2.5 PB/day → 25 PB/day
   Mitigation:
     → Aggressive S3 Intelligent Tiering: posts > 30 days → IA; > 180 days → Glacier
     → AVIF encoding (50% compression vs WebP) for new uploads
     → Video quality cap: 1080p max (4K not served — bandwidth cost too high)
     → Dedup rate at scale increases (more viral content) → savings grow

5. CDN bandwidth:
   Current: 500 GB/s → 5 TB/s
   Mitigation: negotiate multi-CDN contracts (CloudFront + Fastly + Akamai)
   Multi-CDN load balancing via Anycast routing → split traffic geographically
```

### Q4: How do you implement the "Close Friends" feature?

```
Close Friends list:
  Stored in Redis SET close_friends:{user_id}
  Max 250 close friends (Instagram limit)
  → SADD close_friends:{user_id} {friend_id}

Story posting with Close Friends:
  POST /api/v1/stories { media_id, audience: "close_friends" }
  → story tagged: access_level = "close_friends"

Story feed access check:
  For each active story in followee's user_stories:{followee_id}:
    If story.access_level == "close_friends":
      SISMEMBER close_friends:{poster_id} {viewer_id}
      If false → skip this story

UI indicator:
  Close Friends story ring shown with green border (client-side styling)
  Viewer sees green ring only if they're in that user's close friends list
  → Privacy preserved: viewers know they're close friends, non-members just don't see the story

Feed cache for Close Friends posts:
  Do NOT pre-push close friends posts to all followers' caches
  (Privacy risk: fanout worker would need to know close friends lists)
  Instead: pull model for close friends → check access at read time
  → Small performance penalty acceptable (close friends lists are typically small)
```

### Q5: How do you handle a user with 500M followers (global celebrity)?

```
This is the celebrity problem at extreme scale.

Posting:
  Fan-out to 500M followers is impossible in real-time
  500M × ZADD Redis = 500M writes → hours of processing
  Solution: Pure fan-out on READ for all celebrity posts (threshold: ≥ 1M followers)

  Celebrity post stored:
    ZADD user_posts:{celeb_id} {timestamp} {post_id}
    Cassandra: INSERT INTO user_posts (celeb_id, post_id, ...)

  Zero fan-out writes → post appears instantly in celeb's own profile
  Followers see it at next feed load (read-time merge)

Read-time merge for followers:
  User follows 10 celebrities (≥1M followers):
    → Fetch last 5 posts from each: 10 × ZREVRANGE = 10 Redis reads = ~5ms
    → Merge with pre-computed feed: k-way merge of 10+500 candidates = ~2ms
    → Rank and return top 12 = ~1ms
  Total additional latency vs no celebrities followed: ~8ms

  At 500M followers, 500M users do this merge on every feed load:
    500M × 10 Redis reads/load × 5 loads/day = 25B Redis ops/day from celebrity reads
    Distributed across Redis cluster: 25B / 86,400 = 289K reads/sec → manageable

Celebrity media delivery:
  When Taylor Swift posts a new photo and 500M people open the app:
  → CDN Origin Shield absorbs requests
  → Edge PoPs cache image in first 1,000 requests → remaining 499,999,000 from cache
  → S3 origin sees ~500 requests total (0.0001%)
```

### Q6: How do you implement Instagram Reels recommendation (TikTok-style)?

```
Goal: near-infinite scroll of personalized videos

Architecture: same two-stage as Explore but optimized for video engagement signals

Signals unique to video:
  - Watch percentage (watched 90% vs skipped at 2s — strongest signal)
  - Replay count
  - Sound on/off
  - Loop count
  - Completion rate by video duration bucket

Candidate generation:
  User interest embedding (updated after each session via online learning)
  ANN search in FAISS for similar-interest users' liked videos
  Trending Reels in user's language/region (Count-Min Sketch, 1-hour window)
  Creator affinity: creators user has interacted with before

Ranking model:
  Two-tower neural network (same as YouTube DNN recommendation):
    Tower 1: user embedding (interest vector, device, time-of-day)
    Tower 2: video embedding (visual features, audio, caption, engagement stats)
  Score: predicted probability of (watch > 50%) × predicted completion rate

Serving:
  Pre-fetch: client buffers next 5 Reels while watching current
  Prefetch API: GET /api/v1/reels/next?after={current_reel_id}&count=5
  Response includes: HLS playlist URLs (2s segments pre-cached at CDN)
  → Zero buffering: next video starts before user swipes

Feedback loop:
  Client sends engagement events via batched beacon:
    POST /api/v1/reels/events [{ reel_id, event_type, watch_ms, total_ms }, ...]
  Kafka "reels.engagement" → Flink → Redis feature store (real-time signal update)
  → Next 5 Reels already personalized based on what you just watched
```

---

## 10. Interview Summary Card

### Time Allocation (45 min)

| Minute | Focus |
|--------|-------|
| 0–5 | Clarifying questions, lock in scope |
| 5–10 | Functional + Non-Functional requirements |
| 10–15 | Back-of-envelope (uploads, CDN, fan-out math) |
| 15–20 | High-level diagram + core flows |
| 20–35 | Deep dive: Photo pipeline + Fan-out hybrid |
| 35–42 | CDN strategy + trade-offs |
| 42–45 | Follow-up (celebrity problem, viral content) |

### The Three Key Decisions to Articulate

```
1. FAN-OUT STRATEGY:
   "Normal users (<1M followers) → fan-out on write.
    Celebrities (≥1M followers) → fan-out on read, merged at query time.
    This eliminates the 500M-write celebrity storm while keeping read latency <300ms."

2. MEDIA UPLOAD PATTERN:
   "Presigned S3 URLs — the app uploads directly to S3, bypassing our servers.
    Our API returns a media_id + presigned URL, then processing happens async via Kafka.
    This decouples write throughput from media volume (5 MB × 7,200 uploads/sec = 36 GB/s
    that we NEVER touch on our API servers)."

3. CDN IMMUTABILITY:
   "Content-addressed media URLs (cdn.instagram.com/{hash}/{variant}.webp).
    Content never changes at a given URL → Cache-Control: immutable, max-age=31536000.
    This gives us a 99%+ CDN hit rate at 868K requests/sec."
```

### Key Numbers

```
2B MAU, 500M DAU
100M photos + 20M videos + 500M stories uploaded/day
7,200 upload writes/sec → 36 GB/s bypassed via S3 presigned
868K CDN requests/sec (>99% from cache)
48,600 like writes/sec → Redis INCR
Fan-out: 231,400 Redis writes/sec (normal users only)
Storage: 2.5 PB/day new media → 4.56 EB in 5 years
```

### Technology Choices

| Component | Technology | Why |
|-----------|-----------|-----|
| Post storage | Cassandra | High write throughput, time-series, linear scale |
| Feed cache | Redis Sorted Set | O(log N) insert, O(1) range read, score = ranked timestamp |
| Follow graph | Cassandra (adjacency list, dual-shard) | 1T edges, high write, directional queries |
| Media storage | S3 + multi-tier CDN | Petabyte scale, immutable, global delivery |
| Video transcoding | FFmpeg on GPU cluster | Industry standard, hardware acceleration |
| Stories | Redis (TTL-native) + S3 lifecycle | Natural expiry semantics, no GC job needed |
| Explore ranking | FAISS ANN + lightweight neural ranker | Billion-scale candidate retrieval + fast online scoring |
| Fan-out pipeline | Kafka (2,000 partitions) | Durable, parallel, replay on failure |
| Like counting | Redis INCR → Cassandra flush | 48K ops/sec with O(1) latency |
| Dedup | pHash + GiST index | Content-aware, handles compression artifacts |
| Search | Elasticsearch | Full-text, hashtag, geospatial queries |
| Trending | Count-Min Sketch + Flink | Bounded memory, streaming aggregation |

---

*co-authored-by: wibey jetbrains plugin (wibey.walmart.com/code)*
