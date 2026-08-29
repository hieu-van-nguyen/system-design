# System Design: WhatsApp (Real-Time Messaging)

> **FAANG Interview Guide** — Senior / Staff Engineer Level  
> Estimated interview time: 45–60 minutes  
> Difficulty: Hard  
> Core challenges: **100M persistent WebSocket connections · E2EE without losing abuse detection · Group fan-out at 17M delivery events/sec**

---

## 1. Functional Requirements

| # | Requirement |
|---|-------------|
| FR-1 | Users can send and receive **1-on-1 text messages** in real time |
| FR-2 | Users can send **media** (images, video, audio, documents) |
| FR-3 | Users can create and message **group chats** (up to 1,024 members) |
| FR-4 | Messages show **delivery receipts**: Sent ✓, Delivered ✓✓, Read ✓✓ (blue) |
| FR-5 | Users see **online/last-seen** presence indicators |
| FR-6 | Messages are **end-to-end encrypted** (E2EE) |
| FR-7 | Offline users receive messages when they reconnect (**store-and-forward**) |
| FR-8 | Users can share their **live location** |
| FR-9 | Users can make **voice and video calls** (P2P/TURN-assisted) |
| FR-10 | Messages can be **deleted** ("Delete for Everyone") within a time window |

**Out of scope for core design:** Payments, Stories/Status broadcast at scale, Channels, business API.

---

## 2. Non-Functional Requirements

| Category | Target |
|----------|--------|
| **Scale** | 2B registered users; 100M DAU; 100B messages/day |
| **Availability** | 99.99% uptime (messaging is mission-critical) |
| **Latency** | Message delivery p99 < 100ms (same region); < 500ms cross-region |
| **Consistency** | Messages must be delivered **in order** per conversation; at-least-once delivery |
| **Durability** | No message loss for online delivery; offline messages retained for 30 days |
| **Security** | End-to-end encryption (Signal Protocol); zero server-side plaintext access |
| **Storage** | ~10 years message history per user (client-side); server stores only undelivered messages |
| **Throughput** | Peak: ~1.5M messages/sec (10× average during events) |

---

## 3. Back-of-Envelope Estimation

### Traffic

```
Messages/day        = 100B
Avg messages/sec    = 100B / 86,400 ≈ 1.16M msg/sec
Peak (10× factor)   ≈ 11.6M msg/sec

Media sends         = 20% of messages with media attachment
Media messages/sec  = 0.20 × 1.16M ≈ 232,000/sec

Group messages      = 30% of total → each fanout to avg 50 members
Group fanout/sec    = 0.30 × 1.16M × 50 ≈ 17.4M delivery events/sec
```

### Connections

```
DAU                 = 100M users
Avg session length  = 30 min active, 23.5h idle (but connection maintained)
Concurrent WS conn  = 100M persistent WebSocket connections
WebSocket servers   = 100M / 65,000 (ports/server) ≈ 1,540 servers (with headroom: ~5,000)
```

### Storage

```
Avg message size    = 1 KB (text + metadata)
Messages/day        = 100B × 1 KB = 100 TB/day (raw, server-side transient)
Server stores only undelivered messages (avg offline window = 4h)
Undelivered buffer  = 100B/day × (4/24) × 1 KB ≈ 16.7 TB at any instant

Media storage:
  Avg media size    = 500 KB
  Media/day         = 20B × 500 KB = 10 PB/day → stored in CDN/Blob (not message DB)
  10-yr retention   = 10 PB/day × 365 × 10 ≈ 36.5 EB (compressed, deduplicated)

Message metadata DB (routing table, receipts):
  Per message       = 200 bytes
  100B/day × 200B   = 20 TB/day metadata — sharded across Cassandra cluster
```

### Bandwidth

```
Inbound (sends):   1.16M msg/sec × 1 KB  ≈ 1.16 GB/s
Outbound (fan-out): 17.4M events/sec × 1 KB ≈ 17.4 GB/s
Media inbound:     232K × 500 KB ≈ 116 GB/s  → offloaded to media upload service
```

---

## 4. High-Level Design

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              CLIENT (Mobile/Web)                            │
│  Signal Protocol E2EE  │  WebSocket  │  QUIC/HTTP3 (media)                 │
└────────────┬───────────────────────────────────────────────────┬────────────┘
             │ WebSocket (persistent)                            │ HTTPS (media)
   ┌──────────▼───────────────────────────────┐      ┌──────────▼──────────┐
   │          WebSocket Gateway Layer          │      │   Media Service     │
   │  (Connection Managers / Chat Servers)     │      │   (Upload/Download) │
   │  - 5,000+ servers, each holds ~20K conns  │      │   + CDN             │
   └──────────┬───────────────────────────────┘      └──────────┬──────────┘
              │                                                  │
   ┌──────────▼───────────────────────────────┐      ┌──────────▼──────────┐
   │         Message Router / Fan-out Service  │      │   Blob Storage      │
   │  - Routes 1-on-1 and group messages       │      │   (S3 / GCS)        │
   │  - Manages group membership cache         │      └─────────────────────┘
   └──────────┬───────────────────────────────┘
              │
   ┌──────────▼───────────────────────────────┐
   │           Messaging Queue (Kafka)         │
   │  - Per-user partitions                    │
   │  - At-least-once delivery guarantee       │
   └──────────┬───────────────────────────────┘
              │
   ┌──────────▼──────────┐    ┌─────────────────────┐    ┌─────────────────┐
   │   Message Store DB   │    │   Presence Service   │    │  Push Notif.    │
   │   (Cassandra)        │    │   (Redis pub/sub)    │    │  Service        │
   │   Undelivered msgs   │    │   Online/last-seen   │    │  (APNs/FCM)     │
   └─────────────────────┘    └─────────────────────┘    └─────────────────┘
              │
   ┌──────────▼──────────┐
   │   User/Group DB      │
   │   (MySQL/Vitess)     │
   │   Accounts, groups,  │
   │   key exchange       │
   └─────────────────────┘
```

### Core API (WebSocket Events)

```
// Client → Server
SEND_MESSAGE  { toUserId, messageId, ciphertext, mediaRef?, timestamp }
ACK_RECEIVED  { messageId }                    // triggers ✓✓ Delivered
ACK_READ      { messageId }                    // triggers ✓✓ blue Read
TYPING        { conversationId, state }
PRESENCE      { status: "online" | "away" }

// Server → Client
MESSAGE       { fromUserId, messageId, ciphertext, mediaRef?, timestamp }
RECEIPT       { messageId, type: "delivered"|"read", timestamp }
PRESENCE_UPDATE { userId, status, lastSeen }
GROUP_MESSAGE { groupId, fromUserId, messageId, ciphertext, timestamp }
```

### REST API (Setup / Out-of-Band)

```
POST /v1/register           { phone, publicKey }
POST /v1/groups             { name, members[] }
PUT  /v1/groups/{id}/members
GET  /v1/keys/{userId}      → preKeyBundle (for E2EE session setup)
POST /v1/media/upload       → presignedUrl (direct S3 upload)
GET  /v1/media/{mediaId}    → presignedUrl (CDN download)
```

---

## 5. Trade-Off Discussion

### Trade-Off 1: Transport Protocol — WebSocket vs. MQTT vs. HTTP Long-Polling

| Protocol | Latency | Mobile Battery | Bidirectional | Complexity |
|----------|---------|---------------|---------------|------------|
| HTTP Long-Polling | ~500ms | High (frequent reconnect) | Simulated | Simple |
| **WebSocket** | < 50ms | Medium | ✅ Native | Medium |
| MQTT | < 50ms | Low (designed for IoT) | ✅ Native | Medium |
| QUIC/HTTP3 | < 10ms | Low (0-RTT) | ✅ Native | High |

```
WebSocket (recommended for interviews):
  - Full-duplex persistent TCP connection
  - HTTP 101 upgrade handshake → switch to WebSocket frames
  - Handles both directions over one connection: send + receive
  - Widely supported: all mobile OS, browsers
  - Problem: TCP head-of-line blocking on packet loss
  - Problem: 3-4 RTT reconnect on mobile network switch (WiFi → cellular)

MQTT (what WhatsApp actually uses internally, and Facebook Messenger):
  - Publish-subscribe, designed for unreliable networks
  - 2-byte header per message → 60% less overhead than WebSocket
  - Three QoS levels: 0=fire-and-forget, 1=at-least-once, 2=exactly-once
  - QoS=1 maps perfectly to WhatsApp's at-least-once delivery requirement
  - Built-in LWT (Last Will Testament): broker notifies others if client disconnects unexpectedly
    → Natural "offline" detection without separate heartbeat logic
  - Better for mobile: designed for high-latency, lossy networks (IoT origins)

QUIC / HTTP3:
  - 0-RTT connection resumption: phone switches networks → reconnects in 0ms
  - Eliminates TCP HOL blocking: each stream independent
  - Cost: newer protocol, not all network infrastructure supports it
  - WhatsApp is moving toward QUIC for exactly these reasons

Decision for interview: WebSocket (universally understood).
  Mention MQTT as what WhatsApp actually uses — shows depth.
  Mention QUIC as the forward-looking choice.
```

---

### Trade-Off 2: Message Delivery Guarantee — At-Least-Once vs. Exactly-Once

| Guarantee | Complexity | Risk | Used By |
|-----------|------------|------|---------|
| At-Most-Once (fire-and-forget) | Low | Message loss | SMS |
| **At-Least-Once (recommended)** | Medium | Duplicate delivery (idempotent client) | WhatsApp, Kafka |
| Exactly-Once | Very high | None | Kafka transactions (expensive) |

```
At-Least-Once design:

Send:
  1. Alice sends message → Kafka (producer acks after RF=2 replicas)
  2. Kafka consumer delivers to Bob
  3. Bob's client sends ACK → consumer commits Kafka offset
  4. If consumer crashes between step 2 and 3: message re-delivered (duplicate)

Duplicate handling (idempotency at client):
  Every message carries a globally unique client_message_id (UUID generated on client)
  Receiver: IF message_id already in local DB → discard duplicate, do NOT re-render
  → Dedup window: 30 days (matches Cassandra TTL)
  → Cost: 1 DB lookup per message (O(1) with message_id index)

Why not Exactly-Once?
  Kafka exactly-once requires: idempotent producer + transactional consumer
  This adds:
    - 2-phase commit across Kafka + Cassandra (latency: +30-50ms per message)
    - Coordinator failure handling complexity
    - At 1.16M msg/sec: 30ms penalty = impossible
  And the benefit is marginal: clients handle duplicates cheaply client-side
  → At-least-once + client idempotency = effectively exactly-once UX, lower cost

Decision: At-least-once delivery + client-side deduplication via message_id.
  This is the standard pattern across all major messaging systems.
```

---

### Trade-Off 3: Group Fan-Out — Write vs. Read vs. Hybrid

| Strategy | Write Cost | Read Latency | Storage | Best For |
|----------|-----------|-------------|---------|---------|
| Fan-out on Write | O(N members) per message | O(1) | High (N copies) | Small groups < 50 |
| Fan-out on Read | O(1) write | O(following) | Low (1 copy) | Very large groups |
| **Hybrid (Recommended)** | O(N) for small, O(1) for large | O(1) for small, O(K) for large | Medium | All group sizes |

```
Fan-out on Write (small groups ≤ 200 members):
  Message Router reads member list → publishes N delivery events to Kafka
  Each event: { recipient_id, message_payload }
  Kafka consumers: deliver to each recipient's WebSocket or offline queue

  Cost: 1 Kafka message × 200 members = 200 Kafka writes
  Latency: all 200 members receive message in parallel within ~100ms
  Acceptable: 200 × 1 KB = 200 KB per group message → negligible amplification

Fan-out on Read (large groups > 200 members):
  Store ONE copy of message in group_messages table (partition key: group_id)
  Push lightweight notification to members: { group_id, latest_seq_id }
  Members fetch message from group_messages on app foreground

  Cost: 1 write + 1 push notification per member
  Latency: slightly higher (members fetch, not pushed)
  Why this threshold?
    At 1,024 members: fan-out-on-write = 1,024 Kafka messages per group message
    WhatsApp has millions of large groups → 1,024× amplification × millions = impractical

Celebrity group problem:
  A group with 1,024 active online members simultaneously receiving a message:
  All 1,024 WebSocket servers receive gRPC calls simultaneously → thundering herd
  Mitigation: batch fan-out with staggered delivery (50ms jitter between batches of 100)
              → No user-perceptible difference; prevents simultaneous DB hit spike

Decision: Hybrid at threshold 200 members.
  Be specific about the threshold and explain the amplification math.
```

---

### Trade-Off 4: Encryption Model — E2EE vs. Server-Side vs. None

| Model | Privacy | Security | Features Lost | Used By |
|-------|---------|----------|---------------|---------|
| No encryption | None | Low | None | SMS |
| Server-side (TLS only) | Low — provider can read | Medium | Nothing | Slack, Teams |
| Server-side + at-rest encryption | Medium — provider holds key | Medium | Nothing | Gmail |
| **E2EE (Signal Protocol)** | High — server never reads | High | Spam detection, server search, backup | WhatsApp, Signal |

```
E2EE Trade-offs — what you give up:

1. Content-based spam/abuse detection:
   Server cannot scan message content → relies on metadata + user reports
   Mitigation: metadata analysis (message frequency, forward chains), client-reported hashes
   (user presses "Report" → client optionally shares last N messages with consent)

2. Server-side message search:
   Cannot search "find all messages from 2019 mentioning Bob" on server
   Search happens on-device only (SQLite FTS on local message DB)

3. Cloud backup complexity:
   Backup must be separately encrypted with user-controlled key (not server key)
   WhatsApp uses Google Drive / iCloud with backup key derived from account password
   → If user forgets password: permanent data loss (no server-side recovery)

4. Multi-device complexity:
   Must encrypt separately for each device (N devices = N ciphertexts per message)
   Key management: each device registers its own public key; sender fetches all keys

Why E2EE is still the right choice for WhatsApp:
  WhatsApp's value proposition IS privacy (vs. SMS, vs. iMessage which has server-side fallback)
  2B users in authoritarian regimes, journalists, activists — E2EE is non-negotiable
  Signal Protocol is audited, battle-tested, open source → trust without verification

Decision: E2EE via Signal Protocol.
  When interviewer asks about spam: answer with metadata-only detection + user reporting.
  This shows you understand the real tension, not just the happy path.
```

---

### Trade-Off 5: Presence System — Push vs. Pull vs. Event-Driven

| Approach | Server Load | Latency | Accuracy |
|----------|------------|---------|---------|
| Client polling (every 5s) | Very high — 100M × 12 req/min = 1.2B req/min | 5s avg | Eventually consistent |
| Server push to all contacts | Extreme — O(contacts) fan-out per status change | < 1s | Strong |
| **Client-driven subscription (Recommended)** | Low — subscribe only to open chats | < 1s | Strong for active chats |

```
Client-driven subscription model:
  When Alice opens chat with Bob:
    Client → SUBSCRIBE { userId: Bob }
    Server: SADD presence_subscribers:{bob} {alice_connection_id}

  When Alice closes chat with Bob:
    Client → UNSUBSCRIBE { userId: Bob }
    Server: SREM presence_subscribers:{bob} {alice_connection_id}

  When Bob's status changes (online → offline):
    Presence service: SMEMBERS presence_subscribers:{bob}
    → Push PRESENCE_UPDATE to each subscriber's WebSocket server

Why this is optimal:
  Alice has 200 contacts; typically 1-2 chats open at a time
  Instead of subscribing to 200 presence streams: subscribe to 2
  Server fan-out: Bob has 200 contacts; typically 5-10 have Bob's chat open
  → 5-10 notifications per status change (not 200)
  → 20× reduction in presence notifications

Heartbeat-based offline detection:
  Client sends WebSocket ping every 30s
  Chat server: if no ping received in 35s → mark connection dead
  → Update Redis: DEL user:{userId}:server
  → Write lastSeen to MySQL: UPDATE users SET last_seen=NOW()
  → Publish presence:offline event to Redis pub/sub → fans out to subscribers

Presence privacy:
  "Nobody can see my last seen": check privacy setting BEFORE publishing presence update
  Stored in user settings (MySQL); cached in Redis (TTL 60s)
  → No presence events generated for users who have disabled it
```

---

### Trade-Off 6: Message Store — Cassandra vs. MySQL + Vitess vs. DynamoDB

| Criterion | Cassandra | MySQL + Vitess | DynamoDB |
|-----------|-----------|----------------|---------|
| Write throughput | ✅ Extremely high (LSM) | Medium | ✅ High |
| Native TTL | ✅ Per-row | ❌ (cron job) | ✅ Per-item |
| Ordering guarantees | ✅ Clustering key | ✅ B-tree | ✅ Sort key |
| Multi-item transactions | ❌ LWT only | ✅ ACID | ❌ Limited |
| Operational complexity | High | Medium | Low (managed) |
| Cost at 1B msg/day scale | Low | High | Medium |

```
Why messages go to Cassandra, not MySQL:

Access pattern for messages:
  Write: INSERT (append-only) — 1.16M writes/sec
  Read: SELECT WHERE recipient_id = ? ORDER BY message_id ASC (drain offline queue)
  Delete: on delivery ACK (point delete by primary key)

  This is a perfect LSM-tree workload:
    Sequential writes → Cassandra's LSM compacts efficiently (no random I/O)
    Primary key reads → O(1) partition lookup (no table scan)
    TTL → Cassandra tombstones auto-expiry without cron jobs

Why NOT MySQL for messages:
  1.16M writes/sec on MySQL: requires massive sharding (Vitess) + B-tree write amplification
  TTL: requires background job scanning 100B rows → expensive
  At WhatsApp scale: Cassandra costs 5-10× less than equivalent MySQL cluster

Why NOT DynamoDB for this volume:
  DynamoDB cost at 1.16M writes/sec:
    1.16M WCU × $1.25 per million = $86,000/hour = $750M/year
  Cassandra on self-managed EC2: ~$50M/year
  → 15× cost difference makes DynamoDB impractical at this scale

MySQL (Vitess) IS used for: user accounts, group memberships, contact lists
  These need ACID transactions (e.g., atomic group membership change)
  Volume is much lower (millions, not billions, of rows)

Decision: Cassandra for messages (volume, TTL, throughput).
          MySQL/Vitess for user/group metadata (consistency, transactions).
          Explain both — shows you know to match data store to access pattern.
```

---

### Trade-Off 7: Offline Message Delivery — Store-and-Forward vs. Push-Only

| Approach | Message Guarantee | Server Storage | Power (Mobile) |
|----------|-----------------|---------------|---------------|
| Push-Only (no server storage) | ❌ Message lost if app closed | None | Best |
| **Store-and-Forward (Recommended)** | ✅ Delivered on reconnect | 30-day buffer | Medium |
| Peer-to-peer sync | ✅ If peer online | None | Worst |

```
Store-and-Forward — WhatsApp's approach:

Offline storage:
  Cassandra: undelivered_messages table (partition by recipient_id)
  Row-level TTL: 30 days (GDPR-compliant: auto-delete, no manual job)
  Size: 16.7 TB at any instant (see back-of-envelope)

On reconnect (drain sequence):
  1. Client sends: SYNC { last_received_message_id }
  2. Server: SELECT * FROM undelivered_messages WHERE recipient_id=? AND message_id > ?
             LIMIT 50 ORDER BY message_id ASC
  3. Deliver batch → client ACKs → DELETE from Cassandra → next batch
  4. Repeat until queue empty

Ordering guarantee:
  message_id is a TimeUUID (embedded timestamp) → natural time ordering
  Even if messages arrived at server out-of-order (retries), TimeUUID clustering key
  sorts them correctly before delivery

Why 30-day TTL:
  GDPR: store minimum time necessary
  User expectation: "I was offline for 2 weeks and got all messages" (reasonable)
  Storage cost: 100M offline messages × 1KB × 30 days = bounded, manageable
  Business rule: after 30 days, sender sees single "✓" forever — explicit signal to resend

Push notification (FCM/APNs) role:
  NOT the message itself (E2EE: server has no plaintext)
  Just a wake-up signal: "You have N new messages from Alice"
  App wakes → WebSocket connects → drains offline queue → gets actual messages

Decision: Store-and-forward with 30-day TTL is the correct choice.
  Explain the drain sequence — interviewers often probe this.
```

---

## 6. Deep Dive

### 6.1 Connection Management (WebSocket at Scale)

Each **Chat Server** (Connection Manager) maintains persistent WebSocket connections:

```
Connection Manager responsibilities:
  - Authenticate connection (JWT / session token)
  - Register {userId → serverId} in Redis (session registry)
  - Heartbeat every 30s (detect dead connections)
  - Route incoming messages to correct connection
  - Forward outbound messages from Kafka → client

Session Registry (Redis):
  Key:   user:{userId}:server
  Value: serverId (IP or hostname)
  TTL:   35s (refreshed on heartbeat)
```

**Challenge:** 100M concurrent connections across 5,000 servers = hot Redis cluster.

**Optimization:**
- Consistent hashing: users are stickied to a connection server shard by `userId % num_servers`
- Only check Redis on cross-server delivery (user reconnected to different server)
- Local in-process map for users connected to *this* server — no Redis for local delivery

---

### 6.2 Message Delivery Flow (1-on-1)

```
Alice sends message to Bob:

1. Alice's client → WebSocket → Alice's Chat Server (CS-A)
2. CS-A validates message, assigns server-side messageId
3. CS-A publishes to Kafka topic: messages (partition key = Bob's userId)
4. CS-A immediately ACKs back to Alice: ✓ Sent

5. Kafka consumer (Message Delivery Service):
   a. Look up Bob's session in Redis → CS-B (Bob's Chat Server)
   b. Bob is ONLINE → push to CS-B via internal gRPC call
   c. CS-B delivers to Bob's WebSocket
   d. Bob's client ACKs → ✓✓ Delivered event sent back to Alice
   e. Bob reads → ✓✓ blue Read event sent back to Alice

5b. Bob is OFFLINE:
   a. Store message in Cassandra (undelivered queue, keyed by Bob's userId)
   b. Send push notification via APNs/FCM
   c. When Bob reconnects → drain undelivered queue → deliver in order
```

**Message Ordering:**

Each message gets a **Lamport clock sequence number** scoped to the conversation:
```
Cassandra partition key: conversation_id
Clustering key:          sequence_number (monotonically increasing)
```
Clients apply sequence numbers locally; server enforces ordering in storage.

---

### 6.3 Group Messaging Fan-out

Groups have up to 1,024 members — naive fan-out at send time creates amplification:

```
Naive approach:
  1 group message → 1,023 individual deliveries inline → high latency for sender

Fan-out strategies:
```

**Option A — Fan-out on Write (Push Model)**
- Message Router reads group membership → publishes N individual messages to Kafka
- Works well for small groups (< 100 members)

**Option B — Fan-out on Read (Pull Model)**  
- Store one message; receivers pull from group message log on reconnect
- Good for large groups; bad for real-time latency

**Recommendation: Hybrid**

```
Group size ≤ 200  → fan-out on write (low latency, acceptable amplification)
Group size > 200  → store message once in group log; push notification to members;
                    members pull on app foreground (lazy delivery)

Group message storage:
  Cassandra table: group_messages
    partition key: group_id
    clustering key: message_sequence_id (Snowflake)
    value: { sender_id, ciphertext, timestamp, media_ref }

Member delivery tracking:
  delivered_to: bitmap or per-member row in group_receipts table
```

---

### 6.4 End-to-End Encryption (Signal Protocol)

WhatsApp uses the **Signal Protocol** — server never has access to plaintext.

```
Key Components:
  - Identity Key (IK):      long-term key pair, generated on device
  - Signed PreKey (SPK):    medium-term, rotated weekly
  - One-Time PreKeys (OPK): ephemeral, batch-uploaded to server (100 at a time)

Session Establishment (X3DH — Extended Triple Diffie-Hellman):
  Alice wants to message Bob (no prior session):
  1. Alice fetches Bob's KeyBundle from server: { IK_B, SPK_B, OPK_B }
  2. Alice generates ephemeral key EK_A
  3. Shared secret = KDF(
       DH(IK_A, SPK_B) ||
       DH(EK_A, IK_B)  ||
       DH(EK_A, SPK_B) ||
       DH(EK_A, OPK_B)
     )
  4. Server stores only public keys — ZERO plaintext access

Ongoing encryption (Double Ratchet):
  - Each message uses a new encryption key (derived via ratchet)
  - Compromise of one key does NOT expose past messages (forward secrecy)
  - Compromise of one key does NOT expose future messages (break-in recovery)
```

**Server-side key infrastructure:**
```
Key server stores: { userId → [IK_pub, SPK_pub, OPK_pub[]] }
One-time prekeys consumed on session setup → client uploads more when count < 10
Server can signal "no OPKs left" — fallback to SPK-only (slightly weaker but functional)
```

---

### 6.5 Presence Service

```
Online detection:
  - Client sends heartbeat every 30s over WebSocket
  - Chat Server updates Redis key: presence:{userId} = {status, timestamp}  TTL=35s
  - TTL expiry → user considered offline

Last-seen:
  - On disconnect/TTL expiry → write lastSeen timestamp to User DB (MySQL)
  - Served from MySQL read replica; cached in Redis for hot users

Presence fan-out:
  - User A goes online → who needs to know?
    → Only users who have A in their contact list AND have "last seen" enabled
  - Naive: publish to all contacts → O(N) fan-out per status change
  - Optimized: use Redis Pub/Sub per user channel
    → Bob subscribes to presence:{Alice} when they open a chat
    → Unsubscribe when chat closed (client-driven subscription)

Privacy controls:
  - "Nobody", "My Contacts", "Everyone" settings stored in User DB
  - Presence service checks privacy setting before publishing update
```

---

### 6.6 Message Store (Cassandra Schema)

**Undelivered Messages Table** (transient — deleted on delivery)

```cql
CREATE TABLE undelivered_messages (
  recipient_id   UUID,
  message_id     TIMEUUID,    -- TimeUUID = natural time ordering
  sender_id      UUID,
  conversation_id UUID,
  ciphertext     BLOB,
  media_ref      TEXT,
  sent_at        TIMESTAMP,
  PRIMARY KEY (recipient_id, message_id)
) WITH CLUSTERING ORDER BY (message_id ASC)
  AND default_time_to_live = 2592000;  -- 30 days TTL
```

**Conversation Message Log** (for group history / message sync)

```cql
CREATE TABLE conversation_messages (
  conversation_id UUID,
  sequence_id     BIGINT,       -- monotonically increasing per conversation
  message_id      UUID,
  sender_id       UUID,
  ciphertext      BLOB,
  media_ref       TEXT,
  sent_at         TIMESTAMP,
  deleted_at      TIMESTAMP,    -- soft delete for "Delete for Everyone"
  PRIMARY KEY (conversation_id, sequence_id)
) WITH CLUSTERING ORDER BY (sequence_id DESC);
```

**Why Cassandra:**
- Linear horizontal scale with consistent hashing
- Excellent write throughput (LSM tree, sequential writes)
- TTL support at row level — automatic expiry of undelivered messages
- Partition by `recipient_id` → each user's messages on 1 shard (RF=3)

---

### 6.7 Media Handling

```
Upload flow:
  1. Client requests presigned S3 URL from Media Service
  2. Client encrypts media locally (AES-256, random key)
  3. Client uploads directly to S3 (bypasses chat servers)
  4. Client sends message with { mediaRef: s3Key, mediaKey: encryptedAesKey }
  5. Recipient decrypts using mediaKey from message payload

Download flow:
  1. Recipient receives message with mediaRef
  2. Fetches presigned CDN URL from Media Service (authenticated)
  3. Downloads encrypted blob from CDN edge node
  4. Decrypts with mediaKey

Deduplication:
  - Hash(plaintext media) before encryption → check if already uploaded by any user
  - If duplicate hash found → reuse same s3Key, skip upload (content-addressed storage)
  - Saves ~30-40% storage for common memes/forwarded content

CDN strategy:
  - Popular media cached at CDN edge (holiday images, viral content)
  - TTL: 24h for media (immutable once uploaded)
  - Geo-distributed: media served from closest PoP
```

---

### 6.8 Push Notifications (Offline Delivery)

```
When Bob is offline:
  1. Message stored in Cassandra (undelivered queue)
  2. Notification Service sends push via:
     - APNs (iOS)
     - FCM (Android)
     - Web Push (browser)

Push payload (E2EE — no content in push):
  { "type": "new_message", "from": "Alice", "count": 3 }
  → Content is NOT included (E2EE guarantee; server has no plaintext)

On reconnect:
  1. Client connects WebSocket → Chat Server
  2. Client sends: SYNC { lastSeenMessageId }
  3. Server drains Cassandra undelivered queue in order
  4. Delivers all pending messages
  5. Deletes from Cassandra after ACK

Retry logic:
  - Push notification retried 3× with exponential backoff
  - If push fails (app uninstalled, token expired) → message stays in Cassandra until 30-day TTL
```

---

### 6.9 Multi-Device Support

WhatsApp multi-device links up to 4 companion devices:

```
Architecture: Linked Device Model (not server fan-out)

Primary device (phone):
  - Source of truth for contacts, group memberships
  - All devices share same identity (linked via QR code scan)

Key distribution:
  - Each device has its own identity key
  - Primary device signs companion device keys
  - Server stores { userId → [device1_IK, device2_IK, ...] }
  - Senders fetch ALL device keys → encrypt message N times (once per device)
  - Fan-out at sender, not server (E2EE preserved)

Message sync:
  - Companion devices receive encrypted copies independently
  - Conversation history synced via encrypted "sync messages" over primary→companion channel
```

---

### 6.10 Reliability & Fault Tolerance

| Failure Scenario | Mitigation |
|-----------------|------------|
| Chat Server crash | Client reconnects (exponential backoff); session registry TTL expires; user reconnects to any available server |
| Redis (session registry) down | Fallback: route to all known servers (broadcast), slight latency spike |
| Kafka partition failure | RF=3; producer retries with idempotent producer; consumer resumes from committed offset |
| Cassandra node failure | RF=3, quorum writes/reads; automatic repair |
| Cross-region network partition | Active-active multi-region; messages queued locally; sync on partition heal |
| Media CDN failure | Origin fallback to S3; client retries with exponential backoff |
| APNs/FCM failure | Message retained in Cassandra; delivery on reconnect; no notification loss = degraded UX only |

---

### 6.11 Scaling the WebSocket Layer

```
Horizontal scaling challenge: 100M WebSocket connections

WebSocket servers are stateful (connection pinned to server).
Scale-out strategy:

1. L4 Load Balancer (TCP level, not HTTP):
   - AWS NLB / Google Cloud NLB
   - Consistent hashing by client IP → sticky routing
   - New server added → only new connections route there (no migration needed)

2. Connection server capacity:
   - Each server: 64 GB RAM, 32 cores
   - Memory per WebSocket connection: ~10 KB (buffers, TLS state, read/write queues)
   - Per server capacity: 64 GB / 10 KB ≈ 6.4M connections (practical: 2-3M with headroom)
   - Servers needed: 100M / 2M ≈ 50 servers per region (with 3 regions = 150 total)

3. Protocol:
   - WebSocket over TLS 1.3
   - For mobile: MQTT considered (lower overhead for mobile radio); WhatsApp uses custom protocol over TCP
   - Heartbeat: 30s ping/pong to detect dead connections and NAT timeouts
```

---

## 7. Data Flow Summary

### Send Message (1-on-1, Online Recipient)

```
Alice (client)
  → [E2EE encrypt with Bob's session key]
  → WebSocket → CS-A (Alice's Chat Server)
  → Kafka publish (partition: Bob's userId)
  → ✓ Sent ACK to Alice

Kafka consumer (Delivery Service)
  → Redis lookup: Bob → CS-B
  → gRPC: CS-B.deliver(messagePayload)
  → CS-B pushes to Bob's WebSocket

Bob (client)
  → ACK RECEIVED → ✓✓ Delivered to Alice
  → Opens chat → ACK READ → ✓✓ blue to Alice
```

### Send Message (Group, Mixed Online/Offline)

```
Alice sends to Group G (200 members):
  → CS-A validates Alice is group member
  → Publish to Kafka (partition: group_id)
  → ✓ Sent ACK to Alice

Fan-out Service (Kafka consumer):
  → Load group members from cache (Redis)
  → For each member M:
      if online  → push via CS-M WebSocket
      if offline → write to undelivered_messages table + push notification
  → Write to conversation_messages (group log)
```

---

## 8. Follow-Up Questions

### Q1: How do you handle message ordering in groups?
- Each conversation has a **monotonically increasing sequence counter** (using Cassandra counter or Zookeeper-assigned sequence)
- Server assigns sequence number when message is persisted to group log
- Clients sort by `sequence_id`, not by client-side timestamp (clocks drift)
- Out-of-order delivery (network reorder): client buffers and waits 200ms for gap fill before rendering

---

### Q2: How does "Delete for Everyone" work?
```
1. Alice deletes message within 60-second window
2. Client sends: DELETE_MESSAGE { messageId, conversationId }
3. Server:
   a. Validate: Alice is sender AND within time window
   b. Soft-delete: set deleted_at = now() in conversation_messages
   c. Fan-out DELETE event to all recipients' devices (online + offline queue)
4. Recipients' clients redact message from UI on receiving DELETE event
5. Media: CDN URL invalidated; S3 object marked for deletion (async)

Limitation: Cannot guarantee deletion from jailbroken devices with modified client
```

---

### Q3: How do you prevent spam / abuse while maintaining E2EE?
E2EE means the server cannot read content — yet abuse detection is still possible:

```
Client-side signals (reported by users):
  - User reports message → client optionally sends last N messages (with consent) to trust server
  - Report includes sender metadata, not ciphertext of unreported messages

Server-side signals (metadata analysis):
  - Message frequency per sender (spam pattern detection)
  - Group creation rate, rapid member additions
  - Account age, phone verification score
  - Number of blocks/reports received
  - Forward chain depth (message forwarded 5+ times → label as "forwarded many times")

Rate limiting:
  - Message send rate throttled per account (e.g., 1000 msgs/min cap)
  - Group creation rate-limited for new accounts
  - Broadcast lists limited to 256 contacts

No plaintext access → privacy preserved; metadata heuristics catch most spam at scale
```

---

### Q4: How would you design the voice/video calling feature?
```
Signaling (WebSocket):
  - CALL_INVITE  { callId, calleeId, sdpOffer (encrypted) }
  - CALL_ACCEPT  { callId, sdpAnswer }
  - ICE_CANDIDATE { callId, candidate }

Media (WebRTC):
  - Peer-to-peer when both clients can reach each other (STUN server for NAT traversal)
  - TURN relay when P2P fails (symmetric NAT, corporate firewall)
    → TURN servers placed in each region; bandwidth-intensive (relay all media)
  - E2EE: SRTP with keys derived via DTLS-SRTP handshake (standard WebRTC)

Group calls (up to 32 participants):
  - SFU (Selective Forwarding Unit) — server receives all streams, forwards subsets
  - Each participant sends 1 stream up; receives N-1 streams down
  - Simulcast: each client sends 3 quality layers; SFU forwards appropriate layer per receiver
  - No MCU (transcoding) — preserves E2EE (SFU forwards encrypted packets without decrypting)
```

---

### Q5: How do you handle scale to 500M DAU?
```
Current design bottlenecks at scale:

Connection layer (5× growth = 500M DAU):
  → Scale Chat Server fleet linearly (stateless within a server; session registry in Redis)
  → Redis cluster scaled horizontally (sharding by userId)
  → Use QUIC instead of WebSocket — 0-RTT reconnect, multiplexing, better mobile perf

Fan-out at 500M DAU (group messages):
  → Dedicated fan-out worker pool per region
  → Pre-compute group membership lists in Redis (updated on membership change)
  → Hierarchical fan-out: fan-out to regional clusters first, then intra-region

Kafka throughput:
  → Partition count scales with consumers
  → 500M DAU × 100 msg/day = 58M msg/sec (peak)
  → Increase partition count; add Kafka brokers; use tiered storage for retention
```

---

### Q6: How does WhatsApp sync message history to a new device?
```
WhatsApp uses Google Drive / iCloud for encrypted backup:
  1. Client generates backup key (derived from account password)
  2. Encrypts entire local message DB with backup key
  3. Uploads encrypted blob to Google Drive / iCloud
  4. Server never sees backup key or content

On restore:
  1. User enters account password → derive backup key
  2. Download encrypted blob from cloud backup
  3. Decrypt locally → restore message history

Multi-device (no backup needed):
  → Primary device streams encrypted message history to new linked device
  → End-to-end encrypted sync channel (separate from message channel)
```

---

### Q7: How would you design the read receipt system at scale?
```
Read receipts are high-volume: every message read = 1 receipt event

Batching:
  - Client batches read receipts: send once per second, not per-message
  - Payload: { receipts: [{ messageId, readAt }] }  (up to 50 per batch)

Delivery:
  - Receipt published to Kafka (partition: sender_userId — the person who needs to know)
  - Delivered via same WebSocket pipeline as messages
  - If sender offline → stored as undelivered receipt event (lightweight, 50 bytes)

Storage:
  - Receipt state (delivered/read per recipient per message) stored in Cassandra
  - Group receipts: separate table (1 row per member per message = 1,024 rows max per group message)
  - TTL: 30 days (matches message retention)

Privacy:
  - Read receipts can be disabled per user
  - Setting checked at receipt-generation time on client (not sent if disabled)
```

---

## 9. Architecture Decision Record Summary

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Transport | WebSocket (persistent) | Full-duplex, low latency, compatible with mobile |
| Message queue | Kafka (partitioned by userId) | At-least-once, ordered per partition, high throughput |
| Message store | Cassandra | Linear scale, TTL support, fast partition-key reads |
| User/group metadata | MySQL + Vitess | Strong consistency, relational queries, horizontally shardable |
| Encryption | Signal Protocol (X3DH + Double Ratchet) | Industry standard, forward secrecy, server-side zero-knowledge |
| Redirect | 302 → websocket (not applicable) | N/A — messaging, not HTTP redirect |
| Group fan-out | Hybrid (push ≤200, pull >200) | Balances latency vs. amplification at scale |
| Presence | Redis pub/sub + client-driven subscription | Low latency, avoids N×N broadcast storm |
| Media | Direct S3 upload + CDN | Offloads bandwidth from chat servers; CDN reduces latency |
| Multi-device | Per-device keys, sender-side fan-out | Preserves E2EE; server never holds shared key |

---

*Document covers core design for a FAANG-level system design interview. Estimated interview coverage: 45–60 minutes.*
