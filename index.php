<?php
$roadmap = [
    [
        'id'       => 1,
        'title'    => 'Getting Started',
        'icon'     => '🚀',
        'tag'      => 'Introduction',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Course Overview',                            'type' => 'lesson',   'duration' => '5 min'],
            ['title' => 'How to Use This Course',                     'type' => 'lesson',   'duration' => '3 min'],
        ],
    ],
    [
        'id'       => 2,
        'title'    => 'System Design Basics',
        'icon'     => '⚙️',
        'tag'      => 'Fundamentals',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'What Is System Design?',                     'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'How to Approach a System Design Interview',  'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Quiz: System Design Basics',                 'type' => 'quiz',     'duration' => '5 min'],
        ],
    ],
    [
        'id'       => 3,
        'title'    => 'Key Characteristics of Distributed Systems',
        'icon'     => '🌐',
        'tag'      => 'Fundamentals',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Scalability',                                'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Reliability & Availability',                 'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Efficiency & Manageability',                 'type' => 'lesson',   'duration' => '6 min'],
        ],
    ],
    [
        'id'       => 4,
        'title'    => 'Load Balancing',
        'icon'     => '⚖️',
        'tag'      => 'Fundamentals',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'What Is Load Balancing?',                   'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Load Balancing Algorithms',                  'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Redundant Load Balancers',                   'type' => 'lesson',   'duration' => '5 min'],
        ],
    ],
    [
        'id'       => 5,
        'title'    => 'Caching',
        'icon'     => '⚡',
        'tag'      => 'Fundamentals',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Introduction to Caching',                   'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Cache Invalidation Strategies',             'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Cache Eviction Policies (LRU, LFU, FIFO)', 'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Quiz: Caching',                             'type' => 'quiz',     'duration' => '5 min'],
        ],
    ],
    [
        'id'       => 6,
        'title'    => 'Data Partitioning',
        'icon'     => '🗄️',
        'tag'      => 'Fundamentals',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Horizontal vs Vertical Partitioning',       'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Partitioning Methods (Range, Hash, Directory)', 'type' => 'lesson', 'duration' => '10 min'],
            ['title' => 'Partitioning Criteria & Common Problems',   'type' => 'lesson',   'duration' => '7 min'],
        ],
    ],
    [
        'id'       => 7,
        'title'    => 'Indexes',
        'icon'     => '📇',
        'tag'      => 'Fundamentals',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Database Indexes — How & When',             'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Dense vs Sparse Indexes',                   'type' => 'lesson',   'duration' => '5 min'],
        ],
    ],
    [
        'id'       => 8,
        'title'    => 'Proxies',
        'icon'     => '🔀',
        'tag'      => 'Fundamentals',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Forward vs Reverse Proxies',                'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'Proxy Use Cases & Caching',                 'type' => 'lesson',   'duration' => '6 min'],
        ],
    ],
    [
        'id'       => 9,
        'title'    => 'Redundancy & Replication',
        'icon'     => '🔁',
        'tag'      => 'Fundamentals',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Redundancy — Eliminating Single Points of Failure', 'type' => 'lesson', 'duration' => '6 min'],
            ['title' => 'Replication — Active-Passive & Active-Active',       'type' => 'lesson', 'duration' => '8 min'],
        ],
    ],
    [
        'id'       => 10,
        'title'    => 'SQL vs NoSQL',
        'icon'     => '🗃️',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Relational vs Non-Relational Databases',    'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Types of NoSQL (Document, Key-Value, Column, Graph)', 'type' => 'lesson', 'duration' => '10 min'],
            ['title' => 'Which to Choose — Decision Framework',      'type' => 'lesson',   'duration' => '7 min'],
        ],
    ],
    [
        'id'       => 11,
        'title'    => 'CAP Theorem',
        'icon'     => '🔺',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Consistency, Availability, Partition Tolerance', 'type' => 'lesson', 'duration' => '10 min'],
            ['title' => 'Real-World CAP Trade-offs',                 'type' => 'lesson',   'duration' => '6 min'],
        ],
    ],
    [
        'id'       => 12,
        'title'    => 'Consistent Hashing',
        'icon'     => '🔵',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'The Problem with Naive Hashing',            'type' => 'lesson',   'duration' => '6 min'],
            ['title' => 'Consistent Hash Ring & Virtual Nodes',      'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 13,
        'title'    => 'Long-Polling, WebSockets & SSE',
        'icon'     => '📡',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'AJAX Polling vs Long-Polling',              'type' => 'lesson',   'duration' => '6 min'],
            ['title' => 'WebSockets — Full-Duplex Communication',    'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Server-Sent Events (SSE)',                  'type' => 'lesson',   'duration' => '5 min'],
        ],
    ],
    [
        'id'       => 14,
        'title'    => 'Bloom Filters & Probabilistic DS',
        'icon'     => '🌸',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Bloom Filters — Space-Efficient Membership', 'type' => 'lesson',  'duration' => '8 min'],
            ['title' => 'Count-Min Sketch & HyperLogLog',            'type' => 'lesson',   'duration' => '7 min'],
        ],
    ],
    [
        'id'       => 15,
        'title'    => 'Quorum & Leader Election',
        'icon'     => '🗳️',
        'tag'      => 'Advanced Concepts',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Quorum-Based Writes and Reads',             'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Leader Election with Zookeeper / etcd',     'type' => 'lesson',   'duration' => '8 min'],
        ],
    ],
    [
        'id'       => 16,
        'title'    => 'Design a URL Shortener (TinyURL)',
        'icon'     => '🔗',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level Design & API',                   'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Key Generation & Encoding',     'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — DB Partitioning & Caching',     'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 17,
        'title'    => 'Design Pastebin',
        'icon'     => '📋',
        'tag'      => 'Design Problems',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '6 min'],
            ['title' => 'System Design & Component Design',          'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Data Partitioning & Purging',   'type' => 'lesson',   'duration' => '8 min'],
        ],
    ],
    [
        'id'       => 18,
        'title'    => 'Design Instagram',
        'icon'     => '📷',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level System Design',                  'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Database Schema & Photo Uploads',           'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — News Feed & Ranking',           'type' => 'lesson',   'duration' => '12 min'],
        ],
    ],
    [
        'id'       => 19,
        'title'    => 'Design Dropbox',
        'icon'     => '💾',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'High-Level Design & Client Architecture',   'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Block Storage & Chunking',      'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Metadata DB & Sync Service',    'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 20,
        'title'    => 'Design Facebook Messenger',
        'icon'     => '💬',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level Design & WebSocket Architecture','type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Message Storage & Ordering',    'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Group Chat & Presence',         'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 21,
        'title'    => 'Design Twitter',
        'icon'     => '🐦',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level Design & Tweet Storage',         'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Timeline Generation (Fan-out)', 'type' => 'lesson',   'duration' => '14 min'],
            ['title' => 'Deep Dive — Search, Replication & Sharding','type' => 'lesson',  'duration' => '12 min'],
        ],
    ],
    [
        'id'       => 22,
        'title'    => 'Design YouTube / Netflix',
        'icon'     => '🎬',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level Design & Video Upload Pipeline', 'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Video Encoding & Adaptive Bitrate', 'type' => 'lesson', 'duration' => '14 min'],
            ['title' => 'Deep Dive — CDN & Metadata Management',    'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 23,
        'title'    => 'Design Typeahead Suggestion',
        'icon'     => '🔍',
        'tag'      => 'Design Problems',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Requirements & High-Level Design',          'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Deep Dive — Trie Data Structure',           'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Distributed Trie & Ranking',    'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 24,
        'title'    => 'Design an API Rate Limiter',
        'icon'     => '🚦',
        'tag'      => 'Design Problems',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Requirements & Rate Limiting Algorithms',   'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'High-Level Design & Redis Implementation',  'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Distributed Rate Limiting',     'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 25,
        'title'    => 'Design Twitter Search',
        'icon'     => '🔎',
        'tag'      => 'Design Problems',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Requirements & Storage Estimation',         'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'Deep Dive — Inverted Index & Sharding',     'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Fault Tolerance & Replication', 'type' => 'lesson',   'duration' => '8 min'],
        ],
    ],
    [
        'id'       => 26,
        'title'    => 'Design a Web Crawler',
        'icon'     => '🕷️',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'High-Level Design & URL Frontier',          'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Politeness & BFS vs DFS',       'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Deduplication & Storage',       'type' => 'lesson',   'duration' => '8 min'],
        ],
    ],
    [
        'id'       => 27,
        'title'    => 'Design Facebook Newsfeed',
        'icon'     => '📰',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'High-Level Design & Feed Generation',       'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Feed Publishing & Fan-out',     'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Feed Ranking Algorithm',        'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 28,
        'title'    => 'Design Yelp / Nearby Friends',
        'icon'     => '📍',
        'tag'      => 'Design Problems',
        'lessons'  => 3,
        'items'    => [
            ['title' => 'Requirements & Location-Based Search',      'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'Deep Dive — QuadTree & Geospatial Index',   'type' => 'lesson',   'duration' => '14 min'],
            ['title' => 'Deep Dive — Ranking, Sharding & Replication','type' => 'lesson',  'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 29,
        'title'    => 'Design Uber Backend',
        'icon'     => '🚗',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '8 min'],
            ['title' => 'High-Level Design & Driver Location Updates','type' => 'lesson',  'duration' => '10 min'],
            ['title' => 'Deep Dive — Ride Matching & Dispatch',      'type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Surge Pricing & Analytics',     'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 30,
        'title'    => 'Design Ticketmaster',
        'icon'     => '🎟️',
        'tag'      => 'Design Problems',
        'lessons'  => 4,
        'items'    => [
            ['title' => 'Requirements & Estimations',                'type' => 'lesson',   'duration' => '7 min'],
            ['title' => 'High-Level Design & Seat Reservation Flow', 'type' => 'lesson',   'duration' => '10 min'],
            ['title' => 'Deep Dive — Concurrency & Distributed Lock','type' => 'lesson',   'duration' => '12 min'],
            ['title' => 'Deep Dive — Waiting Room & Flash Sale',     'type' => 'lesson',   'duration' => '10 min'],
        ],
    ],
    [
        'id'       => 31,
        'title'    => 'Additional System Design Questions',
        'icon'     => '📚',
        'tag'      => 'Bonus',
        'lessons'  => 2,
        'items'    => [
            ['title' => 'Design Google Docs (Collaborative Editing)','type' => 'lesson',   'duration' => '14 min'],
            ['title' => 'Design a Distributed Message Queue (Kafka)','type' => 'lesson',   'duration' => '14 min'],
        ],
    ],
];

$totalLessons   = array_sum(array_column($roadmap, 'lessons'));
$totalChapters  = count($roadmap);

$tagColors = [
    'Introduction'     => ['bg' => '#EEF2FF', 'text' => '#4338CA', 'border' => '#C7D2FE'],
    'Fundamentals'     => ['bg' => '#F0FDF4', 'text' => '#16A34A', 'border' => '#BBF7D0'],
    'Advanced Concepts'=> ['bg' => '#FFF7ED', 'text' => '#C2410C', 'border' => '#FED7AA'],
    'Design Problems'  => ['bg' => '#EFF6FF', 'text' => '#1D4ED8', 'border' => '#BFDBFE'],
    'Bonus'            => ['bg' => '#FDF4FF', 'text' => '#7E22CE', 'border' => '#E9D5FF'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grokking System Design — Learning Roadmap</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --brand:       #1F5EFF;
    --brand-dark:  #1648CC;
    --brand-light: #EEF4FF;
    --success:     #16A34A;
    --text-primary:#0F172A;
    --text-sec:    #475569;
    --text-muted:  #94A3B8;
    --border:      #E2E8F0;
    --bg:          #F8FAFC;
    --white:       #FFFFFF;
    --radius:      12px;
    --shadow-sm:   0 1px 3px rgba(0,0,0,.08);
    --shadow-md:   0 4px 16px rgba(0,0,0,.10);
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: var(--bg);
    color: var(--text-primary);
    line-height: 1.6;
  }

  /* ── HERO ─────────────────────────────────────────── */
  .hero {
    background: linear-gradient(135deg, #0F1B4C 0%, #1F3A8A 50%, #1F5EFF 100%);
    padding: 72px 24px 80px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .hero::before {
    content: '';
    position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  }
  .hero-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.2);
    color: #93C5FD;
    font-size: 12px; font-weight: 600; letter-spacing: .5px;
    padding: 4px 12px; border-radius: 20px;
    margin-bottom: 20px; text-transform: uppercase;
  }
  .hero h1 {
    color: #fff;
    font-size: clamp(28px, 5vw, 48px);
    font-weight: 800;
    line-height: 1.15;
    max-width: 780px; margin: 0 auto 16px;
  }
  .hero h1 span { color: #60A5FA; }
  .hero p {
    color: #BAD4FA;
    font-size: 18px;
    max-width: 580px; margin: 0 auto 36px;
  }
  .hero-stats {
    display: flex; justify-content: center; gap: 40px;
    flex-wrap: wrap;
  }
  .hero-stat {
    display: flex; flex-direction: column; align-items: center;
  }
  .hero-stat strong {
    color: #fff; font-size: 28px; font-weight: 800;
  }
  .hero-stat span {
    color: #93C5FD; font-size: 13px; font-weight: 500;
  }
  .hero-divider {
    width: 1px; background: rgba(255,255,255,.2);
    height: 40px; align-self: center;
  }

  /* ── LAYOUT ───────────────────────────────────────── */
  .layout {
    max-width: 1200px; margin: 0 auto;
    padding: 48px 24px;
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
    align-items: start;
  }
  @media (max-width: 900px) {
    .layout { grid-template-columns: 1fr; }
    .sidebar { order: -1; }
  }

  /* ── ROADMAP HEADER ───────────────────────────────── */
  .section-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 24px;
  }
  .section-title {
    font-size: 22px; font-weight: 700; color: var(--text-primary);
  }
  .section-meta {
    font-size: 13px; color: var(--text-muted); margin-top: 2px;
  }
  .expand-all-btn {
    background: none; border: 1px solid var(--border);
    color: var(--brand); font-size: 13px; font-weight: 600;
    padding: 6px 14px; border-radius: 8px; cursor: pointer;
    transition: all .15s;
  }
  .expand-all-btn:hover {
    background: var(--brand-light); border-color: var(--brand);
  }

  /* ── CHAPTER CARD ─────────────────────────────────── */
  .chapter {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    margin-bottom: 10px;
    overflow: hidden;
    transition: box-shadow .15s;
  }
  .chapter:hover { box-shadow: var(--shadow-md); }
  .chapter.open  { border-color: #BFDBFE; }

  .chapter-header {
    display: flex; align-items: center; gap: 14px;
    padding: 16px 20px;
    cursor: pointer;
    user-select: none;
  }
  .chapter-num {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--bg);
    border: 1.5px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; color: var(--text-sec);
    flex-shrink: 0;
  }
  .chapter.open .chapter-num {
    background: var(--brand); border-color: var(--brand);
    color: #fff;
  }
  .chapter-icon {
    font-size: 20px; flex-shrink: 0; line-height: 1;
  }
  .chapter-info { flex: 1; min-width: 0; }
  .chapter-name {
    font-size: 15px; font-weight: 600; color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .chapter-footer {
    display: flex; align-items: center; gap: 10px; margin-top: 4px;
  }
  .chapter-tag {
    font-size: 11px; font-weight: 600;
    padding: 2px 8px; border-radius: 20px;
    letter-spacing: .3px;
  }
  .chapter-count {
    font-size: 12px; color: var(--text-muted);
  }
  .chapter-arrow {
    font-size: 12px; color: var(--text-muted);
    transition: transform .2s; flex-shrink: 0;
    width: 20px; text-align: center;
  }
  .chapter.open .chapter-arrow { transform: rotate(180deg); color: var(--brand); }

  /* ── LESSON LIST ──────────────────────────────────── */
  .chapter-body {
    display: none;
    border-top: 1px solid var(--border);
    background: #FAFBFF;
  }
  .chapter.open .chapter-body { display: block; }

  .lesson-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 20px 12px 66px;
    border-bottom: 1px solid var(--border);
    transition: background .1s, box-shadow .1s;
    text-decoration: none; color: inherit;
    cursor: pointer;
  }
  .lesson-item:last-child { border-bottom: none; }
  .lesson-item:hover {
    background: var(--brand-light);
    box-shadow: inset 3px 0 0 var(--brand);
  }
  .lesson-item:hover .lesson-name { color: var(--brand); }

  .lesson-icon {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0;
  }
  .lesson-icon.lesson  { background: #EFF6FF; color: #1D4ED8; border: 1.5px solid #BFDBFE; }
  .lesson-icon.quiz    { background: #FEF9C3; color: #A16207; border: 1.5px solid #FDE68A; }
  .lesson-icon.project { background: #F0FDF4; color: #16A34A; border: 1.5px solid #BBF7D0; }

  .lesson-name {
    flex: 1; font-size: 14px; color: var(--text-primary);
    line-height: 1.4;
  }
  .lesson-duration {
    font-size: 12px; color: var(--text-muted);
    white-space: nowrap;
  }

  /* ── SIDEBAR ──────────────────────────────────────── */
  .sidebar { position: sticky; top: 24px; }

  .sidebar-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    margin-bottom: 16px;
  }
  .sidebar-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .5px; color: var(--text-sec);
  }
  .sidebar-card-body { padding: 20px; }

  .enroll-btn {
    display: block; width: 100%;
    background: var(--brand);
    color: #fff; font-size: 15px; font-weight: 700;
    padding: 14px; border-radius: 10px; border: none;
    cursor: pointer; text-align: center; text-decoration: none;
    transition: background .15s;
    margin-bottom: 12px;
  }
  .enroll-btn:hover { background: var(--brand-dark); }

  .trial-note {
    text-align: center; font-size: 12px; color: var(--text-muted);
  }

  .stat-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
  }
  .stat-row:last-child { border-bottom: none; }
  .stat-row .stat-icon { font-size: 16px; width: 22px; text-align: center; }
  .stat-row .stat-label { flex: 1; color: var(--text-sec); }
  .stat-row .stat-val { font-weight: 600; color: var(--text-primary); }

  .skills-list {
    display: flex; flex-wrap: wrap; gap: 8px;
  }
  .skill-chip {
    background: var(--bg); border: 1px solid var(--border);
    color: var(--text-sec); font-size: 12px; font-weight: 500;
    padding: 4px 10px; border-radius: 20px;
  }

  /* ── PROGRESS BAR ─────────────────────────────────── */
  .progress-bar-wrap {
    background: var(--border); border-radius: 4px; height: 6px;
    overflow: hidden; margin: 12px 0 6px;
  }
  .progress-bar-fill {
    height: 100%; border-radius: 4px;
    background: linear-gradient(90deg, var(--brand), #60A5FA);
  }
  .progress-label {
    display: flex; justify-content: space-between;
    font-size: 12px; color: var(--text-muted);
  }

  /* ── LEGEND ───────────────────────────────────────── */
  .legend {
    display: flex; gap: 16px; flex-wrap: wrap;
    margin-bottom: 20px;
  }
  .legend-item {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--text-sec);
  }
  .legend-dot {
    width: 10px; height: 10px; border-radius: 50%;
  }

  /* ── RESPONSIVE ───────────────────────────────────── */
  @media (max-width: 600px) {
    .hero-stats { gap: 20px; }
    .hero-divider { display: none; }
    .lesson-item { padding-left: 20px; }
    .chapter-name { font-size: 14px; }
  }
</style>
</head>
<body>

<!-- ─── HERO ──────────────────────────────────────────────────── -->
<section class="hero">
  <div style="max-width:1200px;margin:0 auto 20px;display:flex;justify-content:flex-end;position:relative;z-index:1">
    <a href="tracker.php" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none">📊 Progress Tracker</a>
  </div>
  <div class="hero-badge">⭐ Top-Rated Course</div>
  <h1>Grokking the <span>System Design</span> Interview</h1>
  <p>A structured roadmap to master distributed systems design — the way FAANG engineers think.</p>
  <div class="hero-stats">
    <div class="hero-stat">
      <strong><?= $totalChapters ?></strong>
      <span>Chapters</span>
    </div>
    <div class="hero-divider"></div>
    <div class="hero-stat">
      <strong><?= $totalLessons ?></strong>
      <span>Lessons</span>
    </div>
    <div class="hero-divider"></div>
    <div class="hero-stat">
      <strong>48</strong>
      <span>Topics Covered</span>
    </div>
    <div class="hero-divider"></div>
    <div class="hero-stat">
      <strong>~12h</strong>
      <span>Total Content</span>
    </div>
  </div>
</section>

<!-- ─── MAIN LAYOUT ───────────────────────────────────────────── -->
<div class="layout">

  <!-- LEFT: ROADMAP -->
  <main>
    <div class="section-header">
      <div>
        <div class="section-title">Learning Roadmap</div>
        <div class="section-meta"><?= $totalChapters ?> chapters · <?= $totalLessons ?> lessons · All skill levels</div>
      </div>
      <button class="expand-all-btn" id="expandAllBtn" onclick="toggleAll()">Expand All</button>
    </div>

    <!-- Legend -->
    <div class="legend">
      <div class="legend-item">
        <span class="legend-dot" style="background:#1D4ED8"></span> Lesson
      </div>
      <div class="legend-item">
        <span class="legend-dot" style="background:#A16207"></span> Quiz
      </div>
      <div class="legend-item">
        <span class="legend-dot" style="background:#16A34A"></span> Project
      </div>
    </div>

    <?php foreach ($roadmap as $i => $chapter):
      $tag    = $chapter['tag'];
      $colors = $tagColors[$tag] ?? ['bg' => '#F1F5F9', 'text' => '#475569', 'border' => '#CBD5E1'];
      $isOpen = ($i < 2); // first 2 open by default
    ?>
    <div class="chapter <?= $isOpen ? 'open' : '' ?>" id="chapter-<?= $chapter['id'] ?>">
      <div class="chapter-header" onclick="toggleChapter(<?= $chapter['id'] ?>)">
        <!-- Number -->
        <div class="chapter-num"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></div>
        <!-- Icon -->
        <div class="chapter-icon"><?= $chapter['icon'] ?></div>
        <!-- Info -->
        <div class="chapter-info">
          <div class="chapter-name"><?= htmlspecialchars($chapter['title']) ?></div>
          <div class="chapter-footer">
            <span class="chapter-tag" style="background:<?= $colors['bg'] ?>;color:<?= $colors['text'] ?>;border:1px solid <?= $colors['border'] ?>"><?= $tag ?></span>
            <span class="chapter-count"><?= $chapter['lessons'] ?> lesson<?= $chapter['lessons'] !== 1 ? 's' : '' ?></span>
          </div>
        </div>
        <!-- Arrow -->
        <div class="chapter-arrow">▼</div>
      </div>

      <div class="chapter-body">
        <?php foreach ($chapter['items'] as $li => $lesson):
          $typeIcon = match($lesson['type']) {
            'quiz'    => '?',
            'project' => '✦',
            default   => '▶',
          };
          $lessonUrl = 'lessons/c' . $chapter['id'] . '-l' . $li . '.html';
        ?>
        <a class="lesson-item" href="<?= $lessonUrl ?>" target="_blank">
          <div class="lesson-icon <?= $lesson['type'] ?>"><?= $typeIcon ?></div>
          <div class="lesson-name"><?= htmlspecialchars($lesson['title']) ?></div>
          <div class="lesson-duration">⏱ <?= $lesson['duration'] ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </main>

  <!-- RIGHT: SIDEBAR -->
  <aside class="sidebar">

    <!-- Enroll Card -->
    <div class="sidebar-card">
      <div class="sidebar-card-body">
        <a href="#" class="enroll-btn">Start Learning — Free</a>
        <div class="trial-note">No credit card required · 7-day free trial</div>
      </div>
    </div>

    <!-- Course Stats -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">Course Details</div>
      <div class="sidebar-card-body">
        <div class="stat-row">
          <span class="stat-icon">📖</span>
          <span class="stat-label">Chapters</span>
          <span class="stat-val"><?= $totalChapters ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">🎓</span>
          <span class="stat-label">Lessons</span>
          <span class="stat-val"><?= $totalLessons ?></span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">⏱</span>
          <span class="stat-label">Estimated Time</span>
          <span class="stat-val">~12 hours</span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">📊</span>
          <span class="stat-label">Skill Level</span>
          <span class="stat-val">Intermediate</span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">🌐</span>
          <span class="stat-label">Language</span>
          <span class="stat-val">English</span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">🏅</span>
          <span class="stat-label">Certificate</span>
          <span class="stat-val">Yes</span>
        </div>
        <div class="stat-row">
          <span class="stat-icon">♾️</span>
          <span class="stat-label">Access</span>
          <span class="stat-val">Lifetime</span>
        </div>
      </div>
    </div>

    <!-- Progress (mockup) -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">Your Progress</div>
      <div class="sidebar-card-body">
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width: 0%"></div>
        </div>
        <div class="progress-label">
          <span>0 / <?= $totalLessons ?> lessons</span>
          <span>0%</span>
        </div>
      </div>
    </div>

    <!-- Topics Covered -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">Topics Covered</div>
      <div class="sidebar-card-body">
        <div class="skills-list">
          <?php
          $skills = [
            'Distributed Systems','Load Balancing','Caching','Sharding',
            'SQL & NoSQL','CAP Theorem','Consistent Hashing','CDN',
            'WebSockets','Rate Limiting','Message Queues','Replication',
            'Microservices','System Estimation','Scalability',
          ];
          foreach ($skills as $s):
          ?>
          <span class="skill-chip"><?= $s ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Companies -->
    <div class="sidebar-card">
      <div class="sidebar-card-header">Target Companies</div>
      <div class="sidebar-card-body">
        <div class="skills-list">
          <?php
          $companies = ['Google','Amazon','Meta','Apple','Microsoft','Netflix','Uber','Stripe','Airbnb','LinkedIn'];
          foreach ($companies as $c):
          ?>
          <span class="skill-chip"><?= $c ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </aside>
</div>

<script>
  let allExpanded = false;

  function toggleChapter(id) {
    const el = document.getElementById('chapter-' + id);
    el.classList.toggle('open');
  }

  function toggleAll() {
    allExpanded = !allExpanded;
    document.querySelectorAll('.chapter').forEach(el => {
      el.classList.toggle('open', allExpanded);
    });
    document.getElementById('expandAllBtn').textContent =
      allExpanded ? 'Collapse All' : 'Expand All';
  }

  // Keyboard: Enter / Space on chapter header
  document.querySelectorAll('.chapter-header').forEach(h => {
    h.setAttribute('tabindex', '0');
    h.setAttribute('role', 'button');
    h.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        h.click();
      }
    });
  });
</script>
</body>
</html>
