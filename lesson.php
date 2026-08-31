<?php
// ── Shared roadmap data (single source of truth) ─────────────────────────────
$roadmap = [
    ['id'=>1,'title'=>'Getting Started','icon'=>'🚀','tag'=>'Introduction','lessons'=>2,'items'=>[
        ['title'=>'Course Overview','type'=>'lesson','duration'=>'5 min'],
        ['title'=>'How to Use This Course','type'=>'lesson','duration'=>'3 min'],
    ]],
    ['id'=>2,'title'=>'System Design Basics','icon'=>'⚙️','tag'=>'Fundamentals','lessons'=>3,'items'=>[
        ['title'=>'What Is System Design?','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'How to Approach a System Design Interview','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Quiz: System Design Basics','type'=>'quiz','duration'=>'5 min'],
    ]],
    ['id'=>3,'title'=>'Key Characteristics of Distributed Systems','icon'=>'🌐','tag'=>'Fundamentals','lessons'=>3,'items'=>[
        ['title'=>'Scalability','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Reliability & Availability','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Efficiency & Manageability','type'=>'lesson','duration'=>'6 min'],
    ]],
    ['id'=>4,'title'=>'Load Balancing','icon'=>'⚖️','tag'=>'Fundamentals','lessons'=>3,'items'=>[
        ['title'=>'What Is Load Balancing?','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Load Balancing Algorithms','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Redundant Load Balancers','type'=>'lesson','duration'=>'5 min'],
    ]],
    ['id'=>5,'title'=>'Caching','icon'=>'⚡','tag'=>'Fundamentals','lessons'=>4,'items'=>[
        ['title'=>'Introduction to Caching','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Cache Invalidation Strategies','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Cache Eviction Policies (LRU, LFU, FIFO)','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Quiz: Caching','type'=>'quiz','duration'=>'5 min'],
    ]],
    ['id'=>6,'title'=>'Data Partitioning','icon'=>'🗄️','tag'=>'Fundamentals','lessons'=>3,'items'=>[
        ['title'=>'Horizontal vs Vertical Partitioning','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Partitioning Methods (Range, Hash, Directory)','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Partitioning Criteria & Common Problems','type'=>'lesson','duration'=>'7 min'],
    ]],
    ['id'=>7,'title'=>'Indexes','icon'=>'📇','tag'=>'Fundamentals','lessons'=>2,'items'=>[
        ['title'=>'Database Indexes — How & When','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Dense vs Sparse Indexes','type'=>'lesson','duration'=>'5 min'],
    ]],
    ['id'=>8,'title'=>'Proxies','icon'=>'🔀','tag'=>'Fundamentals','lessons'=>2,'items'=>[
        ['title'=>'Forward vs Reverse Proxies','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'Proxy Use Cases & Caching','type'=>'lesson','duration'=>'6 min'],
    ]],
    ['id'=>9,'title'=>'Redundancy & Replication','icon'=>'🔁','tag'=>'Fundamentals','lessons'=>2,'items'=>[
        ['title'=>'Redundancy — Eliminating Single Points of Failure','type'=>'lesson','duration'=>'6 min'],
        ['title'=>'Replication — Active-Passive & Active-Active','type'=>'lesson','duration'=>'8 min'],
    ]],
    ['id'=>10,'title'=>'SQL vs NoSQL','icon'=>'🗃️','tag'=>'Advanced Concepts','lessons'=>3,'items'=>[
        ['title'=>'Relational vs Non-Relational Databases','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Types of NoSQL (Document, Key-Value, Column, Graph)','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Which to Choose — Decision Framework','type'=>'lesson','duration'=>'7 min'],
    ]],
    ['id'=>11,'title'=>'CAP Theorem','icon'=>'🔺','tag'=>'Advanced Concepts','lessons'=>2,'items'=>[
        ['title'=>'Consistency, Availability, Partition Tolerance','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Real-World CAP Trade-offs','type'=>'lesson','duration'=>'6 min'],
    ]],
    ['id'=>12,'title'=>'Consistent Hashing','icon'=>'🔵','tag'=>'Advanced Concepts','lessons'=>2,'items'=>[
        ['title'=>'The Problem with Naive Hashing','type'=>'lesson','duration'=>'6 min'],
        ['title'=>'Consistent Hash Ring & Virtual Nodes','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>13,'title'=>'Long-Polling, WebSockets & SSE','icon'=>'📡','tag'=>'Advanced Concepts','lessons'=>3,'items'=>[
        ['title'=>'AJAX Polling vs Long-Polling','type'=>'lesson','duration'=>'6 min'],
        ['title'=>'WebSockets — Full-Duplex Communication','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Server-Sent Events (SSE)','type'=>'lesson','duration'=>'5 min'],
    ]],
    ['id'=>14,'title'=>'Bloom Filters & Probabilistic DS','icon'=>'🌸','tag'=>'Advanced Concepts','lessons'=>2,'items'=>[
        ['title'=>'Bloom Filters — Space-Efficient Membership','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Count-Min Sketch & HyperLogLog','type'=>'lesson','duration'=>'7 min'],
    ]],
    ['id'=>15,'title'=>'Quorum & Leader Election','icon'=>'🗳️','tag'=>'Advanced Concepts','lessons'=>2,'items'=>[
        ['title'=>'Quorum-Based Writes and Reads','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Leader Election with Zookeeper / etcd','type'=>'lesson','duration'=>'8 min'],
    ]],
    ['id'=>16,'title'=>'Design a URL Shortener (TinyURL)','icon'=>'🔗','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level Design & API','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Key Generation & Encoding','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — DB Partitioning & Caching','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>17,'title'=>'Design Pastebin','icon'=>'📋','tag'=>'Design Problems','lessons'=>3,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'6 min'],
        ['title'=>'System Design & Component Design','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Data Partitioning & Purging','type'=>'lesson','duration'=>'8 min'],
    ]],
    ['id'=>18,'title'=>'Design Instagram','icon'=>'📷','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level System Design','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Database Schema & Photo Uploads','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — News Feed & Ranking','type'=>'lesson','duration'=>'12 min'],
    ]],
    ['id'=>19,'title'=>'Design Dropbox','icon'=>'💾','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'High-Level Design & Client Architecture','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Block Storage & Chunking','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Metadata DB & Sync Service','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>20,'title'=>'Design Facebook Messenger','icon'=>'💬','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level Design & WebSocket Architecture','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Message Storage & Ordering','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Group Chat & Presence','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>21,'title'=>'Design Twitter','icon'=>'🐦','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level Design & Tweet Storage','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Timeline Generation (Fan-out)','type'=>'lesson','duration'=>'14 min'],
        ['title'=>'Deep Dive — Search, Replication & Sharding','type'=>'lesson','duration'=>'12 min'],
    ]],
    ['id'=>22,'title'=>'Design YouTube / Netflix','icon'=>'🎬','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level Design & Video Upload Pipeline','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Video Encoding & Adaptive Bitrate','type'=>'lesson','duration'=>'14 min'],
        ['title'=>'Deep Dive — CDN & Metadata Management','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>23,'title'=>'Design Typeahead Suggestion','icon'=>'🔍','tag'=>'Design Problems','lessons'=>3,'items'=>[
        ['title'=>'Requirements & High-Level Design','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Deep Dive — Trie Data Structure','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Distributed Trie & Ranking','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>24,'title'=>'Design an API Rate Limiter','icon'=>'🚦','tag'=>'Design Problems','lessons'=>3,'items'=>[
        ['title'=>'Requirements & Rate Limiting Algorithms','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'High-Level Design & Redis Implementation','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Distributed Rate Limiting','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>25,'title'=>'Design Twitter Search','icon'=>'🔎','tag'=>'Design Problems','lessons'=>3,'items'=>[
        ['title'=>'Requirements & Storage Estimation','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'Deep Dive — Inverted Index & Sharding','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Fault Tolerance & Replication','type'=>'lesson','duration'=>'8 min'],
    ]],
    ['id'=>26,'title'=>'Design a Web Crawler','icon'=>'🕷️','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'High-Level Design & URL Frontier','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Politeness & BFS vs DFS','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Deduplication & Storage','type'=>'lesson','duration'=>'8 min'],
    ]],
    ['id'=>27,'title'=>'Design Facebook Newsfeed','icon'=>'📰','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'High-Level Design & Feed Generation','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Feed Publishing & Fan-out','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Feed Ranking Algorithm','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>28,'title'=>'Design Yelp / Nearby Friends','icon'=>'📍','tag'=>'Design Problems','lessons'=>3,'items'=>[
        ['title'=>'Requirements & Location-Based Search','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'Deep Dive — QuadTree & Geospatial Index','type'=>'lesson','duration'=>'14 min'],
        ['title'=>'Deep Dive — Ranking, Sharding & Replication','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>29,'title'=>'Design Uber Backend','icon'=>'🚗','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'8 min'],
        ['title'=>'High-Level Design & Driver Location Updates','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Ride Matching & Dispatch','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Surge Pricing & Analytics','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>30,'title'=>'Design Ticketmaster','icon'=>'🎟️','tag'=>'Design Problems','lessons'=>4,'items'=>[
        ['title'=>'Requirements & Estimations','type'=>'lesson','duration'=>'7 min'],
        ['title'=>'High-Level Design & Seat Reservation Flow','type'=>'lesson','duration'=>'10 min'],
        ['title'=>'Deep Dive — Concurrency & Distributed Lock','type'=>'lesson','duration'=>'12 min'],
        ['title'=>'Deep Dive — Waiting Room & Flash Sale','type'=>'lesson','duration'=>'10 min'],
    ]],
    ['id'=>31,'title'=>'Additional System Design Questions','icon'=>'📚','tag'=>'Bonus','lessons'=>2,'items'=>[
        ['title'=>'Design Google Docs (Collaborative Editing)','type'=>'lesson','duration'=>'14 min'],
        ['title'=>'Design a Distributed Message Queue (Kafka)','type'=>'lesson','duration'=>'14 min'],
    ]],
];

// ── Resolve chapter and lesson from query params ──────────────────────────────
$chapterId  = (int)($_GET['c'] ?? 0);
$lessonIdx  = (int)($_GET['l'] ?? 0);

// Flat chapter lookup
$chapter    = null;
$chapterPos = 0;
foreach ($roadmap as $ci => $ch) {
    if ($ch['id'] === $chapterId) { $chapter = $ch; $chapterPos = $ci; break; }
}

if (!$chapter || !isset($chapter['items'][$lessonIdx])) {
    http_response_code(404);
    die('<h1 style="font-family:sans-serif;padding:2rem">Lesson not found. <a href="index.php">← Back to roadmap</a></h1>');
}

$lesson     = $chapter['items'][$lessonIdx];
$totalItems = count($chapter['items']);

// ── Prev / Next navigation ────────────────────────────────────────────────────
$prevUrl = $nextUrl = null;
if ($lessonIdx > 0) {
    $prevUrl = "lesson.php?c={$chapterId}&l=" . ($lessonIdx - 1);
} elseif ($chapterPos > 0) {
    $prevCh  = $roadmap[$chapterPos - 1];
    $prevUrl = "lesson.php?c={$prevCh['id']}&l=" . (count($prevCh['items']) - 1);
}
if ($lessonIdx < $totalItems - 1) {
    $nextUrl = "lesson.php?c={$chapterId}&l=" . ($lessonIdx + 1);
} elseif ($chapterPos < count($roadmap) - 1) {
    $nextCh  = $roadmap[$chapterPos + 1];
    $nextUrl = "lesson.php?c={$nextCh['id']}&l=0";
}

// ── Tag colours ───────────────────────────────────────────────────────────────
$tagColors = [
    'Introduction'     => ['bg'=>'#EEF2FF','text'=>'#4338CA','border'=>'#C7D2FE'],
    'Fundamentals'     => ['bg'=>'#F0FDF4','text'=>'#16A34A','border'=>'#BBF7D0'],
    'Advanced Concepts'=> ['bg'=>'#FFF7ED','text'=>'#C2410C','border'=>'#FED7AA'],
    'Design Problems'  => ['bg'=>'#EFF6FF','text'=>'#1D4ED8','border'=>'#BFDBFE'],
    'Bonus'            => ['bg'=>'#FDF4FF','text'=>'#7E22CE','border'=>'#E9D5FF'],
];
$tc = $tagColors[$chapter['tag']] ?? ['bg'=>'#F1F5F9','text'=>'#475569','border'=>'#CBD5E1'];

// ── Lesson content library ────────────────────────────────────────────────────
// Keyed by "chapter_id:lesson_index" → ['objectives'=>[], 'body'=>'']
if (!function_exists('lessonContent')) {
function lessonContent(int $c, int $l): array {
    $lib = [

    // ── Getting Started ──────────────────────────────────────────────────────
    '1:0' => [
        'objectives' => ['Understand what this course covers','Know the interview format at FAANG companies','Set up your learning schedule'],
        'body' => <<<MD
## Welcome

This course prepares you for **system design interviews** at top technology companies — Google, Amazon, Meta, Apple, Microsoft, Netflix, Uber, and Stripe.

System design interviews are **open-ended**: there is no single correct answer. The goal is to demonstrate structured thinking, awareness of tradeoffs, and the ability to communicate complex ideas clearly.

## What You Will Learn

| Module | Topics |
|--------|--------|
| Fundamentals | Caching, Load Balancing, Replication, Sharding |
| Advanced | CAP Theorem, Consistent Hashing, CRDT |
| Design Problems | 15 end-to-end system designs modelled on real interviews |

## How the Interview Works

A typical 45-minute system design interview follows this structure:

1. **Clarify requirements** (5 min) — functional and non-functional
2. **Back-of-envelope estimation** (5 min) — scale, storage, QPS
3. **High-level design** (15 min) — major components and data flow
4. **Deep dive** (15 min) — interviewer picks an area to explore
5. **Wrap-up** (5 min) — bottlenecks, monitoring, future improvements

> 💡 **Pro tip:** Drive the conversation. Interviewers want to see you ask the right questions, not wait to be led.
MD,
    ],

    '1:1' => [
        'objectives' => ['Navigate course content effectively','Use the built-in code playground','Track your progress'],
        'body' => <<<MD
## Getting the Most From This Course

### Recommended Learning Path

```
Week 1  →  Chapters 1–9   (Fundamentals)
Week 2  →  Chapters 10–15 (Advanced Concepts)
Week 3  →  Chapters 16–23 (Design Problems: Part 1)
Week 4  →  Chapters 24–31 (Design Problems: Part 2)
```

### Active Learning Tips

- **Take notes** on each tradeoff decision — interviewers love "it depends"
- **Draw diagrams** before reading the solution — forces independent thinking  
- **Time yourself** — practice fitting a full design into 45 minutes
- **Teach it back** — explain each design to a colleague or rubber duck

### After Each Design Problem

Ask yourself these questions:

1. What is the **single biggest bottleneck**?
2. How does the system **behave under failure**?
3. What **database** would I choose and why?
4. How does this scale to **10× current load**?
MD,
    ],

    // ── System Design Basics ─────────────────────────────────────────────────
    '2:0' => [
        'objectives' => ['Define system design','Understand why it matters in interviews','Identify the key pillars of any distributed system'],
        'body' => <<<MD
## What Is System Design?

System design is the process of defining the **architecture, components, modules, interfaces, and data** of a system to satisfy specified requirements.

In the context of software engineering, it typically means designing large-scale distributed systems that serve millions of users.

## Why It Matters

| Interview Signal | What It Shows |
|-----------------|---------------|
| Structured approach | Senior engineering thinking |
| Tradeoff awareness | Real-world experience |
| Communication clarity | Leadership potential |
| Scalability intuition | Understanding of distributed systems |

## The Four Pillars

```
┌─────────────────────────────────────────────────┐
│                 ANY SYSTEM                       │
│                                                  │
│   Reliability  ──  Scalability                   │
│        │                │                        │
│   Availability  ──  Efficiency                   │
└─────────────────────────────────────────────────┘
```

**Reliability** — System performs correctly even under faults  
**Scalability** — System handles growing load gracefully  
**Availability** — System is operational when users need it  
**Efficiency** — System uses resources optimally (latency, throughput)
MD,
    ],

    // ── Key Characteristics ──────────────────────────────────────────────────
    '3:0' => [
        'objectives' => [
            'Distinguish vertical from horizontal scaling and know when to use each',
            'Design stateless services that can scale horizontally without data loss',
            'Apply auto-scaling strategies and identify the bottleneck before adding capacity',
        ],
        'body' => <<<MD
## Scalability

Scalability is the ability of a system to **handle growing load** — more users, more data, more requests — without degrading performance or requiring a complete redesign.

> 💡 **Interview framing:** Before jumping to solutions, always ask: *Where is the bottleneck?* CPU? Memory? I/O? Network? Adding more servers cannot fix a single-threaded database lock.

## Vertical vs Horizontal Scaling

| | Vertical (Scale Up) | Horizontal (Scale Out) |
|-|---------------------|------------------------|
| **What** | Bigger machine (more CPU, RAM) | More machines |
| **Limit** | Physical ceiling (~192-core servers) | Near-infinite |
| **Cost** | Exponential — doubling specs costs 4× | Linear |
| **Failure risk** | Single point of failure | Graceful degradation |
| **Complexity** | Simple — no code changes | Requires stateless design |
| **Latency** | Lower (in-process) | Network hop added |
| **Databases** | Read replicas, larger instance | Sharding |

**Rule of thumb:** Vertical scaling is a quick fix; horizontal scaling is the long-term solution.

## Stateless vs Stateful Services

**Stateless services** store no user-specific state locally — every request can be served by any instance.

```
Stateful (hard to scale):               Stateless (easy to scale):

Client ──► Server A (session cache)     Client ──► LB ──► Server A
            if A crashes, session lost             or Server B
                                                   or Server C  ← any works
```

To make a stateful service stateless: **externalize state** to Redis, Cassandra, or a sticky session store at the load balancer.

## Auto-Scaling Strategies

**Reactive scaling** — triggers on current load:
```
CPU > 70% for 2 minutes  →  launch 2 more instances
CPU < 30% for 10 minutes →  terminate 1 instance
```

**Predictive scaling** — triggers on historical pattern:
```
Every weekday 8 AM → pre-warm 10 instances (traffic spike starts 8:30)
Every Sunday 2 AM  → scale down to minimum (lowest traffic window)
```

**Scheduled scaling** — triggers on calendar event:
```
Black Friday: scale to 5× baseline starting 23:00 Thursday
```

> ⚠️ **Cold start problem:** New instances take 30–120s to warm up JVM/cache. Over-scale early; don't wait for the alarm to fire.

## Database Scaling

| Pattern | When | Tradeoff |
|---------|------|----------|
| **Read replicas** | Read-heavy (80%+ reads) | Replication lag; stale reads possible |
| **Connection pooling** (PgBouncer) | Many short-lived connections | Doesn't increase throughput, reduces overhead |
| **Vertical (larger instance)** | Write-heavy, single-region | Hit ceiling; expensive |
| **Horizontal sharding** | Data too large for one node | Cross-shard queries are painful |
| **CQRS + read store** | Complex read patterns | Eventual consistency |

## Metrics to Track for Scalability

- **QPS** — requests per second (current and peak)
- **P99 latency** — 99th percentile response time (not average!)
- **Error rate** — 5xx responses per minute
- **Saturation** — CPU, memory, disk I/O, network utilization
- **Queue depth** — messages waiting in Kafka/SQS/RabbitMQ

> 💡 **Interview tip:** Averages hide tail latency. A P99 of 3 seconds means 1 in 100 users gets a 3-second response — at 10K QPS that's 100 users/sec experiencing slow responses. Always discuss P99/P999.
MD,
    ],

    '3:1' => [
        'objectives' => [
            'Distinguish reliability from availability and calculate the 9s',
            'Define SLA, SLO, and SLI and know how they relate',
            'Apply MTTR, MTBF, RTO, and RPO to failure planning',
        ],
        'body' => <<<MD
## Reliability vs Availability

These terms are often confused — they measure different things:

| Term | Definition | Example |
|------|-----------|---------|
| **Reliability** | System performs *correctly* when used | No data corruption, no wrong results |
| **Availability** | System is *up and accessible* when needed | Service responds to requests |

A system can be **available but unreliable** (returning wrong results) or **reliable but not available** (correct but offline for maintenance).

## The 9s of Availability

| SLA | Downtime / Year | Downtime / Month | Downtime / Day |
|-----|----------------|-----------------|----------------|
| 99% (two 9s) | 3.65 days | 7.2 hours | 14.4 minutes |
| 99.9% (three 9s) | 8.7 hours | 43.8 minutes | 1.44 minutes |
| 99.99% (four 9s) | 52.6 minutes | 4.4 minutes | 8.6 seconds |
| 99.999% (five 9s) | 5.26 minutes | 26.3 seconds | 0.86 seconds |

**Most SaaS targets:** 99.9% (three 9s)
**Critical infrastructure:** 99.99%+ (four 9s)
**Five 9s is extremely expensive** and requires zero-downtime deploys, hot standbys, and automated failover.

## SLI → SLO → SLA

```
SLI (Service Level Indicator)
  │  A measurable metric: "request success rate = 99.7% this week"
  ▼
SLO (Service Level Objective)
  │  Internal target: "we aim for 99.9% success rate"
  ▼
SLA (Service Level Agreement)
     External contract: "we guarantee 99.9% or refund 10% of monthly bill"
```

**Common SLIs:** availability, error rate, request latency, throughput
**Google's four golden signals:** Latency · Traffic · Errors · Saturation

> 💡 **Interview tip:** If asked "how do you ensure reliability?" answer with: SLIs (what to measure), SLOs (what to target), alerting (when to page), and runbooks (what to do when paged).

## MTTR, MTBF, RTO, RPO

| Metric | Meaning | Who cares |
|--------|---------|-----------|
| **MTBF** | Mean Time Between Failures | How often does it break? |
| **MTTR** | Mean Time To Recover | How fast can we fix it? |
| **RTO** | Recovery Time Objective | Max acceptable downtime |
| **RPO** | Recovery Point Objective | Max acceptable data loss |

```
Failure occurs                     Service restored
      │                                    │
──────▼────────────────────────────────────▼──────
      │←──────── MTTR / RTO ─────────────►│
            (minimize this)

Last backup                     Failure occurs
      │                                    │
──────▼────────────────────────────────────▼──────
      │←──────── RPO ─────────────────────│
         (data in this window may be lost)
```

**Design implications:**
- Low RTO → hot standby (expensive), not cold backup
- Low RPO → synchronous replication, not async

## Failure Modes & Mitigations

| Failure Type | Example | Mitigation |
|-------------|---------|-----------|
| Hardware failure | Disk crash, server death | Replication, RAID |
| Software failure | Memory leak, deadlock | Chaos engineering, canary deploys |
| Network failure | Packet loss, partition | Retry with exponential backoff |
| Dependency failure | Database down | Circuit breaker, fallback |
| Human error | Wrong config deployed | Canary → staged rollout, rollback |
| Correlated failure | Power outage takes whole AZ | Multi-AZ / multi-region |

## Circuit Breaker Pattern

```
CLOSED (normal)           OPEN (tripping)          HALF-OPEN (testing)
Requests flow through →  Fail fast, no calls  →   Allow 1 request
      │                        │                        │
      │ 5 failures/10s         │ 30s timeout            │ Success?
      └────────────────────────┘                         └── CLOSE
                                                         │ Failure?
                                                         └── OPEN again
```

Use when a downstream service is struggling — fail fast instead of waiting for timeouts to cascade into your upstream.
MD,
    ],

    '3:2' => [
        'objectives' => [
            'Distinguish throughput from latency and explain the tradeoff',
            'Read and reason about latency percentiles (P50, P95, P99, P999)',
            'Define the three pillars of observability and when to use each',
        ],
        'body' => <<<MD
## Efficiency

Efficiency in distributed systems has two primary dimensions: **performance** (how fast and how much) and **manageability** (how easy to operate and debug).

## Throughput vs Latency

| | Throughput | Latency |
|-|-----------|---------|
| **Definition** | Work done per unit time | Time to complete one request |
| **Unit** | requests/sec, MB/s, msgs/sec | milliseconds, microseconds |
| **Optimize by** | Batching, parallelism, caching | Reducing hops, co-location |
| **Tradeoff** | Higher throughput often increases latency (batching adds wait) | Lower latency often reduces throughput (no buffering) |

```
Throughput-optimized (batch writes):
  Request 1 ─┐
  Request 2 ─┤──► Buffer ──► DB (write 100 rows at once)
  Request 3 ─┘   (wait 10ms)
  → High throughput, but each request waits longer

Latency-optimized (immediate writes):
  Request 1 ──► DB immediately (no wait)
  → Low latency, but more I/O overhead per request
```

## Latency Percentiles (The Hidden Truth in Averages)

**Never use average latency in production systems.** Use percentiles.

```
Example: 100 requests with these response times (ms):
  50 requests at   5ms
  45 requests at  10ms
   4 requests at 100ms
   1 request  at 800ms

Average: (50×5 + 45×10 + 4×100 + 1×800) / 100 = 16.5ms ← misleadingly good!

P50:  5ms   (median — 50% of requests are faster)
P95: 100ms  (95% of requests faster — that "fast" service actually has 5% slow)
P99: 800ms  (1 in 100 users gets 800ms)
P999: 800ms (worst-case tail)
```

> 💡 **Interview standard:** Quote P99 latency targets, not averages. SLOs like "P99 < 100ms" expose tail latency that averages hide. At 10K QPS, P99 = 100 bad experiences/sec.

## Latency Budget

In a microservices chain, latency adds up:

```
Client
  │  10ms (network)
  ▼
API Gateway
  │  5ms (auth)
  ▼
Service A
  │  20ms (business logic)
  ▼
Service B
  │  50ms (DB read)
  ▼
Cache check → HIT: 1ms / MISS: 50ms

Total (cache hit):  10+5+20+50+1  = 86ms
Total (cache miss): 10+5+20+50+50 = 135ms
```

Each hop has a budget. If a design has 10 service calls each taking 50ms → 500ms total — too slow for a user-facing API.

## The Three Pillars of Observability

```
┌─────────────────────────────────────────────┐
│              OBSERVABILITY                   │
│                                              │
│  METRICS      LOGS          TRACES           │
│  (What)       (Why)         (Where)          │
│  Prometheus   Elasticsearch Jaeger/Zipkin    │
│  Grafana      Splunk        AWS X-Ray        │
└─────────────────────────────────────────────┘
```

| Pillar | Purpose | Example | Cost |
|--------|---------|---------|------|
| **Metrics** | Aggregate numbers over time | CPU 82%, QPS 12K, P99=45ms | Low (pre-aggregated) |
| **Logs** | Event details for debugging | ERROR 404 /users/123 at 14:23:01 | Medium (storage) |
| **Traces** | Request path across services | Request 8f2c took 340ms (DB: 200ms) | High (sampling needed) |

**Structured logging** over plain text:
```json
{"level":"error","ts":1720000000,"service":"user-svc",
 "traceId":"8f2c","userId":123,"msg":"DB timeout","latencyMs":5000}
```

## Manageability

A system that runs well but is impossible to debug is not efficient. Manageability covers:

- **Deployment:** Can you ship changes without downtime? (Blue-green, canary)
- **Rollback:** Can you revert in under 5 minutes?
- **Runbooks:** Is every alert mapped to a documented response?
- **On-call burden:** Is the team paged more than 2× per week on average? (Google SRE standard)
- **Configuration:** Are settings in version control, not hand-edited on prod?

> 💡 **Interview tip:** When asked about operational concerns, mention the four golden signals (Latency, Traffic, Errors, Saturation), structured logging with trace IDs, and canary deployments for safe rollouts. This signals production maturity.
MD,
    ],

    // ── Load Balancing ────────────────────────────────────────────────────────
    '4:0' => [
        'objectives' => [
            'Explain what load balancing is and why it is essential at scale',
            'Distinguish L4 from L7 load balancers and choose the right layer for a given problem',
            'Describe health checks, SSL termination, and where LBs sit in a production architecture',
        ],
        'body' => <<<MD
## What Is Load Balancing?

A **load balancer** sits between clients and a pool of backend servers, distributing requests so no single server becomes a bottleneck or single point of failure.

```
                        ┌────────────────────────────────┐
Clients ──HTTPS──►     │      Load Balancer (L7)         │
                        │  SSL termination · health check │
                        └──────┬──────────┬──────────┬───┘
                               │          │          │
                          ┌────▼─┐   ┌────▼─┐   ┌───▼──┐
                          │App-1 │   │App-2 │   │App-3 │
                          │ :8080│   │ :8080│   │ :8080│
                          └──────┘   └──────┘   └──────┘
```

## Where Load Balancers Live in a Modern System

| Layer | LB Type | Example Tools | What It Balances |
|-------|---------|--------------|-----------------|
| **Edge (global)** | DNS / Anycast | AWS Route 53, Cloudflare | Traffic across data centers / regions |
| **Perimeter (L4)** | Network LB | AWS NLB, HAProxy TCP | Raw TCP/UDP — databases, game servers |
| **Application (L7)** | HTTP LB | AWS ALB, Nginx, Envoy | HTTP requests, gRPC, WebSocket |
| **Service mesh (L7)** | Sidecar proxy | Istio/Envoy, Linkerd | East-west (service-to-service) traffic |

In a real production stack you typically have **all four layers**. The interview default is the application-layer LB unless you're designing a database tier.

## L4 vs L7 Load Balancers (Deep Comparison)

| Dimension | L4 — Transport Layer | L7 — Application Layer |
|-----------|---------------------|------------------------|
| **Inspects** | IP + TCP/UDP headers only | Full HTTP headers, URL path, cookies, body |
| **Routing decisions** | Destination IP/port | URL `/api/*`, `Host:` header, cookie |
| **Latency overhead** | ~0.1ms (no parsing) | ~1ms (parse HTTP) |
| **SSL** | Pass-through (encrypted to backend) | Terminate + re-encrypt (or plain to backend) |
| **Sticky sessions** | By IP (fragile with NAT/mobile) | By cookie (reliable) |
| **Content-based routing** | ❌ | ✅ Blue-green, canary, A/B testing |
| **WebSocket / gRPC** | Native (TCP is TCP) | Requires upgrade handling |
| **AWS example** | NLB (Network Load Balancer) | ALB (Application Load Balancer) |
| **Best for** | DB connections, raw TCP, ultra-low latency | REST APIs, microservices, HTTP apps |

## Key Capabilities You Must Know for Interviews

### Health Checks
```
Active (LB polls backend every N seconds):
  GET /health → 200 OK  →  keep in pool
  GET /health → 5xx / timeout → remove from pool

Passive (watch real traffic):
  5 consecutive failures → mark unhealthy → stop sending new connections
  After 30s → send 1 probe request → if OK, re-add to pool
```

### SSL/TLS Termination
```
Client ──TLS──► LB (decrypts) ──plain HTTP──► Backend
                                ↑
         Cheaper: only 1 cert, backends don't handle crypto
         Tradeoff: traffic inside cluster is unencrypted (mitigate with mTLS via service mesh)
```

### Session Affinity (Sticky Sessions)
```
LB sets cookie:  Set-Cookie: SERVERID=App-2; Path=/

All future requests with that cookie → App-2

Problem: App-2 restarts → all its sticky users lose session
Solution: Don't use sticky sessions — externalize session to Redis
```

> 💡 **FAANG interview rule:** If an interviewer asks "how would you scale your auth service?", the first sentence should be "I'd make it stateless — store session tokens in Redis — so any instance can serve any request, and the load balancer uses round robin with no stickiness needed."
MD,
    ],

    '4:1' => [
        'objectives' => [
            'Implement and compare the five core load balancing algorithms',
            'Select the correct algorithm given connection duration, server heterogeneity, and caching requirements',
            'Explain why sticky sessions undermine horizontal scalability and how to avoid them',
        ],
        'body' => <<<MD
## Load Balancing Algorithms

Choosing the wrong algorithm is a common FAANG interview mistake. The decision depends on: request duration, server homogeneity, caching, and whether the service is stateful.

## Algorithm 1 — Round Robin

Requests cycle sequentially across all servers.

```
Request 1 → Server A
Request 2 → Server B
Request 3 → Server C
Request 4 → Server A  ← wraps around
```

**When to use:** Stateless services, homogeneous servers, short-lived requests (REST APIs).
**When NOT to use:** Long-running requests (one slow upload ties up a slot while others get three times the load).

✅ O(1) per request, zero state
❌ Ignores that Server A might be processing a 10-second file upload

## Algorithm 2 — Weighted Round Robin

Each server has a weight proportional to its capacity.

```
Server A (16 cores): weight 4
Server B (4 cores):  weight 1
→ A handles 4 of every 5 requests
```

**When to use:** Heterogeneous fleets — e.g., you added larger instances during a traffic spike and want to drain old ones.
**Problem:** Weights are set at config time; they don't adapt to real-time CPU load.

## Algorithm 3 — Least Connections (Recommended for long-lived connections)

New request → server with the fewest **active** connections.

```
Server A: 120 active connections
Server B:  14 active connections  ← next request goes here
Server C:  87 active connections
```

**When to use:** Long-lived connections — WebSocket, video streaming, file upload/download, database proxies.
**Why it's better than Round Robin here:** A server stuck on a slow upload won't receive new connections until it catches up.

✅ Adapts dynamically to server load
❌ Requires the LB to track connection counts (O(n) state)

## Algorithm 4 — Least Response Time

Extends Least Connections: route to the server with the **lowest average response time AND fewest connections**.

```
score = active_connections × avg_response_time_ms
→ lowest score wins the next request
```

**When to use:** When response time varies significantly between backends (e.g., cache hits vs. DB queries).
Used by: HAProxy `balance leastconn` with response-time weighting, NGINX Plus.

## Algorithm 5 — Consistent Hashing (for cache affinity)

Hash a request attribute (user ID, URL, session ID) → always route to the same backend node.

```
hash(userId=8823) % ring → Server B
→ Server B's in-memory cache warms up for user 8823
→ Future requests for user 8823 always hit Server B's warm cache
```

**When to use:** When backends hold an in-memory cache and cache-miss cost is high. Common in CDN origin selection, database read replica routing.

**Critical problem — what happens when a server is removed?**
```
Naive %N hashing: remove 1 of 3 servers → 2/3 of all keys remap → cache invalidation storm
Consistent hashing (hash ring): remove 1 server → only its 1/N keys remap → minimal disruption
```
→ Always use **consistent hashing with virtual nodes**, not `hash(key) % N`.

## Decision Framework (Use in Interviews)

```
Is the service stateless?
  NO  → Fix that first. Externalize state to Redis.
  YES ↓

Are servers homogeneous?
  NO  → Weighted Round Robin

Are connections long-lived (>5s)?
  YES → Least Connections or Least Response Time
  NO  ↓

Does backend have in-memory cache worth preserving?
  YES → Consistent Hashing
  NO  → Round Robin  (simplest, default choice)
```

## The Sticky Session Anti-Pattern

```
Problem:
  User logs in → hits Server A → session stored in Server A's RAM
  Next request → hits Server B (round robin) → no session → logged out!

Fix 1 (bad): Sticky sessions via cookie — all of user's requests go to Server A
  Consequence: Server A can't be restarted without losing all its users' sessions

Fix 2 (good): Externalize session to Redis
  Any server can serve any user — no stickiness needed
  Redis cluster survives individual server restarts
```

> 💡 **Interview takeaway:** "Sticky sessions" is a code smell in distributed system design. The right answer is always to externalize state. Mention this proactively and you'll signal senior-level thinking.
MD,
    ],

    '4:2' => [
        'objectives' => [
            'Explain why a single load balancer is a single point of failure',
            'Design Active-Passive and Active-Active LB setups with automatic failover',
            'Describe DNS-based global load balancing and Anycast routing',
        ],
        'body' => <<<MD
## The Load Balancer SPOF Problem

A load balancer exists to eliminate single points of failure in your backend — but the load balancer itself is a SPOF unless you design it for redundancy.

```
Without redundancy:
  Client ──► LB (SPOF) ──► [App-1, App-2, App-3]
             LB fails → everything goes down despite healthy backends

With redundancy:
  Client ──► LB-Primary ──► [App-1, App-2, App-3]
              │ heartbeat │
             LB-Standby (takes over in <1s if primary fails)
```

## Active-Passive (Primary / Standby)

The most common LB HA pattern in on-premise and cloud-VM deployments.

```
            ┌──────────────┐         ┌──────────────┐
Client IP   │  LB-Primary  │◄───────►│  LB-Standby  │
(Virtual IP)│  ACTIVE      │ VRRP    │  PASSIVE     │
            │  192.168.1.1 │heartbeat│  (monitoring)│
            └──────┬───────┘         └──────────────┘
                   │                        ▲
            ┌──────▼──────┐   failover:     │
            │  Backends   │   standby claims VIP
            └─────────────┘   (< 1 second)
```

**VRRP (Virtual Router Redundancy Protocol):**
- Primary sends heartbeat every 1 second
- Standby waits; if no heartbeat for 3 seconds → takes ownership of the **Virtual IP (VIP)**
- DNS record points to the VIP — clients never change
- Client sees no downtime if failover completes within TCP timeout (~30s)

**Drawback:** Standby is idle — 50% of hardware wasted.

## Active-Active (Both LBs Handle Traffic)

Both LBs simultaneously route traffic. DNS returns both IPs (round-robin DNS).

```
DNS: lb.example.com → [203.0.113.1, 203.0.113.2]  (both returned)

Client A → 203.0.113.1 (LB-1) → Backends
Client B → 203.0.113.2 (LB-2) → Backends

If LB-1 fails:
  DNS TTL expires → clients retry → only LB-2 returned
  OR health checks remove LB-1 from DNS within 30-60s
```

**Benefit:** Full utilization — no idle hardware.
**Drawback:** DNS propagation delay (30–60s downtime window during failover).

| | Active-Passive | Active-Active |
|-|---------------|---------------|
| **Failover time** | <1 second (VRRP) | 30–60s (DNS TTL) |
| **Resource usage** | 50% idle | 100% utilized |
| **Complexity** | Low | Medium |
| **Traffic split** | One handles all | Both share load |
| **Best for** | On-premise, latency-critical | Cloud, cost-optimized |

## DNS-Based Global Load Balancing

For multi-region architectures, DNS itself becomes the load balancer at the global level.

```
User in Tokyo → DNS query → Route 53 / Cloudflare
  → geolocation policy → return IP of Tokyo data center
  → connects to closest region (lowest latency)

User in London → same DNS query
  → return IP of Ireland data center
```

**Health-check integration:**
```
Route 53 health check: polls /health every 10 seconds
  → Dublin region fails health check
  → Route 53 removes Dublin IP from DNS response
  → All traffic reroutes to Frankfurt within 60s
```

**DNS TTL strategy:**
- Normal operation: TTL = 60 seconds (browsers can cache, reduces DNS load)
- Before planned failover / deploy: TTL = 10 seconds (faster propagation)
- After failover complete: restore TTL = 60 seconds

## Anycast Routing (Used by Cloudflare, AWS Global Accelerator)

Multiple data centers announce the **same IP address** via BGP. The internet routes each client to the **topologically closest** data center automatically.

```
IP: 1.1.1.1 announced by:
  - Cloudflare Frankfurt (AS13335)
  - Cloudflare Los Angeles (AS13335)
  - Cloudflare Singapore (AS13335)

User in Tokyo → BGP shortest path → Singapore PoP
User in Berlin → BGP shortest path → Frankfurt PoP
→ Both hit 1.1.1.1, but land on different physical servers
```

**Advantage:** No DNS changes needed. Failover is handled by BGP convergence (~30-60s). Inherently DDoS-resilient (attack is absorbed across all PoPs).

## Health Check Cascade (How Healthy LBs Find Healthy Backends)

```
Global LB (Route 53)
  │ polls regional LB /health every 10s
  ▼
Regional LB (ALB)
  │ polls app server /health every 5s
  ▼
App Servers
  │ check own DB connection on /health
  ▼
Database

If DB goes down → app /health returns 503 → regional LB removes that app →
regional LB /health aggregates → returns 503 if >50% apps unhealthy →
global LB removes that region → all traffic shifts to healthy region
```

> 💡 **FAANG interview tip:** When asked "how do you achieve 99.99% availability?", the answer involves **redundant LBs at every tier** (global DNS LB → regional L4 LB → per-AZ L7 LB) and health checks that cascade failures upward automatically. Mention the VIP/VRRP mechanism for single-region HA and Anycast for global HA.
MD,
    ],

    // ── Caching ───────────────────────────────────────────────────────────────
    // ── Caching ───────────────────────────────────────────────────────────────
    '5:0' => [
        'objectives' => [
            'Recall the canonical latency numbers and explain why caching is essential at scale',
            'Place a cache at the correct layer for a given access pattern',
            'Choose between Cache-Aside, Read-Through, Write-Through, Write-Behind, and Write-Around',
        ],
        'body' => <<<MD
## Why Cache? — The Numbers

Memorise these latency numbers. Interviewers use them to anchor every caching conversation:

| Storage | Latency | Relative |
|---------|---------|---------|
| L1 CPU cache | 0.5 ns | 1× |
| L2 CPU cache | 7 ns | 14× |
| RAM / in-process | 100 ns | 200× |
| Redis over LAN | 0.5 ms | 1,000,000× |
| SSD random read | 0.1 ms | 200,000× |
| HDD random read | 10 ms | 20,000,000× |
| Cross-datacenter round trip | 150 ms | 300,000,000× |

A Redis cache hit is **20–2000× faster than a DB read** — which translates directly into fewer servers and lower cost.

## Cache Placement Layers

```
Browser          CDN          API Gateway      Application       Database
  │               │               │                │                │
  │ in-memory  edge PoP   Nginx/Varnish     Redis cluster    InnoDB buffer
  │ (2–5 min)  (hours)    (seconds–min)      (seconds)          pool (RAM)
  │
  └─ Each layer catches different data. Closer to user = faster, but smaller & staler.
```

| Layer | What to cache | TTL typical | Tool |
|-------|-------------|-------------|------|
| **Browser** | Static assets (JS, CSS, images) | Hours–days | Cache-Control header |
| **CDN** | Public HTML pages, API responses | Minutes–hours | Cloudflare, AWS CloudFront |
| **Reverse Proxy** | Rendered pages, authenticated responses | Seconds–minutes | Nginx, Varnish |
| **Application** | DB query results, computed data | Seconds–minutes | Redis, Memcached |
| **DB buffer pool** | Hot pages (rows) | Auto-managed | InnoDB buffer, PostgreSQL shared_buffers |

## Read Strategies

### Cache-Aside (Lazy Loading) — Default choice

```
Read:
  1. Check cache → HIT → return data (fast path)
  2. MISS → query DB → write result to cache → return data

Write:
  1. Write to DB
  2. Invalidate (or update) cache entry
```

✅ Only caches what is actually read — no wasted memory
✅ Cache failure degrades gracefully (fall through to DB)
❌ Cache miss = 2 round trips → first request after cold start is slow
❌ Risk of **thundering herd**: 1000 requests on cache miss all hit DB simultaneously

### Read-Through

The **cache itself** queries the DB on a miss (the app only talks to the cache).

```
App → Cache → (on miss) → DB → Cache stores result → App gets result
```

✅ Simpler app code — only one data source to query
❌ Cold start: first request always misses; pre-warming required
❌ Cache tier must know how to query your specific DB

## Write Strategies

### Write-Through — Consistency-first

```
Write:
  1. App writes to cache
  2. Cache synchronously writes to DB
  3. Return success only after DB ack
```

✅ Cache always consistent with DB
❌ Every write hits DB → no write latency improvement
❌ Low read/write ratio means you cache data that's never read ("write amplification")

### Write-Behind (Write-Back) — Throughput-first

```
Write:
  1. App writes to cache
  2. Return success immediately
  3. Cache asynchronously flushes to DB (batched, e.g. every 100ms)
```

✅ Dramatically lower write latency (returns before DB write)
✅ Batch DB writes reduce I/O (great for time-series data)
❌ Data loss window if cache crashes before flush
❌ Cache and DB temporarily inconsistent

### Write-Around — For infrequently-read data

```
Write:
  1. App writes directly to DB, bypassing cache
  2. Cache is only populated on first read (cache-aside)
```

✅ Prevents cache pollution (write-once-read-never data doesn't fill cache)
❌ First read after write always misses cache

## Redis vs Memcached

| | Redis | Memcached |
|-|-------|-----------|
| Data structures | String, List, Hash, Set, ZSet, Stream | String only |
| Persistence | RDB snapshots + AOF | None |
| Replication | Master-replica + Cluster | Third-party |
| Pub/Sub | Native | No |
| Lua scripting | Yes (atomic multi-op) | No |
| Memory efficiency | Slightly lower | Higher (simpler internals) |
| **Choose** | Almost always | Only for pure key-value cache needing max throughput/memory |

> 💡 **Interview default:** Always choose Redis. The only reason to pick Memcached is if you need to squeeze out every last byte of memory efficiency for pure string caching.

## When NOT to Cache

- Data changes faster than the TTL → cache always stale
- Data is unique per user AND user base is huge → cache has near-zero hit rate
- Regulatory requirements → must serve freshest data (financial transactions, medical records)
- Write-heavy workload with rare reads → cache occupies memory but is never hit
MD,
    ],

    '5:1' => [
        'objectives' => [
            'Apply TTL, event-driven, and versioning strategies to keep caches consistent',
            'Solve the thundering herd / cache stampede problem with a mutex or probabilistic early expiry',
            'Design a cache invalidation scheme for a distributed multi-region system',
        ],
        'body' => <<<MD
## Cache Invalidation — The Hardest Problem

Phil Karlton famously said: *"There are only two hard things in Computer Science: cache invalidation and naming things."*

Stale data is dangerous. Your cache invalidation strategy is the difference between a correct system and one that serves outdated prices, wrong inventory counts, or a deleted user's profile.

## Strategy 1 — TTL (Time-to-Live)

Every cached entry expires after N seconds regardless of whether the underlying data changed.

```
SET user:123:profile <data> EX 300   ← expires in 5 minutes
```

| TTL | Data freshness | Cache hit rate |
|-----|--------------|----------------|
| Short (10s) | High | Low (many misses) |
| Long (1 hour) | Low (risk of staleness) | High |
| No TTL | Worst (stale forever) | Best |

**Sweet spot:** Match TTL to how frequently the data actually changes.
- User avatar: 1 hour (changes rarely)
- Stock price: 1 second (changes constantly — consider not caching at all)
- Product catalog: 5–15 minutes

## Strategy 2 — Event-Driven Invalidation (Best for Consistency)

Invalidate the cache entry **the moment the source data changes**, not on a timer.

```
Write path:
  1. Update DB: UPDATE products SET price=29.99 WHERE id=42
  2. Publish event: Kafka topic "product.updated" → {id: 42}
  3. Cache consumer listens → DEL product:42
  4. Next cache read: miss → re-populate from DB

                ┌─────────────┐   CDC / event   ┌─────────────┐
  DB Write ──►  │  Database   │ ──────────────►  │  Cache      │
                │  (Postgres) │                  │  Invalidator│
                └─────────────┘                  └──────┬──────┘
                                                         │ DEL product:42
                                                  ┌──────▼──────┐
                                                  │    Redis     │
                                                  └─────────────┘
```

**Change Data Capture (CDC):** Use Debezium to stream Postgres WAL events → Kafka → cache invalidation service. This decouples the write path from cache management.

## Strategy 3 — Cache Key Versioning

Instead of invalidating by key, embed a **version number** in the key:

```
Old: GET user:123:profile            → "John Doe, v1"
Profile updated:
New: GET user:123:profile:v2         → cache miss → populate from DB

Old key (v1) naturally expires via TTL
New key (v2) is populated on next read
```

**Application:** Used for "deploy-time invalidation" — increment version in config → all caches transparently miss on the new version's key.

## Strategy 4 — Stale-While-Revalidate

Return stale data immediately, then refresh in the background. Used heavily in CDNs and browser caches.

```
Cache-Control: max-age=300, stale-while-revalidate=60
→ Serve stale content for up to 60s while fetching fresh content in background
→ User gets instant response; next request gets fresh data
```

In application code:
```
1. Fetch from cache → return value (even if TTL recently expired, within grace window)
2. Trigger background goroutine/thread to refresh cache
3. Next request gets fresh data
```

## The Thundering Herd / Cache Stampede Problem

```
Problem:
  Popular cache key expires at T=0
  10,000 concurrent requests all get a cache miss at T=0
  → All 10,000 go to the DB simultaneously
  → DB is overwhelmed; cascading failure
```

### Fix 1 — Mutex / Lock

```
1. Request 1 gets cache miss → acquires distributed lock (SETNX lock:product:42 1 EX 5)
2. Requests 2–9999 get cache miss → try to acquire lock → fail → wait (poll or sleep)
3. Request 1 fetches from DB → writes to cache → releases lock
4. Requests 2–9999 wake up → get cache HIT
```

**Problem:** All waiters hit the cache at once when lock is released (thundering release).

### Fix 2 — Probabilistic Early Expiry (XFetch)

Before the key expires, some requests proactively refresh it:

```python
def fetch(key, ttl, beta=1.0):
    value, expiry = cache.get_with_ttl(key)
    # Probability of early refresh increases as expiry approaches
    if current_time - beta * log(random()) >= expiry:
        value = compute_fresh_value()
        cache.set(key, value, ttl)
    return value
```

This staggers cache refreshes before expiry — no thundering herd.

## Distributed Cache Invalidation Challenges

| Challenge | Solution |
|-----------|---------|
| Cache on multiple app servers (local in-memory) | Pub/Sub broadcast (Redis pub/sub) → all instances invalidate |
| Cache lag between regions | Event-driven invalidation via Kafka, accept brief inconsistency |
| Partial failure (some caches not invalidated) | Short TTL as safety net; idempotent invalidation events |
| Cascade invalidation (deleting a user invalidates 50 dependent keys) | Tag-based invalidation (Varnish PURGE by tag) |

> 💡 **Interview tip:** When asked about cache consistency, propose event-driven invalidation via CDC + Kafka as the primary strategy, with TTL as a safety net. This shows you understand both correctness AND what happens when the event pipeline fails.
MD,
    ],

    '5:2' => [
        'objectives' => [
            'Implement LRU cache in O(1) time using a doubly-linked list and hashmap',
            'Compare LRU, LFU, FIFO, and ARC eviction policies and select the right one',
            'Map Redis maxmemory-policy settings to real use cases',
        ],
        'body' => <<<MD
## Why Eviction Policies Exist

A cache has finite memory. When it's full, the cache must decide **which entry to remove** to make room for new data. The eviction policy determines this decision.

Getting it wrong means:
- Evicting hot data → cache miss rate spikes → DB overload
- Keeping cold data → hot data gets evicted → same result

## LRU — Least Recently Used

**Principle:** Evict the entry that was accessed least recently. The assumption: recently used data is likely to be used again soon (temporal locality).

```
Cache (capacity 3):
  Access A → [A]
  Access B → [A, B]
  Access C → [A, B, C]   ← full
  Access D → evict A (LRU) → [B, C, D]
  Access B → [B, C, D]   ← B moves to MRU position
  Access E → evict C (LRU) → [B, D, E]
```

### O(1) LRU Implementation (Common Interview Question)

```
Data structure: Doubly Linked List + HashMap

HashMap:  key → node (O(1) lookup)
DLL:      MRU ←→ ... ←→ LRU  (O(1) move to front, O(1) remove from tail)

get(key):
  if key in map → move node to head (MRU) → return value
  else → return -1

put(key, value):
  if key in map → update node, move to head
  else:
    if full → remove tail node (LRU) from DLL and map
    create new node → add to head → add to map
```

**Java:** `LinkedHashMap` with `accessOrder=true` gives you LRU for free.
**Redis:** `allkeys-lru` maxmemory-policy.

✅ Simple, excellent for general-purpose caching
❌ A single scan (sequential access of all keys once) pollutes the cache — all hot keys get evicted

## LFU — Least Frequently Used

**Principle:** Evict the entry that has been accessed least often. Good when access frequency matters more than recency.

```
key A: accessed 50 times → keep (hot)
key B: accessed  3 times → evict (cold)
key C: accessed  1 time  → evict first
```

### LFU Implementation

```
Frequency Map: freq → DoublyLinkedList of keys with that frequency
Key Map: key → (value, frequency)
minFreq: tracks current minimum frequency

get(key):
  increment freq[key]
  move key from freq_bucket[f] to freq_bucket[f+1]
  update minFreq if freq_bucket[minFreq] is now empty

put(key, value):
  if full → evict LRU key from freq_bucket[minFreq] (tie-break by recency)
  add key to freq_bucket[1]; set minFreq = 1
```

✅ Excellent for stable workloads with predictable hotspots (product catalog)
❌ **Aging problem:** An item accessed frequently in the past but not recently stays in cache
❌ More complex implementation; higher memory overhead

## FIFO — First In, First Out

Evict the oldest entry by insertion order, regardless of access pattern.

```
Cache (capacity 3):
  Insert A → [A]
  Insert B → [A, B]
  Insert C → [A, B, C]   ← full
  Insert D → evict A (first inserted) → [B, C, D]
```

✅ Simple to implement (O(1) with a queue)
❌ Ignores access frequency and recency → poor hit rate in practice
❌ Rarely used in production; mostly for theoretical discussion

## ARC — Adaptive Replacement Cache

ARC maintains **four lists** and dynamically adjusts the balance between recency (LRU) and frequency (LFU):

```
T1: recently inserted, accessed once
T2: recently inserted, accessed more than once
B1: ghost entries evicted from T1 (tracks what was recently evicted)
B2: ghost entries evicted from T2

If a miss hits B1 → grow T1 (recency more valuable right now)
If a miss hits B2 → grow T2 (frequency more valuable right now)
```

✅ Self-tuning — adapts to workload without manual tuning
✅ Outperforms LRU in most real-world workloads
❌ More complex; patent-encumbered (historically; expired)
**Used by:** ZFS, IBM Db2, some SSD controllers

## Redis maxmemory-policy Options

| Policy | Eviction target | When to use |
|--------|---------------|-------------|
| `noeviction` | None — return error when full | Persistent data; never want to lose entries |
| `allkeys-lru` | LRU across all keys | General-purpose cache; default choice |
| `volatile-lru` | LRU among keys with TTL set | Mix of persistent + cache keys |
| `allkeys-lfu` | LFU across all keys | Workloads with clear hot/cold separation |
| `volatile-lfu` | LFU among keys with TTL | Same mix use case, but frequency-based |
| `allkeys-random` | Random | Uniform access distribution (rare) |
| `volatile-ttl` | Soonest-to-expire first | Want short-lived data to go first |

> 💡 **Interview tip:** A common interview sub-question is "implement an LRU cache" — always say O(1) get and put using doubly-linked list + hashmap. If asked about production cache, say Redis with `allkeys-lru` and explain that the eviction policy should match access pattern: LRU for general use, LFU when hot keys are stable.
MD,
    ],

    '5:3' => [
        'objectives' => [
            'Test your understanding of caching concepts from this chapter',
            'Identify common interview trick questions about cache consistency and eviction',
            'Practise answering caching questions under time pressure',
        ],
        'body' => <<<MD
## Quiz: Caching

Test your understanding before the interview. Answer each question before reading the explanation.

---

### Q1: Your product page endpoint has P99 latency of 800ms due to a complex DB join. You add Redis. What strategy do you use, and what TTL?

**Answer:** Cache-Aside (lazy loading). The application checks Redis first; on miss, executes the DB query and stores the result. TTL depends on how often the product data changes — for a typical e-commerce catalog, 5–15 minutes is reasonable. Shorter TTL for frequently-updated inventory; longer for stable descriptions.

---

### Q2: 50,000 users simultaneously request a flash-sale page. The cache key expires at noon exactly. What happens and how do you prevent it?

**Answer:** Cache stampede (thundering herd). All 50K requests get a miss at the same moment and flood the database. Prevention strategies:
- **Mutex lock:** First request acquires a lock and refreshes; others wait.
- **Probabilistic early expiry (XFetch):** Randomly refresh the key before it expires.
- **Stale-while-revalidate:** Serve stale data for a grace period while background refresh occurs.
- **Jitter on TTL:** `TTL = base + random(0, 30s)` — spreads expiration across a window.

---

### Q3: You have 3 app servers each with a local in-memory cache. A user updates their profile. Two of the three servers still serve the old profile. How do you fix this?

**Answer:** Local in-memory caches create **cache inconsistency across instances**. Solutions:
1. **Switch to a centralised cache (Redis)** — all instances share one cache; update in one place.
2. **Pub/Sub invalidation** — on update, publish `INVALIDATE user:123` to Redis pub/sub; all instances subscribe and evict their local copy.
Option 1 is simpler and preferred unless you need the sub-millisecond latency of in-process caching.

---

### Q4: When would you choose Write-Behind over Write-Through?

**Answer:** Write-behind when write throughput is the bottleneck and you can tolerate a small data-loss window (e.g., IoT sensor data, real-time analytics, gaming leaderboards). The cache buffers writes and flushes to DB asynchronously in batches, reducing DB I/O by 10–100×. **Never** use write-behind for financial transactions, medical records, or any data where loss is unacceptable.

---

### Q5: What eviction policy does Redis use by default, and is it suitable for a production cache?

**Answer:** `noeviction` — Redis returns an error when memory is full. This is **not** suitable for a cache workload. For production caching, set `maxmemory-policy allkeys-lru` (general use) or `allkeys-lfu` (stable hotspot workload). Always set `maxmemory` explicitly to prevent Redis from consuming all server RAM.

---

### Q6: A user is on a pricing page. Your cache says a product costs $29. The database says $35 (updated 2 seconds ago). Whose data is correct?

**Answer:** This is a **cache consistency** problem. The DB is the source of truth; the cache is stale. Resolution:
- If using TTL: wait for expiry (2–5 min); user temporarily sees wrong price.
- If using event-driven invalidation: the update event should have deleted `product:42` from cache immediately; a fresh read would return $35.
- Interview lesson: for pricing, inventory, or any financial data — use very short TTLs (≤30s) or event-driven invalidation. Never cache stale prices indefinitely.

> 💡 **The one rule:** If an interviewer asks about a cache, always ask "what is the consistency requirement?" The answer determines your invalidation strategy.
MD,
    ],

    // ── Data Partitioning ────────────────────────────────────────────────────
    '6:0' => [
        'objectives' => [
            'Distinguish horizontal partitioning (sharding) from vertical partitioning',
            'Identify when a single database node is no longer sufficient',
            'Design a sharding strategy that avoids the hot-partition problem',
        ],
        'body' => <<<MD
## Why Partition at All?

A single database node has limits:

| Resource | Practical ceiling | Symptom when hit |
|----------|-----------------|-----------------|
| Storage | ~50 TB on one machine | Disk full |
| Write throughput | ~10K–50K writes/sec | Write latency spikes |
| Read throughput | ~100K reads/sec (with replicas) | Query slowdowns |
| Memory (buffer pool) | ~1–4 TB RAM | Disk I/O dominates |
| CPU | ~96 cores | Query queue depth rises |

When you exceed these limits, you must distribute data across multiple nodes. That's **partitioning**.

## Vertical Partitioning (Column Splitting)

Split a table **by columns** — move logically related column groups into separate tables or separate services.

```
Before (monolithic Users table):
  users(id, name, email, password_hash, bio, avatar_url,
        last_login, created_at, stripe_customer_id, address, ...)

After vertical partitioning:
  users_core(id, name, email, password_hash, created_at)  ← auth service
  users_profile(user_id, bio, avatar_url)                 ← profile service
  users_billing(user_id, stripe_customer_id, address)     ← billing service
```

**Benefits:**
- Each service owns its schema; independent deployments
- Rarely-accessed columns (billing) don't slow down hot queries (auth)
- Natural alignment with microservices

**Drawbacks:**
- Joins across services require application-level join (N+1 problem)
- Transactions that span multiple services require distributed coordination (2PC or Saga)

## Horizontal Partitioning (Sharding)

Split a table **by rows** — each shard contains a subset of rows, identified by a **shard key**.

```
All users:  1 – 1,000,000,000

Shard 0: users where id in [0, 250M)   → DB node 0
Shard 1: users where id in [250M, 500M) → DB node 1
Shard 2: users where id in [500M, 750M) → DB node 2
Shard 3: users where id in [750M, 1B)  → DB node 3
```

Each shard is an independent database with the same schema but different rows. Reads and writes for a given user always go to exactly one shard.

## Choosing the Shard Key — The Most Critical Decision

The shard key determines data distribution and query routing. A bad shard key is the #1 cause of production sharding disasters.

**Good shard key properties:**
- **High cardinality** — many distinct values, so data spreads evenly
- **Even distribution** — no single value accounts for a large % of traffic
- **Locality** — queries that need multiple rows should hit one shard, not all shards
- **Immutable** — once set, changing it requires moving rows across shards

| Shard Key | Distribution | Problem |
|-----------|-------------|---------|
| `user_id` (random UUID) | ✅ Even | Cross-user queries need scatter-gather |
| `created_at` (timestamp) | ❌ Uneven | All writes go to "today's" shard → hot partition |
| `country` | ❌ Uneven | US shard handles 40% of traffic; small countries have near-empty shards |
| `hash(user_id)` | ✅ Even | Range queries are scatter-gather |
| `tenant_id` (B2B SaaS) | Medium | Large tenants create hot shards |

## The Hot Partition Problem

```
Shard key: celebrity_user_id

@taylorswift (200M followers) → all fan activity → Shard 7
→ Shard 7: 100,000 writes/sec
→ Shards 0–6: 100 writes/sec each
→ Shard 7 is the bottleneck; adding shards doesn't help
```

**Solutions:**
- **Compound shard key:** `(user_id, date)` — distributes celebrity's activity across time shards
- **Write amplification:** Write fan activity to multiple shards, read from all and merge (Twitter's approach)
- **Shard splitting:** Detect hot shards by monitoring, split them further
- **Application-level sharding mitigation:** For the top 1% of hot users, apply special handling (separate cluster)

> 💡 **Interview tip:** When you propose sharding, your interviewer will immediately ask "what's your shard key?" Have a specific answer ready with justification. Say "I'd use hash(user_id) to get even distribution, accepting that cross-user queries need scatter-gather, which I'd minimise by designing queries to stay within a single user's data."
MD,
    ],

    '6:1' => [
        'objectives' => [
            'Implement Range, Hash, Directory, and Geographic partitioning schemes',
            'Apply consistent hashing to minimise data movement when nodes are added or removed',
            'Select the right partitioning method for a given query pattern',
        ],
        'body' => <<<MD
## Four Partitioning Methods

### Method 1 — Range-Based Partitioning

Assign rows to shards based on a range of the shard key value.

```
Shard 0: user_id  [0,        25,000,000)
Shard 1: user_id  [25M,      50,000,000)
Shard 2: user_id  [50M,      75,000,000)
Shard 3: user_id  [75M,     100,000,000)
```

**Query efficiency:** Range scans are fast — `WHERE user_id BETWEEN 25M AND 40M` hits only Shard 1.

✅ Excellent for range queries and time-series data
✅ Easy to reason about which shard holds which data
❌ **Sequential insert hotspot:** If shard key is a timestamp or auto-increment ID, all new writes go to the last shard — hot partition
❌ Uneven distribution if data is not uniformly distributed (e.g., most users signed up recently)

**When to use:** Time-series data with time-range queries. HBase, DynamoDB, Bigtable all use range partitioning internally.

### Method 2 — Hash-Based Partitioning

```
shard_id = hash(shard_key) % N_shards

user_id = 12345678
hash(12345678) = 0xABCDE  →  0xABCDE % 4 = 2  →  Shard 2
```

✅ Uniform distribution — each shard gets ~1/N of the data
✅ Eliminates sequential insert hotspots
❌ Range queries require scatter-gather (scan all shards)
❌ **Resharding nightmare:** Adding a shard (N → N+1) remaps `hash(key) % N+1` for almost all keys → massive data migration

**Critical upgrade — Consistent Hashing:**
```
Place shards on a hash ring [0, 2^32)
Each key maps to the first shard clockwise from hash(key)
Adding a shard: only 1/N of keys migrate
Removing a shard: only that shard's keys redistribute to the next shard
```

When to use: User data, social graph, any data without range query requirements. Cassandra, DynamoDB, Riak use consistent hashing.

### Method 3 — Directory-Based Partitioning (Lookup Table)

A central routing service maintains a mapping table: key → shard.

```
Directory service:
  user_id 1   → Shard A
  user_id 2   → Shard C
  user_id 3   → Shard A
  user_id 4   → Shard B
  ...

Application:  lookup(user_id) → shard ID → query that shard
```

✅ Maximum flexibility — move any row to any shard without changing hash/range rules
✅ Trivial to handle special cases (move a hot user to a dedicated shard)
❌ Directory service is a SPOF — must be highly available and cached
❌ Extra network hop on every query
❌ Operational complexity of keeping directory consistent

**When to use:** Multi-tenant SaaS where tenants have wildly different sizes; or when you need to move specific tenants between shards (e.g., large enterprise customer gets dedicated shard).

### Method 4 — Geographic Partitioning

Partition by user location. Data stays within the user's region (compliance, latency).

```
EU users  → EU shard cluster (Frankfurt / Dublin)
US users  → US shard cluster (Virginia / Oregon)
APAC users → APAC shard cluster (Singapore / Tokyo)
```

✅ Low latency (user's data co-located with the user)
✅ Data sovereignty (GDPR: EU user data stays in EU)
❌ Imbalanced if user distribution is uneven (all US → all writes to US shards)
❌ Cross-region queries are expensive (friends in different regions)

## Partitioning Method Comparison

| Method | Query type | Distribution | Resharding | Complexity |
|--------|-----------|-------------|-----------|-----------|
| Range | Range scans ✅ | Uneven ❌ | Easy | Low |
| Hash (% N) | Point lookups ✅ | Even ✅ | Expensive ❌ | Low |
| Consistent Hash | Point lookups ✅ | Even ✅ | Cheap ✅ | Medium |
| Directory | Any ✅ | Manual | Easy | High |
| Geographic | Regional ✅ | Uneven ❌ | Medium | Medium |

## Composite Partitioning

Combine two methods for best-of-both:

```
First partition by region (geographic):
  EU users → EU cluster

Then within each region, partition by hash(user_id):
  EU Shard 0: hash(user_id) % 4 = 0
  EU Shard 1: hash(user_id) % 4 = 1
  ...
```

This is how most large-scale systems actually work — geographic routing first, hash partitioning within a region.

> 💡 **Interview tip:** The default answer for a large-scale user-centric system is **consistent hashing on user_id**. Only switch to range partitioning if range queries are critical (time-series). Mention geographic partitioning when the interviewer asks about multi-region or data residency requirements.
MD,
    ],

    '6:2' => [
        'objectives' => [
            'Identify the four most common sharding problems: hot partitions, cross-shard queries, cross-shard transactions, and rebalancing',
            'Apply scatter-gather and fan-out patterns to cross-shard queries',
            'Design a resharding strategy that avoids downtime',
        ],
        'body' => <<<MD
## Four Problems Every Sharded System Faces

Sharding solves scale but introduces its own class of problems. These are the four you **must** address in any interview that involves sharding.

## Problem 1 — Hot Partition (Skewed Load)

One shard receives disproportionately more traffic than others.

```
Cause 1: Bad shard key (timestamp → all new writes to "latest" shard)
Cause 2: Celebrity / viral content (one user_id generates 1000× normal traffic)
Cause 3: Uneven key distribution (hash collision in small keyspaces)

Detection: Monitor per-shard QPS and latency → one shard lags behind
```

**Solutions:**
- **Compound shard key:** `hash(user_id + date)` — spreads one user's data across multiple shards
- **Virtual shards + consistent hashing:** More virtual shards → finer-grained rebalancing
- **Read replicas for the hot shard:** Scale reads horizontally while the hot shard exists
- **Application-level fan-out for writes:** Duplicate hot-user writes to multiple shards, merge on read

## Problem 2 — Cross-Shard Queries (Scatter-Gather)

A query that does not include the shard key must hit all shards.

```
Sharded by user_id. Query: "find all orders placed in the last 24 hours"
→ Must query ALL shards (scatter)
→ Merge and sort results in the application layer (gather)
→ Latency = slowest shard response time
→ DB load = N × single-shard query cost
```

**Solutions:**

| Pattern | How | Tradeoff |
|---------|-----|---------|
| **Denormalise** | Pre-compute and store the result | Storage cost; consistency risk |
| **Secondary index** | Global secondary index (e.g., DynamoDB GSI) | Additional storage; eventual consistency |
| **Separate read model** | Copy data to a single read-optimised store (Elasticsearch, ClickHouse) | CDC pipeline complexity |
| **Redesign shard key** | Shard by the most common query attribute | May create hot partition elsewhere |

**Fan-out service pattern:**
```
Client → Fan-out Service
            │
            ├──► Shard 0 (async)
            ├──► Shard 1 (async)
            ├──► Shard 2 (async)
            └──► Shard 3 (async)
            │
            ← Merge & sort results
            → Return to client
```

## Problem 3 — Cross-Shard Transactions (Distributed Transactions)

A transaction that modifies data on multiple shards cannot use a single DB transaction.

```
Transfer $100 from User A (Shard 0) to User B (Shard 3):
  - Debit Shard 0: user_A.balance -= 100
  - Credit Shard 3: user_B.balance += 100
  - If Shard 3 goes down between debit and credit → money lost!
```

**Option 1 — Two-Phase Commit (2PC):**
```
Coordinator → Phase 1: "Can you commit?" → Shard 0: YES, Shard 3: YES
Coordinator → Phase 2: "Commit!" → both commit atomically
Problem: Coordinator failure → shards stuck in "prepared" state (blocking)
```

**Option 2 — Saga Pattern (Compensating Transactions):**
```
Step 1: Debit Shard 0 → SUCCESS → publish "debit-succeeded" event
Step 2: Credit Shard 3 → FAIL   → publish "credit-failed" event
Compensation: Refund Shard 0 → re-credit user A
→ No distributed lock; eventual consistency; complex rollback logic
```

**Best practice:** Design shard key so that related data lives on the same shard. If two users' data must be atomic, put both on the same shard (shard by `account_id`, ensure both accounts share an account).

## Problem 4 — Rebalancing (Adding/Removing Shards)

When traffic grows, you need more shards. Moving data while the system is live is the hardest operational challenge.

**Naive re-sharding (hash % N → hash % N+1):**
```
All key mappings change → must move ~(N/(N+1)) of all data
→ Massive data migration; system degraded for hours/days
```

**Consistent hashing re-sharding:**
```
Add new shard on ring between Shard 2 and Shard 3
→ Only keys that were mapped to Shard 3 and now map to new shard need to move
→ ~1/N of data migrates (1/4 = 25% for a 4→5 shard migration)
```

**Online resharding strategy (zero downtime):**
```
1. Start writing to both old shard and new shard (dual-write)
2. Backfill: copy existing data from old to new shard
3. Verify: check new shard has complete, consistent data
4. Cut over: stop writing to old shard; old shard serves as warm backup
5. Drain: after TTL/verification period, decommission old shard
```

This is how Vitess (MySQL sharding layer used by YouTube) and MongoDB handle online resharding.

## Schema Changes in Sharded Environments

```
Problem: ALTER TABLE users ADD COLUMN loyalty_points INT DEFAULT 0;
→ Must run on ALL shards
→ If 8 shards × 30s per ALTER = 4 minutes of degraded writes

Solution 1: Expand-contract pattern
  Phase 1 (expand): Add column nullable, no default (fast DDL in Postgres 12+)
  Phase 2 (migrate): Backfill values in batches
  Phase 3 (contract): Add NOT NULL constraint once backfill complete

Solution 2: Online schema change tools
  pt-online-schema-change (Percona) or gh-ost (GitHub) for MySQL
  → Shadow table approach; no table lock; safe on production
```

> 💡 **Interview tip:** When an interviewer says "how would you scale the database?", say: (1) read replicas first (cheapest), (2) caching layer (reduce DB load), (3) vertical scaling (buy time), (4) sharding only when necessary — and when you shard, use consistent hashing on a high-cardinality immutable key to minimise rebalancing pain. This ordering shows you understand that sharding has a real operational cost.
MD,
    ],

    // ── Indexes ──────────────────────────────────────────────────────────────
    '7:0' => [
        'objectives' => [
            'Explain how a B+Tree index enables O(log N) lookups instead of O(N) full scans',
            'Choose between B-Tree, Hash, and composite indexes for a given query',
            'Identify when an index hurts more than it helps',
        ],
        'body' => <<<MD
## What Is a Database Index?

An index is a **separate data structure** the database maintains alongside a table. It trades storage space and write overhead for dramatically faster reads.

Without an index:
```
SELECT * FROM users WHERE email = 'alice@example.com';
→ Full table scan: read every row until found
→ O(N) — 100M rows = 100M disk reads
```

With a B+Tree index on `email`:
```
→ Traverse tree: O(log N) — 100M rows ≈ 27 comparisons
→ Follow pointer to actual row in heap file
→ ~27 disk reads vs 100,000,000
```

## B+Tree Index Structure

B+Tree is the dominant index structure in relational databases (PostgreSQL, MySQL, Oracle, SQL Server).

```
                    [50 | 75]              ← internal node (pointers only)
                   /    |    \
            [20|35]   [60|70]  [80|90]    ← internal nodes
           /   |   \
       [10] [25|30] [38|40|45]            ← leaf nodes (actual values + row pointers)

Leaf nodes are linked: [10] ↔ [25|30] ↔ [38|40|45] ↔ ...
→ Range scans are sequential reads along leaf chain
```

**Why B+Tree and not B-Tree?**
B+Tree stores all data in leaf nodes and links them. This enables **range scans** (`BETWEEN`, `>`, `<`, `ORDER BY`) without traversing the tree — just follow the linked-list of leaves. B-Tree stores data in all nodes, so range scans require tree traversal.

**Properties:**
- Height is typically 3–4 for billion-row tables
- Branching factor: 100–1000 keys per node (fits in a disk page)
- Each level reduces search space by a factor of ~500
- 1B rows: height ≈ log₅₀₀(1B) ≈ 3.5 → ~4 disk reads to find any row

## Hash Index

Maps keys to row pointers via a hash function. Stored in memory (typically).

```
hash("alice@example.com") = 0x4A3F → bucket 5 → row pointer → row
```

✅ O(1) exact-match lookups — faster than B+Tree for `=` queries
❌ **No range queries** — `WHERE age > 30` requires full scan
❌ Not persistent in most engines (lost on restart)
❌ Hash collisions require chaining → degrades to O(N) in worst case

**When to use:** PostgreSQL hash indexes (persistent since PG 10) for large equality-only lookup tables. Redis uses hash tables internally.

## Composite (Multi-Column) Indexes

An index on multiple columns. Column order is critical.

```sql
CREATE INDEX idx_user_city_age ON users (city, age);
```

This index can serve:
```sql
WHERE city = 'NYC'                     ← ✅ uses index (leftmost prefix)
WHERE city = 'NYC' AND age = 30        ← ✅ uses both columns
WHERE age = 30                         ← ❌ cannot use index (skips city)
WHERE city = 'NYC' AND age > 25        ← ✅ range on last column is OK
```

**Left-prefix rule:** A composite index `(A, B, C)` can answer queries on `A`, `(A,B)`, or `(A,B,C)` — but NOT `B` alone or `C` alone.

## Covering Index

An index that contains all columns the query needs — the database never touches the heap file (table).

```sql
CREATE INDEX idx_cover ON orders (user_id, created_at, total_amount);

SELECT created_at, total_amount
FROM orders
WHERE user_id = 123
ORDER BY created_at DESC;

→ All data (user_id, created_at, total_amount) is in the index
→ No heap fetch needed → "index-only scan" → much faster
```

## Index Selectivity

**Selectivity** = distinct values / total rows. Higher selectivity = better index candidate.

| Column | Distinct values / rows | Selectivity | Good index? |
|--------|----------------------|------------|-------------|
| user_id (UUID) | ~1.0 | Very high | ✅ Excellent |
| email | ~1.0 | Very high | ✅ Excellent |
| status ('active','inactive') | ~0.002 | Very low | ❌ Poor — B+Tree not selective enough |
| country (195 values, 8B users) | ~0.000024 | Low | ❌ Usually poor; maybe useful for very uneven distributions |
| created_at | ~1.0 (continuous) | Very high | ✅ Good for range queries |

**Low-selectivity index:** If `status = 'active'` matches 95% of rows, the database will skip the index and do a full scan anyway — the index provides no benefit.

## When NOT to Index

| Situation | Why index hurts |
|-----------|----------------|
| Write-heavy table (INSERT/UPDATE dominant) | Every write must update all indexes → overhead |
| Small table (<10K rows) | Full scan is faster than index lookup (fits in one disk I/O) |
| Low-cardinality column (`boolean`, `status`) | Index is not selective enough to help |
| Column rarely in WHERE/JOIN/ORDER BY | Index never used; wastes disk and write overhead |
| Bulk data loading | Drop indexes, load, rebuild — much faster |

> 💡 **Interview tip:** When asked to optimise a slow query, say: (1) `EXPLAIN ANALYZE` to see the query plan; (2) check if a covering index would enable an index-only scan; (3) check column selectivity; (4) check if the composite index column order matches the query's WHERE/ORDER BY pattern. Then recite the left-prefix rule — that's a fast filter for whether an existing index is being used.
MD,
    ],

    '7:1' => [
        'objectives' => [
            'Distinguish dense from sparse indexes and explain their storage tradeoff',
            'Compare clustered and non-clustered indexes and their impact on write performance',
            'Describe secondary indexes in NoSQL systems (DynamoDB GSI, Cassandra)',
        ],
        'body' => <<<MD
## Dense vs Sparse Indexes

These terms describe how many entries an index contains relative to the number of rows.

### Dense Index

One index entry **per row** in the table.

```
Table rows:  [Row 1][Row 2][Row 3][Row 4][Row 5]
Dense index: [Key 1][Key 2][Key 3][Key 4][Key 5]
              ↓      ↓      ↓      ↓      ↓
             All rows have a corresponding index entry
```

✅ Any row can be located directly (O(log N) lookup)
✅ Works on any column — rows don't need to be sorted
❌ Large index: same number of entries as rows → more storage, more write overhead

**B+Tree secondary indexes are dense** — every row gets an entry, regardless of the table's physical order.

### Sparse Index

One index entry **per page** (or per block), not per row.

```
Table pages:  [Page 1: Rows 1-100][Page 2: Rows 101-200][Page 3: Rows 201-300]
Sparse index: [Key=Row1]          [Key=Row101]           [Key=Row201]
               ↓                   ↓                      ↓
              Points to page start; scan within page to find exact row
```

✅ Much smaller index (100–1000× fewer entries)
✅ Fits entirely in memory even for huge tables
❌ Requires that the table is **physically sorted** by the index key
❌ Finding a row = find page via index + sequential scan within page

**Clustered indexes (see below) use a sparse index** at the upper B+Tree levels — each internal node stores only the minimum key for its subtree, not every key.

## Clustered vs Non-Clustered Index

This is the most important index distinction for interviews.

### Clustered Index

The table rows are **physically stored in index key order**. There can be only ONE clustered index per table (since rows have only one physical order).

```
Clustered index on user_id:

Disk:  [user_id=1, name="Alice"][user_id=2, name="Bob"][user_id=3, name="Carol"]...
       ← rows stored in user_id order →

Lookup user_id=500:
  Traverse B+Tree (height=3) → find page containing row 500 → read row
  Total disk reads: 3 (tree) + 1 (data) = 4
```

**MySQL InnoDB:** The primary key IS the clustered index. The table IS the B+Tree. If you don't define a primary key, InnoDB invents a hidden one.

**Benefit:** Range queries (`WHERE user_id BETWEEN 100 AND 200`) are fast — rows 100–200 are physically contiguous on disk → sequential I/O.

**Danger:** If your primary key is not monotonically increasing (e.g., UUID), random inserts scatter new rows throughout the B+Tree → **page splits** → write amplification → slow inserts on large tables. Use auto-increment or time-ordered IDs (Snowflake, ULID) for clustered-index tables.

### Non-Clustered (Secondary) Index

Index entries contain the key + a **pointer** back to the row (heap file reference or clustered index key).

```
Non-clustered index on email:

Index:  ["alice@..."→PK=42]["bob@..."→PK=7]["carol@..."→PK=101]
                               ↓
                        Heap lookup: go find row with PK=7

Lookup by email:
  1. Search index B+Tree → find email → get PK (or heap pointer)
  2. Heap fetch: go to the actual row storage using the PK
  Total: index traversal + heap fetch (extra disk read)
```

This extra step is called a **heap fetch** or **bookmark lookup**. A **covering index** eliminates it by including all needed columns in the index.

## Secondary Indexes in NoSQL Systems

### DynamoDB — Global Secondary Index (GSI)

DynamoDB is partitioned by partition key. To query by a non-partition-key attribute, you create a GSI.

```
Table: Orders (partition key: user_id, sort key: order_id)

GSI: orders_by_status (partition key: status, sort key: created_at)
→ Enables: "get all PENDING orders sorted by created_at"
→ Without GSI: full table scan across all partitions

Tradeoff:
- GSI is an asynchronous replica of the table (eventual consistency)
- Writes to the base table propagate to GSI within milliseconds
- Queries on GSI may return slightly stale data
- Each GSI has its own read/write capacity units (cost)
```

### Cassandra — Secondary Indexes and SASI

Cassandra is partitioned by partition key. Secondary indexes on non-key columns:

```
Table: users (partition key: user_id)
Secondary index on: city

Query: SELECT * FROM users WHERE city = 'NYC'
→ Cassandra fans out to ALL nodes (each node checks its own data)
→ Expensive scatter-gather for large tables
→ Only suitable for low-cardinality columns with small result sets

Better approach: Maintain a separate table:
  city_users (city TEXT, user_id UUID, PRIMARY KEY(city, user_id))
→ Explicit denormalisation; write to both tables; reads are cheap
```

**Key lesson:** NoSQL secondary indexes are often more expensive than SQL ones. The recommended pattern is **denormalisation** — duplicate data in a query-optimised structure.

## Index Write Overhead

Every index slows down writes. Quantify this for your system:

```
Table with 5 indexes:
  INSERT one row = 1 heap write + 5 index writes = 6 write operations
  UPDATE indexed column = 2 heap writes + 2×5 index writes (delete old, insert new)

Mitigation for bulk loads:
  1. Drop all non-essential indexes
  2. Bulk insert data (100× faster)
  3. Rebuild indexes
```

| N indexes | Insert cost multiplier |
|-----------|----------------------|
| 0 | 1× (baseline) |
| 1 | ~2× |
| 3 | ~4× |
| 5 | ~6-8× (with page splits) |

> 💡 **Interview tip:** If asked "why is this write so slow?", index write overhead is always in the top 3 causes. Check `SHOW INDEX FROM table_name` (MySQL) or `\d table_name` (Postgres). If a table has 10+ indexes, that's a red flag — audit which are actually being used (`pg_stat_user_indexes` in Postgres).
MD,
    ],

    // ── Proxies ──────────────────────────────────────────────────────────────
    '8:0' => [
        'objectives' => [
            'Distinguish forward proxies from reverse proxies by who they represent and where they sit',
            'Describe the network topology and trust model for each proxy type',
            'Name at least three production use cases for each proxy type',
        ],
        'body' => <<<MD
## What Is a Proxy?

A proxy is an **intermediary server** that sits between two communicating parties. The key question is: *whose side is the proxy on?*

```
Forward Proxy — represents the CLIENT:
  Client ──► [Forward Proxy] ──► Internet / Server
  Server sees the proxy's IP, not the client's IP

Reverse Proxy — represents the SERVER:
  Client ──► Internet ──► [Reverse Proxy] ──► Server(s)
  Client sees the proxy's IP, not the backend server's IP
```

## Forward Proxy

A forward proxy sits on the **client side** of the network. Clients are configured (or forced) to route outbound traffic through it.

```
Corporate Network:
  Employee's laptop ──► Squid Proxy ──► Internet
                            │
                     ┌──────▼───────┐
                     │ Inspect URL  │
                     │ Block social │
                     │ Cache pages  │
                     │ Log traffic  │
                     └──────────────┘
```

### Forward Proxy Use Cases

| Use Case | How |
|----------|-----|
| **Content filtering** | Block blacklisted domains (corporate / parental control) |
| **Privacy / anonymity** | Client hides its real IP (VPN is a type of forward proxy) |
| **Geo-restriction bypass** | Route through a proxy in another country |
| **Bandwidth control** | Cache and compress responses for slow networks |
| **Security inspection** | TLS MITM — decrypt, inspect for malware, re-encrypt |
| **Access control** | Only allow outbound traffic through approved proxy (zero-trust) |

**Tools:** Squid, mitmproxy, Privoxy, corporate VPN gateways

### The MITM (Man-in-the-Middle) Inspection Pattern

```
Client ──TLS──► Forward Proxy
  Proxy presents its own certificate (trusted by corp root CA)
  Proxy decrypts, inspects payload, re-encrypts to destination
  → "SSL inspection" or "TLS break-and-inspect"
  → Requires corp-issued root certificate installed on all client machines
```

## Reverse Proxy

A reverse proxy sits on the **server side**. Clients are unaware of backend servers — they think they're talking directly to `api.example.com`.

```
Internet:
  Client A ─────┐
  Client B ─────┤──► [Nginx Reverse Proxy] ──► App Server 1
  Client C ─────┘       api.example.com        App Server 2
                                                App Server 3
```

### Reverse Proxy Use Cases

| Use Case | Description | Tool |
|----------|-------------|------|
| **Load balancing** | Distribute requests across multiple backends | Nginx, HAProxy, AWS ALB |
| **SSL/TLS termination** | Handle HTTPS; backend speaks plain HTTP | Nginx, Envoy |
| **Caching** | Cache static assets and API responses | Nginx, Varnish |
| **Compression** | gzip/brotli compression of responses | Nginx |
| **Security (WAF)** | Block malicious requests before they reach the app | Cloudflare, AWS WAF |
| **DDoS protection** | Absorb and filter floods before backend | Cloudflare, AWS Shield |
| **API gateway** | Rate limiting, auth, request routing | Kong, AWS API Gateway |
| **Blue-green deploys** | Route % of traffic to new version | Nginx, Envoy, Istio |

**Tools:** Nginx, HAProxy, Envoy, Caddy, AWS ALB, Cloudflare

## Side-by-Side Comparison

| Dimension | Forward Proxy | Reverse Proxy |
|-----------|-------------|---------------|
| **Represents** | Client | Server |
| **Configured by** | Client / IT admin | Server operator |
| **Client awareness** | Client knows it's using a proxy | Client is unaware of backend |
| **Server awareness** | Server sees proxy IP, not client | Server sees proxy IP (or X-Forwarded-For) |
| **Primary purpose** | Control client outbound traffic | Protect and scale server infrastructure |
| **Authentication** | Authenticates clients | Authenticates on behalf of servers |
| **Caching direction** | Caches server responses for clients | Caches server responses close to servers |
| **Typical layer** | Network perimeter / endpoint | Edge of data center / CDN |

## Transparent Proxy

A transparent proxy intercepts traffic without client configuration. The client is completely unaware.

```
ISP / Corporate network:
  All traffic on port 80/443 is silently redirected to a proxy
  Client does not configure anything
  → Used for: ISP content filtering, hotspot login pages (captive portals), network monitoring
```

## Service Mesh — The Modern Reverse Proxy Pattern

In microservices, a **sidecar proxy** (Envoy) is deployed next to each service instance:

```
Service A Pod:             Service B Pod:
  [App Container]            [App Container]
  [Envoy Sidecar] ──mTLS──► [Envoy Sidecar]
       │                           │
  Istio Control Plane — configures all sidecars centrally

Capabilities: mTLS, circuit breaking, retries, tracing, traffic splitting
```

> 💡 **Interview tip:** If asked "how do you implement rate limiting across all microservices without changing each service's code?", the answer is: **reverse proxy / API gateway** or **service mesh sidecar** — the cross-cutting concern is handled at the proxy layer, not inside each service. This is a senior-level insight that distinguishes you from candidates who say "add rate limiting code to every service."
MD,
    ],

    '8:1' => [
        'objectives' => [
            'Configure a reverse proxy for SSL termination, caching, and request routing',
            'Explain how an API gateway extends a reverse proxy with auth, rate limiting, and observability',
            'Describe the service mesh sidecar pattern and when it replaces an API gateway',
        ],
        'body' => <<<MD
## Reverse Proxy as the Swiss Army Knife

A reverse proxy is not just a traffic router — it is the enforcement point for cross-cutting concerns: security, performance, observability. Every enterprise system has one. Knowing its capabilities in depth is essential.

## Use Case 1 — SSL/TLS Termination

```
                ┌─────────────────────────────┐
Client ─HTTPS──►│     Nginx (Reverse Proxy)   │──HTTP──► App Server
                │  cert: *.example.com        │          (plain, trusted network)
                └─────────────────────────────┘

Benefits:
  - Only ONE certificate to manage (at the proxy)
  - Backends don't pay TLS handshake CPU cost
  - Centralised cert rotation (Let's Encrypt + auto-renew)

Risk:
  - Traffic between proxy and backend is unencrypted
  - Mitigate: mTLS between proxy and backend (service mesh), or VPC private network
```

**Nginx config:**
```
server {
    listen 443 ssl;
    ssl_certificate /etc/nginx/certs/example.crt;
    ssl_certificate_key /etc/nginx/certs/example.key;
    location / {
        proxy_pass http://backend_pool;   # plain HTTP to backend
    }
}
```

## Use Case 2 — Caching at the Proxy Layer

Varnish and Nginx cache static assets and dynamic API responses, dramatically reducing backend load.

```
Cache-Control: public, max-age=3600
→ Proxy caches this response for 1 hour
→ 10,000 subsequent requests for /products served from proxy cache
→ Backend sees 1 request, not 10,001

Cache-Control: private, no-cache
→ Proxy MUST NOT cache (user-specific data)
→ Every request hits the backend
```

**Cache decision matrix at proxy:**

| Content Type | Cache? | TTL |
|-------------|--------|-----|
| JS/CSS/images | ✅ Aggressively | Long (versioned: forever) |
| Public API responses | ✅ Carefully | Short (5–60s) |
| HTML pages | ⚠️ Depends | Short if personalised |
| User dashboard | ❌ Never | `Cache-Control: private` |
| POST/PUT/DELETE | ❌ Never | Not cacheable by definition |

**Cache purging:** When data changes, send a `PURGE /products/42` request to Varnish or use Cloudflare Cache API to invalidate specific URLs.

## Use Case 3 — Request Routing and Traffic Splitting

Reverse proxies enable zero-downtime deployments by splitting traffic.

```
Blue-Green Deployment:
  v1 (blue): 100% traffic → v2 deployed → test v2 → 100% → v1 retired

Canary Release (Nginx + split_clients):
  1% of traffic → v2 (canary)
  99% of traffic → v1 (stable)
  Monitor error rate → if good: 10% → 50% → 100%

A/B Test (by cookie or user ID):
  if cookie[experiment] == "B": proxy to v2
  else: proxy to v1
```

**Nginx canary config:**
```
split_clients "\${remote_addr}\${http_user_agent}" \$variant {
    1%    new_backend;
    *     stable_backend;
}
```

## API Gateway — Reverse Proxy + Business Logic

An API gateway extends a reverse proxy with application-level capabilities:

```
Client ──► API Gateway ──► Microservice A
                       └──► Microservice B
                       └──► Microservice C

API Gateway responsibilities:
  ✅ Authentication (JWT validation, OAuth token introspection)
  ✅ Rate limiting (per user, per IP, per API key)
  ✅ Request routing (by URL path, host, header)
  ✅ Request/response transformation (add headers, rewrite paths)
  ✅ Observability (access logs, tracing, metrics)
  ✅ Circuit breaking (stop sending to unhealthy backends)
  ✅ Caching
  ✅ IP allowlist/blocklist
```

| Tool | Type | Best for |
|------|------|---------|
| Nginx | Reverse proxy | Static serving, SSL termination |
| Kong | API gateway | Plugin-based microservice gateway |
| AWS API Gateway | Managed gateway | Serverless / Lambda backends |
| Envoy | Proxy / gateway | Kubernetes, service mesh edge |
| Traefik | Modern gateway | Kubernetes native, auto-config |

## Service Mesh — When Every Service Needs a Proxy

In a large microservices fleet, an API gateway handles north-south traffic (client → cluster). But **east-west traffic** (service A → service B) also needs: mTLS, retries, circuit breakers, tracing.

```
Without service mesh: every service implements retry, timeout, mTLS logic itself
  → Code duplication × 50 services × 3 languages

With service mesh (Istio + Envoy sidecar):
  - Envoy sidecar injected into every pod automatically
  - All service-to-service traffic routed through sidecars
  - mTLS: automatic, no app code change
  - Retries, timeouts, circuit breaking: configured in Istio VirtualService YAML
  - Distributed tracing: header propagation done by sidecar

App code becomes simple:
  → HTTP GET /users/123  (plain, to the sidecar on localhost)
  Sidecar handles mTLS, retry, timeout, tracing transparently
```

**Cost:** ~20ms added latency per hop (sidecar overhead); extra container per pod (~50MB RAM); complex control plane.

## Proxy-Level Caching vs Application-Level Caching

| | Proxy Cache (Nginx/Varnish) | App Cache (Redis) |
|-|---------------------------|------------------|
| **Caches** | Full HTTP responses | Arbitrary data (query results, computed values) |
| **Granularity** | URL-level | Key-level (any structure) |
| **For** | Public, anonymous responses | Per-user, complex data |
| **Invalidation** | PURGE request or TTL | DEL key or TTL |
| **Latency** | Sub-millisecond (in-memory, same host) | ~0.5ms (network) |
| **Miss behaviour** | Falls through to backend | Falls through to DB |

> 💡 **FAANG interview answer:** When asked "how would you scale your read-heavy API?", layer the caches: (1) CDN for global public content, (2) Nginx proxy cache for popular responses at the edge, (3) Redis app cache for DB query results, (4) read replicas for cache misses. Each layer has a 70–95% hit rate, so DB load drops exponentially.
MD,
    ],

    // ── Redundancy & Replication ─────────────────────────────────────────────
    '9:0' => [
        'objectives' => [
            'Enumerate common single points of failure in a production system',
            'Apply N+1 and active-active redundancy to eliminate SPOFs at every tier',
            'Design an automatic failover mechanism using health checks and leader election',
        ],
        'body' => <<<MD
## What Is Redundancy?

Redundancy means having **more than one copy** of a component so that the failure of any single component does not cause system downtime.

A **Single Point of Failure (SPOF)** is any component whose failure brings down the entire system. Eliminating SPOFs is the first step toward high availability.

## Common SPOFs and Their Mitigations

| Component | SPOF Risk | Mitigation |
|-----------|----------|-----------|
| **Web server** | Single instance crashes | Multiple instances behind load balancer |
| **Load balancer** | LB itself crashes | Active-passive pair with VRRP / VIP |
| **Database (primary)** | Primary crashes → no writes | Replica with auto-promotion (Patroni, RDS Multi-AZ) |
| **Cache server** | Redis crashes → all requests miss | Redis Sentinel or Redis Cluster |
| **Message queue** | Kafka broker crashes | Replication factor ≥ 3, min.insync.replicas=2 |
| **DNS** | DNS failure → service unreachable | Multiple nameservers, anycast DNS |
| **Network switch** | Switch failure → isolated zone | Redundant switches (LACP bonding), multi-AZ |
| **Power supply** | PDU failure → rack down | Dual PSU, UPS, multiple AZs |
| **Availability Zone** | Whole AZ down (rare) | Multi-AZ deployment (all stateful services span 3 AZs) |
| **Region** | Region outage | Active-active multi-region or warm standby |
| **Third-party API** | Stripe/Twilio down → feature broken | Circuit breaker + graceful degradation |

## N+1 Redundancy

**N+1:** Deploy N instances to handle full load, plus 1 extra for failover.

```
Traffic: 10,000 RPS
Single instance capacity: 3,500 RPS
N = ceil(10,000 / 3,500) = 3 instances needed

N+1 = 4 instances deployed
→ Any one instance can fail; remaining 3 still handle full load
→ Cost: 33% extra capacity
```

**2N (full duplication):** Deploy 2× capacity for complete hot standby. Used when failover time must be zero.

## Failover Patterns

### Manual Failover (Tier 3 — only for non-critical systems)
```
On-call engineer detects failure → runs failover runbook → promotes replica
Downtime: minutes to hours (pager response + human action)
Use: Non-critical batch systems, internal tools
```

### Automatic Failover with Health Checks
```
Health check daemon polls primary every 1 second
3 consecutive failures → trigger automatic failover:
  1. Fence (isolate) the failed primary to prevent split-brain
  2. Elect new leader (replica with most up-to-date data)
  3. Update routing (DNS, VIP, connection pool) to point to new primary
  4. Alert on-call

Downtime: 10–60 seconds
Tools: Patroni (Postgres), MHA (MySQL), Sentinel (Redis), etcd/ZooKeeper (custom)
```

### Split-Brain — The Most Dangerous Failure Mode

```
Network partition: Primary and replica can't see each other
→ Both think the other is dead
→ Both promote themselves as primary
→ Two primaries accepting writes independently
→ Data diverges — impossible to reconcile

Prevention:
  Quorum: only promote if majority (N/2 + 1) of nodes agree
  STONITH: "Shoot The Other Node In The Head" — fence the failed node
           (cut its network, power it off) before promotion
  Leases: primary holds a time-limited lock; if not renewed, it stops accepting writes
```

## Multi-Tier Redundancy Architecture

A truly resilient system has redundancy at **every tier**:

```
Users
  │
CDN (Cloudflare — 200+ PoPs, Anycast)
  │
  ├── AZ-A ── ALB ── [App ×3] ── [Cache ×3] ── [DB Primary]
  │                                                    │ sync replica
  ├── AZ-B ── ALB ── [App ×3] ── [Cache ×3] ── [DB Replica] ← can promote
  │
  └── AZ-C ── ALB ── [App ×3] ── [Cache ×3] ── [DB Replica]

All 3 AZs active for app tier (active-active)
DB: one primary + 2 replicas; automatic failover via Patroni/RDS Multi-AZ
Cache: Redis Cluster across 3 AZs
```

## Graceful Degradation — The Redundancy Mindset for Dependencies

When a dependency fails, the system should **degrade gracefully** rather than fail completely.

```
Recommendation service is down:
  ❌ Wrong: Return 500 to user
  ✅ Right: Return most popular items (static fallback) + log the error

Payment processor is slow (circuit open):
  ❌ Wrong: Hang the checkout flow until timeout (30s bad UX)
  ✅ Right: Circuit breaker trips after 5 failures → immediate "try again later" message
            Async retry via queue when circuit resets

Search service is down:
  ❌ Wrong: Hide entire search bar
  ✅ Right: Show search bar; on submit, serve cached results or top-N products
```

> 💡 **Interview tip:** Always enumerate redundancy tier by tier — DNS → CDN → Load Balancer → App → Cache → DB → Message Queue. For each tier, state whether it's active-active or active-passive and the failover mechanism. This structured answer immediately signals production experience.
MD,
    ],

    '9:1' => [
        'objectives' => [
            'Compare synchronous and asynchronous replication and quantify the durability tradeoff',
            'Design a master-replica read-scaling architecture and handle replication lag',
            'Explain multi-master replication and the conflict resolution strategies',
        ],
        'body' => <<<MD
## Why Replicate?

Replication creates **copies of data** on multiple nodes. It solves three distinct problems:

| Goal | How replication helps |
|------|----------------------|
| **Durability** | Data survives node failures (hardware crash, disk corruption) |
| **Availability** | Failover to replica if primary dies |
| **Read scalability** | Distribute reads across multiple replicas |
| **Latency** | Keep data close to users (replicate to multiple regions) |

## Synchronous vs Asynchronous Replication

This is the most important replication tradeoff. Know it cold.

### Synchronous Replication

```
Client ──► Primary ──► [write WAL] ──► Replica 1 ─► ACK
                                   └──► Replica 2 ─► ACK
                        wait for all ACKs
                        ↓
                     "commit" returned to client
```

✅ **Zero data loss** — every committed write is on ≥ 2 nodes
✅ Failover is clean — replica is guaranteed up-to-date
❌ **Latency = slowest replica** — if one replica is 200ms away, every write takes ≥ 200ms
❌ If a replica is unavailable, writes can block or fail (depending on config)

**Use when:** financial transactions, inventory writes, anything where data loss is unacceptable.
**AWS RDS Multi-AZ** uses synchronous replication to the standby.

### Asynchronous Replication

```
Client ──► Primary ──► "commit" returned immediately
                   └──► [background] WAL shipped to replicas
                         Replica catches up eventually (replication lag: 0–1000ms typically)
```

✅ **Low write latency** — primary doesn't wait for replicas
✅ Replica failure doesn't block primary
❌ **Replication lag** — replica may be milliseconds (or seconds) behind
❌ **Data loss window** — if primary crashes before lag is replicated, recent writes lost

**Use when:** read replicas for reporting/analytics, cross-region copies, non-critical data.

### Semi-Synchronous (MySQL default, PostgreSQL `synchronous_commit = remote_write`)

```
Primary waits for ACK from at least ONE replica, then commits.
Other replicas catch up asynchronously.
→ Durability of sync with less latency penalty than full sync to all replicas
```

## Active-Passive (Primary-Replica) Architecture

```
                    Primary (Read/Write)
                    │     │
              ┌─────┘     └─────┐
              │                 │
        Replica 1           Replica 2
        (Read only)         (Read only)

Write path:  Client → Primary only
Read path:   Client → Any replica (load balanced)
```

### Read Scaling with Replicas

```
Baseline: Primary at 80% CPU handling 10K QPS (mix of reads + writes)

Add 3 replicas:
  Route all reads (80% of traffic = 8K QPS) to replicas
  Primary handles only writes (2K QPS → drops to 20% CPU)
  Each replica handles ~2.7K QPS → low load

Rule: N read replicas can handle N times the read throughput.
Rule: Replicas do not help write throughput — all writes must go to primary.
```

### Handling Replication Lag (Read-Your-Writes Consistency)

```
Problem:
  User updates profile → write goes to Primary
  User immediately refreshes page → read goes to Replica 2
  Replica 2 is 200ms behind → user sees old profile → confusion!

Solution 1 — Read-after-write routing:
  After any write by user X, route all of user X's reads to Primary for 1 second
  Track in Redis: {user_id: last_write_ts}

Solution 2 — Monotonic reads:
  Pin a user to a specific replica for the duration of their session
  (sticky sessions at the replica level, not the app server level)

Solution 3 — Session causality tokens:
  Primary returns a replication position (LSN) with each write
  Client sends LSN with reads → replica waits until it has caught up to that LSN
  (PostgreSQL sync_replication with "synchronous_commit = remote_apply")
```

## Active-Active (Multi-Master) Replication

Both (all) nodes accept writes simultaneously.

```
     ┌──────────┐         ┌──────────┐
     │  Node A  │◄───────►│  Node B  │
     │ (Primary)│ bidirec-│ (Primary)│
     │ US-East  │ tional  │ EU-West  │
     └──────────┘ replcn  └──────────┘
     Client A writes user.name="Alice"
     Client B writes user.name="Bob" at same millisecond
     → CONFLICT: both nodes committed, now disagree
```

### Conflict Resolution Strategies

| Strategy | How | Use case |
|----------|-----|---------|
| **Last-Write-Wins (LWW)** | Higher timestamp wins | Simple; requires synchronised clocks (NTP) |
| **Application-defined merge** | App code decides (e.g., union of sets) | Shopping cart, collaborative docs |
| **CRDT** (Conflict-free Replicated Data Types) | Math-based merge that always converges | Counters, sets, text (Riak, Redis CRDT) |
| **Operational Transform** | Track operations, transform for context | Google Docs real-time collaboration |
| **Last-write wins with causality** | Vector clocks to detect concurrent writes | DynamoDB, Cassandra (configurable) |

### When to Use Active-Active

- **Geographic distribution:** Write from nearest region (low latency)
- **High availability:** No primary failover needed — all nodes already primary
- **Regulatory:** Data must be written locally (GDPR)

**Cost:** Conflict resolution complexity; eventual consistency between regions.

**Real examples:** Cassandra (all nodes equal; tunable consistency), DynamoDB Global Tables, CockroachDB (distributed SQL with consensus).

## Replication Factor and Quorum

In peer-to-peer replication (Cassandra, Kafka):

```
Replication Factor (RF) = number of copies of each data item

RF=3: each row stored on 3 nodes

Write Quorum (W): how many nodes must ACK before success
Read Quorum (R): how many nodes must respond before returning result

For strong consistency: W + R > RF
  → RF=3, W=2, R=2: 2+2=4 > 3 ✅ guaranteed to see latest write

For availability (eventual consistency): W=1, R=1
  → Fast but may read stale data
```

| Config | W | R | Consistency | Availability |
|--------|---|---|-------------|-------------|
| Strong | 2 | 2 | ✅ | Lower (need 2/3 nodes) |
| Read-optimised | 1 | 3 | ✅ | Good (any write succeeds) |
| Write-optimised | 3 | 1 | ✅ | Lower (need all 3 for write) |
| Eventual | 1 | 1 | ❌ | Highest |

> 💡 **Interview tip:** When someone asks "how do you handle a primary database failure?", your answer should be: (1) we use async replication to 2 read replicas for read scaling; (2) in the same AZ, we have a synchronous replica (via RDS Multi-AZ / Patroni) that can be promoted in <30s with no data loss; (3) we use a circuit breaker in the app layer so DB failover doesn't cascade into application errors. This answer covers durability, availability, AND resilience.
MD,
    ],

    // ── SQL vs NoSQL ─────────────────────────────────────────────────────────
    '10:0' => [
        'objectives' => [
            'Contrast ACID guarantees in SQL with BASE properties in NoSQL systems',
            'Identify which data characteristics favour relational vs non-relational databases',
            'Recognise the polyglot persistence pattern and when to apply it',
        ],
        'body' => <<<MD
## The Core Distinction

The SQL vs NoSQL question is fundamentally about **what guarantees you need** and **at what scale**.

| | SQL (Relational) | NoSQL (Non-relational) |
|-|-----------------|------------------------|
| **Data model** | Tables with fixed schema and foreign keys | Flexible: document, key-value, column, graph |
| **Consistency** | ACID transactions | BASE: eventual consistency (configurable) |
| **Scaling** | Vertical primary; horizontal via sharding (complex) | Horizontal native (designed for it) |
| **Query language** | SQL — rich joins, aggregations, window functions | API / limited query (varies by type) |
| **Schema** | Schema-first (enforce at DB layer) | Schema-on-read (enforce at app layer) |
| **Joins** | Native (foreign keys, JOIN) | Application-level or denormalised |
| **Maturity** | 50+ years, well-understood | 20 years, still evolving |

## ACID vs BASE

### ACID (SQL guarantee)

```
Atomicity   — Transaction is all-or-nothing
              Transfer $100: debit AND credit either both happen or neither

Consistency — DB moves from one valid state to another
              Foreign key constraint: can't insert order for non-existent user_id

Isolation   — Concurrent transactions don't interfere
              Two users buying last ticket: exactly one succeeds

Durability  — Committed data survives crashes
              WAL (write-ahead log) ensures fsync to disk before commit returns
```

### BASE (NoSQL guarantee)

```
Basically Available   — System is available even during partial failures
                        Some nodes may return stale data but the system keeps responding

Soft state            — Data state may change over time even without new writes
                        (due to replication convergence)

Eventually Consistent — All replicas will converge to the same value — eventually
                        Cassandra: write to 1 node, replicate async; read may see old value
```

**Critical insight:** "Eventual consistency" is not a bug — it's a deliberate tradeoff. For a social media "like" count, showing 10,247 vs 10,251 is fine. For a bank balance, it is not.

## When to Choose SQL

| Scenario | Why SQL wins |
|----------|-------------|
| **Financial systems** | ACID transactions — debit/credit must be atomic |
| **E-commerce orders** | Complex relationships (user → order → items → products) |
| **Reporting / analytics** | Rich SQL: JOINs, window functions, GROUP BY |
| **Strong consistency required** | Inventory management, seat booking |
| **Highly relational data** | Social graph traversal (though graph DBs exist) |
| **Regulatory compliance** | Auditability; many compliance frameworks assume relational |
| **Team SQL expertise** | Operational cost of learning a new paradigm is real |

**Best SQL databases:** PostgreSQL (versatile, full-featured), MySQL (battle-tested), CockroachDB (distributed SQL with ACID), Spanner (Google's globally distributed ACID SQL)

## When to Choose NoSQL

| Scenario | Why NoSQL wins |
|----------|----------------|
| **Massive scale** | 100M+ writes/day; NoSQL designed for horizontal sharding |
| **Flexible schema** | User-defined product attributes, CMS content blocks |
| **High write throughput** | IoT sensor data, time-series, log ingestion |
| **Low latency reads** | Key-value lookup by ID (Redis, DynamoDB) — sub-millisecond |
| **Document storage** | JSON-native (MongoDB, Firestore) — no ORM impedance mismatch |
| **Geographic distribution** | Multi-region writes (Cassandra, DynamoDB Global Tables) |
| **Graph traversal** | Social networks, fraud detection — graph DBs (Neo4j) |

## Polyglot Persistence

Large systems rarely use just one database. They use the **right tool for each job**:

```
┌──────────────────────────────────────────────────────────┐
│                    User-Facing API                        │
└──┬──────────┬──────────┬───────────┬──────────┬──────────┘
   │          │          │           │          │
PostgreSQL  Redis     Cassandra  Elasticsearch  Neo4j
(user accts) (sessions) (activity   (full-text   (social
(orders)     (rate limit) feed)      search)      graph)
(payments)   (leaderboard)
```

**Real-world example (Twitter/X):**
- MySQL/Manhattan → user account data (relational, ACID)
- Cassandra → tweets timeline (time-series, high write)
- Redis → hot timelines cache, rate limiting
- Elasticsearch → tweet search
- FlockDB → social graph (who follows whom)

## Common Interview Mistake

Candidates often say "I'd use MongoDB because it's flexible." This is a red flag.

**Better answer:** "For user accounts and orders, I'd use PostgreSQL — the relational model and ACID transactions are a natural fit, and the data is structured and well-understood. For the activity feed (append-heavy, time-ordered, high volume), I'd use Cassandra — it's optimised for time-series writes and scales horizontally without resharding complexity. For full-text search, Elasticsearch on top of both."

> 💡 **Interview tip:** The question "SQL or NoSQL?" is a proxy for "do you understand tradeoffs?" Always answer by listing the specific requirements that drive the choice: consistency needs, write volume, query complexity, schema stability, and scale target. A specific technology name alone is a surface-level answer.
MD,
    ],

    '10:1' => [
        'objectives' => [
            'Describe the data model, query pattern, and scaling approach for each NoSQL family',
            'Match a specific database product to a use case with justification',
            'Identify the internal architecture that makes each NoSQL type fast at its specific workload',
        ],
        'body' => <<<MD
## The Five NoSQL Families

NoSQL is not one thing — it is a family of databases, each optimised for a specific access pattern. Picking the wrong family is a common interview mistake.

## Family 1 — Key-Value Store

**Model:** Opaque values addressed by a string key. The DB knows nothing about the value's structure.

```
SET user:session:abc123 '{"userId":42,"role":"admin","exp":1720000000}'  EX 3600
GET user:session:abc123  → '{"userId":42,...}'
DEL user:session:abc123
```

**Internal architecture:** In-memory hash table + optional persistence (RDB/AOF). O(1) get/set.

| | Redis | DynamoDB (key-value mode) | Memcached |
|-|-------|--------------------------|-----------|
| Data types | Rich (String, List, Hash, Set, ZSet, Stream) | Item with attributes | String only |
| Persistence | Optional (RDB + AOF) | Always (SSD, cross-AZ) | None |
| TTL | Native (`EX` flag) | TTL attribute | Native |
| Clustering | Redis Cluster (hash slots) | Fully managed | Client-side |

**Use cases:** Session storage, rate limiting, caching, leaderboards (Redis Sorted Set), pub/sub, distributed locks (`SETNX`).

**Not for:** Complex queries, joins, data where you need to search by value (not key).

## Family 2 — Document Store

**Model:** JSON/BSON documents with nested objects and arrays. Documents in the same collection can have different fields.

```json
{
  "_id": "507f1f77bcf86cd799439011",
  "title": "iPhone 15",
  "price": 999,
  "specs": {"ram": "6GB", "storage": "256GB"},
  "tags": ["phone", "apple", "5G"],
  "reviews": [{"user": "alice", "rating": 5}]
}
```

**Query:** Rich query language on any field, including nested fields and array elements.
```js
db.products.find({ "specs.ram": "6GB", price: { \$lt: 1000 } })
```

**Internal architecture:** Documents stored as BSON; B+Tree indexes on any field. Horizontal sharding by shard key.

| Product | Strength |
|---------|---------|
| MongoDB | Most feature-rich; transactions since v4; Atlas cloud |
| Firestore | Mobile/web SDK; real-time sync; serverless-friendly |
| CouchDB | Master-master replication; HTTP API; offline sync |
| DynamoDB | Document + key-value; managed, globally distributed |

**Use cases:** Product catalogues (variable attributes), CMS content, user profiles, IoT device state.

**Not for:** Complex multi-document transactions at high volume; heavy aggregations (better in a data warehouse).

## Family 3 — Wide-Column (Column-Family) Store

**Model:** Rows identified by a partition key; columns are not fixed — each row can have different columns. Optimised for write-heavy, time-series workloads.

```
Cassandra table (stored on disk as SSTable):
  Partition key: user_id = "alice"
  Clustering key: timestamp (determines row order within partition)

user_id | timestamp           | event_type | page
--------|---------------------|------------|------
alice   | 2024-01-15 10:00:01 | page_view  | /home
alice   | 2024-01-15 10:00:05 | click      | /products/42
alice   | 2024-01-15 10:00:12 | purchase   | /checkout
```

**Internal architecture:** Log-Structured Merge Tree (LSM Tree). Writes go to in-memory memtable, flushed to immutable SSTables on disk. No random writes → extremely high write throughput.

```
LSM Tree write path:
  Write → MemTable (RAM) → WAL (durability)
  When MemTable full → flush to SSTable (disk, sorted)
  Background: compact SSTables to merge and remove tombstones

Read path:
  Check MemTable → check SSTable Bloom Filter → read SSTables
```

| Product | Strength |
|---------|---------|
| Apache Cassandra | Masterless, linear scale; tunable consistency; proven at Twitter scale |
| HBase | Hadoop ecosystem; strong consistency; HDFS storage |
| Google Bigtable | Managed; powers Gmail, Maps, YouTube |
| Amazon Keyspaces | Managed Cassandra |

**Use cases:** Time-series (IoT, metrics, logs), activity feeds, messaging history, audit logs.

**Not for:** Random point lookups on non-primary-key columns (requires secondary index — expensive in Cassandra); ad-hoc aggregations.

## Family 4 — Graph Database

**Model:** Nodes and Edges with properties. Optimised for relationship traversal queries.

```
(Alice) -[FOLLOWS]→ (Bob) -[FOLLOWS]→ (Carol)
(Alice) -[LIKED]→ (Post:42) -[TAGGED]→ (Topic:Tech)

Query (Cypher):
MATCH (alice:User {name: "Alice"})-[:FOLLOWS*2]->(friend)
RETURN friend.name
→ "Find all second-degree connections of Alice"
```

**Why not SQL for graphs?**
```sql
-- SQL for 3 hops:
SELECT u3.name FROM users u1
  JOIN follows f1 ON u1.id = f1.follower_id
  JOIN users u2 ON f2.follower_id = u2.id
  JOIN follows f2 ON u2.id = f2.follower_id
  JOIN users u3 ON ...
  WHERE u1.name = 'Alice'
-- N hops = N JOIN clauses; variable depth = impossible without recursion
```

Graph databases traverse edges natively in O(depth) — no joins needed.

| Product | Strength |
|---------|---------|
| Neo4j | Most mature; Cypher query language; ACID |
| Amazon Neptune | Managed; Gremlin + SPARQL; property graph + RDF |
| TigerGraph | Extremely fast traversal; analytics |

**Use cases:** Social networks (friends of friends), fraud detection (ring buying patterns), recommendation engines, knowledge graphs, network topology.

## Family 5 — Time-Series Database

**Model:** Measurements indexed by timestamp. Optimised for high-rate sequential writes and time-range queries.

```
Metric: cpu_usage, host: web-01, value: 87.3%, ts: 2024-01-15T10:00:01Z
Metric: cpu_usage, host: web-01, value: 88.1%, ts: 2024-01-15T10:00:02Z
```

**Internal architecture:** Delta encoding + compression (values close together compress 10–100×). Automatic downsampling of old data. Efficient range scans on time dimension.

| Product | Strength |
|---------|---------|
| InfluxDB | Purpose-built; Flux query language; retention policies |
| TimescaleDB | PostgreSQL extension; SQL + time-series functions |
| Prometheus | Pull-based metrics; PromQL; alerting |
| Apache Druid | Real-time analytics; sub-second aggregation |

**Use cases:** Infrastructure metrics, IoT sensor data, financial tick data, application performance monitoring.

> 💡 **Interview tip:** When a design problem involves storing sensor readings, user events, or metrics over time — always reach for a time-series database. The key selling points are: (1) automatic data compression (10:1 to 100:1), (2) built-in downsampling and retention policies, (3) time-range queries are first-class operations (not an afterthought).
MD,
    ],

    '10:2' => [
        'objectives' => [
            'Apply a structured decision framework to choose SQL vs NoSQL for any interview scenario',
            'Justify database choices for real systems (Twitter, Uber, Netflix, Stripe)',
            'Describe migration strategies when outgrowing the original database choice',
        ],
        'body' => <<<MD
## The Decision Framework

When an interviewer asks "what database would you use?", never answer with a tool name alone. Walk through this framework — it signals engineering maturity.

## Step 1 — Understand the Data Shape

```
Is the data structured (known schema) or unstructured (variable fields)?
  Structured → SQL is fine; migrations are manageable
  Unstructured / variable → Document store (MongoDB, Firestore)

Does the data have relationships that need joins?
  Yes, complex joins → SQL (PostgreSQL)
  Yes, but graph relationships → Graph DB (Neo4j, Neptune)
  No joins needed → Any NoSQL; simpler is better
```

## Step 2 — Understand the Consistency Requirement

```
Does a failed write lead to financial loss, double-charging, or data corruption?
  YES → ACID required → SQL (PostgreSQL, CockroachDB, Spanner)
  NO  → BASE acceptable → NoSQL options open

Is "read my own writes" required?
  YES → Synchronous replication or route reads to primary
  NO  → Async replication; replica reads are fine

Can different users see different values for the same field temporarily?
  YES (social like counts, follower counts) → Eventual consistency fine
  NO (bank balance, inventory count) → Strong consistency required
```

## Step 3 — Understand the Scale Target

```
Write throughput:
  < 5,000 writes/sec → PostgreSQL handles this with tuning
  5K–50K writes/sec → PostgreSQL with partitioning, or Cassandra
  > 50K writes/sec → Cassandra, DynamoDB, Kafka (event log)

Read throughput:
  < 50K reads/sec → PostgreSQL + read replicas
  > 50K reads/sec → Add Redis cache; most reads never hit DB

Data volume:
  < 5 TB → Single PostgreSQL node
  5–50 TB → PostgreSQL with table partitioning
  > 50 TB → Sharding (Vitess, CockroachDB) or NoSQL

User base:
  < 1M users → PostgreSQL
  1M–10M users → PostgreSQL + caching + read replicas
  > 10M users → Evaluate sharding or NoSQL for high-volume tables
```

## Step 4 — Understand the Query Pattern

```
Primary access pattern: lookup by ID?
  YES → Key-value (Redis, DynamoDB) or any DB with primary key index

Primary pattern: time-series / append-only reads?
  YES → Cassandra, InfluxDB, TimescaleDB

Primary pattern: full-text search?
  YES → Elasticsearch / OpenSearch (NOT PostgreSQL FTS at large scale)

Primary pattern: complex analytical aggregations?
  YES → Data warehouse (BigQuery, Redshift, ClickHouse) — not OLTP DB

Primary pattern: graph traversal (friends of friends)?
  YES → Neo4j, Neptune
```

## Real-World Database Choices

| Company/System | Database | Why |
|---------------|---------|-----|
| **Stripe** | PostgreSQL | Financial transactions → ACID; complex relational data |
| **Twitter timelines** | Cassandra | High write throughput; time-ordered; scales linearly |
| **Twitter social graph** | FlockDB (MySQL-backed) | Bidirectional follow graph |
| **Twitter search** | Earlybird (Lucene) / Elasticsearch | Inverted index; full-text |
| **Uber trips** | MySQL (early) → Schemaless (custom Cassandra) | Started SQL; moved for scale |
| **Netflix catalog** | MySQL + Cassandra | MySQL for catalog metadata; Cassandra for viewing history |
| **LinkedIn feed** | Espresso (custom LS-Tree) | High-throughput document store |
| **Facebook messages** | HBase | Time-series messages; massive scale |
| **Airbnb listings** | MySQL | Structured, relational; ACID for bookings |

## The Polyglot Answer (For Complex Interview Questions)

When designing a large system, use multiple databases:

```
Example: Design Twitter

User accounts & follow relationships:
  → PostgreSQL (structured, relational, ACID for account operations)

Tweets (write: 6K/s; read: 300K/s):
  → Cassandra (time-ordered writes, horizontal scale)

Home timeline (pre-computed fan-out):
  → Redis (in-memory, sub-ms reads for hot timelines)
  → Cassandra (cold timelines older than 24h)

Search:
  → Elasticsearch (inverted index, relevance ranking)

Media (images, videos):
  → S3 / object storage (not a database; binary BLOBs don't belong in DB)
```

## Migration Strategy (When You Outgrow Your DB)

Migrating from one database to another in production without downtime:

```
Pattern: Strangler Fig + Dual Write

1. New DB deployed in parallel (empty)
2. App writes to BOTH old and new DB (dual write)
3. Backfill: copy existing data old → new
4. Read traffic: starts 0% new DB → gradually shift (5% → 25% → 50% → 100%)
5. Verify: compare query results between old and new
6. Cut over: 100% reads from new DB
7. Stop dual writes; old DB is read-only archive
8. Decommission old DB after retention period
```

**Real example:** GitHub migrated from MySQL to Vitess (MySQL sharding) using dual-write + gradual read migration over 2 years — with zero downtime.

> 💡 **FAANG interview answer pattern:** Start with PostgreSQL for everything. Add Redis when reads are too slow. Add Cassandra (or DynamoDB) when writes exceed 5K/sec or data exceeds 50TB. Add Elasticsearch when full-text search is required. This ordered, justified escalation shows you understand that complexity has a cost — and you only pay that cost when the simpler option genuinely can't meet requirements.
MD,
    ],

    // ── CAP Theorem ──────────────────────────────────────────────────────────
    '11:0' => [
        'objectives' => [
            'State the CAP theorem precisely and explain the Gilbert-Lynch proof',
            'Explain why CA is not a real distributed option',
            'Apply PACELC as a more nuanced model for latency-consistency trade-offs',
        ],
        'body' => <<<MD
## The CAP Theorem

**Eric Brewer's conjecture (2000), proved by Gilbert & Lynch (2002):**
A distributed data store can guarantee at most **two** of:

```
              Consistency (C)
                    ▲
                   /|\
                  / | \
                 /  |  \
           CP   /   |   \  CA (single-node only)
               /    |    \
              /     |     \
             ▼      |      ▼
   Availability (A) ─────── Partition Tolerance (P)
                       AP
```

| Property | Precise Definition |
|----------|--------------------|
| **C**onsistency | Every read sees the most recent committed write — **linearizability** |
| **A**vailability | Every non-failing node responds to every request (no timeout, no error) |
| **P**artition Tolerance | System operates despite arbitrary message loss between nodes |

## The Key Insight: P is Not Optional

In any distributed system, **network partitions happen** — cables fail, switches reboot, cloud zones isolate.
Gilbert & Lynch proved: if nodes can't communicate during a partition, you **cannot** maintain both C and A simultaneously.

Therefore, the real decision is always **CP vs AP**:

| Choice | Behavior During Partition | Choose When |
|--------|--------------------------|-------------|
| **CP** | Reject requests (return error) to avoid stale reads | Financial transactions, inventory, distributed locks |
| **AP** | Serve stale data to preserve availability | Social feeds, DNS, product catalogs, caches |

> ⚠️ **"CA" is a trap in interviews.** CA systems (PostgreSQL on a single node) avoid the tradeoff by having no network between replicas — they're not truly distributed. Never say "I'd choose CA" in a FAANG interview; it shows you don't understand the theorem.

## Consistency Models (Nuance the CAP "C")

"Consistency" in CAP = **linearizability** (strongest). In practice, systems offer weaker models:

```
Strongest ──────────────────────────────────────────── Weakest

Linearizable → Sequential → Causal → Read-Your-Writes → Eventual
     │               │           │             │               │
  Zookeeper      CockroachDB  Cassandra     DynamoDB     DNS/CDN
  etcd            (Raft)       (quorum)    (sessions)    caches
```

| Model | Guarantee | Example |
|-------|-----------|---------|
| **Linearizable** | Real-time ordering — reads see latest write globally | Zookeeper, etcd, Spanner |
| **Sequential** | All clients see operations in same order (not real-time) | Multi-core CPU memory model |
| **Causal** | Causally related ops are ordered; concurrent ops may diverge | Git, CRDTs |
| **Read-Your-Writes** | You always see your own writes | DynamoDB sessions |
| **Eventual** | All replicas converge *eventually* | DNS, Cassandra (AP config) |

## Database CAP Classification

| System | CAP | Consistency Model | Why |
|--------|-----|------------------|-----|
| HBase | CP | Strong | Chooses error over stale — row-level atomic operations |
| Cassandra | AP | Tunable (eventual → strong) | Availability-first; `QUORUM` reads add consistency |
| DynamoDB | AP (default) / CP (strongly consistent reads) | Configurable | Default eventual; `ConsistentRead=true` flips to CP |
| Zookeeper | CP | Linearizable | Coordination requires zero staleness |
| etcd | CP | Linearizable (Raft) | Kubernetes control plane needs CP |
| MongoDB | CP | Strong (since v4.0) | Replica set primary reads are linearizable |
| CockroachDB | CP | Serializable | Distributed SQL on Raft consensus |
| PostgreSQL | CA (single-node) | ACID Serializable | No network partition between nodes |
| Redis Cluster | AP | Eventual (async replication) | Performance > strict consistency |

## PACELC — Beyond CAP

CAP only describes behavior **during a partition**. **PACELC** (Daniel Abadi, 2012) extends it:

```
If Partition (P):
  choose between Availability (A)  or  Consistency (C)
Else (E — no partition, normal operation):
  choose between Latency (L)       or  Consistency (C)
```

| System | Partition | Else | Real behavior |
|--------|-----------|------|--------------|
| DynamoDB | PA | EL | Available + low latency, eventual consistency |
| Cassandra | PA | EL | Same as Dynamo (by design) |
| Zookeeper | PC | EC | Consistent even at cost of latency |
| Spanner | PC | EC | TrueTime ensures strong consistency globally |
| Riak | PA | EL | Availability-first distributed KV store |

> 💡 **FAANG interview tip:** When asked "what's the consistency model of X?", go beyond "CP or AP" — name the consistency level (linearizable, eventual, causal) and explain the latency tradeoff. Mentioning PACELC signals senior-level thinking.
MD,
    ],

    '11:1' => [
        'objectives' => [
            'Map specific real-world systems to their CAP trade-offs',
            'Explain how DynamoDB, Cassandra, and Zookeeper handle partitions differently',
            'Design a system with the appropriate consistency level for a given use case',
        ],
        'body' => <<<MD
## The Art of Picking CP vs AP

The CAP choice is a **product and business decision** as much as a technical one. Use this framework:

```
Step 1: What happens if the user sees stale data?
  → Money lost / data corrupted? → CP
  → Mildly annoying (stale feed)? → AP

Step 2: What happens if the system returns an error?
  → User can retry / fall back?  → CP is tolerable
  → Must stay available always?  → AP

Step 3: What is the partition duration tolerance?
  → Short (ms)? → CP with fast timeout is fine
  → Long (minutes)? → AP with reconciliation
```

## Scenario Analysis

### 💳 Banking / Payments (CP)

```
Partition scenario: User A and User B see account balance = $100
A withdraws $80  (reaches node-1)
B withdraws $80  (reaches node-2, partitioned from node-1)
Both succeed → Balance = -$60 ← CATASTROPHIC

CP solution: node-2 rejects B's request during partition
  → user gets error: "Service temporarily unavailable"
  → Bank: acceptable. Double-spend: never acceptable.
```

**Systems:** HBase (HDFS), CockroachDB, Spanner, PostgreSQL with synchronous replication

### 📱 Social Media Feed (AP)

```
Partition scenario: User sees a tweet from 30 seconds ago instead of now
  → Slightly stale feed — nobody loses money
  → User refreshes and sees latest

AP solution: Serve from nearest replica (possibly stale)
  → 100% availability, p99 latency ~50ms
  → Eventual consistency: replica catches up in seconds
```

**Systems:** Cassandra, DynamoDB, Redis (async replication)

### 🔑 Distributed Config / Locks (CP)

```
Partition scenario: Two services try to acquire the same distributed lock
  If AP: both could acquire it → split-brain, data corruption
  If CP: only one acquires, other gets "try again"

CP solution: etcd / Zookeeper use Raft/ZAB consensus
  → Leader election requires quorum (majority of nodes)
  → During partition: minority side stops serving
```

**Systems:** etcd (Kubernetes), Zookeeper (Kafka, HBase), Consul

### 🌐 DNS (AP)

```
DNS propagation can take 24-48 hours.
Stale DNS responses are fine — worst case: request goes to old IP,
gets redirected, or fails and retries.
Consistency would require global coordination on every lookup — far too slow.
```

**Systems:** Anycast DNS, CDN caches, browser DNS caches

## Cassandra's Tunable Consistency

Cassandra lets you pick consistency **per-query**, trading off C and A dynamically:

```
N = 3 (replication factor)

Write consistency levels:
  ONE    → write to 1 replica (fastest, least durable)
  QUORUM → write to ⌈N/2⌉+1 = 2 replicas
  ALL    → write to all 3 (strongest, unavailable if any node down)

Read consistency levels:
  ONE    → read from 1 replica (fastest, may be stale)
  QUORUM → read from 2 replicas, return newest
  ALL    → read from all 3

Strong consistency: Write QUORUM + Read QUORUM (W + R > N)
Tunable: Relax either W or R for better latency/availability
```

## DynamoDB Consistency Modes

| Mode | Latency | Consistency | Use When |
|------|---------|------------|---------|
| Eventually Consistent Read (default) | ~1ms | Stale by up to 1 second | Read-heavy, non-critical (product catalog) |
| Strongly Consistent Read | ~5ms | Always latest | User balances, session state |
| Transactional (DynamoDB Transactions) | ~10ms | ACID across multiple items | Order placement, inventory decrement |

## The "Consistency Spectrum" Answer

When an interviewer asks "how do you ensure consistency?", don't just say "use a CP system":

```
1. Clarify what "consistent" means for this use case
   → Linearizable? Causal? Read-your-writes?

2. Identify the consistency boundary
   → Single record? Across multiple records? Cross-service?

3. Choose the mechanism
   → Single record:        DB transactions (ACID)
   → Cross-record same DB: DB transactions
   → Cross-service:        Saga pattern (compensating transactions)
   → Read freshness:       Sync replication OR read from primary
   → Eventual is enough:   Async replication + conflict resolution

4. State the tradeoff explicitly
   → "This gives us X consistency at the cost of Y latency/availability"
```

> 💡 **FAANG answer:** "For this financial use case, I'd choose CP with linearizable consistency. Concretely: PostgreSQL with synchronous replication to 1 standby (W+1 durability), reads from primary only. During a network partition the standby won't be promoted until it's confirmed in-sync — so we may have brief unavailability but never stale money reads. If we needed global scale, I'd use Google Spanner or CockroachDB which give linearizability without sacrificing write throughput."
MD,
    ],

    // ── URL Shortener ─────────────────────────────────────────────────────────
    '16:1' => [
        'objectives' => [
            'Design the end-to-end request flow for URL creation and redirect',
            'Choose between 301 and 302 redirects and justify the tradeoff',
            'Design the REST API and system components for TinyURL at scale',
        ],
        'body' => <<<MD
## System Architecture

```
           Write Path                         Read Path
Client ──► LB ──► App Server ──► DB      Client ──► CDN ──► LB ──► App ──► Redis ──► DB
                     │                                               (cache miss only)
                  Cache warm
```

**Components:**

| Component | Technology | Purpose |
|-----------|-----------|---------|
| Load Balancer | Nginx / AWS ALB | Route traffic, SSL termination |
| App Servers | Stateless, horizontally scalable | URL creation, redirect logic |
| Cache | Redis Cluster | Hot URL lookups — target 95%+ hit rate |
| Primary DB | Cassandra / DynamoDB | Durable URL storage, massive write scale |
| CDN | CloudFront / Akamai | Cache popular redirects at edge |
| Key Generator | Zookeeper-coordinated counter | Collision-free short code generation |

## REST API Design

```
POST /api/v1/urls
  Request:  { "long_url": "https://...", "alias": "my-brand", "ttl_days": 30 }
  Response: { "short_code": "abc123", "short_url": "https://tiny.url/abc123", "expires_at": "2024-12-31" }
  Errors:   409 Conflict (alias taken), 400 Bad Request (invalid URL), 429 Too Many Requests

GET /{short_code}
  Response: HTTP 301/302 Location: https://long-url-here.com
  Errors:   404 Not Found, 410 Gone (expired)

DELETE /api/v1/urls/{short_code}
  Auth:     Bearer token (owner only)
  Response: 204 No Content

GET /api/v1/urls/{short_code}/stats
  Response: { "clicks": 12345, "created_at": "...", "expires_at": "..." }
```

## 301 vs 302 Redirect — The Critical Tradeoff

| | 301 Permanent | 302 Temporary |
|-|--------------|--------------|
| Browser behavior | Caches redirect permanently — skips your server on repeat visits | Re-requests your server every time |
| Server load | Very low — most traffic served by browser/CDN cache | Higher — every click hits your server |
| Analytics | **Cannot track repeat clicks** — browser never hits your server again | **Full click tracking** — every redirect logged |
| URL preview tools | May cache stale destination | Always checks current destination |
| Default for TinyURL | ❌ (breaks analytics) | ✅ (enables click tracking) |

**Interview answer:** "I'd use 302. URL shorteners typically monetize analytics (click counts, geographic distribution, device breakdown). 301 would break analytics for repeat visitors. The latency cost of hitting our server (~5ms in Redis) is acceptable."

## Database Schema

```sql
-- Cassandra (primary store)
CREATE TABLE url_mappings (
    short_code   TEXT PRIMARY KEY,   -- partition key — O(1) lookup
    long_url     TEXT,
    user_id      UUID,
    created_at   TIMESTAMP,
    expires_at   TIMESTAMP,
    click_count  COUNTER              -- approximate, separate table
);

-- Optional: reverse lookup (long_url → short_code dedup)
CREATE TABLE url_reverse_index (
    long_url_hash TEXT PRIMARY KEY,   -- SHA256(long_url)[0:16]
    short_code    TEXT
);
```

## Request Flow — URL Creation

```
1. Client  → POST /api/v1/urls { long_url: "..." }
2. App     → Validate URL (not malicious, parseable)
3. App     → Check rate limit (Redis: INCR user:\$userId:ratelimit, EXPIRE 60)
4. App     → Check reverse index: does this long_url already have a short code?
5a. Hit    → Return existing short_code (dedup — same URL, same code)
5b. Miss   → Generate new short_code via Snowflake ID → base62
6. App     → INSERT INTO url_mappings (atomic, IF NOT EXISTS for custom alias)
7. App     → Warm Redis cache: SET short_code long_url EX 86400
8. App     → Return { short_code, short_url, expires_at }
```

## Request Flow — Redirect

```
1. Client  → GET /abc123
2. CDN     → Cache check: HIT → 302 redirect immediately (0ms DB cost)
3. App     → Redis cache check: HIT → 302 redirect (1ms)
4. App     → Cassandra read: SELECT long_url WHERE short_code = 'abc123'
5. App     → Redis: SET abc123 long_url EX 86400 (cache miss → warm)
6. App     → 302 Location: long_url
7. Async   → Increment click_count in background (fire-and-forget)
```

> 💡 **FAANG tip:** Mention the caching hierarchy explicitly: CDN → Redis → Cassandra. Each layer is ~10x slower than the previous. For a read-heavy system (100:1 read/write), a warm Redis cache means >95% of traffic never reaches Cassandra, keeping p99 < 5ms.
MD,
    ],

    '16:0' => [
        'objectives' => [
            'Scope functional and non-functional requirements using the FAANG interview framework',
            'Perform back-of-envelope estimation for QPS, storage, and bandwidth',
            'Identify the five key design decisions that drive all subsequent architecture choices',
        ],
        'body' => <<<MD
## Clarifying Requirements (First 5 Minutes)

In a FAANG interview, spend the first 5 minutes defining scope. Use these questions:

```
"Should I support custom aliases?"           → Yes
"Do URLs expire? Who controls TTL?"          → User-defined, default 1 year
"Do we need analytics (click tracking)?"     → Yes — clicks, geography, device
"Should the same long URL always produce
  the same short code? (dedup)"              → Yes, per user
"Do we need user authentication?"            → Out of scope for now
"What's the expected scale?"                 → 100M new URLs/day, 10B reads/day
```

## Functional Requirements

| Requirement | Details | Priority |
|-------------|---------|----------|
| Shorten URL | Given long URL → unique 7-char short code | Must |
| Redirect | Short URL → 302 redirect to long URL | Must |
| Custom alias | User-defined short code (e.g. `tiny.url/my-brand`) | Should |
| Expiry / TTL | URLs expire after configurable time (default: 1 year) | Should |
| Click analytics | Count clicks, country, device, referer per URL | Nice |
| Delete URL | Owner can deactivate a short URL | Nice |

**Explicitly out of scope:** User accounts (auth/login), real-time analytics dashboard, AB testing.

## Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| Availability | 99.99% (52 min downtime/year) | Redirects are on critical path — broken links = lost revenue |
| Redirect latency | p99 < 20ms globally | Users abandon if redirect takes >100ms |
| Write latency | p99 < 200ms | URL creation is infrequent, tolerate more latency |
| Read:Write ratio | ~8,000:1 (after caching) | Extreme read skew drives caching strategy |
| Durability | No URL loss after creation | URL deletion = intentional only |
| Eventual consistency | Acceptable for click counts | Counts don't need to be real-time exact |

## Back-of-Envelope Estimation

```
Daily scale:
  New URLs:    100M / day
  Redirects: 10,000M / day (10B)
  Read:Write = 100:1

QPS:
  Write QPS = 100,000,000 / 86,400        ≈ 1,200 writes/sec
  Read  QPS = 10,000,000,000 / 86,400     ≈ 115,000 reads/sec
  Peak  QPS = 3x average                  ≈ 345,000 reads/sec

Short code namespace:
  7 chars × base62 = 62^7 = 3.5 trillion unique codes
  At 1,200 writes/sec → 37.8 billion codes/year
  3.5 trillion / 37.8 billion ≈ 92 years of capacity ✓

Storage (5 years):
  Per record: ~500 bytes (short_code + long_url avg 200 chars + metadata)
  Records:    1,200/sec × 86,400 × 365 × 5 = 189 billion records
  Total:      189B × 500 bytes ≈ 95 TB

Cache sizing (80/20 rule):
  20% of URLs drive 80% of traffic
  20% of 189B = 37.8B URLs... but hot URLs are much smaller subset
  Top 10M hot URLs × 500 bytes = 5 GB → fits in a single Redis node
  Top 100M hot URLs × 500 bytes = 50 GB → Redis cluster

Bandwidth:
  Read:  115,000 req/sec × 500 bytes response = 57 MB/s outbound
  Write: 1,200 req/sec × 2 KB request = 2.4 MB/s inbound
```

## The Five Key Design Decisions

Before drawing any architecture, raise these with the interviewer:

```
1. Short code generation strategy
   Hash-based (MD5 + truncate):  Simple, collision-prone, predictable
   Snowflake ID + base62:        No collisions, distributed, recommended
   Pre-generated pool:           Complex ops, no runtime generation needed

2. 301 vs 302 redirect
   301 Permanent: browser caches → lower server load, breaks click tracking
   302 Temporary: every click hits server → enables full analytics
   Decision: 302 for analytics capability

3. Database choice
   PostgreSQL:  Simple, ACID, can't scale to 95 TB
   Cassandra:   High write throughput, TTL support, horizontal scale
   DynamoDB:    Managed Cassandra alternative, pay-per-request
   Decision: Cassandra (or DynamoDB if managed is preferred)

4. Caching strategy
   Redis with read-through cache, 24h TTL
   CDN for top redirects (edge caching at 60+ PoPs)
   Negative cache for non-existent/expired codes

5. URL deduplication
   Same long URL from same user → same short code?
   Option A: Always new code (simple, bloats DB)
   Option B: Hash long URL, check reverse index (dedup, extra lookup)
   Decision: Dedup with reverse index for same user
```

> 💡 **FAANG tip:** Interviewers want to see you raise these decisions proactively, not wait to be asked. Naming the 301 vs 302 tradeoff and the Snowflake ID approach in the first 10 minutes signals you've designed real URL shorteners before.
MD,
    ],

    '16:2' => [
        'objectives' => [
            'Compare all short code generation strategies and their collision properties',
            'Implement Snowflake ID generation and base62 encoding step by step',
            'Design the Key Generation Service (KGS) as a scalable, pre-generation alternative',
        ],
        'body' => <<<MD
## Short Code Generation — Full Comparison

| Strategy | Collision Risk | Predictable | Distributed | Recommended |
|----------|---------------|-------------|-------------|------------|
| MD5 + truncate | Medium (birthday problem) | Yes | Yes | No |
| Snowflake + base62 | None (by construction) | Slightly | Yes | **Yes** |
| Secure random | Low (probability-based) | No | Yes | Only if security critical |
| Pre-generated pool (KGS) | None | No | Via DB | Yes (for highest scale) |

## Option A — MD5/SHA256 Hash + Truncation

```python
import hashlib, base64

def shorten_md5(long_url: str) -> str:
    hash_bytes = hashlib.md5(long_url.encode()).digest()
    # Base64-encode, take first 7 URL-safe chars
    return base64.urlsafe_b64encode(hash_bytes)[:7].decode()

# Problem: "same URL → same code" only within same namespace
# Collision example:
#   md5("https://a.com") = "0cc175b9..."
#   md5("https://b.com") = "92eb5ffe..."
#   Both truncate to "abc1234" → COLLISION → need DB check + retry
```

**Why MD5 truncation fails at scale:**
```
Birthday problem: with 7-char base62 (3.5T codes), collisions start
appearing at ~59M insertions (√(3.5T × ln2) ≈ 49M)

At 100M URLs/day → collisions from day 1.
Every write requires: generate code → check DB → collision? → retry loop
Under high write load, retry storms amplify latency.
```

## Option B — Snowflake ID + Base62 (Recommended)

### Snowflake ID Structure (Twitter, 2010)

```
64-bit integer layout:
┌─────────────────────┬──────────────┬─────────────────┐
│   41 bits           │   10 bits    │   12 bits       │
│   Timestamp (ms)    │  Machine ID  │  Sequence #     │
│   since epoch       │  (0-1023)    │  per ms (0-4095)│
└─────────────────────┴──────────────┴─────────────────┘

Capacity:
  Timestamp: 2^41 ms = 69 years of uniqueness
  Machines:  2^10 = 1,024 generator nodes
  Per node:  2^12 = 4,096 IDs per millisecond
  Total:     4,096 × 1,024 × 1,000 = 4.19 billion IDs/second
```

### Python Implementation

```python
import time

class SnowflakeGenerator:
    EPOCH = 1672531200000  # 2023-01-01 00:00:00 UTC in ms
    MACHINE_ID = 1         # Set per deployment node (0-1023)

    def __init__(self):
        self.sequence = 0
        self.last_ms = -1

    def next_id(self) -> int:
        now_ms = int(time.time() * 1000)

        if now_ms == self.last_ms:
            self.sequence = (self.sequence + 1) & 0xFFF  # 12-bit mask
            if self.sequence == 0:
                # Sequence exhausted — wait for next millisecond
                while now_ms <= self.last_ms:
                    now_ms = int(time.time() * 1000)
        else:
            self.sequence = 0

        self.last_ms = now_ms
        return ((now_ms - self.EPOCH) << 22) | (self.MACHINE_ID << 12) | self.sequence


def to_base62(num: int) -> str:
    ALPHABET = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
    if num == 0:
        return ALPHABET[0]
    result = []
    while num:
        result.append(ALPHABET[num % 62])
        num //= 62
    return ''.join(reversed(result))


# Usage:
gen = SnowflakeGenerator()
snowflake_id = gen.next_id()           # e.g. 7234891234567
short_code = to_base62(snowflake_id)[:7]  # e.g. "aB3xK9m"
```

### Why Snowflake + base62 has no collisions

```
Snowflake IDs are unique by construction:
  - Timestamp ms + machine ID + sequence → guaranteed unique across all nodes
  - Base62 encoding is a bijection (1:1 mapping) — no two IDs produce same code
  - No DB lookup needed before insert — zero collision risk

Tradeoff: IDs are slightly sequential (time-based)
  → An attacker could enumerate codes (guess abc1234, abc1235...)
  Fix: shuffle the base62 alphabet (keep private) or add HMAC prefix
```

## Option C — Key Generation Service (KGS)

Pre-generate all 7-character base62 codes, store in a database, mark as used on demand.

```
KGS Database:
  Table: url_codes
  Columns: code TEXT, used BOOLEAN
  Pre-populated: all 3.5 trillion codes (offline batch job)

  SELECT code FROM url_codes WHERE used = false LIMIT 1000;
  UPDATE url_codes SET used = true WHERE code IN (...);

App Server flow:
  1. Fetch batch of 1,000 codes from KGS (in-memory pool per server)
  2. On URL creation → pop code from local pool
  3. When pool low → async refill from KGS
  4. KGS uses transactions to prevent double-assignment
```

**Tradeoffs:**
- No runtime generation — O(1) code assignment
- Single point of failure if KGS is down (fix: replicate KGS, use 2-phase assignment)
- Requires pre-population job (3.5T rows = large but feasible for SSD storage)
- Used by Bit.ly (they pre-generate codes in batches)

## Handling Custom Aliases

```sql
-- PostgreSQL (for smaller scale or metadata service):
INSERT INTO url_mappings (short_code, long_url, user_id)
VALUES ('my-brand', 'https://...', 123)
ON CONFLICT (short_code) DO NOTHING
RETURNING short_code;
-- NULL returned if conflict → respond 409

-- Cassandra (for high scale):
INSERT INTO url_mappings (short_code, long_url)
VALUES ('my-brand', 'https://...')
IF NOT EXISTS;
-- [applied] = false on conflict → respond 409

-- Rate limit custom aliases per user (prevent namespace squatting):
-- Redis: INCR user:\$userId:custom_aliases / day → max 10/day
```

## Alphabet Shuffling (Security Hardening)

```python
# Default base62 alphabet is enumerable: 0,1,2,3... → aB3xK9m,aB3xK9n...
# Shuffle the alphabet (deploy-time secret):

import random
DEFAULT = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz"
SHUFFLED = "mK7xZqP2NfGa..."  # shuffled at deploy, stored in config

def to_base62_secure(num: int) -> str:
    ALPHABET = SHUFFLED  # Use shuffled alphabet
    # Same algorithm, non-sequential output
```

> 💡 **FAANG tip:** The interviewer will often ask "what if two users request the same long URL?" — have a clear answer: either (a) always generate new code (simpler, more DB rows) or (b) lookup a `url_by_hash` reverse index and return the existing code (dedup). The tradeoff is one extra read per write vs. duplicate rows. For most systems, option (a) is fine unless storage is a concern.
MD,
    ],

    '16:3' => [
        'objectives' => [
            'Design the database partitioning strategy for 189 TB of URL data',
            'Build a multi-tier cache with CDN, Redis, and application-level caching',
            'Handle URL expiry, cleanup, and analytics at scale',
        ],
        'body' => <<<MD
## Database at Scale

### Why Cassandra?

```
Requirements:
  Writes: 1,200/sec sustained, 5,000/sec peak
  Reads:  115,000/sec (mostly cache hits, ~5,000 DB reads/sec after cache)
  Data:   189 TB over 5 years
  Pattern: write-once, read-many (no updates to existing URLs)

Cassandra fit:
  - Log-structured writes (LSM Tree) → high write throughput
  - Linear horizontal scaling — add nodes without downtime
  - Tunable consistency: QUORUM reads/writes for strong consistency
  - No single point of failure — all nodes equal
  - TTL support built-in: automatically expires rows

Alternative — DynamoDB:
  - Managed service (no ops overhead)
  - On-demand scaling
  - Slightly higher cost at extreme scale
  - Same eventual consistency model
```

### Partitioning Strategy

```
Partition key: short_code (already hash-distributed by base62)

Problem: sequential IDs would hot-spot one partition.
Solution: short_code itself is pseudo-random (base62 of Snowflake ID)
          → uniform distribution across Cassandra vnodes automatically

Replication: RF=3, all three data centers
  → Tolerate 1 full DC failure with LOCAL_QUORUM reads
  → Writes use LOCAL_QUORUM (2/3 replicas in local DC)

Table TTL:
  INSERT INTO url_mappings (short_code, long_url, expires_at)
  VALUES ('abc123', 'https://...', '2024-12-31')
  USING TTL 2592000;  -- 30 days in seconds
  -- Cassandra auto-deletes after TTL, no cleanup job needed
```

### Schema Finalized

```sql
-- Primary table (Cassandra)
CREATE TABLE url_mappings (
    short_code   TEXT,
    long_url     TEXT,
    user_id      UUID,
    created_at   TIMESTAMP,
    expires_at   TIMESTAMP,
    is_active    BOOLEAN,
    PRIMARY KEY  (short_code)
) WITH default_time_to_live = 0;   -- TTL set per-row

-- Click analytics (separate table, write-heavy)
CREATE TABLE url_clicks (
    short_code   TEXT,
    hour_bucket  TIMESTAMP,         -- truncated to hour
    country      TEXT,
    device       TEXT,
    click_count  COUNTER,
    PRIMARY KEY  ((short_code, hour_bucket), country, device)
);

-- Reverse index (long_url dedup)
CREATE TABLE url_by_hash (
    long_url_hash  TEXT PRIMARY KEY,  -- SHA256(long_url)[0:20]
    short_code     TEXT,
    long_url       TEXT
);
```

## Multi-Tier Cache Architecture

```
Request: GET /abc123

Tier 1 — CDN (CloudFront/Akamai)
  - Cache-Control: max-age=3600 (1 hour for 302 responses)
  - Hit rate: ~60% for popular URLs (80/20 rule — 20% URLs = 80% traffic)
  - Latency: ~5ms globally
  - Cost: eliminates 60% of origin server load

Tier 2 — Redis Cluster
  - Key: short_code → Value: long_url
  - TTL: 24 hours (re-validate at expiry)
  - Hit rate: ~35% of remaining traffic (35% of 40% = 14% of total)
  - Latency: ~1ms (in-region)
  - Memory: 200 GB cluster (top 1M URLs × 200 bytes/entry)

Tier 3 — Cassandra
  - Handles the remaining ~1% of requests (cold URLs, first-time visits)
  - Latency: ~10ms (SSD, single partition lookup)
  - 99% of traffic never reaches this tier
```

### Cache Implementation Details

```python
def get_long_url(short_code: str) -> str | None:
    # Tier 2: Redis
    cached = redis.get(f"url:{short_code}")
    if cached:
        return cached.decode()

    # Tier 3: Cassandra
    row = cassandra.execute(
        "SELECT long_url, expires_at, is_active FROM url_mappings WHERE short_code = %s",
        [short_code]
    ).one()

    if not row or not row.is_active:
        # Negative cache: prevent DB hammering for non-existent codes
        redis.setex(f"url:{short_code}", 300, "__NOT_FOUND__")
        return None

    if row.expires_at and row.expires_at < datetime.now():
        redis.setex(f"url:{short_code}", 60, "__EXPIRED__")
        return None

    # Cache the result
    ttl = min(86400, (row.expires_at - datetime.now()).seconds) if row.expires_at else 86400
    redis.setex(f"url:{short_code}", ttl, row.long_url)
    return row.long_url
```

### Negative Caching

**Problem:** Non-existent or expired codes hit Cassandra on every request.
**Solution:** Cache misses with a short TTL (300s) in Redis.

```
Request: GET /notexist
  Redis: GET url:notexist → miss
  Cassandra: SELECT → no row
  Redis: SET url:notexist "__NOT_FOUND__" EX 300
  Next 300s: Redis returns __NOT_FOUND__, no Cassandra hit
```

## URL Expiry and Cleanup

### TTL-based (Recommended for Cassandra)

```
Cassandra TTL handles deletion automatically:
  INSERT INTO url_mappings (...) USING TTL 2592000  -- 30 days
  After TTL: Cassandra marks row as tombstone, removes in compaction
  Redis: separate TTL matches or is shorter (auto-eviction)

Benefit: zero cleanup jobs, zero application logic
Cost: tombstones accumulate until compaction
```

### Lazy Deletion (for non-TTL stores)

```python
def redirect(short_code: str):
    url = get_long_url(short_code)
    if url == "__EXPIRED__" or (url and is_expired(short_code)):
        # Mark deleted asynchronously
        async_queue.send({"action": "expire", "code": short_code})
        return 410  # Gone
    return 302, url
```

### Background Cleanup Job

```python
# Runs daily — sweeps Cassandra for rows past expires_at
def cleanup_expired_urls():
    # Can't query by non-partition key efficiently in Cassandra
    # Solution: secondary index on expires_at (acceptable read cost for daily job)
    # OR: maintain a separate expiry queue (Kafka with time-based partitioning)

    expired = cassandra.execute(
        "SELECT short_code FROM url_expiry_queue WHERE expire_date = %s",
        [date.today().isoformat()]
    )
    for row in expired:
        cassandra.execute(
            "UPDATE url_mappings SET is_active = false WHERE short_code = %s",
            [row.short_code]
        )
        redis.delete(f"url:{row.short_code}")
```

## Analytics Pipeline

```
Every redirect:
  App Server → Kafka topic "url-clicks"
    { short_code, timestamp, ip, user_agent, referer }

Kafka consumer → Flink streaming job:
  - GeoIP lookup (ip → country)
  - User-agent parsing (device type)
  - Hourly aggregation → Cassandra url_clicks table
  - Real-time dashboard via WebSocket

Batch job (daily):
  - Spark aggregation of url_clicks → data warehouse (Redshift/BigQuery)
  - Top URLs, geographic distribution, device breakdown
  - Served via /api/v1/urls/{code}/stats
```

## Final Architecture Summary

```
Write:  Client → LB → App[Snowflake ID] → Cassandra[RF=3] + Redis[warm]
Read:   Client → CDN[60%] → Redis[35%] → Cassandra[5%] → 302 redirect
Expiry: Cassandra TTL + Redis TTL (synchronized)
Analytics: App → Kafka → Flink → Cassandra + Warehouse
```

> 💡 **FAANG tip:** Two senior-level details that impress: (1) **negative caching** — always cache misses to prevent DB amplification on non-existent codes, and (2) **CDN cache invalidation on delete** — when a URL is deleted, you must purge the CDN cache via CloudFront API (not just Redis), otherwise deleted URLs continue to redirect for up to 1 hour. Mention this proactively as a consistency edge case.
MD,
    ],

    // ── Consistent Hashing ────────────────────────────────────────────────────
    '12:0' => [
        'objectives' => [
            'Explain why modular hashing breaks when the cluster size changes',
            'Calculate the percentage of keys remapped when a node is added or removed',
            'Articulate why naive hashing causes thundering-herd cache misses at scale',
        ],
        'body' => <<<MD
## Naive Hashing: The Simple Approach

The simplest way to distribute data across N nodes:

```
node_index = hash(key) % N
```

**Example with N=4 nodes:**

```
hash("user:alice")  = 1,234,567  →  1,234,567 % 4 = 3  → Node 3
hash("user:bob")    = 8,901,234  →  8,901,234 % 4 = 2  → Node 2
hash("product:42")  = 5,678,901  →  5,678,901 % 4 = 1  → Node 1
```

This works perfectly — until the cluster changes.

## The Thundering Herd Problem

### Adding a Node (N=4 → N=5)

```
Before: hash(key) % 4
After:  hash(key) % 5

Let's check what happens to all keys:

key          | hash    | % 4 | % 5 | Same?
─────────────|─────────|─────|─────|──────
user:alice   | 1234567 |  3  |  2  |  ❌
user:bob     | 8901234 |  2  |  4  |  ❌
product:42   | 5678901 |  1  |  1  |  ✅  ← only 1 in 5 stays!
session:xyz  | 3456789 |  1  |  4  |  ❌
order:999    | 7890123 |  3  |  3  |  ✅  ← lucky
```

**Mathematical reality:** When N changes to N+1, approximately **(N-1)/N** keys remap to different nodes.

```
N=4 → N=5:  (4-1)/4 = 75% of keys remap
N=9 → N=10: (9-1)/9 = 89% of keys remap
N=99→N=100: 99% of keys remap
```

### Why This Causes Catastrophic Cache Misses

```
Scenario: Redis cache cluster, 4 nodes, warm cache (100% hit rate)
A new node is added to handle traffic growth

t=0:  Cache hit rate: 100%
t=1:  Node added, 75% of cache keys now point to wrong nodes
t=2:  Cache hit rate drops to ~25%

Effect:
  Before: 1M req/sec, DB load = 10K req/sec (90% cache hits)
  After:  1M req/sec, DB load = 750K req/sec ← DB overwhelmed

This is the "thundering herd" — the entire cache invalidates instantly,
smashing the database with 75× its normal load.
```

### Removing a Node (N=4 → N=3)

```
Before: hash(key) % 4
After:  hash(key) % 3

Remapping: (4-1)/4 = 75% of keys → different nodes again

Plus: The node being removed held data that must now be served.
  → All its keys will cache-miss until warmed on new nodes.
  → If it was a database shard: all its data must be migrated.
```

## Why Naive Hashing Fails at Scale

| Problem | Impact |
|---------|--------|
| **Cluster resize** | ~(N-1)/N of all keys remap — massive cache miss storm |
| **Node failure** | All data on failed node must be re-fetched from origin |
| **Rolling upgrade** | Can't take nodes out one-by-one without repeated mass remaps |
| **Auto-scaling** | Scaling in/out is dangerous — always causes thundering herd |
| **Non-uniform load** | Hash collisions can overload specific nodes ("hot spots") |

## Naive Hashing: When Is It OK?

Naive hashing works fine when:
- **N never changes** (fixed cluster, no scaling needed)
- **Data is stateless / cheap to recompute** (not a cache — a routing rule)
- **Data migration cost is acceptable** (offline batch jobs, not live traffic)

For everything else (live caches, database sharding, session stores) you need **consistent hashing** — which limits key remapping to ~1/N keys when the cluster size changes.

> 💡 **Interview setup:** When an interviewer asks "how would you shard your database across N nodes?", don't jump straight to consistent hashing. First explain naive hashing and its failure mode. Then propose consistent hashing as the solution. This shows you understand *why* the solution exists, not just *how* it works.
MD,
    ],

    '12:1' => [
        'objectives' => ['Explain the consistent hash ring','Describe virtual nodes and why they are needed','Apply consistent hashing to cache and database sharding'],
        'body' => <<<MD
## The Problem with Naive Hashing

Simple approach: `server = hash(key) % N` where N = number of servers.

**Problem:** When N changes (server added or removed), almost all keys map to different servers → **massive cache invalidation** or **data migration**.

```
N=3: key "user:123" → hash=456 % 3 = 0 → Server 0
N=4: key "user:123" → hash=456 % 4 = 2 → Server 2  ← DIFFERENT!
```

With N=3→4, approximately 75% of keys remap. For a cache, this means 75% cache miss rate instantly.

## The Consistent Hash Ring

```
                    0°
                    │
              Server A (90°)
           ╱               ╲
         ╱                   ╲
   270° ──   Hash Ring (0–360°)  ── 90°
         ╲                   ╱
           ╲               ╱
              Server B (270°)
                    │
                   180°
```

Each server is placed at a position on the ring using `hash(serverName)`.  
Each key is placed at `hash(key)`.  
A key is served by the **first server clockwise** from its position.

**Adding a server:** Only keys between the new server and its predecessor remapped (~1/N of keys).  
**Removing a server:** Only that server's keys remapped to the next clockwise server.

## Virtual Nodes

**Problem:** Real servers may cluster on the ring → uneven load distribution.

**Solution:** Each physical server gets K **virtual nodes** (K=100–200).

```
Physical:  Server A          → Virtual: A-1, A-2, A-3 ... A-150
Physical:  Server B          → Virtual: B-1, B-2, B-3 ... B-150
```

Virtual nodes spread evenly around the ring → uniform load distribution.

Benefit: When a server is removed, its load spreads proportionally to all remaining servers (not just the next one clockwise).

## Interview Applications

- **Distributed caching** (Memcached, Redis Cluster)
- **Database sharding** (Cassandra, DynamoDB)
- **Content delivery** (routing requests to CDN PoPs)
- **Load balancing** (consistent routing of user sessions)
MD,
    ],

    // ── Long-Polling, WebSockets & SSE ────────────────────────────────────────
    '13:0' => [
        'objectives' => [
            'Contrast short polling, long-polling, and streaming on latency and resource usage',
            'Explain the HTTP mechanics of long-polling and its connection lifecycle',
            'Choose the right real-time communication pattern for a given use case',
        ],
        'body' => <<<MD
## The Real-Time Data Problem

Traditional HTTP is **request-response**: client asks, server answers, connection closes.
For real-time data (notifications, chat, live scores), the client must somehow learn about server-side changes.

Three progressively better solutions:

## Short Polling (Naive)

```
Client:  GET /notifications  ──────────────────► Server
Server:  200 OK {"new": false} ◄───────────────── (empty)
                                  (wait 5 sec)
Client:  GET /notifications  ──────────────────► Server
Server:  200 OK {"new": false} ◄───────────────── (empty)
                                  (wait 5 sec)
Client:  GET /notifications  ──────────────────► Server
Server:  200 OK {"new": true, data: ...} ◄──────── (data!)
```

**Implementation:** `setInterval(() => fetch('/notifications'), 5000)`

| Metric | Impact |
|--------|--------|
| Latency | Up to 5 seconds (poll interval) |
| Server load | N clients x (1 request / 5 sec) = high at scale |
| Wasted requests | ~99% return empty responses |
| Connection overhead | Full TCP + TLS handshake per poll |

**Use:** Only for very low-frequency updates (metrics dashboards refreshing every minute)

## Long-Polling

Client holds the connection open; server responds only when data is available.

```
Client:  GET /notifications?timeout=30s ─────────────────► Server
                                    (server holds request, waits)
                                    (28 seconds later: event!)
Server:  200 OK {"event": "message", data: "..."} ◄──────── (respond)
Client:  GET /notifications?timeout=30s ─────────────────► Server
                                    (immediately re-opens)
```

**Server implementation (Node.js):**

```javascript
app.get('/notifications', async (req, res) => {
    const timeout = 30_000;  // 30 second max hold
    const startTime = Date.now();

    while (Date.now() - startTime < timeout) {
        const event = await eventQueue.poll(userId, 1000);
        if (event) {
            return res.json(event);
        }
    }
    res.status(204).send();  // Timeout — client will re-connect
});
```

**Connection timeline:**

```
t=0:   Client opens connection (TCP established, TLS handshake)
t=0:   Server holds request (thread or async handle blocked)
t=28:  Event arrives on server
t=28:  Server responds with event data
t=28:  Connection closes
t=28:  Client immediately opens new connection
```

| Metric | Impact |
|--------|--------|
| Latency | Near-real-time (~100ms) |
| Server load | 1 open connection per client (manageable with async I/O) |
| Wasted bandwidth | Low — only real events transmitted |
| Complexity | Medium — need timeout handling and reconnect logic |

## Short Polling vs Long-Polling vs WebSockets

| Dimension | Short Polling | Long-Polling | WebSockets |
|-----------|--------------|-------------|-----------|
| Latency | Poll interval (1-30s) | ~100ms | ~10ms |
| Server connections | Burst (new conn per poll) | Sustained (1 per client) | Persistent (1 per client) |
| Empty responses | Majority | None | None |
| Bidirectional | No | No | Yes |
| Works with standard LBs | Yes | Yes | Requires sticky sessions |
| Server-side complexity | Low | Medium | High |
| Best for | Low-frequency updates | Notifications, basic chat | Chat, gaming, trading |

## Long-Polling in the Wild

```
Slack (early 2013):
  → Used long-polling for real-time message delivery
  → Later migrated to WebSockets, but LP was production-ready for years

GitHub Live:
  → PR comment counts and CI status use long-polling
  → Avoids WebSocket complexity for infrequent updates
```

## Choosing a Real-Time Strategy

```
Is the update frequency < once per minute?  → Short polling
Does data only flow server → client?         → Long-polling or SSE
Do you need bidirectional communication?     → WebSockets
Is sub-100ms latency critical?               → WebSockets
Are you behind HTTP/2 infrastructure?        → SSE (multiplexed)
Is implementation simplicity top priority?   → SSE
```

> 💡 **FAANG tip:** Most interviewers accept long-polling for notification systems unless they specify latency < 1 second. For live chat, trading, or collaborative editing — jump straight to WebSockets.
MD,
    ],

    '13:1' => [
        'objectives' => [
            'Explain the HTTP upgrade handshake and WebSocket frame format',
            'Describe how to scale WebSocket connections horizontally with a message broker',
            'Identify which FAANG products use WebSockets and why',
        ],
        'body' => <<<MD
## WebSockets: Full-Duplex Communication

HTTP/1.1 is half-duplex. WebSockets upgrade HTTP to a **persistent, full-duplex, low-overhead** channel — either side can send a message at any time.

## The Upgrade Handshake

```
Client to Server:
  GET /ws HTTP/1.1
  Host: chat.example.com
  Upgrade: websocket
  Connection: Upgrade
  Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==
  Sec-WebSocket-Version: 13

Server to Client:
  HTTP/1.1 101 Switching Protocols
  Upgrade: websocket
  Connection: Upgrade
  Sec-WebSocket-Accept: s3pPLMBiTxaQ9kYGzzhZRbK+xOo=
```

After the 101, the TCP connection is re-used for WebSocket framing — no more HTTP overhead.

## WebSocket Frame Format

```
  0                   1                   2                   3
  0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
 ├─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┼─┤
 │F│R│R│R│ opcode  │M│    Payload len    │  Extended length        │
 │I│S│S│S│         │A│                   │  (if len = 126 or 127)  │
 │N│V│V│V│         │S│                   │                         │
 └─┴─┴─┴─┴─────────┴─┴───────────────────┴─────────────────────────┘
```

- **Overhead per message:** 2-10 bytes (vs HTTP: 500-800 bytes header overhead)
- **Opcodes:** 0x1 text, 0x2 binary, 0x8 close, 0x9 ping, 0xA pong

## Scaling WebSockets Horizontally

**The problem:** WebSocket connections are stateful. A user's connection lives on server-1. If that user's message must reach a user on server-3, server-1 must know about it.

**Solution: Pub/Sub message broker (Redis or Kafka)**

```
User A ──WS──► WS-Server-1 ──pub──► Redis Pub/Sub ──sub──► WS-Server-2 ──WS──► User B
                    │                      │                      │
                    └──────────────────────┴──────────────────────┘
                          All servers subscribe to all rooms/channels
```

**Implementation (Node.js + Redis):**

```javascript
const sub = redis.createClient();

wss.on('connection', (ws, req) => {
    const userId = getUserId(req);
    connections.set(userId, ws);

    sub.subscribe(`user:\${userId}`, (message) => {
        ws.send(message);
    });
});

async function sendToUser(userId, data) {
    await pub.publish(`user:\${userId}`, JSON.stringify(data));
}
```

## Connection Lifecycle with Heartbeat

```
Client              WS Server
  │                     │
  │──── ping ──────────►│ (every 30s)
  │◄─── pong ───────────│
  │                     │
  │ (no pong for 60s)   │
  │◄─── close frame ────│ (server closes dead connection)
  │──── reconnect ──────►│
```

## WebSocket at FAANG Scale

| Product | Use Case | Scale |
|---------|----------|-------|
| **Discord** | Real-time chat, voice signaling | 15M+ concurrent WS connections per gateway cluster |
| **Slack** | Message delivery, presence, typing | Per-workspace WS connections |
| **Google Docs** | Collaborative editing (OT/CRDT) | Document-scoped WS rooms |
| **Robinhood** | Live stock price ticks | Per-symbol subscriptions |
| **Figma** | Multi-user cursor and shape updates | CRDT synced over WebSockets |
| **Twitch** | Chat messages, viewer counts | Sharded by channel |

> 💡 **FAANG tip:** When designing a chat system or live trading platform, propose WebSockets over Redis Pub/Sub. Mention: (1) sticky sessions vs pub/sub tradeoff, (2) heartbeat/ping-pong for connection health, (3) Kafka for durable message history, and (4) sequence numbers for reconnect without missing messages.
MD,
    ],

    '13:2' => [
        'objectives' => [
            'Explain the SSE protocol and its automatic reconnection mechanism',
            'Compare SSE to WebSockets across latency, complexity, and infrastructure compatibility',
            'Choose SSE vs WebSockets for common use cases',
        ],
        'body' => <<<MD
## Server-Sent Events (SSE)

SSE is a **unidirectional** (server → client only) streaming protocol built on plain HTTP. The client opens a single HTTP connection and the server streams events indefinitely.

## Wire Format

```
Client to Server:
  GET /events HTTP/1.1
  Accept: text/event-stream
  Last-Event-ID: 42        (reconnect hint)

Server to Client (stream stays open):
  HTTP/1.1 200 OK
  Content-Type: text/event-stream
  Cache-Control: no-cache

  id: 43
  event: price-update
  data: {"symbol":"AAPL","price":185.20}

  id: 44
  event: price-update
  data: {"symbol":"GOOGL","price":142.80}

  : heartbeat comment (keeps connection alive)
```

**Fields:** `id:` event ID, `event:` type, `data:` payload, `:` comment/heartbeat

## Auto-Reconnection

SSE has **built-in reconnect** — the browser EventSource API handles it automatically:

```javascript
const source = new EventSource('/events');

source.addEventListener('price-update', (event) => {
    const data = JSON.parse(event.data);
    updateUI(data);
});

// Browser auto-reconnects with Last-Event-ID header
// Server resumes from that ID — no missed events if server buffers recent events
source.addEventListener('error', () => {
    console.log('Reconnecting...');
});
```

## Server Implementation

```javascript
app.get('/events', (req, res) => {
    const lastId = parseInt(req.headers['last-event-id'] || '0');

    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'X-Accel-Buffering': 'no',  // Disable Nginx buffering!
    });

    // Replay missed events
    const missed = eventStore.since(lastId);
    missed.forEach(e => sendEvent(res, e));

    const unsubscribe = eventBus.subscribe((event) => {
        sendEvent(res, event);
    });

    // Heartbeat every 15s to prevent proxy timeout
    const heartbeat = setInterval(() => {
        res.write(': heartbeat\n\n');
    }, 15_000);

    req.on('close', () => {
        clearInterval(heartbeat);
        unsubscribe();
    });
});
```

## SSE vs WebSockets: Decision Matrix

| Dimension | SSE | WebSockets |
|-----------|-----|-----------|
| Direction | Server → Client only | Bidirectional |
| Protocol | HTTP/1.1 or HTTP/2 | Custom (ws://) |
| Browser reconnect | Automatic | Manual |
| HTTP/2 multiplexing | Multiple SSE streams on one TCP | Single connection |
| Proxy/firewall compat | Standard HTTP | Some proxies block ws:// |
| Load balancer support | Standard LBs work | Requires sticky sessions or pub/sub |
| Client-to-server data | Separate HTTP requests | In-band |
| Implementation complexity | Low | Medium-High |

## When to Use SSE

**Choose SSE when:**
- Data only flows server → client (notifications, feeds, dashboards)
- You want browser auto-reconnect without client code
- Your infrastructure uses standard HTTP load balancers
- HTTP/2 is available (unlimited SSE streams multiplexed on one connection)

**SSE real-world examples:**

| Product | Use Case |
|---------|----------|
| GitHub | CI/CD status updates on PR pages |
| Jira | Board updates, sprint burndown live refresh |
| Grafana | Real-time metric dashboard panels |
| Stripe | Payment dashboard live event stream |

## HTTP/2 + SSE

```
HTTP/1.1 SSE limitation:
  Browser opens max 6 connections per domain
  → Max 6 concurrent SSE streams per tab

HTTP/2 SSE solution:
  Single TCP connection multiplexes all SSE streams
  → Unlimited concurrent streams, zero 6-connection limit
  → Nginx / Caddy / Envoy all support HTTP/2 SSE
```

> 💡 **FAANG tip:** For notification systems (GitHub notifications, Jira updates), SSE is often the right answer — simpler than WebSockets, automatic reconnect, and fully HTTP-compatible. Mention the `Last-Event-ID` replay mechanism to show you've thought about missed events during reconnect.
MD,
    ],

    // ── Bloom Filters & Probabilistic DS ─────────────────────────────────────
    '14:0' => [
        'objectives' => [
            'Explain how Bloom filters trade false positives for space efficiency',
            'Calculate optimal bit array size and hash function count',
            'Identify real-world systems that use Bloom filters and why',
        ],
        'body' => <<<MD
## The Membership Problem

**Question:** "How do you prevent loading non-existent rows from the database?"

**Naive:** Query the DB — pay the disk read cost every time.
**Hash set:** Keep all existing keys in memory → 1B keys x 8 bytes = 8 GB RAM.
**Bloom filter:** 1B keys → ~1.2 GB (10 bits/key) with 1% false positive rate.

## How a Bloom Filter Works

A Bloom filter is a **bit array** of M bits + **K hash functions**.

### Insert

```
Insert "user:alice":
  hash1("user:alice") = 23  → set bit[23] = 1
  hash2("user:alice") = 87  → set bit[87] = 1
  hash3("user:alice") = 142 → set bit[142] = 1
```

### Query

```
Query "user:alice":
  bit[23]=1 ✓, bit[87]=1 ✓, bit[142]=1 ✓
  → All bits set → MAYBE EXISTS (could be false positive)

Query "user:carol" (never inserted):
  bit[23]=1 ✓, bit[99]=0 ✗
  → Bit NOT set → DEFINITELY DOES NOT EXIST
```

**Key property:**
- **False negatives: impossible** — if inserted, all bits are set
- **False positives: possible** — another element's bits happen to collide

## Optimal Parameters

```
n = expected number of elements
p = desired false positive rate (e.g., 0.01 = 1%)

Optimal bit array size:
  m = -(n x ln p) / (ln 2)^2

Optimal hash functions:
  k = (m / n) x ln 2

Example (n=1,000,000, p=0.01):
  m = 9,585,058 bits ≈ 1.14 MB
  k ≈ 7 hash functions
```

**Memory comparison (1 billion entries):**

| Approach | Memory | False Positive Rate |
|----------|--------|-------------------|
| Hash Set (64-bit IDs) | 8 GB | 0% |
| Bloom Filter (10 bits/entry) | 1.25 GB | ~1% |
| Bloom Filter (7 bits/entry) | 875 MB | ~2% |
| Bloom Filter (5 bits/entry) | 625 MB | ~5% |

## Real-World Applications

### Cassandra — SSTable Membership

```
Problem: A read may need to check 10+ SSTable files on disk for a key.

Solution: Each SSTable has a Bloom filter in memory.
  Query: check filter → DEFINITELY NOT → skip file (no disk I/O)
                      → MAYBE → open file and check

Effect: Reduces disk I/O by 90%+ for non-existent key lookups
```

### Chrome Safe Browsing

```
Problem: 3M+ known malicious URLs — can't send all to every browser.

Solution: ~8MB Bloom filter downloaded to browser.
  Local check: MAYBE MALICIOUS → Google API check
               DEFINITELY SAFE → no network request (99.9% of URLs)
```

### Akamai CDN — One-Hit Wonders

```
Problem: 75% of web objects are requested only once.
         Caching them wastes RAM (cache pollution).

Solution: Bloom filter tracks "has this URL been seen before?"
  First request: NOT IN FILTER → don't cache, add to filter
  Second request: IN FILTER → cache it

Effect: Only cache items requested 2+ times → 3x cache hit rate improvement
```

### Redis — Built-in Bloom Filter Module

```bash
BF.RESERVE myfilter 0.01 1000000     # 1% FP rate, 1M capacity
BF.ADD myfilter "user:12345"
BF.EXISTS myfilter "user:12345"      # 1 (maybe exists)
BF.EXISTS myfilter "user:99999"      # 0 (definitely not exists)
```

### Other Applications

| Use Case | What's Filtered |
|----------|----------------|
| Web crawler | Already-crawled URLs |
| GitHub / HaveIBeenPwned | Known breached passwords |
| Ad servers | Recently shown ad IDs per user |
| Twitter / GitHub | Already-taken usernames |

## Counting Bloom Filter (Supports Deletion)

Standard Bloom filters can't delete (clearing a bit could affect other elements).
**Counting Bloom Filter:** Replace each bit with a 4-bit counter.

```
Insert: increment counter at each hash position
Delete: decrement counter at each hash position
Query:  if any counter = 0 → DEFINITELY NOT EXISTS
```

Cost: 4x memory vs standard Bloom filter.

## Cuckoo Filter (Modern Alternative)

- Supports deletion natively
- Better space efficiency (12 bits/element at same FP rate)
- Faster lookup (max 2 memory accesses vs k hash functions)
- Used in: FoundationDB, Apache Flink

> 💡 **FAANG tip:** "How do you prevent cache stampede on non-existent keys?" → Bloom filter in front of the cache. "What's the false positive implication?" → A false positive means we check the cache/DB unnecessarily — still correct, just slightly inefficient. False negatives are impossible, so we never incorrectly skip a real key.
MD,
    ],

    '14:1' => [
        'objectives' => [
            'Explain how Count-Min Sketch estimates frequency in sub-linear space',
            'Describe how HyperLogLog estimates cardinality using probabilistic bit tricks',
            'Apply these structures to trending, unique visitors, and heavy hitter scenarios',
        ],
        'body' => <<<MD
## Probabilistic Frequency and Cardinality

| Problem | Exact Solution | Space | Probabilistic | Space |
|---------|---------------|-------|--------------|-------|
| Count occurrences | HashMap | O(n) | **Count-Min Sketch** | O(1/epsilon x log 1/delta) |
| Count distinct elements | HashSet | O(n) | **HyperLogLog** | O(log log n) |

## Count-Min Sketch

**Problem:** In a stream of events (tweets, clicks, purchases), what is the frequency of each element? You can't store all distinct elements.

### Structure

A 2D array: `d` rows x `w` columns, each row uses a different hash function.

```
       col: 0   1   2   3   4   5   6   7
row 0: [    0   0   0   2   0   3   0   1  ]
row 1: [    0   1   0   0   0   2   0   2  ]
row 2: [    0   0   1   0   0   3   0   1  ]
```

### Update and Query

```
Update "tweet:abc":
  hash_0("tweet:abc")=5 → row[0][5]++
  hash_1("tweet:abc")=5 → row[1][5]++
  hash_2("tweet:abc")=5 → row[2][5]++

Query "tweet:abc":
  answer = min(row[0][5], row[1][5], row[2][5])
         = min(3, 2, 3) = 2  (may overcount, never undercount)
```

**Error bounds:**

```
Width:  w = ceil(e / epsilon)    where epsilon = error fraction
Depth:  d = ceil(ln(1/delta))    where delta = probability of error

Example: epsilon=0.1%, delta=1%
  w ≈ 2,719 columns
  d = 5 rows
  Memory: 5 x 2,719 x 4 bytes = ~54 KB

vs. exact HashMap for 1B distinct items ≈ 8-16 GB
```

### Applications

| Application | What's Counted |
|-------------|---------------|
| Twitter trending | Tweet frequency per hashtag |
| DDoS detection | Requests per IP per minute |
| Ad frequency cap | Impressions per (user, ad) pair |
| Heavy hitters | Top-K most frequent elements |

**Top-K Heavy Hitters:** Count-Min Sketch + min-heap of size K = find top K frequent elements in a stream.

## HyperLogLog (HLL)

**Problem:** How many unique users visited this page today? You can't store 100M user IDs in RAM.

### The Bit Trick

In a random binary string, the probability of observing k leading zeros is 1/2^k.
If your max leading zeros is 10, you've likely seen ~2^10 = 1,024 unique elements.

### HLL Algorithm

```
1. Hash each element to a 64-bit hash
2. First b bits → bucket index
3. Remaining bits → count leading zeros, update bucket max
4. Harmonic mean of all bucket estimates → final cardinality

Buckets:  m = 2^14 = 16,384 buckets
Memory:   16,384 x 5 bits = ~10 KB

Accuracy: +/-1.04 / sqrt(m) = +/-1.04 / 128 ≈ +/-0.8% error
```

### Redis HyperLogLog

```bash
PFADD unique_visitors "user:alice" "user:bob" "user:carol"
PFCOUNT unique_visitors          # → 3

# Merge hourly into daily
PFADD visitors:hour1 "user:alice" "user:bob"
PFADD visitors:hour2 "user:carol" "user:dave"
PFMERGE visitors:daily visitors:hour1 visitors:hour2
PFCOUNT visitors:daily           # → 4

# Always 12 KB per HLL regardless of cardinality
```

### Applications

| Application | What's Counted |
|-------------|---------------|
| Analytics (GA, Segment) | Unique visitors per page |
| Elasticsearch | Cardinality aggregation |
| Apache Spark | approx_count_distinct() |
| Redis | Daily Active Users |
| CDN analytics | Unique IP addresses |

## Choosing the Right Structure

```
"How many times did event X occur?"
  → Count-Min Sketch

"What are the top-K most frequent events?"
  → Count-Min Sketch + min-heap

"How many distinct events occurred?"
  → HyperLogLog

"Is event X in the set?"
  → Bloom Filter

"Has event X occurred in the last N minutes?"
  → Sliding window Count-Min Sketch or time-bucketed Bloom filters
```

> 💡 **FAANG tip:** For "build a trending topics feature" — say: Count-Min Sketch for per-hashtag frequency + min-heap for top-K, updated in a sliding window (24h in 1-hour buckets). Mention that estimates within 0.1% are indistinguishable from exact in practice.
MD,
    ],

    // ── Quorum & Leader Election ───────────────────────────────────────────────
    '15:0' => [
        'objectives' => [
            'Derive the quorum formula W + R > N and explain why it guarantees strong consistency',
            'Contrast sloppy quorum and strict quorum with hinted handoff',
            'Design read and write paths for a distributed database using quorum',
        ],
        'body' => <<<MD
## Why Quorum?

In a replicated system, reads and writes go to multiple replicas. **Quorum** defines how many replicas must acknowledge an operation before it is considered successful — balancing durability, consistency, and availability.

## The Quorum Formula

```
N = replication factor (number of replicas)
W = write quorum (replicas that must ACK a write)
R = read quorum (replicas that must respond to a read)

Strong consistency condition:
  W + R > N  →  guaranteed to read the latest write
```

**Why it works:**

```
N=3 replicas, W=2, R=2:

Write: written to replica 1 and replica 2 (quorum of 2/3)
Read:  must read from any 2 replicas

The write set {1,2} and read set must overlap by at least 1 replica.
That replica has the latest write → read always returns latest.

Proof: W + R > N → |write_set intersect read_set| >= W + R - N >= 1
```

## Common Quorum Configurations

| N | W | R | Behavior | Use Case |
|---|---|---|----------|---------|
| 3 | 2 | 2 | **Strong consistency** (standard) | Default for most data stores |
| 3 | 3 | 1 | Write all, read any — high write durability | Archive, append-only logs |
| 3 | 1 | 3 | Write fast, read all | Write-heavy workloads |
| 3 | 1 | 1 | **Eventual consistency** (AP mode) | Maximum availability |
| 5 | 3 | 3 | 5-node cluster quorum | Higher failure tolerance |

**Failure tolerance:** Can tolerate `min(N-W, N-R)` node failures.
- N=3, W=2, R=2: tolerate 1 node failure
- N=5, W=3, R=3: tolerate 2 node failures

## Write Path

```
Client ──── write("alice=25") ──────────────────────────────► Coordinator
                                                                   │
                    ┌──────────────────────────────────────────────┤
                    ▼                    ▼                          ▼
              Replica-1             Replica-2                Replica-3
              (ACK ✓)              (ACK ✓)                  (timeout ✗)
                    └──────────┬─────────┘
                               │
                    W=2 ACKs received → return 200 OK to client
                    Replica-3 syncs via read repair or anti-entropy
```

## Read Path with Read Repair

```
Client ──── read("alice") ──────────────────────────────────► Coordinator
                                                                   │
                    ┌──────────────────────────────────────────────┤
                    ▼                    ▼                          ▼
              Replica-1             Replica-2                Replica-3
              val=25 (v3)           val=25 (v3)              val=20 (v2) stale
                    └──────────┬─────────┘──────────────────────────┘
                               │
                    R=2 fastest responses → compare versions → return v3
                    Background: send v3 to Replica-3 (read repair)
```

## Sloppy Quorum & Hinted Handoff

**Strict quorum:** Reads/writes must contact the correct replicas for a key. If those replicas are down, operation fails.

**Sloppy quorum (Dynamo/Cassandra):** Accept writes to any available node, not just the correct ones.

```
Key "user:alice" should live on Replicas {A, B, C}.
Replica C is temporarily down.

Sloppy quorum (W=2):
  Write to A (correct) + write to D (stand-in)
  D stores: {value, hint: "this belongs to C"}

When C recovers:
  D sends hinted value to C (hinted handoff) then deletes its copy
```

**Trade-off:** Higher availability during failures, but briefly stale reads from C after recovery.

## DynamoDB Consistency Modes

| Mode | Latency | Consistency |
|------|---------|------------|
| Eventually Consistent Read (default) | ~1ms | Stale by up to 1 replica lag |
| Strongly Consistent Read | ~5ms | Always latest (W+R>N) |
| DynamoDB Transactions | ~10ms | ACID across multiple items |

## Interview Answer

**"How do you ensure your database read always returns the latest write?"**

```
Answer: "I'd use N=3, W=2, R=2.

W + R = 4 > N = 3 → write set and read set overlap by at least 1 replica.
That overlapping replica has the latest write.

The coordinator compares version timestamps across R=2 responses
and returns the highest. It triggers async read repair on stale replicas.

This tolerates 1 replica failure. For a 5-node cluster tolerating 2 failures:
N=5, W=3, R=3."
```

> 💡 **FAANG tip:** Cassandra's `QUORUM` consistency level maps exactly to W + R > N. `LOCAL_QUORUM` applies quorum only within a data center — used in geo-distributed setups to avoid cross-DC write latency while still ensuring strong consistency within a region.
MD,
    ],

    '15:1' => [
        'objectives' => [
            'Explain why distributed systems need leader election and the consequences of split-brain',
            'Describe Raft consensus: leader election, term numbers, and log replication',
            'Apply Zookeeper/etcd leader election to real distributed services',
        ],
        'body' => <<<MD
## Why Leader Election?

Many distributed systems need a **single writer** or **coordinator** to prevent conflicts:

| System | What the Leader Does |
|--------|---------------------|
| Kafka broker cluster | One leader per partition — handles all reads/writes |
| HBase | One master — manages region assignment |
| Kubernetes | kube-controller-manager: one active instance |
| Redis Sentinel | Leader sentinel initiates failover |
| Distributed cron | One scheduler — prevents duplicate job execution |

**Without leader election:** Multiple nodes think they're the leader → **split-brain** → data corruption, duplicate processing.

## The Split-Brain Problem

```
Normal:                       Network partition:
  ┌──────┐                    ┌──────┐         ┌──────┐
  │Leader│──follow──► F1      │Leader│  ✗✗✗   │ L'   │ F1 elected itself!
  │      │──follow──► F2      │      │  ✗✗✗   │      │
  └──────┘                    └──────┘         └──────┘

Both L and L' think they're leader → diverged state.
```

**Fix:** Leader can only lead if it has quorum (majority of nodes). Minority partition cannot form quorum → stops accepting writes.

## Raft Consensus Algorithm

Raft is the most teachable consensus algorithm (Ongaro, 2013). Used by etcd, CockroachDB, TiKV, Consul.

### Node States and Transitions

```
    ┌──────────────┐    timeout    ┌──────────────┐
    │   Follower   │──────────────►│  Candidate   │
    └──────────────┘               └──────────────┘
           ▲                              │ majority votes
           │  leader heartbeat            ▼
           │                      ┌──────────────┐
           └──────────────────────│    Leader    │
                                  └──────────────┘
```

### Term Numbers

```
Term = monotonically increasing logical clock. Each election starts a new term.

Term 1: Node A is leader (nodes B, C follow)
         Network partition → A isolated
Term 2: B and C hold election → B wins (majority: 2/3)
         B becomes leader for term 2

When A recovers:
  A sees B's heartbeat with term=2 > its own term=1
  A immediately steps down and follows B
```

### Leader Election

```
1. Follower's election timeout fires (150-300ms, randomized)
2. Follower increments term, becomes Candidate
3. Sends RequestVote RPCs to all other nodes
4. Each node votes for the first Candidate per term
5. Candidate receives majority → becomes Leader
6. Sends AppendEntries heartbeats to suppress new elections
```

**Why random timeouts?** Prevent all followers starting elections simultaneously (vote splitting). Random 150-300ms ensures one starts first.

### Log Replication

```
Client ──── write("x=5") ────────────────────────────────► Leader
                                                             │
                    AppendEntries{term:2, idx:7, entry:"x=5"}
                              ▼                ▼
                         Follower-B      Follower-C
                         (ACK ✓)         (ACK ✓)
                              └──────┬──────┘
                                     │ Majority ACK
                                     ▼
                    Leader commits entry #7 → returns success to client
                    Sends commit notification to followers
```

## Zookeeper Ephemeral Nodes for Leader Election

```java
// Each candidate creates an ephemeral sequential znode
String path = zk.create("/election/candidate-", nodeId.getBytes(),
    Ids.OPEN_ACL_UNSAFE, CreateMode.EPHEMERAL_SEQUENTIAL);
// Creates: /election/candidate-0000000001

List<String> candidates = zk.getChildren("/election", watcher);
Collections.sort(candidates);

String leader = candidates.get(0);
if (path.endsWith(leader)) {
    becomeLeader();
} else {
    // Watch predecessor (not leader) — avoids herd effect
    String predecessor = candidates.get(candidates.indexOf(myNode) - 1);
    zk.getData("/election/" + predecessor, true, null);
    becomeFollower();
}
```

**Why watch predecessor instead of leader?** If everyone watches the leader, when it dies N-1 nodes all get notified simultaneously (thundering herd). Watching predecessor means only the next-in-line gets notified.

**Ephemeral node:** When leader crashes, Zookeeper auto-deletes its ephemeral node → triggers watcher on next node → triggers election.

## etcd Leader Election

```go
sess, _ := concurrency.NewSession(client)
election := concurrency.NewElection(sess, "/leader")

// Campaign — blocks until this node becomes leader
election.Campaign(context.Background(), "node-id")

// Now the leader. Do leader work.
go doLeaderWork()
```

## Real Systems Using Leader Election

| System | Mechanism | Leader's Role |
|--------|-----------|--------------|
| **Kafka** | Zookeeper (KRaft in 3.x) | Partition leader — all reads/writes |
| **etcd** | Raft (native) | Key-value writes, cluster state |
| **Kubernetes** | etcd + leader election API | Scheduler, controller-manager |
| **Elasticsearch** | Raft-based (since 7.x) | Master: index/shard management |
| **Redis Sentinel** | Quorum-based | Triggers failover + promotes replica |
| **PostgreSQL (Patroni)** | etcd/Consul + STONITH | Primary accepts writes |
| **CockroachDB** | Raft per-range | Range lease holder |

> 💡 **FAANG tip:** When designing any system with a master or coordinator component, address leader election explicitly: "I'd use etcd's leader election primitive, backed by Raft. This gives us a single leader, automatic failover in <500ms, and split-brain protection via quorum. The leader's lease expires if it loses contact with etcd, so it voluntarily steps down — preventing stale leadership."
MD,
    ],

    // ── Pastebin ──────────────────────────────────────────────────────────────
    '17:0' => [
        'objectives' => [
            'Define functional and non-functional requirements for Pastebin with precise scope',
            'Perform back-of-envelope estimation for QPS, storage, and bandwidth',
            'Identify the key design differences between Pastebin and a URL shortener',
        ],
        'body' => <<<MD
## Clarifying Requirements

Start by asking the interviewer these questions:

```
"What is the max paste size?"           → 10 MB per paste
"Do pastes expire?"                     → Yes — 1 hour, 1 day, 1 week, 1 month, never
"Do we need user accounts?"             → Nice-to-have (anonymous pastes supported)
"Do we need syntax highlighting?"       → Yes (stored server-side by language tag)
"Should we support search?"             → No (out of scope)
"What's the expected scale?"            → 1M new pastes/day, 100M reads/day
```

## Functional Requirements

| Requirement | Details | Priority |
|-------------|---------|----------|
| Create paste | Plain text (up to 10 MB), optional title, language tag | Must |
| Read paste | Unique URL → render paste content | Must |
| Expiry | Per-paste TTL (1h / 1d / 1w / 1mo / never) | Must |
| Custom alias | User-defined URL slug (e.g. `paste.dev/my-snippet`) | Should |
| Delete paste | Owner can delete before expiry | Should |
| Syntax highlighting | Store language tag, render on client | Nice |
| Private paste | Password-protected or unlisted | Nice |

**Out of scope:** Full-text search, real-time collaboration, version history, comments.

## Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| Availability | 99.99% | Developers depend on Pastebin for sharing — downtime breaks workflows |
| Read latency | p99 < 50ms | Paste rendering must feel instant |
| Write latency | p99 < 200ms | Creation is less latency-sensitive |
| Durability | No data loss after creation | Lost code snippets are frustrating |
| Max paste size | 10 MB | Prevents abuse, covers 99.9% of legitimate use |
| Read:Write ratio | 100:1 | Heavily read-skewed — cache aggressively |

## Back-of-Envelope Estimation

```
Scale:
  New pastes:  1,000,000 / day
  Reads:     100,000,000 / day
  Read:Write = 100:1

QPS:
  Write QPS = 1,000,000 / 86,400 ≈ 12 writes/sec
  Read  QPS = 100,000,000 / 86,400 ≈ 1,160 reads/sec
  Peak (3x) = 3,500 reads/sec

Storage (5 years, avg paste = 10 KB):
  1M pastes/day × 365 × 5 = 1.825 billion pastes
  1.825B × 10 KB = 18.25 TB of paste content
  Plus metadata (~200 bytes/paste): 1.825B × 200 B ≈ 365 GB

  Total: ~18.6 TB — split:
    Object storage (S3): 18.25 TB  (paste content — cheap, durable)
    Relational DB (PostgreSQL): 365 GB  (metadata — needs queries)

Bandwidth:
  Read: 1,160 req/sec × 10 KB avg = 11.6 MB/s outbound
  Write: 12 req/sec × 10 KB = 120 KB/s inbound

Cache sizing:
  Hot pastes (top 10% drives 90% of reads): 182M pastes
  But truly hot pastes (viral snippets) are far fewer:
  Top 100K pastes × 10 KB = 1 GB → fits in a single Redis instance
```

## Pastebin vs URL Shortener — Key Differences

| Dimension | URL Shortener | Pastebin |
|-----------|--------------|---------|
| Payload size | ~200 bytes (just a URL) | Up to 10 MB (arbitrary text) |
| Storage strategy | All in DB | Metadata in DB, content in object storage |
| Cache strategy | Cache entire record | Cache metadata + lazy-load content |
| Expiry complexity | Simple TTL | Must handle 5 TTL tiers + manual delete |
| Write QPS | 1,200/sec (massive) | 12/sec (trivial) |
| Read QPS | 115,000/sec (massive) | 1,160/sec (moderate) |
| DB scale pressure | Very high (Cassandra) | Moderate (PostgreSQL fine) |

> 💡 **FAANG tip:** Pastebin's write QPS is 100× lower than TinyURL's — this is a key insight. Pastebin can use PostgreSQL instead of Cassandra, because it never hits the write throughput ceiling where you'd need LSM-Tree storage. The complexity shifts to the **object storage + metadata split** and the **expiry/purging system**, not the DB scale.
MD,
    ],

    '17:1' => [
        'objectives' => [
            'Design the two-tier storage architecture (object storage + metadata DB)',
            'Build the REST API and end-to-end request flows for create and read',
            'Design the paste ID generation and custom alias collision handling',
        ],
        'body' => <<<MD
## Architecture Overview

```
                     Write Path
Client ──► LB ──► Paste Service ──► PostgreSQL (metadata)
                        │
                        └──► S3 (paste content)

                     Read Path
Client ──► LB ──► Paste Service ──► Redis (metadata cache)
                        │                    │ miss
                        │               PostgreSQL
                        │
                        └──► CDN ──► S3 (content cache)
```

**Why split storage?**

```
Paste content = variable-size blob (1 byte to 10 MB)
  → Object storage (S3) is ideal: cheap, durable, content-addressed
  → Storing blobs in PostgreSQL causes table bloat and vacuuming issues

Paste metadata = structured, queryable
  → PostgreSQL: paste_id, user_id, created_at, expires_at, language, title
  → Indexed queries: fetch by paste_id (PK), filter by user_id, expire jobs
```

## Database Schema

```sql
-- PostgreSQL metadata table
CREATE TABLE pastes (
    paste_id     CHAR(8)      PRIMARY KEY,    -- base62-encoded ID
    user_id      UUID,                         -- NULL for anonymous
    title        VARCHAR(255),
    language     VARCHAR(50)  DEFAULT 'text',  -- syntax highlighting tag
    s3_key       VARCHAR(200) NOT NULL,        -- e.g. "pastes/ab/cd/abcd1234"
    size_bytes   INTEGER      NOT NULL,
    is_private   BOOLEAN      DEFAULT false,
    password_hash VARCHAR(60),                 -- bcrypt, if private
    created_at   TIMESTAMPTZ  DEFAULT NOW(),
    expires_at   TIMESTAMPTZ,                  -- NULL = never expires
    deleted_at   TIMESTAMPTZ                   -- soft delete
);

CREATE INDEX idx_pastes_user    ON pastes (user_id, created_at DESC);
CREATE INDEX idx_pastes_expires ON pastes (expires_at) WHERE expires_at IS NOT NULL;
CREATE INDEX idx_pastes_deleted ON pastes (deleted_at) WHERE deleted_at IS NULL;

-- Custom alias table (separate to avoid sparse columns in main table)
CREATE TABLE paste_aliases (
    alias        VARCHAR(50)  PRIMARY KEY,
    paste_id     CHAR(8)      NOT NULL REFERENCES pastes(paste_id),
    created_at   TIMESTAMPTZ  DEFAULT NOW()
);
```

## REST API

```
POST /api/v1/pastes
  Request:
    { "content": "...", "title": "my snippet", "language": "python",
      "expires_in": "1d", "alias": "my-snippet", "private": false }
  Response: 201 Created
    { "paste_id": "aB3xK9mZ", "url": "https://paste.dev/aB3xK9mZ",
      "expires_at": "2024-01-02T12:00:00Z" }
  Errors: 409 Conflict (alias taken), 413 Payload Too Large (>10MB)

GET /api/v1/pastes/{paste_id}
  Response: 200 OK
    { "paste_id": "aB3xK9mZ", "title": "my snippet", "language": "python",
      "content": "print('hello')", "created_at": "...", "expires_at": "..." }
  Errors: 404 Not Found, 410 Gone (expired), 403 Forbidden (private, no auth)

DELETE /api/v1/pastes/{paste_id}
  Auth: Bearer token (owner only)
  Response: 204 No Content

GET /api/v1/users/{user_id}/pastes
  Response: paginated list of paste metadata (no content)
```

## Paste ID Generation

```python
import secrets
import string

ALPHABET = string.ascii_letters + string.digits  # base62

def generate_paste_id(length: int = 8) -> str:
    # 62^8 = 218 trillion unique IDs
    # At 1M pastes/day × 365 × 5 = 1.825B total → collision negligible
    return ''.join(secrets.choice(ALPHABET) for _ in range(length))

# Why random (not Snowflake) for Pastebin?
# - Write rate is 12/sec — Snowflake's complexity not needed
# - Random IDs are non-enumerable (prevents scraping all pastes)
# - At 12 writes/sec, collision probability ≈ 1.825B / 218T ≈ 0.00084%
#   → Retry on collision (rare, one extra DB check per collision)
```

## Request Flows

### Create Paste

```
1. Client  → POST /api/v1/pastes { content: "...", language: "python", expires_in: "1d" }
2. Svc     → Validate: size <= 10MB, language in allowlist
3. Svc     → Generate paste_id (8-char random base62)
4. Svc     → Compute s3_key = "pastes/{paste_id[:2]}/{paste_id[2:4]}/{paste_id}"
5. Svc     → Upload content to S3:
               PUT s3://pastebin-content/pastes/aB/3x/aB3xK9mZ
               Content-Type: text/plain; charset=utf-8
6. Svc     → INSERT INTO pastes (paste_id, s3_key, language, expires_at, ...)
7. Svc     → Redis: SETEX paste:aB3xK9mZ 86400 {metadata_json}
8. Svc     → Return { paste_id, url, expires_at }
```

### Read Paste

```
1. Client  → GET /api/v1/pastes/aB3xK9mZ
2. Svc     → Redis: GET paste:aB3xK9mZ
   a. HIT  → Parse metadata, check expired/deleted
   b. MISS → SELECT * FROM pastes WHERE paste_id = 'aB3xK9mZ'
              → Redis: SETEX paste:aB3xK9mZ 3600 {metadata_json}

3. If expired (now > expires_at) → 410 Gone
4. If deleted → 404 Not Found
5. If private and no valid auth → 403 Forbidden

6. Svc     → Fetch content from CDN/S3:
   CDN HIT:  Return content directly (cached at edge)
   CDN MISS: Fetch from S3, CDN caches for TTL

7. Return { metadata + content }
```

## S3 Key Design

```
Naive:   s3://bucket/aB3xK9mZ
Problem: All 1.825B objects in one "directory" → S3 LIST operations slow

Better:  s3://bucket/pastes/aB/3x/aB3xK9mZ
         First 2 chars + next 2 chars as prefixes
         → 62×62 = 3,844 virtual directories
         → Each directory: 1.825B / 3,844 ≈ 475K objects
         → LIST operations fast, S3 prefix-parallel requests work well
```

## CDN Strategy for Content

```
S3 Origin + CloudFront CDN:
  Cache-Control: public, max-age=86400  (public pastes)
  Cache-Control: private, no-store      (private/password pastes)

Invalidation:
  On delete: CloudFront.create_invalidation(paths=["/pastes/aB3xK9mZ"])
  On expiry: Handled by cleanup job (expired URLs return 410 at app layer)

Benefit: Popular pastes (viral code snippets) served from 60+ PoPs globally
         → 50ms → 5ms for 80% of reads
```

> 💡 **FAANG tip:** The two-tier storage pattern (S3 for blobs, PostgreSQL for metadata) is used by Pastebin, GitHub Gists, Notion, and most document-oriented products. When an interviewer asks "why not put the content in the database?", say: "Large text blobs cause table bloat, vacuum pressure, and replication lag in PostgreSQL. S3 is 10x cheaper per GB, globally replicated, and designed for exactly this use case. The database should only store what it needs to query."
MD,
    ],

    '17:2' => [
        'objectives' => [
            'Design an efficient expiry system using a sorted set and background sweep',
            'Implement data partitioning to handle 18 TB across S3 and PostgreSQL',
            'Build a secure paste deletion flow with CDN invalidation',
        ],
        'body' => <<<MD
## The Expiry Problem

Pastebin supports 5 TTL tiers: 1 hour, 1 day, 1 week, 1 month, never.
Millions of pastes expire daily. Two approaches to cleanup:

### Approach 1 — Lazy Deletion (Simple)

```python
def get_paste(paste_id: str):
    paste = db.query("SELECT * FROM pastes WHERE paste_id = %s", [paste_id])
    if not paste:
        return 404

    # Check at read time
    if paste.expires_at and paste.expires_at < datetime.now():
        # Mark deleted (but don't block the response)
        async_queue.send({"action": "expire", "paste_id": paste_id})
        return 410  # Gone

    return paste
```

**Pros:** No background jobs, no scheduled tasks, simple code.
**Cons:** Expired rows accumulate in DB until read; storage not reclaimed immediately.

### Approach 2 — Eager Deletion with Expiry Queue (Production)

```
Expiry Queue: Redis Sorted Set
  Key:   "paste:expiry_queue"
  Score: Unix timestamp of expiry
  Member: paste_id

On paste creation:
  ZADD paste:expiry_queue 1706745600 "aB3xK9mZ"  -- expire at 2024-02-01

Background worker (runs every 60 seconds):
  now = time.time()
  expired = ZRANGEBYSCORE paste:expiry_queue 0 now LIMIT 0 1000
  for paste_id in expired:
    mark_deleted(paste_id)
    delete_s3_object(paste_id)
    ZREM paste:expiry_queue paste_id
    Redis.delete(f"paste:{paste_id}")
    CDN.invalidate(paste_id)
```

**Pros:** Storage reclaimed promptly, DB stays clean, S3 costs don't accumulate.
**Cons:** Worker must be highly available (use distributed cron with leader election).

### Hybrid Approach (Recommended)

```
1. Lazy check at read time (immediate user feedback: 410 Gone)
2. Background worker sweeps expired rows every 5 minutes
3. Cassandra/PostgreSQL TTL handles row expiry automatically at DB level

Sequence:
  t=0:    Paste created with expires_at = t+86400
  t+1h:   Paste read → lazy check → still valid → serve
  t+86400: Paste expires
  t+86400+5min: Background worker runs →
    → SELECT paste_id FROM pastes WHERE expires_at < NOW() AND deleted_at IS NULL LIMIT 1000
    → For each: soft-delete in PostgreSQL, delete S3 object, purge Redis/CDN
```

## Database Partitioning

### PostgreSQL Partitioning by Created Date

```sql
-- Range partition by month (manageable partition sizes)
CREATE TABLE pastes (
    paste_id    CHAR(8),
    created_at  TIMESTAMPTZ NOT NULL,
    ...
) PARTITION BY RANGE (created_at);

CREATE TABLE pastes_2024_01 PARTITION OF pastes
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE TABLE pastes_2024_02 PARTITION OF pastes
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');

-- Benefits:
-- 1. Old partitions can be archived/dropped without affecting active data
-- 2. Queries with created_at filter hit only relevant partition (partition pruning)
-- 3. Vacuum runs faster on smaller partition tables
-- 4. Expired pastes: DROP TABLE pastes_2024_01 (instant delete vs DELETE rows)
```

### S3 Partitioning

```
Prefix strategy: pastes/{year}/{month}/{paste_id[:2]}/{paste_id}

Example:
  pastes/2024/01/aB/aB3xK9mZ
  pastes/2024/01/zQ/zQ9rT2pL

Benefits:
  - Monthly prefix enables lifecycle rules (archive after 1 year to Glacier)
  - S3 Intelligent Tiering: auto-moves cold objects to cheaper storage class
  - Parallel LIST scans during cleanup jobs

S3 Lifecycle rules:
  - Objects in STANDARD → STANDARD_IA after 30 days (60% cost reduction)
  - Objects in STANDARD_IA → GLACIER after 1 year
  - DELETE after paste expiry (triggered by expiry worker via S3 DeleteObject)
```

## Secure Paste Deletion

### Soft Delete Flow

```python
def delete_paste(paste_id: str, user_id: str):
    # 1. Verify ownership
    paste = db.query(
        "SELECT user_id FROM pastes WHERE paste_id = %s AND deleted_at IS NULL",
        [paste_id]
    ).one()

    if not paste or paste.user_id != user_id:
        raise Forbidden("You don't own this paste")

    # 2. Soft delete in DB (preserve audit trail)
    db.execute(
        "UPDATE pastes SET deleted_at = NOW() WHERE paste_id = %s",
        [paste_id]
    )

    # 3. Invalidate cache immediately
    redis.delete(f"paste:{paste_id}")

    # 4. Purge CDN (async — takes 1-60 seconds)
    cloudfront.create_invalidation(paths=[f"/pastes/{paste_id}"])

    # 5. Delete S3 object (async — fire and forget)
    async_queue.send({"action": "delete_s3", "s3_key": paste.s3_key})
```

### Hard Delete (GDPR Compliance)

```python
def hard_delete_paste(paste_id: str):
    # Called by scheduled job 30 days after soft delete
    paste = db.query("SELECT s3_key FROM pastes WHERE paste_id = %s", [paste_id]).one()

    # Delete from S3 first (idempotent)
    s3.delete_object(Bucket="pastebin-content", Key=paste.s3_key)

    # Hard delete from DB
    db.execute("DELETE FROM pastes WHERE paste_id = %s", [paste_id])
    db.execute("DELETE FROM paste_aliases WHERE paste_id = %s", [paste_id])
```

## Abuse Prevention

```
Rate limiting (Redis):
  Anonymous: 10 pastes/hour per IP
    INCR ip:ratelimit:{ip}:{hour_bucket} → max 10 → EXPIRE 3600
  Authenticated: 100 pastes/hour per user_id

Content size validation:
  Check Content-Length header before reading body
  → Return 413 immediately if > 10MB

Malware scanning (async):
  Upload paste → virus scan via ClamAV or S3 object scan
  → Quarantine if malicious (soft-delete, flag for review)

URL scanning:
  If paste contains URLs → check against Google Safe Browsing API (async)
  → Flag paste if malicious URLs found

Paste visibility:
  Default: public (shareable by URL)
  Private: requires auth token to view
  Password-protected: bcrypt hash stored, checked at read time
```

## Caching Strategy Summary

```
Tier 1 — Redis (metadata cache):
  Key: paste:{paste_id}
  Value: JSON {paste_id, title, language, s3_key, expires_at, ...}
  TTL: min(paste TTL, 24 hours)
  Size: 1M hot pastes × 500 bytes = 500 MB → single Redis instance

Tier 2 — CDN (content cache):
  Origin: S3 presigned URL or CloudFront + S3 origin
  Cache-Control: public, max-age=3600 (for public pastes)
  Private pastes: Cache-Control: private, no-store (bypass CDN)

Tier 3 — S3 (durable object storage):
  All paste content — accessed only on CDN miss
  Latency: ~20ms (same-region S3)

Negative caching:
  Redis: SET paste:{paste_id} "__NOT_FOUND__" EX 300
  Prevents repeated DB lookups for deleted/expired paste IDs
```

## Full System Diagram

```
[Client]
    │
    ▼
[CDN — CloudFront]  ← 80% of read traffic served here
    │  miss
    ▼
[Load Balancer]
    │
    ▼
[Paste Service — stateless, 3-10 instances]
    │              │                  │
    ▼              ▼                  ▼
[Redis]     [PostgreSQL]           [S3]
(metadata     (metadata             (paste
 cache)        primary)              content)
    │
    ▼
[Expiry Worker — 1 active, leader-elected via Redis]
    │
    ├──► Sweep expired pastes → soft delete → S3 delete → CDN purge
    └──► Archive old partitions → S3 Glacier lifecycle
```

> 💡 **FAANG tip:** The expiry worker is a common "what about cleanup?" follow-up. Answer: "I use a Redis sorted set as a priority queue, scored by expiry timestamp. A background worker polls it every 60 seconds with ZRANGEBYSCORE 0 now LIMIT 1000. This is O(log N + K) per sweep and handles millions of expirations efficiently. The worker runs with leader election (Redis SETNX) to prevent duplicate deletes across instances."
MD,
    ],
    ];

    $key = "{$c}:{$l}";
    return $lib[$key] ?? [
        'objectives' => [
            "Understand the core concepts of: {TITLE}",
            "Identify key tradeoffs and design decisions",
            "Apply this knowledge in a system design interview",
        ],
        'body' => "## {TITLE}\n\nThis lesson covers the key concepts, tradeoffs, and interview patterns for **{TITLE}**.\n\n> Content for this lesson is being prepared. Check back soon!\n\n## Key Takeaways\n\n- Always clarify requirements before designing\n- Consider scale, availability, and consistency tradeoffs\n- Justify your technology choices with concrete reasoning\n- Know when to use SQL vs NoSQL, cache vs no cache\n\n## Interview Tips\n\n- Draw the architecture diagram first, then explain components\n- Think out loud — interviewers value your reasoning process\n- Acknowledge what you don't know and explain how you'd find out\n- Ask clarifying questions: read vs write heavy? consistency requirements?",
    ];
}
} // end if (!function_exists('lessonContent'))

$content = lessonContent($chapterId, $lessonIdx);
$body    = str_replace('{TITLE}', htmlspecialchars($lesson['title']), $content['body']);

// ── Simple Markdown → HTML renderer ─────────────────────────────────────────
if (!function_exists('mdToHtml')) {
function mdToHtml(string $md): string {
    $lines = explode("\n", $md);
    $html  = '';
    $inCode    = false;
    $inTable   = false;
    $inList    = false;
    $codeLines = [];

    foreach ($lines as $line) {
        // Code blocks
        if (str_starts_with($line, '```')) {
            if ($inCode) {
                $html   .= '<pre><code>' . htmlspecialchars(implode("\n", $codeLines)) . '</code></pre>';
                $codeLines = []; $inCode = false;
            } else {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $inCode = true;
            }
            continue;
        }
        if ($inCode) { $codeLines[] = $line; continue; }

        // Table rows
        if (str_starts_with(trim($line), '|')) {
            if (!$inTable) {
                if ($inList) { $html .= '</ul>'; $inList = false; }
                $html .= '<table>';
                $inTable = true;
            }
            if (preg_match('/^\|[-| ]+\|$/', trim($line))) continue; // separator row
            $cells = array_map('trim', explode('|', trim($line, '| ')));
            $tag   = ($html === '<table>' || substr_count($html, '<tr>') === 0) ? 'th' : 'td';
            $html .= '<tr>' . implode('', array_map(fn($c) => "<{$tag}>" . inlineMarkdown($c) . "</{$tag}>", $cells)) . '</tr>';
            continue;
        }
        if ($inTable) { $html .= '</table>'; $inTable = false; }

        // List items
        if (preg_match('/^[-*]\s+(.+)/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . inlineMarkdown($m[1]) . '</li>';
            continue;
        }
        if ($inList && trim($line) !== '') { /* continue list for wrapped lines */ }
        if ($inList && trim($line) === '') { $html .= '</ul>'; $inList = false; }

        // Headings
        if (preg_match('/^(#{1,4})\s+(.+)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $level = strlen($m[1]);
            $html .= "<h{$level}>" . inlineMarkdown($m[2]) . "</h{$level}>";
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s*(.*)/', $line, $m)) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<blockquote>' . inlineMarkdown($m[1]) . '</blockquote>';
            continue;
        }

        // Horizontal rule
        if (preg_match('/^---+$/', trim($line))) {
            $html .= '<hr>';
            continue;
        }

        // Empty line → paragraph break
        if (trim($line) === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '';
            continue;
        }

        // Normal paragraph line
        if (!$inList) {
            $html .= '<p>' . inlineMarkdown($line) . '</p>';
        }
    }

    if ($inList)  $html .= '</ul>';
    if ($inTable) $html .= '</table>';
    if ($inCode)  $html .= '<pre><code>' . htmlspecialchars(implode("\n", $codeLines)) . '</code></pre>';

    return $html;
}
} // end if (!function_exists('mdToHtml'))

if (!function_exists('inlineMarkdown')) {
function inlineMarkdown(string $s): string {
    $s = htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    // Bold
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    // Italic
    $s = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $s);
    // Inline code
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    // Links (already HTML-escaped, undo for href)
    $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $s);
    return $s;
}
} // end if (!function_exists('inlineMarkdown'))

$renderedBody = mdToHtml($body);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($lesson['title']) ?> — Grokking System Design</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:       #1F5EFF;
  --brand-dark:  #1648CC;
  --brand-light: #EEF4FF;
  --text-primary:#0F172A;
  --text-sec:    #475569;
  --text-muted:  #94A3B8;
  --border:      #E2E8F0;
  --bg:          #F8FAFC;
  --white:       #FFFFFF;
  --radius:      12px;
  --code-bg:     #0F172A;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  color: var(--text-primary);
  line-height: 1.7;
  min-height: 100vh;
}

/* ── TOP NAV ──────────────────────────────────────────────── */
.topnav {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 0 24px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.topnav-brand {
  font-weight: 800;
  font-size: 16px;
  color: var(--brand);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 8px;
}
.topnav-brand span { color: var(--text-primary); }
.topnav-back {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text-sec);
  text-decoration: none;
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  transition: all .15s;
}
.topnav-back:hover { background: var(--brand-light); color: var(--brand); border-color: var(--brand); }
.topnav-progress {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--text-muted);
}

/* ── LAYOUT ───────────────────────────────────────────────── */
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 40px 24px 80px;
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 40px;
  align-items: start;
}
@media (max-width: 800px) {
  .page { grid-template-columns: 1fr; }
  .lesson-toc { display: none; }
}

/* ── LEFT: TABLE OF CONTENTS (Chapter lessons) ────────────── */
.lesson-toc {
  position: sticky;
  top: 72px;
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
}
.toc-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--text-muted);
}
.toc-chapter-title {
  padding: 12px 16px 8px;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 8px;
}
.toc-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 16px;
  font-size: 13px;
  color: var(--text-sec);
  text-decoration: none;
  border-left: 3px solid transparent;
  transition: all .1s;
  line-height: 1.4;
}
.toc-item:hover { background: var(--bg); color: var(--brand); }
.toc-item.active {
  background: var(--brand-light);
  color: var(--brand);
  font-weight: 600;
  border-left-color: var(--brand);
}
.toc-dot {
  width: 18px; height: 18px; border-radius: 50%;
  border: 1.5px solid currentColor;
  display: flex; align-items: center; justify-content: center;
  font-size: 9px; flex-shrink: 0; margin-top: 1px;
}
.toc-item.active .toc-dot {
  background: var(--brand);
  border-color: var(--brand);
  color: #fff;
}

/* ── RIGHT: MAIN CONTENT ──────────────────────────────────── */
.lesson-content { min-width: 0; }

.lesson-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--text-muted);
  margin-bottom: 24px;
  flex-wrap: wrap;
}
.lesson-breadcrumb a { color: var(--brand); text-decoration: none; }
.lesson-breadcrumb a:hover { text-decoration: underline; }
.bc-sep { color: var(--border); }

.lesson-header {
  margin-bottom: 32px;
}
.lesson-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: 20px;
  margin-bottom: 14px;
  border: 1px solid;
}
.lesson-title {
  font-size: clamp(22px, 4vw, 32px);
  font-weight: 800;
  line-height: 1.2;
  color: var(--text-primary);
  margin-bottom: 16px;
}
.lesson-meta {
  display: flex;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}
.lesson-meta-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--text-muted);
}
.lesson-type-badge {
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .4px;
}
.badge-lesson  { background: #EFF6FF; color: #1D4ED8; }
.badge-quiz    { background: #FEF9C3; color: #A16207; }
.badge-project { background: #F0FDF4; color: #16A34A; }

/* ── OBJECTIVES BOX ───────────────────────────────────────── */
.objectives {
  background: linear-gradient(135deg, #EEF4FF, #F0F9FF);
  border: 1px solid #BFDBFE;
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-bottom: 32px;
}
.objectives h3 {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--brand);
  margin-bottom: 12px;
}
.objectives ul { list-style: none; }
.objectives li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 14px;
  color: var(--text-primary);
  padding: 4px 0;
}
.objectives li::before {
  content: '✓';
  color: var(--brand);
  font-weight: 800;
  flex-shrink: 0;
  margin-top: 1px;
}

/* ── SEPARATOR ────────────────────────────────────────────── */
.content-divider {
  height: 1px;
  background: var(--border);
  margin: 32px 0;
}

/* ── ARTICLE BODY ─────────────────────────────────────────── */
.lesson-body { font-size: 15px; }

.lesson-body h2 {
  font-size: 22px; font-weight: 700;
  color: var(--text-primary);
  margin: 32px 0 14px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}
.lesson-body h3 {
  font-size: 17px; font-weight: 700;
  color: var(--text-primary);
  margin: 24px 0 10px;
}
.lesson-body h4 {
  font-size: 15px; font-weight: 600;
  color: var(--text-primary);
  margin: 20px 0 8px;
}
.lesson-body p  { margin-bottom: 14px; color: var(--text-primary); }
.lesson-body ul { margin: 12px 0 16px 20px; }
.lesson-body li { margin-bottom: 6px; }

.lesson-body table {
  width: 100%;
  border-collapse: collapse;
  margin: 18px 0;
  font-size: 14px;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid var(--border);
}
.lesson-body th {
  background: #F1F5F9;
  padding: 10px 14px;
  text-align: left;
  font-weight: 700;
  font-size: 13px;
  color: var(--text-sec);
  border-bottom: 1px solid var(--border);
}
.lesson-body td {
  padding: 10px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: top;
}
.lesson-body tr:last-child td { border-bottom: none; }
.lesson-body tr:hover td { background: var(--bg); }

.lesson-body pre {
  background: var(--code-bg);
  border-radius: 10px;
  padding: 20px;
  overflow-x: auto;
  margin: 16px 0;
  position: relative;
}
.lesson-body code {
  font-family: 'SF Mono', 'Fira Code', Consolas, monospace;
  font-size: 13px;
  line-height: 1.6;
  color: #E2E8F0;
}
.lesson-body p code, .lesson-body li code {
  background: #F1F5F9;
  color: #C2410C;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 13px;
}

.lesson-body blockquote {
  border-left: 4px solid var(--brand);
  background: var(--brand-light);
  padding: 14px 18px;
  border-radius: 0 8px 8px 0;
  margin: 18px 0;
  color: var(--text-primary);
  font-size: 14px;
}

.lesson-body strong { font-weight: 700; }
.lesson-body em { font-style: italic; color: var(--text-sec); }
.lesson-body hr { border: none; border-top: 1px solid var(--border); margin: 24px 0; }

/* ── PREV / NEXT NAV ──────────────────────────────────────── */
.lesson-nav {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-top: 48px;
  padding-top: 32px;
  border-top: 1px solid var(--border);
}
.nav-card {
  display: flex;
  flex-direction: column;
  padding: 16px 20px;
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  text-decoration: none;
  color: inherit;
  transition: all .15s;
  min-width: 0;
}
.nav-card:hover {
  border-color: var(--brand);
  box-shadow: 0 4px 14px rgba(31,94,255,.12);
}
.nav-card.next { text-align: right; }
.nav-card.empty { visibility: hidden; }
.nav-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  color: var(--text-muted);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 6px;
}
.nav-card.next .nav-label { justify-content: flex-end; }
.nav-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-chapter {
  font-size: 12px;
  color: var(--text-muted);
  margin-top: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── MARK COMPLETE ────────────────────────────────────────── */
.complete-bar {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-top: 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}
.complete-bar p {
  font-size: 14px;
  color: var(--text-sec);
  margin: 0;
}
.complete-btn {
  padding: 10px 22px;
  background: #16A34A;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: background .15s;
  white-space: nowrap;
}
.complete-btn:hover { background: #15803D; }
.complete-btn.done {
  background: #F0FDF4;
  color: #16A34A;
  border: 1.5px solid #BBF7D0;
  cursor: default;
}
</style>
</head>
<body>

<!-- ─── TOP NAV ───────────────────────────────────────────────────── -->
<nav class="topnav">
  <a class="topnav-brand" href="index.php">
    📐 <span>Grokking System Design</span>
  </a>
  <div class="topnav-progress">
    Lesson <?= $lessonIdx + 1 ?> of <?= $totalItems ?> in this chapter
  </div>
  <a class="topnav-back" href="index.php">← Back to Roadmap</a>
</nav>

<!-- ─── PAGE LAYOUT ───────────────────────────────────────────────── -->
<div class="page">

  <!-- LEFT: CHAPTER TOC ─────────────────────────────────────────── -->
  <aside class="lesson-toc">
    <div class="toc-header">Chapter <?= str_pad($chapterPos + 1, 2, '0', STR_PAD_LEFT) ?></div>
    <div class="toc-chapter-title">
      <?= $chapter['icon'] ?> <?= htmlspecialchars($chapter['title']) ?>
    </div>
    <?php foreach ($chapter['items'] as $li => $lsn):
      $isActive = ($li === $lessonIdx);
      $tIcon = match($lsn['type']) { 'quiz' => '?', 'project' => '✦', default => '▶' };
    ?>
    <a class="toc-item <?= $isActive ? 'active' : '' ?>"
       href="lesson.php?c=<?= $chapter['id'] ?>&l=<?= $li ?>">
      <div class="toc-dot"><?= $isActive ? '✓' : $tIcon ?></div>
      <span><?= htmlspecialchars($lsn['title']) ?></span>
    </a>
    <?php endforeach; ?>
  </aside>

  <!-- RIGHT: LESSON CONTENT ─────────────────────────────────────── -->
  <main class="lesson-content">

    <!-- Breadcrumb -->
    <div class="lesson-breadcrumb">
      <a href="index.php">Roadmap</a>
      <span class="bc-sep">›</span>
      <span><?= htmlspecialchars($chapter['title']) ?></span>
      <span class="bc-sep">›</span>
      <span><?= htmlspecialchars($lesson['title']) ?></span>
    </div>

    <!-- Lesson Header -->
    <div class="lesson-header">
      <div class="lesson-tag" style="background:<?= $tc['bg'] ?>;color:<?= $tc['text'] ?>;border-color:<?= $tc['border'] ?>">
        <?= $chapter['icon'] ?> <?= $chapter['tag'] ?>
      </div>
      <h1 class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></h1>
      <div class="lesson-meta">
        <div class="lesson-meta-item">
          <span class="lesson-type-badge badge-<?= $lesson['type'] ?>"><?= $lesson['type'] ?></span>
        </div>
        <div class="lesson-meta-item">⏱ <?= $lesson['duration'] ?></div>
        <div class="lesson-meta-item">📖 Chapter <?= $chapterPos + 1 ?>, Lesson <?= $lessonIdx + 1 ?></div>
      </div>
    </div>

    <!-- Learning Objectives -->
    <div class="objectives">
      <h3>🎯 Learning Objectives</h3>
      <ul>
        <?php foreach ($content['objectives'] as $obj): ?>
        <li><?= htmlspecialchars(str_replace('{TITLE}', $lesson['title'], $obj)) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="content-divider"></div>

    <!-- Lesson Body -->
    <article class="lesson-body">
      <?= $renderedBody ?>
    </article>

    <!-- Mark Complete -->
    <div class="complete-bar">
      <p>Ready to move on? Mark this lesson complete to track your progress.</p>
      <button class="complete-btn" id="completeBtn" onclick="markComplete()">
        ✓ Mark as Complete
      </button>
    </div>

    <!-- Prev / Next -->
    <nav class="lesson-nav">
      <?php if ($prevUrl): ?>
      <?php
        // Find prev lesson title
        $prevTitle = ''; $prevChapterTitle = '';
        foreach ($roadmap as $ci => $ch) {
          foreach ($ch['items'] as $li => $lsn) {
            if ("lesson.php?c={$ch['id']}&l={$li}" === $prevUrl) {
              $prevTitle = $lsn['title'];
              $prevChapterTitle = $ch['title'];
            }
          }
        }
      ?>
      <a class="nav-card prev" href="<?= $prevUrl ?>">
        <div class="nav-label">← Previous</div>
        <div class="nav-title"><?= htmlspecialchars($prevTitle) ?></div>
        <div class="nav-chapter"><?= htmlspecialchars($prevChapterTitle) ?></div>
      </a>
      <?php else: ?>
      <div class="nav-card empty"></div>
      <?php endif; ?>

      <?php if ($nextUrl): ?>
      <?php
        $nextTitle = ''; $nextChapterTitle = '';
        foreach ($roadmap as $ci => $ch) {
          foreach ($ch['items'] as $li => $lsn) {
            if ("lesson.php?c={$ch['id']}&l={$li}" === $nextUrl) {
              $nextTitle = $lsn['title'];
              $nextChapterTitle = $ch['title'];
            }
          }
        }
      ?>
      <a class="nav-card next" href="<?= $nextUrl ?>">
        <div class="nav-label">Next →</div>
        <div class="nav-title"><?= htmlspecialchars($nextTitle) ?></div>
        <div class="nav-chapter"><?= htmlspecialchars($nextChapterTitle) ?></div>
      </a>
      <?php else: ?>
      <div class="nav-card next" style="background:#F0FDF4;border-color:#BBF7D0">
        <div class="nav-label" style="color:#16A34A">🎉 Course Complete!</div>
        <div class="nav-title" style="color:#16A34A">You've finished the roadmap</div>
        <div class="nav-chapter"><a href="index.php" style="color:#16A34A">← Back to Roadmap</a></div>
      </div>
      <?php endif; ?>
    </nav>

  </main>
</div>

<script>
function markComplete() {
  const btn = document.getElementById('completeBtn');
  btn.textContent = '✓ Completed!';
  btn.classList.add('done');
  // In a real app: AJAX call to save progress
}

// Keyboard navigation
document.addEventListener('keydown', e => {
  if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
  <?php if ($prevUrl): ?>
  if (e.key === 'ArrowLeft') window.location = '<?= $prevUrl ?>';
  <?php endif; ?>
  <?php if ($nextUrl): ?>
  if (e.key === 'ArrowRight') window.location = '<?= $nextUrl ?>';
  <?php endif; ?>
});
</script>
</body>
</html>
