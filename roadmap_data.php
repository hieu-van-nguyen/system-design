<?php
// ── Single source of truth for the 31-chapter / 95-lesson roadmap ────────────
// Shared by index.php, lesson.php, and build.php via require_once.
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
