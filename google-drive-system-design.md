# System Design: Google Drive

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard  
> Core challenges: **Delta sync with chunked dedup · Conflict resolution across concurrent edits · 1B users × 15 GB storage at petabyte scale**

---

## Table of Contents

1. [Clarifying Questions](#1-clarifying-questions)
2. [Functional Requirements](#2-functional-requirements)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Back-of-Envelope Estimation](#4-back-of-envelope-estimation)
5. [High-Level Design](#5-high-level-design)
6. [Trade-Off Discussion](#6-trade-off-discussion)
7. [Deep Dive](#7-deep-dive)
   - 7.1 Chunking & Delta Sync
   - 7.2 Upload & Download Pipeline
   - 7.3 Conflict Detection & Resolution
   - 7.4 Real-Time Sync Across Devices
   - 7.5 File Versioning
   - 7.6 Sharing & Access Control
   - 7.7 Search
8. [Data Models](#8-data-models)
9. [Follow-Up Questions](#9-follow-up-questions)
10. [Interview Summary Card](#10-interview-summary-card)

---

## 1. Clarifying Questions

```
"Are we designing Google Drive specifically, or a generic cloud storage product?"
→ Generic cloud storage (like Dropbox / Google Drive) — not Google Docs collaboration

"Do we need real-time collaborative editing (Google Docs)?"
→ Out of scope — that's a different system (Operational Transforms / OT)
  We handle file sync, not character-level collaborative editing

"Do we need file sharing and permissions?"
→ Yes — share with specific users, public links, view/edit/comment permissions

"Do we need versioning / file history?"
→ Yes — last 30 versions retained

"Do we need offline support?"
→ Yes — client works offline, syncs on reconnect

"What's the scale?"
→ 1 billion users, 15 GB free storage each, paid tiers up to 2 TB

"Do we need mobile (iOS / Android) in addition to desktop?"
→ Yes — sync across all devices
```

---

## 2. Functional Requirements

### Core (Must Have)

| # | Requirement | Notes |
|---|-------------|-------|
| FR-1 | **Upload file** | Any file type; up to 5 TB (Google Drive limit); resumable |
| FR-2 | **Download file** | Stream large files; resume interrupted downloads |
| FR-3 | **File sync** | Changes on one device sync to all other devices automatically |
| FR-4 | **Delta sync** | Only upload changed bytes, not entire file on modification |
| FR-5 | **Folder hierarchy** | Nested folders; move/rename/delete |
| FR-6 | **File versioning** | Last 30 versions; restore any version |
| FR-7 | **Sharing** | Share with email, group, or public link; view/edit/comment |
| FR-8 | **Offline access** | Mark files for offline; client works without network |
| FR-9 | **Search** | Full-text search across file names and document content |
| FR-10 | **Conflict resolution** | If same file edited on two devices offline → create conflict copy |

### Out of Scope

- Real-time collaborative editing (Google Docs), Google Photos AI features, admin console, DLP policies

---

## 3. Non-Functional Requirements

| Property | Target | Rationale |
|----------|--------|-----------|
| **Availability** | 99.99% | Users store critical work files — outage = lost productivity |
| **Durability** | 11 nines | Files are irreplaceable — use S3/GCS cross-region replication |
| **Upload throughput** | Limited by user's bandwidth | No artificial server cap; handle 10 GB/s aggregate inbound |
| **Sync latency** | < 30 seconds for small files | Change on device A → visible on device B within 30s |
| **Download latency** | p99 < 200ms to first byte | Metadata fast; large file streaming via CDN |
| **Consistency** | Strong per-file (last-write-wins for metadata) | User must see their own writes immediately |
| **Deduplication** | Block-level global dedup | Storage efficiency at billion-user scale |
| **Security** | Encryption in transit (TLS) + at rest (AES-256) | GDPR, HIPAA compliance |
| **Scale** | 1B users × 15 GB = 15 EB baseline | Petabyte-scale operations |

---

## 4. Back-of-Envelope Estimation

### Storage

```
Users:
  Total users: 1 billion
  Avg storage used: 5 GB (many use < 1 GB; some use 100 GB+)
  Total stored data: 1B × 5 GB = 5 EB

Storage breakdown:
  Files: documents (1 MB avg), photos (3 MB avg), videos (100 MB avg)
  Documents: 60% of files, 20% of storage
  Photos:    30% of files, 40% of storage
  Videos:    5% of files,  35% of storage
  Other:     5% of files,  5% of storage

New uploads per day:
  1B users × 2 files/day avg = 2B file operations/day
  New data written: 2B × 500 KB avg = 1 PB/day (net, after dedup: ~300 TB)
  Deduplication: photos especially — many users backup same stock images,
    memes, WhatsApp forwarded images → 70% dedup rate for photos
    Documents: 20% dedup rate (unique content more likely)
    Effective new storage: ~300 TB/day → 110 PB/year

Chunk storage:
  Average chunk size: 4 MB
  Total chunks: 5 EB / 4 MB = 1.25 trillion unique chunks
  After dedup: ~40% unique → 500 billion unique chunks
  Chunk index size: 500B × 32 bytes (SHA-256 hash + location) = 16 TB
  → Fits in distributed hash table (sharded across 1,000 nodes × 16 GB each)
```

### QPS

```
File operations:
  2B operations/day / 86,400 = 23,148 ops/sec (avg)
  Peak: 3× = 69,444 ops/sec

Upload bandwidth:
  1B users × 10 MB new files/day = 10 PB/day inbound
  10 PB / 86,400 = 115 GB/s aggregate upload bandwidth
  Per server (100 Gbps NIC): 125 MB/s → need 920 upload servers
  With chunked S3 presigned upload (bypasses servers): ~10 load balancers sufficient

Download bandwidth:
  Users download ~3× what they upload: 30 PB/day → 347 GB/s aggregate
  CDN absorbs 80% for popular/shared files: origin serves ~70 GB/s

Sync events (metadata):
  1B users × 3 devices avg × 1 sync check/min = 3B checks/min = 50M checks/sec
  → This is too high for polling; use push notifications (WebSocket/SSE) instead
  With push: only active users create sync traffic
    50M active at any moment × 1 sync notification/event = manageable

Metadata DB:
  1B users × 100 files avg = 100B file records
  At 1 KB metadata per file: 100 TB → sharded PostgreSQL (1,000 shards × 100 GB)
```

### Infrastructure

```
Chunk storage: GCS / S3 (PB-scale object storage)
Chunk index:  Redis cluster (16 TB RAM — 160 nodes × 100 GB each)
Metadata DB:  PostgreSQL, sharded by user_id (1,000 shards)
Sync service: WebSocket connections for 50M active users
  → 50M × 10 KB connection state = 500 GB RAM → 500 servers × 1 GB/server
CDN:          CloudFront / GCS CDN for popular/shared files
```

---

## 5. High-Level Design

### Architecture Overview

```
                     ┌──────────────────────────────────┐
                     │           API Gateway             │
                     │   Auth · Rate Limit · TLS term    │
                     └───────────────┬──────────────────┘
                                     │
    ┌──────────────┬─────────────────┼───────────────┬──────────────┐
    │              │                 │               │              │
    ▼              ▼                 ▼               ▼              ▼
┌──────────┐ ┌──────────┐  ┌──────────────┐  ┌──────────┐  ┌──────────────┐
│ Upload   │ │ Download │  │ Metadata Svc │  │  Sync    │  │   Search     │
│ Service  │ │ Service  │  │ (file tree,  │  │  Service │  │   Service    │
└────┬─────┘ └────┬─────┘  │  versioning) │  │(WebSocket│  └──────────────┘
     │            │         └──────┬───────┘  │ /SSE)   │
     │            │                │          └──────────┘
     ▼            ▼                ▼
┌──────────────────────┐  ┌──────────────┐
│   GCS / S3           │  │  PostgreSQL  │
│   (chunk storage)    │  │  (metadata,  │
│   Content-addressed  │  │   versions,  │
│   by SHA-256         │  │   ACLs)      │
└──────────────────────┘  └──────┬───────┘
                                  │
                         ┌────────┴────────┐
                         │     Kafka        │
                         │  file.uploaded   │
                         │  file.modified   │
                         │  file.deleted    │
                         └────────┬────────┘
                                  │
                    ┌─────────────┼────────────┐
                    ▼             ▼            ▼
             Sync Notifier   Search        Thumbnail
             (push to        Indexer       Generator
              devices)
```

### Core Request Flows

#### Upload New File

```
1. Client computes file chunks:
   Split file into 4 MB chunks
   Compute SHA-256 of each chunk
   Compute SHA-256 of entire file (content hash = file identity)

2. Client → POST /api/v1/files { name, parent_folder_id, content_hash, chunks: [{hash, size}, ...] }
   Response: { file_id, missing_chunk_hashes: ["abc...", "def..."] }
   (Server tells client which chunks it doesn't have yet — dedup!)

3. Client: for each missing chunk hash:
   GET /api/v1/upload/chunk-url?hash={chunk_hash}
   → Server: returns S3 presigned PUT URL
   Client: PUT {presigned_url} {chunk_bytes}  (direct to S3)

4. Client → POST /api/v1/files/{file_id}/complete
   → Metadata Service: mark file as available
   → Kafka "file.uploaded" { file_id, user_id, parent_folder_id }

5. Kafka consumers:
   ├── Sync Notifier: push change event to user's other devices
   ├── Search Indexer: extract text → index in Elasticsearch
   └── Thumbnail Generator: generate preview for office docs / images

Upload resumes on failure:
  Same content_hash → same file_id (idempotent)
  missing_chunk_hashes tells client exactly what to resume uploading
  Already-uploaded chunks (on S3) skipped automatically
```

#### Download / Sync File

```
1. Device B receives sync notification: "file {file_id} updated on device A"
   (WebSocket push — see Deep Dive 7.4)

2. Client → GET /api/v1/files/{file_id}/metadata
   Response: { name, size, content_hash, chunks: [{hash, order, size}], version }

3. Client: compare local chunks vs. server chunks:
   For each chunk: if local_hash == server_hash → skip (unchanged)
   Download only chunks where hash differs (delta sync)

4. For each chunk to download:
   GET /api/v1/download/chunk-url?hash={chunk_hash}
   → Presigned S3 GET URL (or CDN URL for popular chunks)
   Client: GET {presigned_url} → receives chunk bytes

5. Client: reassemble file from chunks (concat in order)
   Verify: SHA-256 of reassembled file == content_hash (corruption check)

6. Client: atomically swap temp file → final path (rename operation)
   → No partial writes visible to user's applications
```

---

## 6. Trade-Off Discussion

### Trade-Off 1: Chunk Size — Fixed vs. Variable (Content-Defined)

| Approach | Dedup Efficiency | Complexity | Chunk Count |
|----------|-----------------|------------|-------------|
| Fixed 4 MB chunks | Poor — insertion shifts all chunk boundaries | Simple | Predictable |
| Fixed 1 MB chunks | Better dedup resolution | Simple | 4× more chunks |
| **Content-Defined Chunking (Rabin fingerprinting)** | Excellent — insertion only affects local chunks | Medium | Variable, avg 4 MB |

```
Content-Defined Chunking (CDC) — Rabin Fingerprinting:

Algorithm:
  Sliding window of W=48 bytes scans the file byte by byte
  At each position: compute rolling polynomial hash of window content
  If hash % target_chunk_size == 0: cut chunk boundary here

Properties:
  Average chunk size: ~4 MB (tunable via target_chunk_size)
  Min chunk: 512 KB (prevent tiny chunks)
  Max chunk: 8 MB (prevent huge chunks)
  Boundaries are content-defined → inserting text in middle of file only
  changes one chunk boundary, not all subsequent chunks (like fixed-size would)

Example — Why CDC is superior for delta sync:
  File: [AAAA][BBBB][CCCC][DDDD]  (4 MB chunks, fixed)
  User inserts 100 bytes at position 0:
    Fixed: ALL chunks shift → 4 chunks re-uploaded (4 MB × 4 = 16 MB wasted)
    CDC:   Only the first chunk boundary shifts → 1 chunk re-uploaded (4 MB)

Dedup benefit at Google Drive scale:
  Many users have same operating system DLLs, photos forwarded via WhatsApp,
  copied documents — identical byte sequences produce identical chunk hashes
  Global chunk dedup eliminates ~30% of stored data at scale

Decision: Content-Defined Chunking for production. Fixed chunks simpler to explain
  in interviews — offer CDC as the enhanced version when interviewer asks for depth.
```

---

### Trade-Off 2: Conflict Resolution Strategy

| Approach | Data Safety | UX | Complexity |
|----------|------------|-----|------------|
| Last-Write-Wins | Data loss (losing edit) | Simple | Simple |
| Lock-based (prevent conflict) | Perfect | Poor (blocks editors) | Medium |
| **Conflict Copy (Dropbox/Drive approach)** | No data loss | Requires user action | Medium |
| Operational Transforms (Google Docs) | Perfect, auto-merge | Seamless | Very high |
| CRDTs | Auto-merge | Seamless | High |

```
Decision: Conflict Copy for file-level sync (recommended for interviews)

Why not Last-Write-Wins?
  User A edits report.docx offline on laptop for 2 hours.
  User B edits same file on mobile for 1 hour.
  B's device syncs first → LWW discards A's 2 hours of work.
  Unacceptable data loss.

Why not Operational Transforms?
  OT requires all operations to flow through a central server in order.
  Only viable for real-time collaboration (Google Docs — users online simultaneously).
  For offline sync (our requirement), OT is impractical — we don't have
  the operation log from the offline device.

Conflict Copy approach:
  Detect: file has two divergent versions (both modified since last common ancestor)
  Resolve: keep BOTH versions
    Original path: report.docx (one version)
    Conflict copy: "report (conflicted copy by Alice, 2024-01-15).docx"
  User: manually merge the two versions (or discard one)
  Notify: "A conflicting change was detected — we've kept both versions"

When does conflict occur?
  User A modifies file_v1 → file_v2 (offline)
  User B modifies file_v1 → file_v3 (offline, same base)
  Both sync → server has file_v2 and file_v3, both derived from file_v1
  Server detects: same parent_version_id, different content_hash → conflict

Implementation:
  Server-side: if file.parent_version != server.current_version AND
                  file.content_hash != server.content_hash → conflict
  Create conflict copy with modified filename
  Emit notification to all devices: "Conflict detected for report.docx"
```

---

### Trade-Off 3: Sync Protocol — Polling vs. WebSocket vs. SSE

| Approach | Latency | Server Load | Complexity |
|----------|---------|------------|------------|
| Polling (every 30s) | 30s avg | Very high — 50M × 2 req/min = 100M/min | Simple |
| Long polling | 5-10s avg | High — open HTTP connections | Medium |
| **WebSocket (Recommended)** | < 1s | Low — event-driven push | Medium |
| SSE | < 1s | Low — unidirectional push | Simple |

```
Decision: WebSocket for desktop (bidirectional needed for heartbeat + ack)
          SSE for mobile (simpler, auto-reconnect, battery-friendly)

WebSocket connection lifecycle:
  Client connects → server assigns to a consistent-hashing shard
  (user_id mod N → always same WebSocket server → session affinity)

  Heartbeat: client pings every 30s; server pong or disconnect
  On file change: server pushes { event: "file_changed", file_id, version }
  Client: fetches metadata delta → downloads changed chunks

  Connection state stored in Redis (not in-memory — for failover):
    HSET ws_session:{user_id} { device_id, server_id, last_seen }

  At 50M concurrent WebSocket connections:
    Each connection: ~10 KB RAM (socket buffer)
    Total: 50M × 10 KB = 500 GB RAM → 500 WebSocket servers × 1 GB connection state

Why not SSE for desktop?
  Desktop client needs to send events up to server (e.g., "I've synced this version")
  SSE is unidirectional (server → client only)
  WebSocket handles both directions cleanly

Mobile optimization (SSE + APNS/FCM fallback):
  SSE when app is in foreground (active sync)
  FCM/APNs push notification when app backgrounded:
    Server: detects WebSocket closed → send push notification
    "Your files have been updated"
    User opens app → SSE reconnects → fetches delta
```

---

### Trade-Off 4: Chunk Storage — Deduplicated vs. Per-User

| Approach | Storage Cost | Privacy Risk | Query Complexity |
|----------|-------------|-------------|-----------------|
| **Global dedup (content-addressed)** | 30-50% reduction | Timing attacks possible | Medium |
| Per-user isolated storage | Highest (no dedup) | Zero cross-user info | Simple |
| Per-user with intra-user dedup | Medium | Low | Medium |

```
Decision: Global dedup with careful privacy mitigation

Global dedup mechanics:
  chunk_store key: SHA-256(chunk_bytes)
  All users' files share the same chunk store
  chunk_store[hash] exists → don't store duplicate bytes → save 30-50% storage

Privacy concern — "Timestamp Attack":
  Attacker uploads rare file → if upload is "instant" (0 bytes transferred) →
  server already has this content → someone else uploaded it before you
  (Was it the FBI? A journalist? Who else has this file?)

Mitigation:
  Mandatory re-upload: even if server has chunk, client still "uploads" a small token
  Server: always responds with same fixed latency (200ms) regardless of cache hit
  → Attacker cannot distinguish "chunk existed" from "chunk uploaded" by timing
  → Note: NSA / law enforcement with server access can still see chunk hashes
           For true privacy: per-user encryption keys (client-side encryption below)

Client-side encryption (E2E) option:
  User enables "End-to-End Encryption" (like Mega.nz):
  Client: encrypt each chunk with user's derived key before computing hash
  Upload: encrypted bytes → SHA-256(encrypted) as chunk key
  Server: sees only ciphertext — cannot read content
  Dedup: zero cross-user dedup (each user's key produces unique ciphertext)
  Trade-off: no server-side search, no thumbnail generation, no content moderation
  → E2E sacrifices features for privacy — explicit user choice

Decision: Global dedup by default (no E2E) + timing mitigation
          E2E encryption as opt-in "Vault" feature for sensitive files
```

---

## 7. Deep Dive

### 7.1 Chunking & Delta Sync

#### Rabin Fingerprinting (Content-Defined Chunking)

```python
# Simplified CDC implementation (Python pseudocode)
WINDOW_SIZE = 48
MIN_CHUNK = 512 * 1024    # 512 KB
MAX_CHUNK = 8 * 1024 * 1024  # 8 MB
MASK = 0x0003FFFF          # controls avg chunk size (~4 MB when 18 bits)

def chunk_file(file_bytes: bytes) -> list[bytes]:
    chunks = []
    start = 0
    window_hash = 0

    for i in range(len(file_bytes)):
        # Rolling hash: add new byte, remove oldest byte from window
        window_hash = ((window_hash << 1) ^ file_bytes[i]
                      ^ (file_bytes[i - WINDOW_SIZE] if i >= WINDOW_SIZE else 0))

        chunk_size = i - start + 1

        # Chunk boundary: hash fingerprint matches mask, within size bounds
        if (window_hash & MASK == 0 and chunk_size >= MIN_CHUNK) or chunk_size >= MAX_CHUNK:
            chunks.append(file_bytes[start:i+1])
            start = i + 1
            window_hash = 0

    if start < len(file_bytes):
        chunks.append(file_bytes[start:])  # Last chunk

    return chunks

def compute_chunk_hash(chunk: bytes) -> str:
    return hashlib.sha256(chunk).hexdigest()
```

#### Delta Sync Protocol

```
Initial state:
  file.txt on server: chunks [A, B, C, D] (each chunk identified by SHA-256)
  file.txt on client: identical — client knows [hash_A, hash_B, hash_C, hash_D]

User edits file (inserts paragraph in middle):
  New file: chunks [A, B', C, D, E]  (B' = modified B, E = new chunk from insertion)

Sync process:
  1. Client: detect file change via OS file system watcher (inotify on Linux, FSEvents on Mac)
  2. Client: re-chunk file using CDC → compute new chunk hashes
             [hash_A, hash_B', hash_C, hash_D, hash_E]
  3. Client → POST /api/v1/files/{id}/sync
     Body: { content_hash: new_file_hash, chunks: [hash_A, hash_B', hash_C, hash_D, hash_E] }
  4. Server: compare with stored chunks [hash_A, hash_B, hash_C, hash_D]
     Response: { missing_chunks: [hash_B', hash_E] }
     (hash_A, hash_C, hash_D already in chunk store — no upload needed!)
  5. Client: upload only [B', E] → 2 chunks × 4 MB = 8 MB (not 20 MB entire file)

Bandwidth saved: (5 - 2) / 5 = 60% bandwidth reduction
For large files: editing a 1 GB file in one place → upload ~8 MB not 1 GB (99.2% savings)
```

---

### 7.2 Upload & Download Pipeline

#### Upload Service

```
Resumable upload state machine:
  States: INITIATED → UPLOADING → CHUNKS_RECEIVED → COMMITTED → FAILED

  INITIATED:
    Client: POST /api/v1/files { metadata }
    Server: generates file_id (Snowflake), stores metadata with status=INITIATED
            Records expected chunk hashes
    Response: { file_id, missing_chunk_hashes }

  UPLOADING:
    Client uploads chunks via S3 presigned URLs
    Each chunk PUT: S3 triggers Lambda → validates SHA-256 → mark chunk as received
    Server tracks: SET received_chunks:{file_id} (Redis set of received chunk hashes)

  CHUNKS_RECEIVED:
    All chunks uploaded → Client: POST /api/v1/files/{id}/complete
    Server: verify all expected chunks in received_chunks set
            Compose S3 object (or reference existing chunk paths)

  COMMITTED:
    Metadata marked as committed, version incremented
    Kafka event published
    Old temp chunk tracking deleted from Redis

  FAILED:
    TTL 24h on upload session; if expired → client must restart upload
    Uploaded chunks retained for 24h (another user may need same chunks)

Large file optimizations:
  Server-side chunk assembly: for files > 100 chunks, server assembles from chunks
    S3 Multipart Copy: compose final object from chunk objects (no re-upload)
    GCS Compose API: up to 32 component objects per compose call (batch for 100+ chunks)
  Parallel chunk uploads: client uploads up to 5 chunks simultaneously
```

#### Download & Streaming

```
Small files (< 10 MB):
  Client → GET /api/v1/files/{id}/download
  → Server: generate presigned S3 URL (valid 1 hour)
  → Client: GET {presigned_url} → receives entire file

Large files (> 10 MB):
  Client → GET /api/v1/files/{id}/chunks → list of chunk hashes in order
  → Client: for each chunk, GET presigned URL → download chunk
  → Parallel downloads: up to 5 chunks simultaneously (range requests)
  → Resume: skip already-downloaded chunks (compare local vs server hashes)

Range requests for streaming:
  If user previews a large video in Drive:
  Client: GET {chunk_url} with Range: bytes=0-4194303 (first chunk)
  → Progressive playback starts after first chunk
  → Background: prefetch next 3 chunks

Shared file CDN optimization:
  Files shared publicly or with many users:
    → Cache in CloudFront CDN (Cache-Control: public, max-age=3600)
    → Presigned URL workaround: use Cookie-based CDN auth (not URL params)
      → Allows CDN caching (same URL for all users)
  Private files: Cache-Control: private → no CDN caching → direct S3
```

---

### 7.3 Conflict Detection & Resolution

#### Vector Clocks for Version Tracking

```
Each file maintains a version history:
  version_id: monotonically increasing integer per file
  parent_version_id: which version this was derived from

Conflict detection algorithm:
  A and B are in conflict if:
    A.parent_version_id == B.parent_version_id (same base version)
    A.content_hash != B.content_hash (different content)
    A.version_id != B.version_id (both modified base)

Example:
  Server has version 5 (parent=4)
  Device A (offline): modifies file → version A (parent=5)
  Device B (offline): modifies file → version B (parent=5)
  A syncs first → server accepts A as version 6
  B syncs → server detects: B.parent=5 ≠ server.current=6
             B.content_hash ≠ server.content_hash
             → CONFLICT

Conflict resolution:
  Server creates conflict copy:
    Rename: "report.docx" → "report (conflicted copy by Bob, 2024-01-15).docx"
    Both versions stored (version 6 = A, version 7 = conflict copy of B)
  All devices receive sync notification for BOTH versions
  User sees conflict copy in their Drive → manually resolves

Auto-resolution (safe cases):
  Binary files: never auto-merge (too risky)
  Text files: attempt 3-way merge (GNU diff3)
    Base = common ancestor version
    Ours = Device A changes
    Theirs = Device B changes
    If no overlapping edits: merge succeeds (no conflict copy needed)
    If overlap: create conflict copy (can't safely merge)
```

---

### 7.4 Real-Time Sync Across Devices

#### Change Notification Architecture

```
Components:
  Kafka topic: "file.changes" (partitioned by user_id)
  Sync Service: WebSocket servers (stateless, horizontally scalable)
  Connection Registry: Redis (maps user_id → active WebSocket server IDs)

Flow when File Changes:
  1. Upload Service → Kafka "file.changes" { user_id, file_id, event_type, version }
  2. Sync Service (Kafka consumer):
     a. Look up user's devices: SMEMBERS user_devices:{user_id}
     b. For each device:
        - Look up which WebSocket server the device is connected to:
          GET ws_conn:{device_id} → server_id (Redis)
        - If same server: push event directly via local WebSocket
        - If different server: publish to Redis Pub/Sub channel "ws_server:{server_id}"
     c. Target WebSocket server: subscribes to its own channel → receives event → pushes to client

Connection Registry (Redis):
  HSET ws_conn:{device_id} { server_id, connected_at, user_id }
  EXPIRE ws_conn:{device_id} 70   (refresh every 30s heartbeat → auto-expires if disconnected)

  SADD user_devices:{user_id} {device_id}
  EXPIRE individual device registration on disconnect

WebSocket message format:
  { "type": "file_changed",
    "file_id": "F123456",
    "version": 47,
    "event": "modified",   // created | modified | deleted | moved
    "modified_by": "device_A",
    "timestamp": 1705329600000 }

Client behavior on receiving event:
  1. Download metadata: GET /api/v1/files/{file_id}/metadata (get new chunk list)
  2. Compute delta (compare local vs new chunk hashes)
  3. Download only changed chunks
  4. Notify user: "Syncing report.docx..." → "Up to date ✓"

Offline device handling:
  Device reconnects after offline period:
    Client: GET /api/v1/sync/changes?since={last_sync_timestamp}
    Server: returns list of file events since that timestamp
    Client: processes events in order, applies local changes
    → Guaranteed no missed events (Kafka retained for 7 days)
```

---

### 7.5 File Versioning

#### Version Storage

```
Versioning strategy:
  NOT stored as full file copies (too expensive: 30 versions × 1 GB = 30 GB per file)
  Stored as chunk-level snapshots:
    Each version = ordered list of chunk hashes
    Chunks shared across versions → storage = unique chunks only

Example:
  Version 1: [A, B, C, D]         (all unique)
  Version 2: [A, B', C, D]        (B→B', B' is new chunk)
  Version 3: [A, B', C, D, E]     (E is new chunk)

  Stored chunks: A, B, B', C, D, E = 6 chunks (not 3 versions × 4 chunks = 12)
  Storage savings: 50% in this example; typically 80-95% for document edits

Chunk retention:
  Chunk referenced by any version within retention window → keep
  Chunk referenced only by expired versions → eligible for deletion
  GC job (daily): scan versions older than 30 days → unreference chunks → delete from S3

Version metadata (PostgreSQL):
  file_versions table:
    version_id   BIGINT PRIMARY KEY
    file_id      BIGINT
    version_num  INTEGER
    content_hash TEXT (SHA-256 of full file)
    chunk_hashes TEXT[] (ordered array of chunk SHAs)
    size_bytes   BIGINT
    created_at   TIMESTAMPTZ
    created_by   BIGINT (user_id — which device/user created this version)
    comment      TEXT (optional — "Before tax season edits")

Restore version:
  GET /api/v1/files/{id}/versions → list of versions
  POST /api/v1/files/{id}/restore?version={version_num}
  → Server: creates new version with chunk_hashes from target version
            (not a time machine — creates new head version identical to old)
  → All devices get sync notification → download "new" version (which is chunks from old)
  → Delta sync efficiency: if user already has those chunks locally → zero download!
```

---

### 7.6 Sharing & Access Control

#### Permission Model

```
Permission levels:
  OWNER:    full control (delete, share, change perms)
  EDITOR:   read + write + share (cannot delete or change owner)
  COMMENTER: read + comment only (Google Docs comment)
  VIEWER:   read only

Sharing mechanisms:
  1. Direct share: share with specific email address / Google account
  2. Link sharing: anyone with link can view/edit (no login required for view)
  3. Domain sharing: anyone at @company.com can access (Workspace feature)

ACL storage (PostgreSQL):
  CREATE TABLE file_permissions (
      permission_id  BIGINT PRIMARY KEY,
      file_id        BIGINT NOT NULL,
      grantee_type   TEXT NOT NULL,   -- 'user'|'group'|'domain'|'anyone'
      grantee_id     TEXT,            -- user_id, group_id, domain name, or NULL for 'anyone'
      permission     TEXT NOT NULL,   -- 'OWNER'|'EDITOR'|'COMMENTER'|'VIEWER'
      link_token     TEXT UNIQUE,     -- for link-based sharing
      expires_at     TIMESTAMPTZ,
      created_at     TIMESTAMPTZ DEFAULT NOW()
  );
  CREATE INDEX idx_perms_file ON file_permissions (file_id);
  CREATE INDEX idx_perms_grantee ON file_permissions (grantee_type, grantee_id);

Folder inheritance:
  Files inherit parent folder's permissions (unless explicitly overridden)
  Recursive traversal expensive for deep hierarchies
  Optimization: pre-compute "effective permissions" at cache layer
    Redis HSET file_effective_perms:{file_id} { user_id: 'EDITOR', ... }
    Invalidated when folder permissions change (cascade)

Link-based sharing:
  link_token: 128-bit cryptographically random token (URL-safe base64)
  CDN: public links served via CDN (cacheable, no auth cookie required)
  Revocation: DELETE permission_id → immediately invalidates CDN cache
    CDN invalidation: CloudFront CreateInvalidation for /shared/{link_token}/*
```

---

### 7.7 Search

#### Full-Text Search Pipeline

```
Indexing:
  Kafka "file.committed" → Search Indexer worker
  Text extraction by file type:
    .docx → Apache Tika → plain text
    .pdf  → pdfminer → plain text (OCR for scanned PDFs via Tesseract)
    .xlsx → extract cell values, formulas
    Images → Vision API → alt text extraction (OCR for screenshots)

  Elasticsearch document:
  {
    "file_id": "F123",
    "user_id": "U456",
    "name": "Q4 Financial Report",
    "content": "Revenue was $2.3B in Q4...",
    "owner_id": "U456",
    "shared_with": ["U789", "G123"],
    "type": "document",
    "size_bytes": 524288,
    "updated_at": "2024-01-15T10:30:00Z",
    "tags": ["finance", "quarterly"]
  }

Search query:
  User: searches "Q4 revenue"
  1. Elasticsearch multi-field search:
     BM25 on name (boost 3×), content (boost 1×), tags (boost 2×)
  2. MANDATORY filter: file must be accessible to requesting user
     Filter: { "should": [
       { "term": { "owner_id": user_id } },
       { "term": { "shared_with": user_id } }
     ]}
     → Security critical: user cannot find files they don't have access to
  3. Result ranking:
     Combine BM25 relevance + recency + "pinned" flag
  4. Return: file name, path, snippet (highlighted match in content)

Permission-aware search:
  Naive approach: query Elasticsearch, then filter results by ACL
    → Problem: if first 20 Elasticsearch results are all private files,
               user sees 0 results (even though position 21 might match)
    → "Security pagination" issue

  Better approach: include user's permission list IN the Elasticsearch filter
    user_accessible_file_ids = fetch from ACL service (cached in Redis)
    Elasticsearch filter: file_id IN user_accessible_file_ids
    → Works well if user has access to < 100K files (stored as Redis SET)
    → For Workspace (company-wide sharing): pre-index domain perms in Elasticsearch
```

---

## 8. Data Models

### File Metadata

```sql
-- PostgreSQL (sharded by user_id % 1000)
CREATE TABLE files (
    file_id          BIGINT         PRIMARY KEY,    -- Snowflake ID
    owner_id         BIGINT         NOT NULL,
    parent_folder_id BIGINT,                        -- NULL for root
    name             VARCHAR(1024)  NOT NULL,
    file_type        VARCHAR(20),                   -- 'file'|'folder'|'shortcut'
    mime_type        VARCHAR(100),
    content_hash     TEXT,                          -- SHA-256 of full file
    current_version  INTEGER        DEFAULT 1,
    size_bytes       BIGINT         DEFAULT 0,
    is_trashed       BOOLEAN        DEFAULT false,
    is_starred       BOOLEAN        DEFAULT false,
    created_at       TIMESTAMPTZ    DEFAULT NOW(),
    modified_at      TIMESTAMPTZ    DEFAULT NOW(),
    trashed_at       TIMESTAMPTZ
);

CREATE UNIQUE INDEX idx_files_parent_name
    ON files (parent_folder_id, name)
    WHERE is_trashed = false;                        -- unique name per folder (excluding trash)

CREATE INDEX idx_files_owner ON files (owner_id, modified_at DESC);
```

### File Versions

```sql
CREATE TABLE file_versions (
    file_id         BIGINT         NOT NULL,
    version_num     INTEGER        NOT NULL,
    content_hash    TEXT           NOT NULL,
    chunk_hashes    TEXT[]         NOT NULL,        -- ordered array of chunk SHA-256s
    size_bytes      BIGINT         NOT NULL,
    created_at      TIMESTAMPTZ    DEFAULT NOW(),
    created_by      BIGINT         NOT NULL,        -- user_id who created this version
    device_id       TEXT,                           -- which device uploaded
    comment         TEXT,                           -- optional version description
    PRIMARY KEY     (file_id, version_num)
);
```

### Chunk Store Index

```sql
-- PostgreSQL or Redis (lookups by chunk hash)
-- PostgreSQL for durability + joins
CREATE TABLE chunks (
    chunk_hash      CHAR(64)       PRIMARY KEY,    -- SHA-256 hex (64 chars)
    gcs_path        TEXT           NOT NULL,        -- gs://bucket/chunks/{hash[:2]}/{hash}
    size_bytes      INTEGER        NOT NULL,
    reference_count INTEGER        DEFAULT 0,       -- how many file versions reference this
    created_at      TIMESTAMPTZ    DEFAULT NOW()
);
-- reference_count decremented by GC job; deleted when count = 0
```

### Sync State (Redis)

```
# Per-device sync cursor
HSET device_sync:{device_id} {
    user_id: "U123",
    last_sync_ts: 1705329600000,   # millisecond timestamp of last sync
    server_id: "ws-server-42",     # WebSocket server this device is on
    platform: "macos",
    app_version: "3.4.1"
}
EXPIRE device_sync:{device_id} 86400   # device inactive for 1 day → clean up

# File upload session tracking
HSET upload_session:{file_id} {
    owner_id: "U123",
    expected_chunks: ["hash1", "hash2", "hash3"],
    received_chunks: ["hash1"],     # grows as chunks arrive
    initiated_at: 1705329600000
}
EXPIRE upload_session:{file_id} 86400  # 24h upload window
```

---

## 9. Follow-Up Questions

### Q1: How do you handle GDPR "Right to Erasure" (delete all user data)?

```
Challenge: User data is spread across:
  - Files (GCS chunks)
  - Metadata (PostgreSQL)
  - Search index (Elasticsearch)
  - Sync state (Redis)
  - Audit logs
  - Backups

Erasure process (must complete within 30 days per GDPR):

Immediate (synchronous):
  1. Disable account: all auth tokens invalidated
  2. Remove from ACLs: no one can access user's shared files
  3. Cascade delete: files owned by user → mark is_deleted in PostgreSQL
  4. Shared files: revoke user's access from others' files

Async (within 7 days):
  1. GCS object deletion: files owned exclusively by user → delete GCS objects
     Shared ownership files: decrement reference_count; delete chunk if count=0
  2. Search index: DELETE by query { "term": { "owner_id": user_id } }
  3. Redis: DELETE all device_sync:{device_id} for user's devices
  4. PostgreSQL: DELETE from all user tables (files, versions, permissions, events)

Backups (within 30 days):
  Backups rotated on 30-day cycle → user's data naturally expires from backups
  If backup retention > 30 days: restore backup, delete user data, re-backup
  → Expensive; prefer backup retention ≤ 30 days for GDPR compliance

Audit trail:
  Retain: "user_id {X} deletion completed at {timestamp}" (no PII, just compliance record)
```

### Q2: How do you design offline-first sync (Dropbox "LAN Sync" optimization)?

```
LAN Sync:
  If two devices on same WiFi network: sync directly peer-to-peer (bypass cloud)
  Saves: upload bandwidth (no upload to cloud), download bandwidth (no download)
  Useful: syncing large files between home devices

Discovery:
  mDNS (Bonjour): devices broadcast presence on local network
    "GoogleDrive-deviceABC._googledrive._tcp.local"
  On file change: query mDNS for other Drive devices on same network

Transfer:
  Direct TCP connection between devices (no relay server)
  Same chunk-hash protocol: "I have [A, B, C], you need [B, C]? Here:"
  Encryption: TLS 1.3 with pinned certificate (prevent MITM on local network)
  Fallback: if LAN transfer fails → regular cloud sync

Consistency:
  LAN transfer: still notifies cloud "Device B synced file F, version 7"
  Cloud: records that B is up to date (no re-download on mobile later)

Bandwidth savings:
  Dropbox reports: LAN Sync saves ~60% of bandwidth for team deployments
  Large file (1 GB Photoshop file): 0 cloud bandwidth vs. 1 GB upload + 1 GB download
```

### Q3: How do you implement storage deduplication at the admin level (server-side)?

```
Server-side deduplication (beyond chunk-level):

File-level dedup:
  If two users upload identical files:
    Same content_hash → same root chunk hash list
    Second upload: "upload" is instant (all chunks already in store)
    Both users see their own "copy" (separate metadata records)
    Actual bytes: stored once

  Storage savings: photos (album dumps, meme images) → 60-70% dedup rate
  Documents: moderate (same templates, boilerplate) → 20-30% dedup rate

Chunk reference counting:
  chunks.reference_count tracks how many file versions reference each chunk
  Garbage collection:
    Nightly job: find chunks where reference_count = 0
    Cross-reference with file_versions: confirm no references
    Delete from GCS: gsutil -m rm gs://chunks/{hash}

  Safety: never delete a chunk in the same transaction as decrementing reference_count
    Race condition: Process A fetches chunk for download WHILE Process B deletes it
    Solution: "tombstone" period — mark chunk for deletion, actually delete 24h later
              Any new upload of same content "resurrects" tombstoned chunk

Cross-user dedup security:
  User cannot discover what other users' files contain via dedup timing attacks
  (see Trade-Off 4 mitigation above)
  Admin access: GCS audit logs show chunk access (compliance requirement)
```

### Q4: How do you handle a file that's 5 TB in size?

```
Chunking: 5 TB / 4 MB avg chunk = 1,310,720 chunks
  Chunk hash list stored in file_versions.chunk_hashes: 1.3M × 64 bytes = 84 MB
  → Too large for array column in PostgreSQL (column limit ~1 GB but query impractical)

Chunklist storage for large files:
  Store chunk manifest in GCS, not PostgreSQL column:
    file_versions: chunk_manifest_path TEXT
      (points to GCS object containing the ordered chunk hash list)
    Metadata: size_bytes, content_hash still in PostgreSQL
  → chunk_manifest_path = "gs://manifests/{file_id}/{version_num}/chunks.bin"
  → Binary format: 32 bytes per entry × 1.3M = 40 MB manifest file

Upload:
  5 TB / 256 MB chunk = 20,480 parallel upload requests
  Client: upload 20 chunks in parallel → ~200 MB/s with 1 Gbps connection
  Total upload time: 5 TB / 200 MB/s = ~7 hours (connection-limited, not server-limited)
  Resumable: on interruption, resume from last confirmed chunk
  Expiry: upload session TTL extended to 7 days for very large files

Download:
  Client downloads 20 chunks in parallel
  Progress bar: chunks_downloaded / total_chunks × 100%
  Integrity: SHA-256 verified per chunk + full file hash at end
```

### Q5: How do you implement virus scanning at 23K uploads/sec?

```
Challenge: virus scan 23,148 files/sec without blocking upload confirmation

Asynchronous scanning (recommended):
  1. Upload completes → file marked status='pending_scan'
  2. Kafka "file.committed" → Virus Scanner worker pool
  3. Scanner: fetch file chunks from GCS → scan with ClamAV + proprietary engine
  4a. Clean: file status → 'available' → notify devices to sync
  4b. Infected: file status → 'quarantined' → notify user, remove from sync

User experience:
  File shows as "Processing..." until scan complete (seconds to minutes)
  Small files (< 10 MB): scan in < 5 seconds → minimal UX impact
  Large files: scan takes minutes → show progress indicator

Scanner fleet sizing:
  23,148 uploads/sec × avg scan time 0.5 sec = 11,574 concurrent scans
  Each scanner: 4 cores, 8 GB RAM, scans 4 files simultaneously
  Workers needed: 11,574 / 4 = 2,894 scanner VMs
  Auto-scale on Kafka lag (same as transcoding workers)

Cloud sandbox for high-risk files:
  .exe, .dll, .js, macros (Office files) → detonated in isolated VM
  Observe behavior: does it make suspicious network calls? Registry changes?
  Dynamic analysis catches zero-day exploits that signature scanning misses
  Latency: 5-30 seconds in sandbox → file "Processing..." during this time
```

---

## 10. Interview Summary Card

### Time Allocation (45 min)

| Minute | Focus |
|--------|-------|
| 0–5 | Clarifying questions (nail offline + conflict requirements) |
| 5–10 | Functional + Non-Functional requirements |
| 10–15 | Back-of-envelope (storage math is key here) |
| 15–20 | High-level architecture + upload flow |
| 20–32 | Deep dive: Chunking + Delta sync (the heart of the problem) |
| 32–40 | Conflict resolution + real-time sync |
| 40–45 | Trade-offs + follow-up (GDPR, large files) |

### The Three Key Decisions

```
1. CONTENT-DEFINED CHUNKING:
   "Fixed-size chunks break delta sync for insertions — shifting all subsequent
    chunk boundaries. Rabin fingerprinting creates content-defined boundaries:
    inserting text in the middle only changes 1-2 chunk boundaries locally.
    Delta sync saves 95%+ bandwidth for typical document edits."

2. CONFLICT COPY (NOT LAST-WRITE-WINS):
   "LWW silently discards one user's edits — unacceptable.
    We detect conflicts via (parent_version_id, content_hash) comparison.
    When conflict detected: create conflict copy with timestamped filename.
    Both versions preserved; user manually resolves. Safe and simple."

3. CHUNK-LEVEL GLOBAL DEDUP:
   "Content-addressed chunks (SHA-256 → GCS path) enable global deduplication.
    Second upload of identical content: instant (0 bytes transferred).
    Reference counting tracks chunk usage; GC deletes unreferenced chunks.
    Timing attack mitigated by fixed-latency responses."
```

### Key Numbers

```
1B users × 15 GB free = 15 EB potential; actual stored: ~5 EB (avg 5 GB used)
300 TB/day net new storage (after dedup from 1 PB raw)
23,148 file operations/sec
50M concurrent WebSocket connections for sync (500 GB RAM across 500 servers)
Chunk size: 4 MB avg (CDC with 512 KB min, 8 MB max)
Chunk index: 500B chunks × 32 bytes = 16 TB (sharded Redis)
Versions retained: 30 (stored as chunk hash lists, not full copies)
```

### Technology Choices

| Component | Technology | Why |
|-----------|-----------|-----|
| Chunk storage | GCS / S3 | Petabyte scale, 11 nines durability, lifecycle |
| Chunk index | Redis cluster | O(1) hash lookup, 16 TB fits in RAM |
| Metadata DB | PostgreSQL (sharded) | Relational hierarchy, ACID transactions |
| File versions | PostgreSQL + GCS manifests | Chunk-level, space-efficient |
| Sync notifications | WebSocket (desktop) + SSE (mobile) | Low latency push, not polling |
| Conflict detection | Parent version + content hash | Simple, reliable, no false positives |
| CDC chunking | Rabin fingerprinting | Content-defined boundaries, dedup-friendly |
| Search | Elasticsearch + Apache Tika | Full-text, multi-format extraction |
| Change pipeline | Kafka | Durable, fan-out to sync/search/scan |
| Virus scanning | ClamAV + sandbox (async) | Non-blocking, deep analysis for executables |

---

*co-authored-by: wibey jetbrains plugin (wibey.walmart.com/code)*
