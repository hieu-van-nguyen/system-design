<?php
/**
 * tracker.php — FAANG System Design Interview Progress Tracker
 * Persistence: localStorage (no backend DB required)
 * Data source: README.md problem list
 */

// ── Problem data (sourced from README.md) ──────────────────────────────────
$problems = [

    // ── 🟢 EASY ──────────────────────────────────────────────────────────
    [
        'id'         => 'tinyurl',
        'title'      => 'TinyURL',
        'file'       => 'tiny-url.md',
        'difficulty' => 'easy',
        'hardProblem'=> 'Hash collision + redirect latency',
        'topics'     => ['URL shortening','Base62 encoding','Cassandra','Redis cache-aside','301 vs 302','Hash collisions','Expiry/TTL','Rate limiting'],
        'tags'       => ['Hashing','Caching','Cassandra'],
    ],
    [
        'id'         => 'rate-limiter',
        'title'      => 'Rate Limiter',
        'file'       => 'rate-limiter.md',
        'difficulty' => 'easy',
        'hardProblem'=> 'Distributed quota across replicas',
        'topics'     => ['Token Bucket','Sliding Window Counter','Fixed Window','Leaky Bucket','Redis INCR atomicity','Fail-open','Dynamic rule propagation'],
        'tags'       => ['Redis','Distributed','Algorithms'],
    ],
    [
        'id'         => 'parking-garage',
        'title'      => 'Parking Garage',
        'file'       => 'parking-garage.md',
        'difficulty' => 'easy',
        'hardProblem'=> 'Atomic spot assignment + offline gate hardware',
        'topics'     => ['Redis atomic queue','Offline-capable gate hardware','LPR','IoT sensor pipeline','Dynamic pricing','Multi-tenant'],
        'tags'       => ['IoT','Redis','LLD/HLD'],
    ],
    [
        'id'         => 'elevator-system',
        'title'      => 'Elevator System',
        'file'       => 'elevator-system.md',
        'difficulty' => 'easy',
        'hardProblem'=> 'LLD state machine + fleet-scale HLD',
        'topics'     => ['LOOK algorithm','State machine','Strategy/Observer patterns','OOP','Kafka + Flink','Predictive maintenance ML','InfluxDB'],
        'tags'       => ['LLD','OOP','IoT','ML'],
    ],
    [
        'id'         => 'weather-app',
        'title'      => 'Weather App',
        'file'       => 'weather-app.md',
        'difficulty' => 'easy',
        'hardProblem'=> 'Sensor ingestion + geospatial interpolation',
        'topics'     => ['MQTT/Kafka ingestion','TimescaleDB','Flink stream aggregation','IDW/Kriging interpolation','S2 cell heat map','Anomaly detection'],
        'tags'       => ['IoT','Geospatial','Time-series'],
    ],

    // ── 🟡 MEDIUM ─────────────────────────────────────────────────────────
    [
        'id'         => 'netflix',
        'title'      => 'Netflix',
        'file'       => 'netflix.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Transcoding pipeline + CDN + recommendations',
        'topics'     => ['Transcoding pipeline','DASH/HLS adaptive bitrate','Open Connect CDN','DRM + Widevine','Cassandra watch history','Two-tower recommendations','AV1 codec economics'],
        'tags'       => ['CDN','Video','ML Recommendations'],
    ],
    [
        'id'         => 'uber',
        'title'      => 'Uber',
        'file'       => 'uber.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Geo-matching + real-time tracking + surge pricing',
        'topics'     => ['Redis GEO + distributed lock','Driver location at 100K writes/sec','WebSocket real-time tracking','H3 hexagonal surge pricing','Trip state machine','Cassandra location history'],
        'tags'       => ['Geospatial','WebSocket','Real-time'],
    ],
    [
        'id'         => 'build-system',
        'title'      => 'Build System',
        'file'       => 'build-system.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'DAG scheduling + worker isolation + incremental builds',
        'topics'     => ['Git webhook ingestion','Pipeline DAG','Worker scheduling','Firecracker isolation','Log streaming','Artifact store','Incremental builds','Monorepo change detection'],
        'tags'       => ['DAG','Scheduling','CI/CD'],
    ],
    [
        'id'         => 'job-scheduler',
        'title'      => 'Job Scheduler',
        'file'       => 'job-scheduler.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Thundering herd + exactly-once execution',
        'topics'     => ['Cron + one-time jobs','FOR UPDATE SKIP LOCKED','Shard-based scheduling','Fencing tokens','Outbox pattern','Thundering herd','Retry backoff','Sub-second scheduling'],
        'tags'       => ['Distributed Locking','Exactly-once','Scheduling'],
    ],
    [
        'id'         => 'restaurant-reservation',
        'title'      => 'Restaurant Reservation',
        'file'       => 'restaurant-reservation.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Distributed slot locking + waitlist fan-out',
        'topics'     => ['Slot modeling','Redis distributed lock','Two-layer search','Table merging algorithm','Waitlist Kafka fan-out','No-show enforcement','City-sharded PostgreSQL'],
        'tags'       => ['Distributed Locking','Elasticsearch','Kafka'],
    ],
    [
        'id'         => 'ad-click-aggregator',
        'title'      => 'Ad Click Aggregator',
        'file'       => 'ad-click-aggregator.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Exactly-once billing + hot ad partitions',
        'topics'     => ['Kappa architecture','1M events/sec ingestion','Event-time windowing','Watermarks + late events','Hot ad partitioning','Redis HyperLogLog','ClickHouse OLAP','Bloom filter dedup','Exactly-once billing'],
        'tags'       => ['Flink','Kafka','OLAP','Billing'],
    ],
    [
        'id'         => 'subway-ticket',
        'title'      => 'Subway Ticket System',
        'file'       => 'subway-ticket-system.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Offline payment + ticket forgery prevention',
        'topics'     => ['Payment idempotency (outbox)','Offline kiosk (EMV floor limit)','HMAC-signed QR tickets (HSM)','Bloom filter turnstile validation','PCI-DSS isolation','Daily reconciliation'],
        'tags'       => ['Payments','PCI-DSS','Offline'],
    ],
    [
        'id'         => 'booking',
        'title'      => 'Booking.com',
        'file'       => 'booking.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Inventory locking + overbooking tolerance + dynamic pricing',
        'topics'     => ['Hotel search (ES + Redis)','Optimistic locking + overbooking 0.05%','Booking saga pattern','Dynamic pricing','Saga pattern','Anti-fraud ML scoring','Multi-region','GDPR/CCPA'],
        'tags'       => ['Saga Pattern','Elasticsearch','Pricing'],
    ],
    [
        'id'         => 'google-drive',
        'title'      => 'Google Drive',
        'file'       => 'google-drive-system-design.md',
        'difficulty' => 'medium',
        'hardProblem'=> 'Content-defined chunking + delta sync + conflict resolution',
        'topics'     => ['Rabin fingerprinting (avg 4 MB)','Delta sync (95%+ bandwidth savings)','SHA-256 content-addressed dedup','Conflict copy resolution','WebSocket real-time sync','File versioning as chunk-hash snapshots','Presigned S3 uploads','GDPR erasure pipeline'],
        'tags'       => ['Chunking','Deduplication','Sync'],
    ],

    // ── 🔴 HARD ───────────────────────────────────────────────────────────
    [
        'id'         => 'whatsapp',
        'title'      => 'WhatsApp',
        'file'       => 'whatsapp.md',
        'difficulty' => 'hard',
        'hardProblem'=> 'E2EE at scale + group fan-out + multi-device sync',
        'topics'     => ['WebSocket at scale','Signal Protocol (X3DH + Double Ratchet)','Kafka fan-out','Group messaging','Presence','Media upload','Push notifications','Multi-device E2EE'],
        'tags'       => ['E2EE','WebSocket','Fan-out'],
    ],
    [
        'id'         => 'ticketmaster',
        'title'      => 'Ticketmaster',
        'file'       => 'ticketmaster.md',
        'difficulty' => 'hard',
        'hardProblem'=> '14M concurrent users on flash sale + atomic multi-seat hold',
        'topics'     => ['Seat hold (Redis SET NX + Lua)','Virtual queue (14M users)','Seat map read scaling (250K/sec)','HMAC-signed QR tickets','Outbox pattern payments','Dynamic pricing','Bot prevention'],
        'tags'       => ['Flash Sale','Virtual Queue','Distributed Locking'],
    ],
    [
        'id'         => 'smart-delivery',
        'title'      => 'Smart Delivery System',
        'file'       => 'smart-delivery-system.md',
        'difficulty' => 'hard',
        'hardProblem'=> 'VRP route optimization + Saga choreography + real-time tracking',
        'topics'     => ['Choreography Saga (Kafka)','Atomic inventory reservation (Redis Lua)','FC assignment scoring','VRP route optimization (OR-Tools)','Drone dispatch','WebSocket + Redis Pub/Sub','SLA breach detection'],
        'tags'       => ['Saga','Route Optimization','Real-time'],
    ],
    [
        'id'         => 'gm-car-tracking',
        'title'      => 'GM Car Tracking',
        'file'       => 'gm_car_tracking.md',
        'difficulty' => 'hard',
        'hardProblem'=> '500K events/sec IoT ingestion + OTA rollout + predictive ML',
        'topics'     => ['IoT telemetry ingestion (500K events/sec)','Multi-region (GDPR/CCPA)','OTA updates (staged canary rollout)','Command & control (at-least-once)','Flink anomaly detection','XGBoost predictive maintenance','InfluxDB + Glacier archive'],
        'tags'       => ['IoT','OTA','Predictive ML','GDPR'],
    ],
    [
        'id'         => 'amazon',
        'title'      => 'Amazon',
        'file'       => 'amazon-system-design.md',
        'difficulty' => 'hard',
        'hardProblem'=> 'Flash sale inventory + checkout saga + multi-region active-active',
        'topics'     => ['DynamoDB + OpenSearch catalog','Cart (Redis HASH)','Checkout saga + idempotency','PCI DSS payment tokenization','Inventory reservation (Redis Lua for flash sales)','Collaborative filtering','Kafka notification fan-out','Multi-region active-active'],
        'tags'       => ['Microservices','Saga','Flash Sale','Multi-region'],
    ],
    [
        'id'         => 'twitter',
        'title'      => 'Twitter',
        'file'       => 'twitter-system-design.md',
        'difficulty' => 'hard',
        'hardProblem'=> 'Celebrity fan-out bifurcation + trending at 1M tweets/sec',
        'topics'     => ['Hybrid fan-out (write vs read for celebrities)','Snowflake IDs','Cassandra tweet storage','Redis sorted-set timeline (1.28 TB)','Kafka fan-out pipeline','Earlybird search (NRT Elasticsearch)','Count-Min Sketch trending (Flink)','Two-tower neural network'],
        'tags'       => ['Fan-out','Snowflake IDs','Trending','ML'],
    ],
    [
        'id'         => 'instagram',
        'title'      => 'Instagram',
        'file'       => 'instagram-system-design.md',
        'difficulty' => 'hard',
        'hardProblem'=> '868K CDN req/sec + celebrity fan-out math + pHash dedup + 4.56 EB',
        'topics'     => ['S3 presigned upload (36 GB/s bypassed)','Multi-tier CDN (868K req/sec)','Hybrid fan-out (celebrity threshold 1M followers)','pHash dedup','HLS adaptive bitrate + GPU transcoding','Stories (Redis TTL)','Explore two-stage ranking (FAISS ANN)','Reels recommendation (two-tower)'],
        'tags'       => ['CDN','Fan-out','pHash','Recommendations'],
    ],
    [
        'id'         => 'youtube',
        'title'      => 'YouTube',
        'file'       => 'youtube-system-design.md',
        'difficulty' => 'hard',
        'hardProblem'=> 'DAG transcoding + Content ID fingerprinting + watch-time rec + 54 EB',
        'topics'     => ['Transcoding DAG (60 parallel segments)','Multi-codec (H.264/VP9/AV1)','DASH adaptive bitrate','Google Global Cache (GGC)','Two-tower DNN recommendation','Content ID fingerprinting (5.76T fingerprints)','Bigtable watch history','ScaNN ANN over 800M videos'],
        'tags'       => ['DAG','CDN','ML','Fingerprinting'],
    ],
];

// ── Difficulty config ──────────────────────────────────────────────────────
$difficultyConfig = [
    'easy'   => ['label' => '🟢 Easy',   'color' => '#16A34A', 'bg' => '#F0FDF4', 'border' => '#BBF7D0', 'stage' => 'L3–L4'],
    'medium' => ['label' => '🟡 Medium', 'color' => '#B45309', 'bg' => '#FFFBEB', 'border' => '#FDE68A', 'stage' => 'L4–L5'],
    'hard'   => ['label' => '🔴 Hard',   'color' => '#DC2626', 'bg' => '#FEF2F2', 'border' => '#FECACA', 'stage' => 'L5–L7'],
];

// ── Study stages per problem ───────────────────────────────────────────────
$studyStages = [
    ['id' => 'read',        'label' => 'Read the document',                 'icon' => '📖'],
    ['id' => 'fr',          'label' => 'Functional requirements',            'icon' => '✅'],
    ['id' => 'nfr',         'label' => 'NFR & Back-of-envelope estimations', 'icon' => '📐'],
    ['id' => 'hld',         'label' => 'Drew the architecture diagram',      'icon' => '🏗️'],
    ['id' => 'deepdive',    'label' => 'Deep dives & data models',           'icon' => '🔬'],
    ['id' => 'tradeoffs',   'label' => 'Trade-offs discussion',              'icon' => '⚖️'],
    ['id' => 'interview',   'label' => 'Can explain in 45-min interview',    'icon' => '🎯'],
];

$totalProblems = count($problems);
$easyCount     = count(array_filter($problems, fn($p) => $p['difficulty'] === 'easy'));
$mediumCount   = count(array_filter($problems, fn($p) => $p['difficulty'] === 'medium'));
$hardCount     = count(array_filter($problems, fn($p) => $p['difficulty'] === 'hard'));
$stageCount    = count($studyStages);

// Encode data for JS
$problemsJson = json_encode(array_map(fn($p) => [
    'id' => $p['id'], 'difficulty' => $p['difficulty']
], $problems));
$stagesJson = json_encode(array_column($studyStages, 'id'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Progress Tracker — FAANG System Design</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:        #1F5EFF;
  --brand-dark:   #1648CC;
  --brand-light:  #EEF4FF;
  --easy:         #16A34A;
  --easy-bg:      #F0FDF4;
  --easy-border:  #BBF7D0;
  --medium:       #B45309;
  --medium-bg:    #FFFBEB;
  --medium-border:#FDE68A;
  --hard:         #DC2626;
  --hard-bg:      #FEF2F2;
  --hard-border:  #FECACA;
  --text-primary: #0F172A;
  --text-sec:     #475569;
  --text-muted:   #94A3B8;
  --border:       #E2E8F0;
  --bg:           #F8FAFC;
  --white:        #FFFFFF;
  --radius:       12px;
  --shadow-sm:    0 1px 3px rgba(0,0,0,.08);
  --shadow-md:    0 4px 16px rgba(0,0,0,.10);
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  color: var(--text-primary);
  line-height: 1.6;
}

/* ── HEADER ───────────────────────────────────────── */
.header {
  background: linear-gradient(135deg, #0F1B4C 0%, #1F3A8A 50%, #1F5EFF 100%);
  padding: 40px 24px;
  position: relative;
  overflow: hidden;
}
.header::before {
  content: '';
  position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.header-inner {
  max-width: 1200px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 20px;
  position: relative;
}
.header-left h1 {
  color: #fff;
  font-size: clamp(22px, 4vw, 32px);
  font-weight: 800;
  margin-bottom: 4px;
}
.header-left h1 span { color: #60A5FA; }
.header-left p {
  color: #BAD4FA;
  font-size: 14px;
}
.header-nav {
  display: flex; gap: 10px;
}
.header-nav a {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px;
  background: rgba(255,255,255,.12);
  border: 1px solid rgba(255,255,255,.2);
  color: #fff;
  border-radius: 8px;
  font-size: 13px; font-weight: 600;
  text-decoration: none;
  transition: background .15s;
}
.header-nav a:hover { background: rgba(255,255,255,.2); }
.header-nav a.active {
  background: rgba(255,255,255,.25);
  border-color: rgba(255,255,255,.4);
}

/* ── LAYOUT ───────────────────────────────────────── */
.layout {
  max-width: 1200px;
  margin: 0 auto;
  padding: 32px 24px;
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 28px;
  align-items: start;
}
@media (max-width: 860px) {
  .layout { grid-template-columns: 1fr; }
}

/* ── SIDEBAR ──────────────────────────────────────── */
.sidebar {
  position: sticky;
  top: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.stat-card {
  background: var(--white);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
}
.stat-card-header {
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .6px;
  color: var(--text-sec);
  display: flex; align-items: center; justify-content: space-between;
}
.stat-card-body { padding: 16px; }

/* Overall progress donut */
.donut-wrap {
  display: flex; align-items: center; gap: 16px;
  margin-bottom: 12px;
}
.donut {
  position: relative;
  width: 72px; height: 72px;
  flex-shrink: 0;
}
.donut svg { transform: rotate(-90deg); }
.donut-bg   { fill: none; stroke: var(--border); stroke-width: 6; }
.donut-fill { fill: none; stroke: var(--brand); stroke-width: 6;
              stroke-linecap: round; transition: stroke-dasharray .4s ease; }
.donut-label {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; font-weight: 800; color: var(--text-primary);
}
.donut-text h3 {
  font-size: 16px; font-weight: 700; color: var(--text-primary);
}
.donut-text p { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

/* Category progress rows */
.cat-row {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 10px;
}
.cat-row:last-child { margin-bottom: 0; }
.cat-dot {
  width: 10px; height: 10px; border-radius: 50%;
  flex-shrink: 0;
}
.cat-label { font-size: 13px; color: var(--text-sec); flex: 1; }
.cat-bar-wrap {
  flex: 2;
  background: var(--border);
  border-radius: 4px;
  height: 5px;
  overflow: hidden;
}
.cat-bar { height: 100%; border-radius: 4px; transition: width .4s ease; }
.cat-count { font-size: 12px; font-weight: 600; color: var(--text-primary); min-width: 36px; text-align: right; }

/* Filter buttons */
.filter-group {
  display: flex; flex-direction: column; gap: 6px;
}
.filter-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px;
  border: 1.5px solid var(--border);
  background: var(--white);
  border-radius: 8px;
  font-size: 13px; font-weight: 500; color: var(--text-sec);
  cursor: pointer; transition: all .15s;
  text-align: left; width: 100%;
}
.filter-btn:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }
.filter-btn.active { border-color: var(--brand); color: var(--brand); background: var(--brand-light); font-weight: 600; }
.filter-btn .fb-count {
  margin-left: auto;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 11px; font-weight: 700;
  padding: 1px 7px;
  color: var(--text-sec);
}
.filter-btn.active .fb-count {
  background: var(--brand); border-color: var(--brand); color: #fff;
}

/* Reset + export */
.action-btn {
  display: block; width: 100%;
  padding: 9px 12px;
  border: 1.5px solid var(--border);
  background: var(--white);
  border-radius: 8px;
  font-size: 13px; font-weight: 600; color: var(--text-sec);
  cursor: pointer; transition: all .15s;
  text-align: center;
}
.action-btn:hover { border-color: #DC2626; color: #DC2626; background: #FEF2F2; }
.action-btn.export { margin-top: 0; }
.action-btn.export:hover { border-color: var(--brand); color: var(--brand); background: var(--brand-light); }

/* ── MAIN CONTENT ─────────────────────────────────── */
.main-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
  flex-wrap: wrap; gap: 12px;
}
.main-title {
  font-size: 20px; font-weight: 700; color: var(--text-primary);
}
.main-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }

.sort-wrap {
  display: flex; align-items: center; gap: 8px;
}
.sort-wrap label { font-size: 12px; color: var(--text-muted); }
.sort-select {
  padding: 6px 28px 6px 10px;
  border: 1.5px solid var(--border);
  background: var(--white);
  border-radius: 8px;
  font-size: 13px; color: var(--text-primary);
  cursor: pointer; appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M6 8L1 3h10z' fill='%2394A3B8'/%3E%3C/svg%3E");
  background-repeat: no-repeat; background-position: right 8px center;
}

/* ── PROBLEM CARD ─────────────────────────────────── */
.problem-section {
  margin-bottom: 32px;
}
.section-label {
  display: flex; align-items: center; gap: 10px;
  font-size: 13px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; margin-bottom: 12px; padding-bottom: 8px;
  border-bottom: 2px solid var(--border);
}
.section-label .sl-badge {
  font-size: 11px; font-weight: 700;
  padding: 2px 8px; border-radius: 10px;
}

.problem-card {
  background: var(--white);
  border: 1.5px solid var(--border);
  border-radius: var(--radius);
  margin-bottom: 10px;
  overflow: hidden;
  transition: box-shadow .15s, border-color .15s;
}
.problem-card:hover { box-shadow: var(--shadow-md); }
.problem-card.completed { border-color: #BBF7D0; background: #FAFFFE; }
.problem-card.in-progress { border-color: #BFDBFE; }

.card-header {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 18px;
  cursor: pointer;
  user-select: none;
}
.card-progress-ring {
  position: relative;
  width: 40px; height: 40px;
  flex-shrink: 0;
}
.card-progress-ring svg { transform: rotate(-90deg); }
.ring-bg   { fill: none; stroke: var(--border); stroke-width: 3.5; }
.ring-fill { fill: none; stroke-width: 3.5; stroke-linecap: round;
             transition: stroke-dasharray .3s ease; }
.ring-icon {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
}
.card-info { flex: 1; min-width: 0; }
.card-title {
  font-size: 15px; font-weight: 700; color: var(--text-primary);
  display: flex; align-items: center; gap: 8px;
  flex-wrap: wrap;
}
.diff-badge {
  font-size: 10px; font-weight: 700;
  padding: 2px 7px; border-radius: 10px;
  white-space: nowrap;
}
.card-subtitle {
  font-size: 12px; color: var(--text-muted); margin-top: 3px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.card-meta {
  display: flex; align-items: center; gap: 8px;
  flex-shrink: 0;
}
.stage-pips {
  display: flex; gap: 3px;
}
.pip {
  width: 7px; height: 7px;
  border-radius: 50%;
  border: 1.5px solid var(--border);
  background: var(--white);
  transition: background .2s, border-color .2s;
}
.pip.done { background: #16A34A; border-color: #16A34A; }

.card-arrow {
  font-size: 11px; color: var(--text-muted);
  transition: transform .2s;
  margin-left: 4px;
}
.problem-card.open .card-arrow { transform: rotate(180deg); }

/* ── CARD BODY ────────────────────────────────────── */
.card-body {
  display: none;
  border-top: 1px solid var(--border);
  background: #FAFBFF;
  padding: 16px 18px 18px;
}
.problem-card.open .card-body { display: block; }

.card-body-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 24px;
  margin-bottom: 18px;
}
@media (max-width: 600px) {
  .card-body-grid { grid-template-columns: 1fr; }
}

.info-block h4 {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--text-muted);
  margin-bottom: 8px;
}
.hard-problem-pill {
  display: inline-block;
  background: #FFF7ED;
  border: 1px solid #FED7AA;
  color: #C2410C;
  font-size: 12px; font-weight: 600;
  padding: 4px 10px; border-radius: 6px;
}
.topic-tags {
  display: flex; flex-wrap: wrap; gap: 5px;
}
.topic-tag {
  background: var(--bg);
  border: 1px solid var(--border);
  color: var(--text-sec);
  font-size: 11px; font-weight: 500;
  padding: 3px 8px; border-radius: 6px;
}

/* ── CHECKLIST ────────────────────────────────────── */
.checklist-title {
  font-size: 11px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .5px; color: var(--text-muted);
  margin-bottom: 10px;
}
.checklist {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}
@media (max-width: 600px) {
  .checklist { grid-template-columns: 1fr; }
}
.check-item {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  cursor: pointer;
  transition: all .15s;
  background: var(--white);
  user-select: none;
}
.check-item:hover { border-color: var(--brand); background: var(--brand-light); }
.check-item.checked {
  border-color: #BBF7D0;
  background: #F0FDF4;
}
.check-box {
  width: 18px; height: 18px;
  border: 2px solid var(--border);
  border-radius: 4px;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px;
  transition: all .15s;
  background: var(--white);
}
.check-item.checked .check-box {
  background: #16A34A; border-color: #16A34A; color: #fff;
}
.check-label {
  font-size: 12px; font-weight: 500; color: var(--text-sec);
  flex: 1; line-height: 1.3;
}
.check-item.checked .check-label {
  color: #15803D;
}
.check-icon { font-size: 14px; flex-shrink: 0; }

/* ── CARD FOOTER ──────────────────────────────────── */
.card-footer {
  display: flex; align-items: center; justify-content: space-between;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
  flex-wrap: wrap; gap: 10px;
}
.card-progress-bar-wrap {
  flex: 1; min-width: 120px;
}
.card-progress-bar-label {
  display: flex; justify-content: space-between;
  font-size: 11px; color: var(--text-muted);
  margin-bottom: 4px;
}
.card-progress-bar-track {
  background: var(--border);
  border-radius: 4px;
  height: 5px;
  overflow: hidden;
}
.card-progress-bar-fill {
  height: 100%;
  border-radius: 4px;
  background: linear-gradient(90deg, #16A34A, #4ADE80);
  transition: width .3s ease;
}
.open-md-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 6px 12px;
  background: var(--brand-light);
  border: 1.5px solid #BFDBFE;
  color: var(--brand);
  border-radius: 7px;
  font-size: 12px; font-weight: 600;
  text-decoration: none;
  transition: all .15s;
  white-space: nowrap;
}
.open-md-btn:hover { background: var(--brand); color: #fff; border-color: var(--brand); }

/* ── EMPTY STATE ──────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: var(--text-muted);
}
.empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; }
.empty-state h3 { font-size: 18px; font-weight: 700; color: var(--text-sec); margin-bottom: 6px; }

/* ── TOAST ────────────────────────────────────────── */
.toast {
  position: fixed; bottom: 24px; right: 24px;
  background: #0F172A; color: #fff;
  padding: 10px 18px; border-radius: 8px;
  font-size: 13px; font-weight: 600;
  display: flex; align-items: center; gap: 8px;
  box-shadow: var(--shadow-md);
  transform: translateY(80px); opacity: 0;
  transition: all .25s ease;
  z-index: 9999;
  pointer-events: none;
}
.toast.show { transform: translateY(0); opacity: 1; }

/* ── RESPONSIVE ───────────────────────────────────── */
@media (max-width: 480px) {
  .card-header { padding: 12px 14px; }
  .card-body { padding: 12px 14px; }
  .header { padding: 24px 16px; }
}
</style>
</head>
<body>

<!-- ─── HEADER ──────────────────────────────────────────────────── -->
<header class="header">
  <div class="header-inner">
    <div class="header-left">
      <h1>FAANG System Design <span>Tracker</span></h1>
      <p><?= $totalProblems ?> problems · <?= $stageCount ?> study stages each · Saved locally in your browser</p>
    </div>
    <nav class="header-nav">
      <a href="index.php">📚 Roadmap</a>
      <a href="tracker.php" class="active">📊 Tracker</a>
    </nav>
  </div>
</header>

<!-- ─── LAYOUT ──────────────────────────────────────────────────── -->
<div class="layout">

  <!-- ── SIDEBAR ──────────────────────────────────────────────── -->
  <aside class="sidebar">

    <!-- Overall Progress -->
    <div class="stat-card">
      <div class="stat-card-header">
        Overall Progress
        <span id="overallPct" style="color:var(--brand);font-size:13px;">0%</span>
      </div>
      <div class="stat-card-body">
        <div class="donut-wrap">
          <div class="donut">
            <svg viewBox="0 0 36 36" width="72" height="72">
              <circle class="donut-bg" cx="18" cy="18" r="15.9"/>
              <circle class="donut-fill" id="donutFill" cx="18" cy="18" r="15.9"
                stroke-dasharray="0 100"/>
            </svg>
            <div class="donut-label" id="donutLabel">0%</div>
          </div>
          <div class="donut-text">
            <h3 id="donutCompleted">0 / <?= $totalProblems ?></h3>
            <p>problems mastered</p>
          </div>
        </div>

        <!-- Easy -->
        <div class="cat-row">
          <div class="cat-dot" style="background:var(--easy)"></div>
          <div class="cat-label">🟢 Easy</div>
          <div class="cat-bar-wrap">
            <div class="cat-bar" id="barEasy" style="background:var(--easy);width:0%"></div>
          </div>
          <div class="cat-count" id="countEasy">0/<?= $easyCount ?></div>
        </div>
        <!-- Medium -->
        <div class="cat-row">
          <div class="cat-dot" style="background:var(--medium)"></div>
          <div class="cat-label">🟡 Medium</div>
          <div class="cat-bar-wrap">
            <div class="cat-bar" id="barMedium" style="background:var(--medium);width:0%"></div>
          </div>
          <div class="cat-count" id="countMedium">0/<?= $mediumCount ?></div>
        </div>
        <!-- Hard -->
        <div class="cat-row">
          <div class="cat-dot" style="background:var(--hard)"></div>
          <div class="cat-label">🔴 Hard</div>
          <div class="cat-bar-wrap">
            <div class="cat-bar" id="barHard" style="background:var(--hard);width:0%"></div>
          </div>
          <div class="cat-count" id="countHard">0/<?= $hardCount ?></div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="stat-card">
      <div class="stat-card-header">Filter</div>
      <div class="stat-card-body">
        <div class="filter-group">
          <button class="filter-btn active" data-filter="all" onclick="setFilter('all',this)">
            📋 All Problems <span class="fb-count"><?= $totalProblems ?></span>
          </button>
          <button class="filter-btn" data-filter="easy" onclick="setFilter('easy',this)">
            🟢 Easy Only <span class="fb-count"><?= $easyCount ?></span>
          </button>
          <button class="filter-btn" data-filter="medium" onclick="setFilter('medium',this)">
            🟡 Medium Only <span class="fb-count"><?= $mediumCount ?></span>
          </button>
          <button class="filter-btn" data-filter="hard" onclick="setFilter('hard',this)">
            🔴 Hard Only <span class="fb-count"><?= $hardCount ?></span>
          </button>
          <button class="filter-btn" data-filter="not-started" onclick="setFilter('not-started',this)">
            ⬜ Not Started <span class="fb-count" id="countNotStarted">—</span>
          </button>
          <button class="filter-btn" data-filter="in-progress" onclick="setFilter('in-progress',this)">
            🔄 In Progress <span class="fb-count" id="countInProgress">—</span>
          </button>
          <button class="filter-btn" data-filter="completed" onclick="setFilter('completed',this)">
            ✅ Completed <span class="fb-count" id="countCompleted">—</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="stat-card">
      <div class="stat-card-header">Actions</div>
      <div class="stat-card-body" style="display:flex;flex-direction:column;gap:8px;">
        <button class="action-btn export" onclick="exportProgress()">📤 Export Progress JSON</button>
        <button class="action-btn export" onclick="importProgress()">📥 Import Progress JSON</button>
        <button class="action-btn" onclick="confirmReset()">🗑️ Reset All Progress</button>
      </div>
    </div>

  </aside>

  <!-- ── MAIN ──────────────────────────────────────────────────── -->
  <main>

    <div class="main-header">
      <div>
        <div class="main-title">Study Progress</div>
        <div class="main-subtitle" id="visibleCount">Showing all <?= $totalProblems ?> problems</div>
      </div>
      <div class="sort-wrap">
        <label for="sortSelect">Sort:</label>
        <select class="sort-select" id="sortSelect" onchange="renderProblems()">
          <option value="default">Default order</option>
          <option value="progress-asc">Progress ↑ (low first)</option>
          <option value="progress-desc">Progress ↓ (high first)</option>
          <option value="difficulty-asc">Easy → Hard</option>
          <option value="difficulty-desc">Hard → Easy</option>
          <option value="alpha">A → Z</option>
        </select>
      </div>
    </div>

    <div id="problemList">
      <!-- Rendered by JS -->
    </div>

  </main>
</div>

<!-- ── TOAST ──────────────────────────────────────────────────── -->
<div class="toast" id="toast"></div>

<!-- ── HIDDEN FILE INPUT FOR IMPORT ──────────────────────────── -->
<input type="file" id="importFile" accept=".json" style="display:none" onchange="handleImport(event)">

<script>
// ─────────────────────────────────────────────────────────
//  DATA
// ─────────────────────────────────────────────────────────
const PROBLEMS = <?= $problemsJson ?>;
const STAGES   = <?= $stagesJson ?>;
const STORAGE_KEY = 'sd_tracker_v2';

const PROBLEM_META = {
<?php foreach ($problems as $p): ?>
  '<?= $p['id'] ?>': {
    title: <?= json_encode($p['title']) ?>,
    file:  <?= json_encode($p['file']) ?>,
    difficulty: '<?= $p['difficulty'] ?>',
    hardProblem: <?= json_encode($p['hardProblem']) ?>,
    topics: <?= json_encode($p['topics']) ?>,
    tags: <?= json_encode($p['tags']) ?>,
  },
<?php endforeach; ?>
};

const STAGE_META = {
<?php foreach ($studyStages as $s): ?>
  '<?= $s['id'] ?>': { label: <?= json_encode($s['label']) ?>, icon: <?= json_encode($s['icon']) ?> },
<?php endforeach; ?>
};

const DIFF_CONFIG = {
  easy:   { label: '🟢 Easy',   color: '#16A34A', bg: '#F0FDF4', border: '#BBF7D0', barColor: '#16A34A', stage: 'L3–L4' },
  medium: { label: '🟡 Medium', color: '#B45309', bg: '#FFFBEB', border: '#FDE68A', barColor: '#D97706', stage: 'L4–L5' },
  hard:   { label: '🔴 Hard',   color: '#DC2626', bg: '#FEF2F2', border: '#FECACA', barColor: '#EF4444', stage: 'L5–L7' },
};

// ─────────────────────────────────────────────────────────
//  STATE
// ─────────────────────────────────────────────────────────
let progress = {};  // { problemId: Set<stageId> }
let currentFilter = 'all';
let openCards = new Set();

function loadProgress() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return;
    const data = JSON.parse(raw);
    for (const [id, stages] of Object.entries(data)) {
      progress[id] = new Set(stages);
    }
  } catch(e) { console.warn('Progress load error', e); }
}

function saveProgress() {
  const data = {};
  for (const [id, stagesSet] of Object.entries(progress)) {
    data[id] = Array.from(stagesSet);
  }
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

function getProblemProgress(id) {
  const done = progress[id] ? progress[id].size : 0;
  return { done, total: STAGES.length, pct: Math.round(done / STAGES.length * 100) };
}

function isCompleted(id) {
  return getProblemProgress(id).done === STAGES.length;
}
function isInProgress(id) {
  const { done } = getProblemProgress(id);
  return done > 0 && done < STAGES.length;
}
function isNotStarted(id) {
  return getProblemProgress(id).done === 0;
}

// ─────────────────────────────────────────────────────────
//  STATS
// ─────────────────────────────────────────────────────────
function updateStats() {
  const byDiff = { easy: { done: 0, total: 0 }, medium: { done: 0, total: 0 }, hard: { done: 0, total: 0 } };
  let totalDone = 0, notStarted = 0, inProgress = 0, completed = 0;

  PROBLEMS.forEach(p => {
    byDiff[p.difficulty].total++;
    if (isCompleted(p.id)) { byDiff[p.difficulty].done++; totalDone++; completed++; }
    else if (isInProgress(p.id)) inProgress++;
    else notStarted++;
  });

  const totalProblems = PROBLEMS.length;
  const overallPct = Math.round(totalDone / totalProblems * 100);

  // Donut
  const circ = 2 * Math.PI * 15.9;
  const fillLen = (overallPct / 100) * circ;
  document.getElementById('donutFill').setAttribute('stroke-dasharray', `${fillLen.toFixed(1)} ${(circ - fillLen).toFixed(1)}`);
  document.getElementById('donutLabel').textContent = overallPct + '%';
  document.getElementById('overallPct').textContent = overallPct + '%';
  document.getElementById('donutCompleted').textContent = `${totalDone} / ${totalProblems}`;

  // Category bars
  ['easy','medium','hard'].forEach(d => {
    const { done, total } = byDiff[d];
    const pct = total ? Math.round(done / total * 100) : 0;
    document.getElementById('bar' + d.charAt(0).toUpperCase() + d.slice(1)).style.width = pct + '%';
    document.getElementById('count' + d.charAt(0).toUpperCase() + d.slice(1)).textContent = `${done}/${total}`;
  });

  // Filter counts
  document.getElementById('countNotStarted').textContent = notStarted;
  document.getElementById('countInProgress').textContent = inProgress;
  document.getElementById('countCompleted').textContent = completed;
}

// ─────────────────────────────────────────────────────────
//  FILTER & SORT
// ─────────────────────────────────────────────────────────
function setFilter(filter, btn) {
  currentFilter = filter;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderProblems();
}

function getFilteredSorted() {
  let list = [...PROBLEMS];

  // Filter
  if (currentFilter !== 'all') {
    list = list.filter(p => {
      if (currentFilter === 'easy' || currentFilter === 'medium' || currentFilter === 'hard')
        return p.difficulty === currentFilter;
      if (currentFilter === 'completed')  return isCompleted(p.id);
      if (currentFilter === 'in-progress') return isInProgress(p.id);
      if (currentFilter === 'not-started') return isNotStarted(p.id);
      return true;
    });
  }

  // Sort
  const sort = document.getElementById('sortSelect')?.value || 'default';
  const diffOrder = { easy: 1, medium: 2, hard: 3 };
  if (sort === 'progress-asc')  list.sort((a,b) => getProblemProgress(a.id).pct - getProblemProgress(b.id).pct);
  if (sort === 'progress-desc') list.sort((a,b) => getProblemProgress(b.id).pct - getProblemProgress(a.id).pct);
  if (sort === 'difficulty-asc')  list.sort((a,b) => diffOrder[a.difficulty] - diffOrder[b.difficulty]);
  if (sort === 'difficulty-desc') list.sort((a,b) => diffOrder[b.difficulty] - diffOrder[a.difficulty]);
  if (sort === 'alpha') list.sort((a,b) => PROBLEM_META[a.id].title.localeCompare(PROBLEM_META[b.id].title));

  return list;
}

// ─────────────────────────────────────────────────────────
//  RENDER
// ─────────────────────────────────────────────────────────
function renderProblems() {
  const list = getFilteredSorted();
  const container = document.getElementById('problemList');

  if (list.length === 0) {
    container.innerHTML = `<div class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>No problems match this filter</h3>
      <p>Try a different filter or check back after studying more!</p>
    </div>`;
    document.getElementById('visibleCount').textContent = 'No problems match';
    return;
  }

  document.getElementById('visibleCount').textContent = `Showing ${list.length} of <?= $totalProblems ?> problems`;

  // Group by difficulty if "all" filter
  if (currentFilter === 'all' || currentFilter === 'easy' || currentFilter === 'medium' || currentFilter === 'hard') {
    const groups = { easy: [], medium: [], hard: [] };
    list.forEach(p => groups[p.difficulty].push(p));
    let html = '';
    const order = currentFilter === 'hard' ? ['hard'] : currentFilter === 'medium' ? ['medium'] : currentFilter === 'easy' ? ['easy'] : ['easy','medium','hard'];
    order.forEach(diff => {
      if (!groups[diff].length) return;
      const cfg = DIFF_CONFIG[diff];
      html += `<div class="problem-section">
        <div class="section-label" style="color:${cfg.color};border-color:${cfg.border}">
          ${cfg.label}
          <span class="sl-badge" style="background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border}">${cfg.stage}</span>
          <span style="font-size:11px;color:var(--text-muted);font-weight:500;margin-left:4px">${groups[diff].length} problem${groups[diff].length !== 1 ? 's' : ''}</span>
        </div>
        ${groups[diff].map(p => renderCard(p)).join('')}
      </div>`;
    });
    container.innerHTML = html;
  } else {
    container.innerHTML = list.map(p => renderCard(p)).join('');
  }

  // Restore open state
  openCards.forEach(id => {
    const card = document.getElementById('card-' + id);
    if (card) card.classList.add('open');
  });

  attachChecklistEvents();
}

function renderCard(p) {
  const meta  = PROBLEM_META[p.id];
  const prog  = getProblemProgress(p.id);
  const cfg   = DIFF_CONFIG[p.difficulty];
  const circ  = 2 * Math.PI * 15;
  const fill  = (prog.pct / 100) * circ;
  const icon  = prog.done === STAGES.length ? '✅' : prog.done > 0 ? '📖' : meta.title.charAt(0);
  const cardClass = prog.done === STAGES.length ? 'completed' : prog.done > 0 ? 'in-progress' : '';
  const ringColor = prog.done === STAGES.length ? '#16A34A' : prog.done > 0 ? cfg.barColor : '#CBD5E1';

  const pipsHtml = STAGES.map(stageId => {
    const done = progress[p.id] && progress[p.id].has(stageId);
    return `<div class="pip ${done ? 'done' : ''}" title="${STAGE_META[stageId].label}"></div>`;
  }).join('');

  const stagesHtml = STAGES.map(stageId => {
    const done = progress[p.id] && progress[p.id].has(stageId);
    const sm = STAGE_META[stageId];
    return `<div class="check-item ${done ? 'checked' : ''}" data-problem="${p.id}" data-stage="${stageId}" onclick="toggleStage('${p.id}','${stageId}',this)">
      <div class="check-box">${done ? '✓' : ''}</div>
      <div class="check-icon">${sm.icon}</div>
      <div class="check-label">${sm.label}</div>
    </div>`;
  }).join('');

  const topicsHtml = meta.topics.slice(0,6).map(t => `<span class="topic-tag">${escHtml(t)}</span>`).join('');

  return `<div class="problem-card ${cardClass}" id="card-${p.id}">
    <div class="card-header" onclick="toggleCard('${p.id}')">
      <div class="card-progress-ring">
        <svg viewBox="0 0 36 36" width="40" height="40">
          <circle class="ring-bg" cx="18" cy="18" r="15"/>
          <circle class="ring-fill" cx="18" cy="18" r="15"
            stroke="${ringColor}"
            stroke-dasharray="${fill.toFixed(1)} ${(circ-fill).toFixed(1)}"/>
        </svg>
        <div class="ring-icon">${icon}</div>
      </div>
      <div class="card-info">
        <div class="card-title">
          ${escHtml(meta.title)}
          <span class="diff-badge" style="background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border}">${cfg.label}</span>
        </div>
        <div class="card-subtitle">⚡ ${escHtml(meta.hardProblem)}</div>
      </div>
      <div class="card-meta">
        <div class="stage-pips">${pipsHtml}</div>
        <span style="font-size:11px;color:var(--text-muted);margin-left:6px;white-space:nowrap">${prog.done}/${STAGES.length}</span>
        <div class="card-arrow">▼</div>
      </div>
    </div>

    <div class="card-body">
      <div class="card-body-grid">
        <div class="info-block">
          <h4>Key Hard Problem</h4>
          <span class="hard-problem-pill">⚡ ${escHtml(meta.hardProblem)}</span>
        </div>
        <div class="info-block">
          <h4>Key Topics</h4>
          <div class="topic-tags">${topicsHtml}</div>
        </div>
      </div>

      <div class="checklist-title">Study Checklist — mark each stage when complete</div>
      <div class="checklist">${stagesHtml}</div>

      <div class="card-footer">
        <div class="card-progress-bar-wrap">
          <div class="card-progress-bar-label">
            <span>Progress</span>
            <span>${prog.done} / ${STAGES.length} stages (${prog.pct}%)</span>
          </div>
          <div class="card-progress-bar-track">
            <div class="card-progress-bar-fill" style="width:${prog.pct}%"></div>
          </div>
        </div>
        <a class="open-md-btn" href="${escHtml(meta.file)}" target="_blank">📄 Open Study Guide</a>
      </div>
    </div>
  </div>`;
}

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ─────────────────────────────────────────────────────────
//  INTERACTIONS
// ─────────────────────────────────────────────────────────
function toggleCard(id) {
  const card = document.getElementById('card-' + id);
  if (!card) return;
  const isOpen = card.classList.toggle('open');
  if (isOpen) openCards.add(id);
  else openCards.delete(id);
}

function attachChecklistEvents() {
  // Events attached inline via onclick — no additional attachment needed
}

function toggleStage(problemId, stageId, el) {
  el.stopPropagation && el.stopPropagation();

  if (!progress[problemId]) progress[problemId] = new Set();
  const wasChecked = progress[problemId].has(stageId);

  if (wasChecked) {
    progress[problemId].delete(stageId);
    el.classList.remove('checked');
    el.querySelector('.check-box').textContent = '';
    el.querySelector('.check-label').style.color = '';
  } else {
    progress[problemId].add(stageId);
    el.classList.add('checked');
    el.querySelector('.check-box').textContent = '✓';
  }

  saveProgress();

  // Update card visuals in-place
  const prog = getProblemProgress(problemId);
  const card = document.getElementById('card-' + problemId);
  if (card) {
    // Pips
    const pips = card.querySelectorAll('.pip');
    STAGES.forEach((sid, i) => {
      pips[i]?.classList.toggle('done', progress[problemId]?.has(sid) || false);
    });
    // Stage count badge
    const badge = card.querySelector('.card-meta span');
    if (badge) badge.textContent = `${prog.done}/${STAGES.length}`;
    // Progress bar
    const barFill = card.querySelector('.card-progress-bar-fill');
    if (barFill) barFill.style.width = prog.pct + '%';
    const barLabel = card.querySelector('.card-progress-bar-label span:last-child');
    if (barLabel) barLabel.textContent = `${prog.done} / ${STAGES.length} stages (${prog.pct}%)`;
    // Ring
    const circ = 2 * Math.PI * 15;
    const fill = (prog.pct / 100) * circ;
    const ringFill = card.querySelector('.ring-fill');
    if (ringFill) {
      ringFill.setAttribute('stroke-dasharray', `${fill.toFixed(1)} ${(circ - fill).toFixed(1)}`);
      const cfg = DIFF_CONFIG[PROBLEM_META[problemId].difficulty];
      ringFill.setAttribute('stroke', prog.done === STAGES.length ? '#16A34A' : prog.done > 0 ? cfg.barColor : '#CBD5E1');
    }
    // Ring icon
    const ringIcon = card.querySelector('.ring-icon');
    if (ringIcon) {
      const meta = PROBLEM_META[problemId];
      ringIcon.textContent = prog.done === STAGES.length ? '✅' : prog.done > 0 ? '📖' : meta.title.charAt(0);
    }
    // Card class
    card.classList.toggle('completed', prog.done === STAGES.length);
    card.classList.toggle('in-progress', prog.done > 0 && prog.done < STAGES.length);
    card.classList.remove('completed', 'in-progress');
    if (prog.done === STAGES.length) card.classList.add('completed');
    else if (prog.done > 0) card.classList.add('in-progress');
  }

  updateStats();

  // Toast on completion
  if (prog.done === STAGES.length) {
    showToast(`🎉 ${PROBLEM_META[problemId].title} — all stages complete!`);
  } else if (!wasChecked) {
    // silent save
  }
}

// ─────────────────────────────────────────────────────────
//  TOAST
// ─────────────────────────────────────────────────────────
let toastTimer;
function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ─────────────────────────────────────────────────────────
//  EXPORT / IMPORT / RESET
// ─────────────────────────────────────────────────────────
function exportProgress() {
  const data = {};
  for (const [id, stagesSet] of Object.entries(progress)) {
    data[id] = Array.from(stagesSet);
  }
  const meta = {
    exportedAt: new Date().toISOString(),
    version: 2,
    progress: data,
  };
  const blob = new Blob([JSON.stringify(meta, null, 2)], { type: 'application/json' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url;
  a.download = `sd-progress-${new Date().toISOString().slice(0,10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
  showToast('📤 Progress exported!');
}

function importProgress() {
  document.getElementById('importFile').click();
}

function handleImport(e) {
  const file = e.target.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    try {
      const parsed = JSON.parse(ev.target.result);
      const raw = parsed.progress || parsed; // support both wrapped and bare formats
      progress = {};
      for (const [id, stages] of Object.entries(raw)) {
        if (PROBLEM_META[id]) {
          progress[id] = new Set(Array.isArray(stages) ? stages : []);
        }
      }
      saveProgress();
      renderProblems();
      updateStats();
      showToast('📥 Progress imported successfully!');
    } catch(err) {
      showToast('❌ Invalid JSON file — import failed');
    }
    e.target.value = '';
  };
  reader.readAsText(file);
}

function confirmReset() {
  if (!confirm('Reset ALL progress? This cannot be undone.')) return;
  progress = {};
  saveProgress();
  renderProblems();
  updateStats();
  showToast('🗑️ Progress reset');
}

// ─────────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────────
loadProgress();
renderProblems();
updateStats();
</script>
</body>
</html>
