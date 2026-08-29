# System Design: Twitter

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard  
> Core challenge: **Fan-out at 500M tweets/day with celebrity accounts having 100M+ followers**

---

## Table of Contents

1. [Clarifying Questions](#1-clarifying-questions)
2. [Functional Requirements](#2-functional-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Back-of-Envelope Estimation](#4-back-of-envelope-estimation)
5. [High-Level Design](#5-high-level-design)
6. [Deep Dive](#6-deep-dive)
   - 6.1 Tweet Creation & Storage
   - 6.2 Timeline Generation (The Core Problem)
   - 6.3 Follow Graph
   - 6.4 Search & Trending Topics
   - 6.5 Media Upload & Delivery
   - 6.6 Notifications
7. [Trade-offs Discussion](#7-trade-offs-discussion)
8. [Data Models](#8-data-models)
9. [Follow-Up Questions](#9-follow-up-questions)
10. [Interview Summary Card](#10-interview-summary-card)

---

## 1. Clarifying Questions

```
"Are we designing the full Twitter platform?"
→ Yes — tweet, timeline, follow, search, notifications

"Do we need Direct Messages?"
→ Out of scope for now (similar to Messenger design)

"What's the scale? Global product?"
→ Yes — 500M DAU, global

"Do we need Twitter Spaces / Moments / Communities?"
→ Out of scope — focus on core feed

"Do we need ads / monetization?"
→ Out of scope

"Retweets, quotes, replies, likes?"
→ Yes — all standard interactions

"Any constraints on tweet size?"
→ 280 characters text; images and videos supported
```

---

## 2. Functional Requirements

### Core (Must Have)

| # | Requirement | Notes |
|---|-------------|-------|
| FR-1 | **Post tweet** | 280 chars, optional media (images/video), @mentions, #hashtags |
| FR-2 | **Home timeline** | Chronologically reverse-sorted feed of tweets from followed users |
| FR-3 | **Follow / Unfollow** | Asymmetric graph (follow without mutual approval) |
| FR-4 | **Like / Unlike tweet** | Like count visible; user can see their liked tweets |
| FR-5 | **Retweet / Quote tweet** | Retweet propagates to follower timelines |
| FR-6 | **Reply** | Threaded replies to tweets |
| FR-7 | **Search** | Full-text search of tweets by keyword, hashtag, @user |
| FR-8 | **User profile** | Profile page, tweets, followers, following counts |
| FR-9 | **Trending topics** | Top 10 trending hashtags globally / by region |
| FR-10 | **Notifications** | Likes, retweets, follows, @mentions |

### Out of Scope

- Direct Messages, Twitter Spaces, Communities, Ads, Bookmarks, Lists

---

## 3. Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| **Availability** | 99.99% | Twitter is a public utility — outages are global news |
| **Timeline read latency** | p99 < 300ms | Feed must feel real-time |
| **Tweet post latency** | p99 < 500ms | Write can tolerate slightly more |
| **Timeline consistency** | Eventual (seconds) | Seeing a tweet 2 seconds late is acceptable |
| **Search latency** | p99 < 500ms | Full-text search; near-real-time indexed |
| **Read:Write ratio** | ~1000:1 | Twitter is extremely read-heavy |
| **Fan-out scale** | 100M followers (Katy Perry) | The defining scalability challenge |
| **Scalability** | Handle 500M tweets/day | 5,800 tweets/sec average, 15K peak |
| **Durability** | No tweet loss after posted | Tweets are permanent public record |

---

## 4. Back-of-Envelope Estimation

### Users & Content

```
Monthly Active Users (MAU):    700 million
Daily Active Users (DAU):      500 million
Tweets posted/day:             500 million
Avg followers/user:            200
Avg following/user:            200

Read:Write ratio:
  Timeline loads/day: 500M users × 10 timeline loads = 5 billion
  Tweets/day: 500M
  Ratio: 5B / 500M = 10,000:1 (actually ~1000:1 after accounting for cache)
```

### QPS

```
Write (tweet creation):
  500M tweets/day / 86,400 = 5,800 tweets/sec (avg)
  Peak (breaking news): 15,000 tweets/sec

Read (timeline):
  5B timeline loads/day / 86,400 = 57,870 reads/sec (avg)
  Peak: 150,000 reads/sec

Search:
  200M searches/day / 86,400 = 2,315 searches/sec

Fan-out writes (most critical):
  5,800 tweets/sec × 200 avg followers = 1,160,000 timeline writes/sec
  But: top 1000 celebrities with 10M+ followers skipped in fan-out
       (handled by read-time merge — see Deep Dive 6.2)
  Practical fan-out: 5,800 × 150 (adjusted avg) = 870,000 writes/sec
```

### Storage

```
Tweets (5 years):
  500M tweets/day × 365 × 5 = 912.5 billion tweets
  Avg tweet: 280 chars + metadata = 500 bytes
  Raw storage: 912.5B × 500 bytes = 456 TB

Media:
  ~20% of tweets have images: 100M/day × 3 images × 200 KB = 60 TB/day
  ~5% have video: 25M/day × 5 MB = 125 TB/day
  Annual media: (60 + 125) TB × 365 = 67.5 PB/year → CDN + object storage

Home timeline cache (Redis):
  500M active users × top 800 tweet IDs × 8 bytes = 3.2 TB
  Only cache active users (logged in last 7 days): ~200M
  200M × 800 × 8 bytes = 1.28 TB → manageable Redis cluster

Follow graph:
  500M users × 200 avg following × 8 bytes (user_id) = 800 GB
```

### Bandwidth

```
Timeline read: 57,870 req/sec × 10 tweets × 500 bytes = 289 MB/s
Media (CDN): ~1 TB/s during peak (handled by CloudFront/CDN, not origin)
Tweet write: 5,800 req/sec × 500 bytes = 2.9 MB/s inbound
```

---

## 5. High-Level Design

### Architecture Overview

```
                        ┌──────────────────────────┐
                        │       API Gateway         │
                        │  Auth · Rate Limit · Route│
                        └────────────┬─────────────┘
                                     │
        ┌────────────────────────────┼────────────────────────────┐
        │                           │                            │
        ▼                           ▼                            ▼
┌──────────────┐           ┌──────────────┐            ┌──────────────────┐
│ Tweet Service│           │Timeline Svc  │            │  Follow Service  │
│  (write path)│           │ (read path)  │            │  (graph store)   │
└──────┬───────┘           └──────┬───────┘            └──────────────────┘
       │                          │
       ▼                          ▼
┌──────────────┐           ┌──────────────┐
│  Kafka       │           │  Redis       │           ┌──────────────────┐
│  (fan-out    │           │  (timeline   │           │  Search Service  │
│   pipeline)  │           │   cache)     │           │  (Earlybird /    │
└──────┬───────┘           └──────┬───────┘           │  Elasticsearch)  │
       │                          │ miss               └──────────────────┘
       ├──► Fan-out Worker         ▼
       │    (normal users)  ┌──────────────┐          ┌──────────────────┐
       │                    │  Tweet Store │          │  Trend Service   │
       ├──► Notification Svc │  (Manhattan/ │          │  (Count-Min      │
       │                    │  Cassandra)  │          │   Sketch)        │
       └──► Search Indexer  └──────────────┘          └──────────────────┘
```

### Core Request Flows

#### Post Tweet

```
1. Client → POST /api/v1/tweets { text, media_ids[], reply_to }
2. API Gateway → authenticate JWT, rate limit (300 tweets/3hr)
3. Tweet Service:
   a. Validate (280 char limit, media exists, not spam)
   b. Generate tweet_id (Snowflake ID — time-ordered, globally unique)
   c. Write tweet to Tweet Store (Cassandra / Manhattan)
   d. Publish to Kafka topic "tweet.created" { tweet_id, user_id, timestamp }
4. Kafka consumers (async, parallel):
   ├── Fan-out Worker → writes tweet_id to followers' timeline caches
   ├── Search Indexer → indexes tweet in Elasticsearch (< 30s lag)
   ├── Notification Service → notifies @mentioned users, reply targets
   └── Trend Aggregator → increments hashtag counters
5. Return 201 Created { tweet_id, url } to client immediately
   (fan-out happens asynchronously — user doesn't wait)
```

#### Read Home Timeline

```
1. Client → GET /api/v1/timeline?cursor=&limit=20
2. Timeline Service:
   a. Read from Redis: ZREVRANGE timeline:{user_id} 0 19 WITHSCORES
      → Returns list of (tweet_id, timestamp) pairs
   b. For celebrity followees (>1M followers):
      → Merge in their recent tweets at read time (not pre-fanned)
   c. Fetch tweet objects: MGET tweet:{id1} tweet:{id2} ... (batch from Redis/Cassandra)
   d. Hydrate: attach user profiles, like counts, retweet counts
3. Return paginated timeline (20 tweets)

Latency breakdown:
  Redis ZREVRANGE: ~1ms
  Celebrity merge: ~5ms (check last 5 tweets from each celebrity followed)
  Tweet hydration: ~10ms (Redis MGET pipeline)
  Total: ~16ms → well under 300ms p99
```

---

## 6. Deep Dive

### 6.1 Tweet Creation & Storage

#### Snowflake ID — Tweet Identity

Twitter invented Snowflake IDs specifically for tweets:

```
64-bit integer layout:
┌──────────────────────────┬─────────────┬──────────────────┐
│    41 bits               │   10 bits   │    12 bits       │
│  Timestamp (ms)          │  Machine ID │  Sequence #      │
│  since Twitter epoch     │  (0-1023)   │  per ms (0-4095) │
│  (Nov 4, 2010)           │             │                  │
└──────────────────────────┴─────────────┴──────────────────┘

Properties:
  - Globally unique across all Tweet Service nodes
  - Monotonically increasing → natural sort by creation time
  - Embed timestamp → no separate created_at column needed
  - Decodes: tweet_id >> 22 + EPOCH = creation timestamp (ms)
  - 69 years of unique IDs from Twitter epoch
  - 4,096 tweets/ms per node × 1,024 nodes = 4.19 billion tweets/sec capacity
```

#### Tweet Storage — Manhattan / Cassandra

```
Twitter built Manhattan (a distributed key-value store) for tweet storage.
For our design: Cassandra is equivalent.

Primary table (lookup by tweet_id):
  Partition key: tweet_id
  Read pattern: point lookups only — O(1)
  Write pattern: insert-only (tweets are immutable after posting)

User tweets table (lookup by user_id):
  Partition key: user_id
  Clustering key: tweet_id DESC (newest first)
  → Efficiently fetch "user's last N tweets" for profile page

Replication:
  RF = 3 across 3 data centers
  Write: LOCAL_QUORUM (2/3 local replicas)
  Read: LOCAL_ONE (fast, eventually consistent — fine for tweets)
```

#### Tweet Object Cache

```
After writing to Cassandra:
  Redis: SETEX tweet:{tweet_id} 86400 {tweet_json}
  tweet_json: { id, user_id, text, media_ids, reply_to, created_at,
                like_count, retweet_count, reply_count }

Read path:
  1. MGET tweet:id1 tweet:id2 ... tweet:id20 (pipeline — single round trip)
  2. Cache miss → Cassandra batch read → warm Redis

Like/Retweet counts:
  Stored as Redis counters: INCR tweet:{id}:likes
  Periodically (every 60s) flushed to Cassandra via batch job
  → Counts are approximate (±1-2 under race conditions) — acceptable
```

---

### 6.2 Timeline Generation — The Core Problem

This is the most important design decision in the entire interview.

#### Option A: Fan-Out on Write (Push Model)

When a user tweets, immediately push the tweet_id to all followers' timeline caches.

```
User X (200 followers) posts tweet T1:

Fan-out Worker:
  followers = FollowService.getFollowers(user_X)  // [u1, u2, ... u200]
  for follower_id in followers:
    Redis.ZADD timeline:{follower_id} timestamp tweet_id
    Redis.ZREMRANGEBYRANK timeline:{follower_id} 0 -801  // keep top 800

Cost per tweet:
  5,800 tweets/sec × 200 avg followers = 1,160,000 Redis writes/sec
  → Achievable with Redis cluster (millions of writes/sec possible)
```

**Pros:** Timeline read is O(1) — just read pre-computed cache  
**Cons:** Celebrity problem — Katy Perry (100M followers) posts 1 tweet → 100M Redis writes instantly

#### Option B: Fan-Out on Read (Pull Model)

When a user loads their timeline, fetch the latest tweets from everyone they follow.

```
User loads timeline (follows 200 accounts):
  tweet_ids = []
  for followee_id in following_list:
    tweets = Cassandra.query(
      "SELECT tweet_id FROM user_tweets WHERE user_id = ? ORDER BY tweet_id DESC LIMIT 20",
      [followee_id]
    )
    tweet_ids.extend(tweets)

  # Merge and sort 200 × 20 = 4,000 tweet IDs in memory
  timeline = sorted(tweet_ids, reverse=True)[:20]
```

**Pros:** No fan-out cost on write; celebrities handled automatically  
**Cons:** Every read requires 200 Cassandra queries + merge — catastrophically slow at scale

#### Option C: Hybrid Model (Twitter's Actual Approach — Recommended)

```
Classification:
  "Normal" user: < 1M followers → use fan-out on WRITE
  "Celebrity" user: ≥ 1M followers → use fan-out on READ (merge at query time)

Write path (when normal user tweets):
  Fan-out Worker → push tweet_id to all followers' Redis timelines
  (skip pushing to celebrity followers' timelines — they'll pull)

Write path (when celebrity tweets):
  NO fan-out → just write tweet to Cassandra user_tweets table
  (too expensive to push to 100M timelines)

Read path (timeline load):
  1. Read pre-computed timeline from Redis (tweet_ids from normal followees)
  2. For each celebrity the user follows:
     → Fetch their last 20 tweets from Cassandra (O(C) where C = # celebrities followed)
  3. Merge: in-memory k-way merge of all lists → top 20 by timestamp
  4. Fetch full tweet objects (Redis MGET)

User experience: identical — user can't tell the difference
Latency: Redis read (1ms) + celebrity merges (5ms) + hydration (10ms) = ~16ms ✓
```

#### Timeline Cache Design

```
Redis Sorted Set per user:
  Key:   timeline:{user_id}
  Score: tweet_id (Snowflake — encodes timestamp as score automatically)
  Member: tweet_id

Operations:
  Fan-out write: ZADD timeline:{follower_id} {tweet_id} {tweet_id}
                 (score = tweet_id itself, since it's time-ordered)
  Read:          ZREVRANGE timeline:{user_id} 0 19 (top 20)
  Trim:          ZREMRANGEBYRANK timeline:{user_id} 0 -801 (keep 800 max)

Cache capacity:
  200M active users × 800 tweet_ids × 8 bytes = 1.28 TB
  Redis cluster: 20 nodes × 64 GB = 1.28 TB → fits exactly

Cache miss (user hasn't logged in for >7 days):
  → Timeline rebuilder job: query Cassandra for all followees,
    fetch their recent tweets, populate Redis timeline
  → Done asynchronously; show "Loading feed..." to user
```

#### Fan-Out Worker — Parallelism

```
Kafka topic: "tweet.created"
  Partitions: 1,000 (parallelism = 1,000 concurrent fan-out workers)
  Key: user_id (all tweets from same user go to same partition → ordering)

Fan-out Worker (per partition):
  1. Consume tweet.created event
  2. Fetch followers from Follow Service (paginated, cached in worker memory)
  3. Batch Redis writes: pipeline ZADD commands (1,000 per pipeline flush)
  4. Skip followers whose timelines aren't cached (offline users)
  5. Acknowledge Kafka after all writes complete

Backpressure: if a viral tweet causes lag, Kafka buffers it
  → Twitter's fan-out can take up to 5 minutes for low-priority accounts
  → Breaking news / normal usage: < 5 seconds

Zombie optimization:
  Skip timeline update for users inactive > 30 days
  → 30% of Twitter accounts are dormant → 30% write reduction
```

---

### 6.3 Follow Graph

#### Why a Dedicated Graph Store?

```
Follow relationships form a directed graph:
  - 500M users × 200 avg following = 100 billion edges
  - Access patterns:
      "Who does user A follow?" (outgoing edges) — for fan-out
      "Who follows user A?" (incoming edges) — for notification, fan-out
      "Do A and B have a mutual follow?" — for DMs, features

  - Not suitable for relational DB: 100B rows, O(N) scans for followers
  - Not suitable for graph DB (Neo4j): doesn't scale to this volume

Twitter built FlockDB (MySQL-based adjacency list, sharded by user_id).
For our design: PostgreSQL or Cassandra with careful schema design.
```

#### Follow Graph Schema

```sql
-- Following table: "Who does user X follow?"
-- Sharded by follower_id (the person doing the following)
CREATE TABLE following (
    follower_id   BIGINT   NOT NULL,  -- partition key
    followee_id   BIGINT   NOT NULL,  -- person being followed
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (follower_id, followee_id)
);
-- Shard across 1,000 PostgreSQL instances by follower_id % 1000

-- Followers table: "Who follows user X?"
-- Sharded by followee_id (the person being followed)
CREATE TABLE followers (
    followee_id   BIGINT   NOT NULL,  -- partition key
    follower_id   BIGINT   NOT NULL,
    created_at    TIMESTAMPTZ DEFAULT NOW(),
    PRIMARY KEY (followee_id, follower_id)
);
-- Shard by followee_id % 1000

-- Both tables updated on every follow/unfollow (dual write, transactional per shard)
```

#### Follower Count Cache

```
Redis:
  INCR followers:{user_id}   -- on follow
  DECR followers:{user_id}   -- on unfollow
  INCR following:{user_id}

Fan-out worker caches follower lists in local memory:
  Key: {user_id} → [follower_id_1, follower_id_2, ...]
  TTL: 60 seconds
  Avoids hitting Follow Service DB on every tweet
  Refreshed async in background
```

---

### 6.4 Search & Trending Topics

#### Search Architecture — Earlybird (Inverted Index)

Twitter's search engine is called Earlybird — a modified Lucene.

```
Indexing pipeline:
  Tweet posted → Kafka "tweet.created"
                  → Search Indexer consumer
                  → Tokenize tweet text
                  → Build inverted index entry
                  → Write to Earlybird (Elasticsearch equivalent)

Inverted index:
  "breaking" → [(tweet_id=1001, score=0.9), (tweet_id=2005, score=0.7), ...]
  "news"     → [(tweet_id=1001, score=0.8), ...]

  Index is sharded by time (each shard = 1 day of tweets)
  Recent tweets (last 7 days) = hot shards, in-memory
  Older tweets = warm/cold shards, SSD-backed

Search query: "breaking news"
  1. Parse query: tokens = ["breaking", "news"]
  2. Query all shards in parallel (fan-out to 20 Elasticsearch nodes)
  3. Each node: intersect posting lists → score by (relevance + recency + engagement)
  4. Merge top-K results (distributed top-K merge)
  5. Hydrate tweet objects → return results

Indexing latency: < 30 seconds from tweet post to searchable
Search latency: p99 < 500ms (Elasticsearch with 20-node cluster)
```

#### Real-Time Trending Topics — Count-Min Sketch

```
Problem:
  500M tweets/day with hashtags — which are trending right now?
  Can't store exact count for all possible hashtags (unbounded cardinality)

Solution: Count-Min Sketch (probabilistic frequency estimation)

Data structure:
  d=5 rows × w=10,000 columns
  Each row has independent hash function

Flink streaming job (processes Kafka tweet stream):
  Window: sliding 30-minute window, updated every 1 minute
  For each tweet: extract hashtags → increment CMS counters

  Every 1 minute:
    top_hashtags = min-heap of size 50
    for each hashtag in recent window:
      count_estimate = min(CMS.query(hashtag))
      if count_estimate > min(top_hashtags):
        push to heap

Redis: ZADD trending:global {count} {hashtag}

Trend Service API:
  GET /trends → ZREVRANGE trending:global 0 9 WITHSCORES

Localized trends:
  Separate CMS per geo-region (US, UK, Japan, etc.)
  Geo determined by user's IP / account settings
  ZADD trending:{region_code} {count} {hashtag}

Trending decay:
  Older tweets weighted less: score = count × exp(-λ × age_hours)
  λ = 0.5 → half-life of 2 hours (something trending 4h ago has 25% weight)
```

---

### 6.5 Media Upload & Delivery

#### Upload Flow

```
Problem: Large media files (images up to 5MB, videos up to 512MB)
         Cannot send through Tweet API (too slow, blocks tweet posting)

Solution: Pre-upload before tweet composition

1. Client → POST /api/v1/media/upload/init
   Response: { media_id, upload_url (S3 presigned) }

2. Client → PUT {upload_url} (direct to S3, bypasses Twitter API)
   → S3 receives file directly (no Twitter server in media path)

3. S3 event → Lambda → Media Processing Queue (Kafka)

4. Media Worker:
   For images:
     → Resize to: 200×200 (thumbnail), 600px width (timeline), 1200px (full)
     → Convert to WebP (30% smaller than JPEG at same quality)
     → Store all variants in S3

   For videos:
     → Transcode: 240p, 480p, 720p, 1080p (adaptive bitrate HLS)
     → Generate thumbnail from frame 1
     → Extract dominant color (for loading placeholder)
     → Store segments in S3, generate .m3u8 playlist

5. Media Worker → SET media:{media_id}:status "ready" in Redis
   Client polls /api/v1/media/{media_id}/status until ready

6. Client attaches media_id to tweet:
   POST /api/v1/tweets { text: "...", media_ids: ["M123456"] }
```

#### CDN Strategy

```
All media served through CloudFront CDN (300+ edge PoPs globally):

CDN URL pattern:
  https://pbs.twimg.com/media/{media_id}?format=webp&name=small

Cache-Control: public, max-age=604800 (7 days)
  → Images are immutable (never updated) → aggressive caching

S3 → CloudFront origin:
  Cache HIT (>95% for popular images): served from edge, ~5ms
  Cache MISS: origin fetch from S3, ~50ms, cached for next request

Video streaming:
  HLS: browser/app requests .m3u8 playlist → fetches segments from CDN
  Adaptive bitrate: player monitors bandwidth → switch quality automatically
  Bandwidth saving: 4K not offered → max 1080p (most watches on mobile)

CDN cost optimization:
  S3 Intelligent Tiering: viral images (high access) → S3 Standard
                          old images (low access) → S3 Standard-IA → Glacier
  CDN cache warming: viral tweets → pre-warm CDN edges before traffic hits
```

---

### 6.6 Notifications

#### Notification Types

| Event | In-App | Push | Email |
|-------|--------|------|-------|
| Someone liked your tweet | ✅ | ✅ | Optional |
| Someone retweeted | ✅ | ✅ | Optional |
| New follower | ✅ | ✅ | ✅ |
| @mention | ✅ | ✅ | ✅ |
| Reply to your tweet | ✅ | ✅ | Optional |

#### Architecture

```
Kafka topic: "notifications.raw"
  Producers: Tweet Service (@mention, like, retweet), Follow Service (new follower)

Notification Fanout Service (Flink consumer):
  1. Read event from Kafka
  2. Check user notification preferences (Redis cache → PostgreSQL)
  3. Dedup: SET notif:{user_id}:{event_type}:{source_id} 1 NX EX 3600
     → If key exists: skip (already notified)
  4. Aggregate: "Alice and 47 others liked your tweet" (batch within 30s window)
  5. Route to channel:

     In-App:
       Redis: LPUSH notif_inbox:{user_id} {notification_json}
       LTRIM notif_inbox:{user_id} 0 99  (keep last 100)
       Client polls or SSE for real-time delivery

     Push:
       FCM (Android) / APNs (iOS) via Firebase Admin SDK
       Batch: up to 500 tokens per FCM call (efficiency)

     Email:
       SES / SendGrid for transactional email
       Daily digest for low-engagement users (reduce noise)

Notification aggregation (like bundling):
  Rather than: "Alice liked your tweet", "Bob liked your tweet", "Carol liked..."
  Show: "Alice, Bob, and 45 others liked your tweet"

  Implementation:
    Flink: 30-second tumbling window per (tweet_id, event_type)
    Aggregate all actors within window → single notification
```

---

## 7. Trade-offs Discussion

### 7.1 Fan-Out Strategy: Write vs Read vs Hybrid

This is the defining architectural decision for Twitter and the one interviewers probe most deeply.

| Strategy | Write Cost | Read Cost | Celebrity Handling | Offline Users |
|----------|-----------|-----------|-------------------|---------------|
| **Fan-out on Write** | 870K Redis writes/sec | O(1) — Redis ZREVRANGE | ❌ 100M writes per Katy Perry tweet | Wastes writes to inactive timelines |
| **Fan-out on Read** | O(1) — Cassandra insert | O(following) — 200 Cassandra queries/load | ✅ Natural | ✅ No wasted writes |
| **Hybrid** (chosen) | Normal users only (870K/sec adjusted) | O(1) + O(celebrities) ≈ 16ms total | ✅ Merge at read time | Skip inactive users (30% reduction) |

```
Celebrity threshold math:
  Katy Perry: 100M followers
  Fan-out on write cost: 100M Redis ZADD ops per tweet
  At 50K ZADD/sec per Redis node: 100M / 50K = 2,000 seconds = 33 MINUTES
  → Completely untenable → celebrity must use read-time merge

Normal user threshold math (1M followers, classified as "celebrity"):
  1M Redis ZADD ops / 50K per node = 20 seconds of fan-out
  With 1,000 fan-out worker threads and 100 Redis nodes: 1M / (50K × 100) = 0.2s
  → Borderline; Twitter set threshold at 1M based on actual latency SLA

Read-time merge cost for celebrity followees:
  User follows 5 celebrities → 5 Cassandra reads of "last 20 tweets" → 5ms each = 25ms
  Total timeline load: 1ms (Redis) + 25ms (celebrity merge) + 10ms (hydration) = 36ms ✓
```

**Why not fan-out on read everywhere?** 200 Cassandra queries per timeline load × 58,000 timeline loads/sec = 11.6M Cassandra reads/sec. At ~5ms each, p99 becomes 200 × 5ms = 1,000ms — 3× over the 300ms SLA. Fan-out on write pre-computes the merge so reads stay O(1).

**Zombie optimization (critical in real systems)**: Skip fan-out for users inactive >30 days. 30% of Twitter accounts are dormant → 30% reduction in Redis writes → saves ~261K writes/sec.

---

### 7.2 Tweet ID: Snowflake vs UUID vs Auto-Increment

| Approach | Globally Unique | Time-Ordered | Distributed | Size |
|----------|----------------|-------------|-------------|------|
| **Snowflake** (chosen) | ✅ (machine ID prevents collision) | ✅ (41-bit ms timestamp prefix) | ✅ (no central coordinator) | 64-bit int — small |
| UUID v4 | ✅ (probabilistic) | ❌ (random) | ✅ | 128-bit — 2× larger |
| Auto-increment (MySQL sequence) | ✅ (single source) | ✅ | ❌ (single point of failure) | 64-bit int |
| ULID | ✅ | ✅ (48-bit ms prefix) | ✅ | 128-bit (26-char string) |

**Why time-ordering matters**: The timeline cache uses Redis Sorted Sets with `tweet_id` as the score (not a separate `created_at` timestamp). Snowflake IDs are monotonically increasing with time, so `ZADD timeline:{user_id} {tweet_id} {tweet_id}` uses the ID itself as the sort key — zero extra storage for timestamps, and `ZREVRANGE` returns tweets newest-first. UUID v4 would require a separate timestamp score, doubling the sorted set overhead.

**Why not auto-increment?** At 5,800 tweets/sec across 100 Tweet Service nodes, a centralized sequence generator becomes a bottleneck and single point of failure. Twitter learned this from their MySQL era — Snowflake was built specifically to eliminate this SPOF.

**Interview insight**: Be ready to decode a Snowflake ID on the whiteboard: `tweet_id >> 22 + TWITTER_EPOCH_MS = creation timestamp`. Demonstrates deep understanding vs just naming it.

---

### 7.3 Tweet Storage: Cassandra vs PostgreSQL vs DynamoDB

```
Access patterns analysis:
  Pattern A: Point lookup by tweet_id         → any store handles this
  Pattern B: "Last 20 tweets by user" (profile page) → needs time-ordered range scan by user_id
  Pattern C: Write 5,800 tweets/sec, immutable after write
  Pattern D: Delete tweet (is_deleted flag)    → soft delete only

PostgreSQL analysis:
  5 years × 500M tweets/day = 912.5 billion rows
  PostgreSQL single node max: ~500M rows before performance degrades
  Even with sharding: COUNTER types (like_count) require row-level locking
  Sequential writes (5,800/sec): PostgreSQL WAL handles this, but fan-out
    writes (870K/sec) to denormalized tables → WAL I/O saturation
  ❌ Rejects at scale for high-throughput write path

DynamoDB analysis:
  Partition key: tweet_id → handles Pattern A perfectly
  Pattern B ("last 20 by user"): requires GSI on user_id
    912.5B rows × GSI overhead ≈ $500K+/month at this scale
  COUNTER type: DynamoDB has no native counter → application-level CAS
  ❌ Expensive for range queries; no native counters

Cassandra analysis:
  912.5B rows: Cassandra designed for this (petabyte-scale in production)
  user_tweets table: (user_id partition, tweet_id DESC clustering) → O(1) range scan
  COUNTER type: native — like_count INCR is atomic, no app-layer CAS
  Write throughput: ~1M writes/sec per cluster (matches 870K fan-out writes/sec)
  Immutable records: perfect for Cassandra's append-optimized LSM tree
  ✅ All patterns satisfied
```

**ADR**: Cassandra for tweet storage (tweets table + user_tweets denormalized table). Accept eventual consistency (RF=3, LOCAL_ONE reads) because stale tweet data by milliseconds is tolerable — you can't tell if a tweet was written 50ms ago vs 150ms ago.

---

### 7.4 Timeline Cache: Redis Sorted Set vs Time-Series DB vs Materialized View

| Option | Read Latency | Write Latency | Memory Cost | Eviction |
|--------|-------------|--------------|-------------|---------|
| **Redis Sorted Set** (chosen) | Sub-ms ZREVRANGE | Sub-ms ZADD | 1.28 TB (200M users) | ZREMRANGEBYRANK on fan-out |
| TimescaleDB | ~5ms | ~5ms | Disk (cheap) | Time-based partitioning |
| PostgreSQL materialized view | Refresh lag (seconds) | Background refresh | Disk | Manual REFRESH MATERIALIZED VIEW |
| Application-level sort on read | O(following × tweets) | None | None | N/A |

**Redis Sorted Set fit analysis**:
- Score = tweet_id (Snowflake, encodes timestamp) → no separate timestamp field
- ZREVRANGE O(log N + K) where K=20 returned elements → effectively O(1) for fixed K
- ZADD O(log N) where N=800 max elements per timeline → ~10 comparisons
- Pipeline 870K ZADDs/sec across Redis cluster: 1M ops/sec per node × 10 nodes = fine
- Memory: 200M users × 800 entries × 8 bytes = 1.28 TB → 20 nodes × 64GB each

**Why not TimescaleDB?** 5ms latency × 58,000 timeline reads/sec = 290K concurrent queries. Even with connection pooling, TimescaleDB's query execution overhead for this read volume would require 500+ nodes. Redis keeps it at 20 nodes.

**Inactive user optimization**: Only maintain Redis timelines for users active within 7 days (~200M of 500M total). Cold-start rebuild job populates timeline on first login after 7+ days offline. This halves Redis cluster size requirement.

---

### 7.5 Trending Topics: Count-Min Sketch vs Exact Counter vs Top-K Heap

```
Problem constraints:
  500M tweets/day with arbitrary hashtags (unbounded cardinality)
  Need top-10 trending in real-time (≤1 min lag)
  30-minute sliding window with time decay

Exact counter approach:
  HashMap<hashtag, count> — updated in Flink for each tweet
  Problem: unbounded cardinality — at 5,800 tweets/sec with avg 1.5 hashtags/tweet,
    unique hashtags in 30-min window: ~2M distinct hashtags
    At 8 bytes (key pointer) + 48 bytes (string avg) + 8 bytes (count) = 64 bytes/entry
    2M × 64 bytes = 128 MB state per Flink task — manageable!
  But: merging across 100 Flink tasks = 200M merge operations per window
  ❌ State explosion at partitioned parallelism level

Count-Min Sketch approach (chosen):
  d=5 rows × w=100,000 columns × 8 bytes = 4 MB state per task (vs 128 MB)
  Overcount error: ε = e/w = 2.718/100,000 = 0.00003 (0.003% overcounting)
  With 95% probability, error ≤ ε × total_events
  30-min window events: 5,800 tweets/sec × 60s × 30 = 10.44M events
  Max error: 0.00003 × 10.44M ≈ 313 counts — negligible for trending detection
  Merge cost across tasks: 5 × 100,000 = 500K additions (vs 200M for exact)
  ✅ 99% state reduction, trivially mergeable across partitions

Top-K heap approach:
  Min-heap of size 50 per Flink task — tracks top-50 hashtags by CMS estimate
  Updated every 60 seconds → merged across tasks → Redis ZADD
  Redis top-10 read: ZREVRANGE trending:global 0 9 → O(log N)
```

**Time decay implementation**: `score = count × exp(-0.5 × age_hours)`. At 2h age: 60.7% weight. At 4h: 36.8%. This prevents yesterday's #WorldCup from staying trending after the match ends. Implemented as multiplier in Flink score computation before heap insertion.

---

### 7.6 Follow Graph Sharding: Single Table vs Dual Table vs Graph DB

```
Access patterns require two opposite traversals:
  "Who does user A follow?" → for fan-out, timeline building
  "Who follows user A?" → for notification, count display, reverse fan-out

Option A: Single table sharded by follower_id
  covering: FOLLOWING(follower_id PK, followee_id)
  "Who follows A?" → scatter-gather across all shards → O(shards × scan)
  At 1,000 shards: 1,000 parallel queries to find A's followers
  Result merge: up to 100M follower_ids for celebrity → 800MB of data
  ❌ Reverse lookup catastrophically slow for celebrities

Option B: Dual denormalized tables (chosen)
  FOLLOWING(follower_id, followee_id) — sharded by follower_id
  FOLLOWERS(followee_id, follower_id) — sharded by followee_id
  Write: every follow/unfollow updates BOTH tables (dual write in transaction)
  "Who does A follow?": single shard in FOLLOWING table → O(1)
  "Who follows A?": single shard in FOLLOWERS table → O(1)
  Cost: 2× write amplification (acceptable — follows are rare vs reads)
  ✅ Both traversals are O(1) at query time

Option C: Graph database (Neo4j, JanusGraph)
  Natural model for graph traversal (2nd-degree connections)
  Scale: Neo4j Enterprise tops out at ~50B edges — Twitter has 100B edges
  Sharding graph DBs is extremely complex; no native horizontal scaling
  ❌ Scale ceiling; Netflix/Twitter both migrated away from graph DBs
```

**Dual-write consistency**: Follow Service uses a saga pattern for the dual write. If FOLLOWING write succeeds but FOLLOWERS write fails, a retry job (polling a follow_events Cassandra table) reconciles within 30 seconds. Inconsistency window: ~30s. Impact: fan-out might miss a new follower for 30s — fully acceptable.

---

### 7.7 Like Counts: Exact vs Approximate vs Redis Counter

```
Scale: 500M DAU, avg 10 likes/user/day = 5B like events/day
  = 57,870 like events/sec average
  Viral tweet like storm: up to 500K likes/sec on a single tweet

Option A: PostgreSQL UPDATE counter per like (row lock):
  UPDATE tweets SET like_count = like_count + 1 WHERE id = ?
  Row-level lock → serialized updates → max ~5K updates/sec per row
  At 500K likes/sec on viral tweet: 99.9% of requests queue → timeout
  ❌ Deadlock and contention at viral scale

Option B: Redis INCR (chosen):
  INCR tweet:{id}:likes → atomic, O(1), no locks
  Redis handles 1M+ INCR/sec per node on a single key
  Async batch flush to Cassandra every 60 seconds
  Risk: Redis crash → up to 60s of like counts lost
  Mitigation: Redis AOF persistence (flush every second) → max 1s loss
  ✅ Handles 500K likes/sec; 1s data loss acceptable for engagement metrics

Option C: Sharded counter (multiple Redis keys):
  tweet:{id}:likes:shard:{0-15} → 16 shards
  INCR on random shard → read: SUM of all 16 shards
  Use when: single Redis node is a bottleneck (>1M likes/sec per tweet)
  At 500K likes/sec: single Redis node fine (100× headroom)
  ✅ Available as scaling escape hatch if viral tweet exceeds 1M likes/sec

Option D: Approximate count (HyperLogLog):
  Good for unique users who liked (cardinality), not total count
  ❌ Like count needs exact total, not unique count
```

**Interview insight**: "Like count is eventually consistent — you might see 1,203,847 likes when the true count is 1,203,849. This ±2 error under race conditions is acceptable for social engagement metrics. The key principle: decouple the write-hot counter from the read path using Redis as a counter buffer."

---

### 7.8 Search Architecture: Elasticsearch vs Earlybird vs Algolia

| Option | Index freshness | Scale at 500M tweets/day | Custom ranking | Cost |
|--------|----------------|--------------------------|----------------|------|
| **Elasticsearch** (design choice) | <30s (pipeline lag) | 5-20 node cluster | Full BM25 + custom scoring | Moderate |
| **Earlybird** (Twitter actual) | <30s (optimized Lucene) | Twitter-scale (petabytes) | Full custom | Internal R&D |
| Algolia | <60s | Rate-limited at this volume | Limited | Very high |
| PostgreSQL full-text (pg_tsvector) | Synchronous | ~50M tweets max (1 node) | Limited | Low |

```
Elasticsearch index design for Twitter:
  Time-based sharding: 1 index per day of tweets
  - Recent 7 days (hot): in-memory, SSD-backed, 3 replicas
  - 8-30 days (warm): SSD-backed, 2 replicas
  - 30+ days (cold): S3-backed via searchable snapshots, 1 replica

  Why time sharding?
  - Search results dominated by recent tweets (recency signal weights heavily)
  - Can close/freeze old indices to save memory
  - Shard deletion for GDPR (delete a day's index = all tweets from that day gone)

  Query fan-out:
  "breaking news" → 7 hot indices × N shards each = parallel scatter
  → Each shard returns top-K → coordinate node merges → final top-50
  → Client sees: most recent, most relevant 50 results in <500ms
```

**Real-time indexing**: Kafka "tweet.created" → Elasticsearch bulk API (batch of 1,000 tweets per call, every 5 seconds). Bulk indexing throughput: 50,000 tweets/batch × 12 batches/min = 600,000 tweets/min capacity vs 288,000 actual tweets/min — 2× headroom.

---

### 7.9 Consistency Model Matrix

| Component | Model | Justification |
|-----------|-------|---------------|
| Tweet posting (write to Cassandra) | **Strong (LOCAL_QUORUM)** | Tweet must not be lost after 201 response; quorum ensures durability on 2/3 replicas |
| Timeline read (Redis cache) | **Eventual (seconds)** | Fan-out propagation takes 1–5s; user tolerates slight delay seeing a new tweet |
| Like/Retweet counts | **Approximate eventual (±2, 60s lag)** | Social engagement metrics don't need exactness; Redis batch flush to Cassandra |
| Follow/Unfollow | **Strong (read-your-writes)** | Unfollow must be immediately effective (safety/harassment use case) |
| Dual follow-graph tables | **Eventual (≤30s reconciliation)** | 30s window where fan-out might include a just-unfollowed person — acceptable |
| Search index | **Eventual (≤30s)** | New tweet searchable within 30s is acceptable |
| Trending topics | **Approximate eventual (1-min lag)** | Trending is best-effort; CMS error ≤0.003% of event volume |
| Notification delivery | **At-least-once** | Duplicate "Alice liked your tweet" notification is better than missed one |
| User profile data | **Strong (read-your-writes)** | Bio/avatar change must be immediately reflected to the user making the change |
| Media (CDN) | **Eventual (CDN TTL 7 days)** | Images are immutable; stale cache serves identical content — no consistency issue |

**Key insight for interviewers**: Twitter's consistency boundary falls at the **fan-out pipeline**. Before Kafka publication, tweet writes are strongly consistent (Cassandra LOCAL_QUORUM). After Kafka — everything downstream (timeline cache, search index, trending, notifications) is explicitly eventual. Articulating this pipeline-as-consistency-boundary is what distinguishes senior system design answers.

---

## 8. Data Models

### Tweet

```sql
-- Cassandra primary table
CREATE TABLE tweets (
    tweet_id     BIGINT     PRIMARY KEY,  -- Snowflake ID (encodes timestamp)
    user_id      BIGINT     NOT NULL,
    text         TEXT,
    media_ids    LIST<TEXT>,              -- references to media objects
    reply_to_id  BIGINT,                 -- NULL if not a reply
    retweet_of   BIGINT,                 -- NULL if not a retweet
    quote_of     BIGINT,                 -- NULL if not a quote
    lang         TEXT,
    source       TEXT,                   -- "Twitter for iPhone", "Web"
    like_count   COUNTER,
    retweet_count COUNTER,
    reply_count  COUNTER,
    is_deleted   BOOLEAN    DEFAULT false
);

-- User's tweet timeline (for profile page)
CREATE TABLE user_tweets (
    user_id      BIGINT,
    tweet_id     BIGINT,
    PRIMARY KEY  (user_id, tweet_id)
) WITH CLUSTERING ORDER BY (tweet_id DESC);
```

### User

```sql
-- PostgreSQL (structured, needs rich queries)
CREATE TABLE users (
    user_id      BIGINT       PRIMARY KEY,   -- Snowflake ID
    username     VARCHAR(15)  UNIQUE NOT NULL,
    display_name VARCHAR(50),
    bio          VARCHAR(160),
    profile_img  TEXT,                        -- CDN URL
    header_img   TEXT,
    location     VARCHAR(30),
    website      TEXT,
    verified     BOOLEAN      DEFAULT false,
    followers_count  INTEGER  DEFAULT 0,
    following_count  INTEGER  DEFAULT 0,
    tweets_count     INTEGER  DEFAULT 0,
    is_protected     BOOLEAN  DEFAULT false,  -- protected account
    created_at   TIMESTAMPTZ  DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_users_username ON users (LOWER(username));
```

### Follow Graph

```sql
-- Cassandra (sharded by follower_id for "who do I follow?" queries)
CREATE TABLE following (
    follower_id  BIGINT,
    followee_id  BIGINT,
    created_at   TIMESTAMP,
    PRIMARY KEY  (follower_id, followee_id)
);

-- Cassandra (sharded by followee_id for "who follows me?" queries)
CREATE TABLE followers (
    followee_id  BIGINT,
    follower_id  BIGINT,
    created_at   TIMESTAMP,
    PRIMARY KEY  (followee_id, follower_id)
);
```

### Like

```sql
-- Cassandra (high write volume — 500M likes/day)
CREATE TABLE likes (
    tweet_id    BIGINT,
    user_id     BIGINT,
    created_at  TIMESTAMP,
    PRIMARY KEY (tweet_id, user_id)
);

-- For "tweets liked by user" (profile page → liked tab)
CREATE TABLE user_likes (
    user_id     BIGINT,
    tweet_id    BIGINT,
    created_at  TIMESTAMP,
    PRIMARY KEY (user_id, tweet_id)
) WITH CLUSTERING ORDER BY (tweet_id DESC);
```

---

## 9. Follow-Up Questions

### Q1: How do you handle a tweet going viral (10M likes in 1 hour)?

```
Problem: A viral tweet creates a hot key in Cassandra and Redis.

Mitigation layers:

1. Like counter: Redis INCR tweet:{id}:likes (atomic, O(1))
   → Don't update Cassandra on every like — batch flush every 60 seconds
   → Redis handles 1M+ INCR/sec easily

2. Tweet object hot key:
   → Redis handles 100K reads/sec on a single key natively
   → If needed: replicate hot tweet to multiple Redis keys
     tweet:{id}:replica:{0-9} — route reads round-robin across replicas
   → Application-level read fan-out

3. Fan-out storm prevention:
   → If a normal user goes viral (post hits 1M reposts):
     Dynamically reclassify to "celebrity" mode
     Stop fan-out, switch to read-time merge for new followers

4. CDN absorbs media load:
   → Viral tweet image: CloudFront serves from 300+ edge nodes
   → Origin (S3) sees at most 0.01% of actual request volume
```

### Q2: How do you implement "Who to follow" recommendations?

```
Approach: Graph-based collaborative filtering

"Friends of friends" algorithm:
  You follow A and B.
  A and B both follow C.
  → Recommend C to you (2nd-degree connection)

Implementation:
  Offline batch job (Spark GraphX, runs hourly):
    1. Load follow graph from Cassandra
    2. For each user U: find 2nd-degree connections
       2nd_degree[U] = { v : v ∈ ∪(following[w]) for w ∈ following[U] }
                         minus already followed users
    3. Score by: intersection count × followee engagement score
    4. Top-10 per user → Redis SET reco:follow:{user_id} [user_ids]

Real-time signals boost:
  Just followed A → Flink reads A's top-10 followees → boost in recommendations
  → Updates Redis within 30 seconds of follow action

Similar interests:
  If your tweet engagement is high on tech topics:
    → Boost tech accounts in recommendations
  Implemented via topic embedding similarity (offline ML model)
```

### Q3: How do you handle tweet deletion (GDPR / user delete)?

```
Tweet deletion must be:
  1. Soft delete in Cassandra: SET is_deleted = true
  2. Hard delete from search index (Elasticsearch delete by ID)
  3. Remove from all followers' timeline caches:
     → Cannot iterate all followers (100M for celebrities)
     → Solution: lazy deletion — check is_deleted at read time, filter client-side
     → CDN: create_invalidation for tweet media URLs

For GDPR "right to be forgotten":
  1. Anonymize tweet author: user_id → anonymous_id
  2. Delete all personal data from users table
  3. Elasticsearch: delete all documents where user_id = X
  4. Follow graph: delete all following/followers records
  5. Timeline caches: let them expire naturally (TTL) — tweet content served
     from Cassandra will return "deleted" flag → filtered client-side

Timeline: 30 days to complete deletion (legal requirement met)
```

### Q4: How do you scale for Super Bowl / breaking news (sudden 100× spike)?

```
Pre-event scaling (predictable events like Super Bowl):
  - Capacity planning: scale to 5× normal 2 hours before kickoff
  - Pre-warm CDN caches for team hashtags, player profiles
  - Disable non-essential features (recommendations, suggestions) under extreme load

Real-time auto-scaling:
  Fan-out workers: Kubernetes HPA on Kafka consumer lag
    If lag > 10,000 messages → add fan-out workers
    At 100× spike: 1,000 fan-out workers × 4 threads each

Graceful degradation ladder:
  100% load: full feature set
  150% load: disable recommendations, trending update interval → 5 min
  200% load: serve stale timelines (cache-only, no DB fallback)
  300% load: rate limit non-authenticated reads, show simplified timeline
  500% load: emergency read-only mode, freeze timeline at last cache state

Real example: Bin Laden announcement (2011) — Twitter "Fail Whale" era
  Modern Twitter handles 140,000 tweets/sec on breaking news
```

### Q5: How do you prevent spam / abuse at scale?

```
Multi-layer defense:

Layer 1 — Rate limiting (API Gateway):
  Tweet creation: 300 per 3 hours per user
  Follow: 400 per day per user (Twitter limit)
  Like: 1,000 per day
  Implemented: Redis sliding window counter per (user_id, action, window)

Layer 2 — Spam detection (ML, async):
  Every tweet → Kafka → ML Spam Classifier
  Features: text patterns, URL domains, account age, follower/following ratio,
            tweet velocity, device fingerprint
  If spam score > 0.9: soft-delete tweet, flag account
  → 99.7% precision (< 0.3% false positive rate)

Layer 3 — Captcha / SMS verification:
  New accounts: verify phone number before 1st tweet
  Anomalous behavior: step-up to CAPTCHA

Layer 4 — Network analysis:
  Coordinated inauthentic behavior:
    Graph clustering: accounts that follow each other in bulk and post same content
    Detected by: similar tweet timing, duplicate content fingerprints
    → Suspend account cluster

Layer 5 — User reporting:
  "Report tweet" → human review queue
  High-volume reports → auto-escalate to Trust & Safety team
```

### Q6: How do you design the algorithm timeline (non-chronological)?

```
Twitter's "For You" timeline (algorithmic):

Architecture: Two-tower neural network (similar to YouTube recommendations)
  Tower 1: User embedding (who you are, what you engage with)
  Tower 2: Tweet embedding (what the tweet is about, its virality)
  Score: dot product of the two embedding vectors

Feature inputs for tweet scoring:
  - Recency (exponential decay)
  - Author's relationship to you (following → 2× boost)
  - Your historical engagement with this author
  - Tweet engagement velocity (likes/hr in first 30 min)
  - Topic affinity (do you engage with tech tweets? boost tech)
  - Media type preference (do you stop on videos? boost videos)

Serving:
  Candidate generation: ANN retrieval from FAISS (top 1,500 candidates)
  Heavy ranker: ML model scores all 1,500 → top 500
  Rule-based filters: remove seen tweets, blocked users, spam
  Diversity: at most 2 consecutive tweets from same author
  Final: top 20 served to user

Offline training: daily Spark ML job on 90-day engagement history
Online update: real-time feature updates via Kafka → Redis feature store
```

---

## 10. Interview Summary Card

### Time Allocation (45 min)

| Minute | Action |
|--------|--------|
| 0–5 | Clarifying questions, agree on scope |
| 5–10 | Requirements (functional + non-functional) |
| 10–15 | Back-of-envelope (QPS, storage, fan-out math) |
| 15–20 | High-level diagram + core request flows |
| 20–35 | Deep dive: Fan-out on write/read hybrid (most time here) |
| 35–45 | 2nd deep dive (search/trending, or celebrity problem details) |

### The Central Design Decision

```
Fan-out strategy is THE key decision — make it explicit:

"The hardest problem in Twitter's design is fan-out.
 We have 5,800 tweets/sec and users with up to 100M followers.
 Pure fan-out on write: 870K Redis writes/sec — manageable for normal users
                         but 100M writes for Katy Perry's single tweet
 Pure fan-out on read:  200 Cassandra queries per timeline load — too slow
 Hybrid (my recommendation):
   Normal users (<1M followers): fan-out on write
   Celebrities (≥1M followers):  fan-out on read, merged at query time
 This brings celebrity writes to zero fan-out cost while keeping
 read timeline latency under 20ms."
```

### Key Numbers

```
500M DAU, 500M tweets/day
5,800 tweets/sec write; 58,000 timeline reads/sec
870K Redis fan-out writes/sec (after hybrid optimization)
1.28 TB Redis timeline cache (200M active users)
912.5B tweets over 5 years = 456 TB tweet storage
Media: 185 TB/day → 67.5 PB/year (CDN essential)
```

### Technology Choices

| Component | Technology | Why |
|-----------|-----------|-----|
| Tweet storage | Cassandra | High write throughput, immutable records, time-series |
| Timeline cache | Redis Sorted Set | O(log N) inserts, O(1) range reads, TTL |
| Follow graph | Cassandra (adjacency list) | Sharded key-value, 100B edges |
| Search | Elasticsearch (Earlybird) | Inverted index, near-real-time, distributed |
| Fan-out pipeline | Kafka | Durable, replay, 1,000-partition parallelism |
| Media storage | S3 + CloudFront | Petabyte scale, global CDN, lifecycle rules |
| Trending | Count-Min Sketch + Flink | O(1) space, streaming aggregation |
| Notifications | Kafka → FCM/APNs | Async, fan-out, device-specific routing |
| IDs | Snowflake (64-bit) | Time-ordered, distributed, no coordination |

### Trade-Offs to Articulate

```
Consistency vs Latency:
  Timeline: eventual consistency (tweet appears within 5 seconds) — acceptable
  Like count: approximate (±2 under race) — acceptable for social signals
  Follow: strongly consistent (unfollow must be immediate — safety feature)

Write vs Read amplification:
  Fan-out on write: amplifies writes (1 tweet → 200 Redis writes)
                    but reads are O(1) → latency win
  Fan-out on read:  no write amplification but reads are O(following_count)
  Hybrid: best of both for practical workloads

CAP: AP system overall (availability > consistency for social use cases)
     Exception: payment (if Twitter Blue subscription) → CP
```

---

*co-authored-by: wibey jetbrains plugin (wibey.walmart.com/code)*
