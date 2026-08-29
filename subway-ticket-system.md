# System Design: Subway Train Ticket System (PayPal)

> Context: A system where commuters can purchase subway tickets via kiosk, mobile app, or web — then receive a printed physical ticket or digital QR ticket. Turnstiles validate tickets at entry. Asked by PayPal, so payment reliability, idempotency, and financial accuracy are first-class concerns.

---

## 1. Functional Requirements

**Core (In Scope)**
- Users can purchase tickets via: physical kiosk, mobile app, or web browser
- Ticket types: single ride, round trip, multi-ride (10-pack), day pass, weekly/monthly pass
- Payment methods: credit/debit card, PayPal, contactless (Apple Pay, Google Pay), cash (kiosk only)
- Kiosk prints a physical ticket (barcode/QR on thermal paper)
- Mobile/web issues a digital QR ticket stored in app or email
- Turnstiles validate tickets at entry (scan QR/barcode)
- Users can view purchase history and active passes
- Station agents can issue refunds or replacements for damaged tickets
- Support for reduced fares (senior, student, disabled — requires credential verification)

**Out of Scope**
- Real-time train scheduling / arrival boards
- Route planning / journey planner
- Loyalty/rewards programs
- Multi-operator interoperability (other transit systems)

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Availability | 99.99% for payment + validation; kiosk must work **offline** during network outages |
| Payment Latency | Purchase completed (payment + ticket issued) < **3 seconds** p99 |
| Turnstile Validation | Gate open/reject decision < **300ms** — commuters can't queue |
| Exactly-Once Payments | Zero double-charges, zero missed charges — financial accuracy critical |
| Scale | 500 stations; 5K kiosks; 50M rides/day (NYC MTA scale) |
| Offline Resilience | Kiosk operates standalone for up to **4 hours** without central connectivity |
| Ticket Security | Tickets unforgeable; duplicates detected at turnstile |
| Durability | All transactions persisted before user receives ticket |
| Idempotency | Retry-safe: duplicate payment requests return same result |
| Compliance | PCI-DSS for card data; SOX for financial records |

---

## 3. Back of Envelope Calculation

**Scale Assumptions (NYC MTA scale)**
- 50M rides/day → **~580 rides/sec** average; **3,000/sec** peak (8–9 AM rush)
- 5K kiosks across 500 stations; avg 10 transactions/kiosk/hour peak → **50K transactions/hour** = ~14 TPS per kiosk peak
- 20M unique commuters/month; avg 2.5 rides/day

**Ticket Volume**
- 50M validations/day at turnstiles → **580 scans/sec** average; **3,000/sec** peak
- Per-station peak: avg 100 stations at major hubs; 30 scans/sec/station

**Storage**
- Transaction record: ~1 KB; 50M/day × 365 = 18.25B/year → **18 TB/year**
- Ticket record: ~500 bytes; 50M/day active + 50M validated → **50 GB/day**
- Turnstile scan log: ~200 bytes × 50M/day = **10 GB/day**
- Valid ticket set (for offline validation): ~10M active tickets × 50 bytes = **500 MB** → fits on kiosk/turnstile

**Payment**
- Avg ticket: $3.50; 50M rides/day = **$175M/day** in transactions — financial integrity paramount
- 10% of tickets purchased at kiosk (5M/day); 90% via app/web (45M/day)

---

## 4. High-Level Design

```
┌───────────────────────────────────────────────────┐
│         Purchase Channels                          │
│   ┌──────────┐  ┌────────────┐  ┌──────────────┐  │
│   │  Kiosk   │  │ Mobile App │  │  Web Browser │  │
│   │(offline- │  │            │  │              │  │
│   │ capable) │  └─────┬──────┘  └──────┬───────┘  │
│   └──────┬───┘        │                │           │
└──────────┼────────────┼────────────────┼───────────┘
           │            │                │
           ▼            ▼                ▼
┌──────────────────────────────────────────────────┐
│            API Gateway + CDN                     │
│        (auth, rate-limit, TLS termination)       │
└───────────────────────┬──────────────────────────┘
                        │
          ┌─────────────┼──────────────┐
          ▼             ▼              ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────────┐
│   Ticket     │  │   Payment    │  │   Validation     │
│   Service    │  │   Service    │  │   Service        │
│  (issue,     │  │ (charge,     │  │  (turnstile      │
│   activate)  │  │  refund,     │  │   gate control)  │
└──────┬───────┘  │  idempotency)│  └──────────┬───────┘
       │          └──────┬───────┘             │
       │                 │                     │
       ▼                 ▼                     ▼
┌──────────────────────────────────────────────────┐
│              Apache Kafka                        │
│   (PaymentInitiated, PaymentCharged, TicketIssued│
│    TicketValidated, RefundRequested)             │
└──────┬────────────────────────────┬──────────────┘
       │                            │
       ▼                            ▼
┌─────────────────┐      ┌──────────────────────┐
│  PostgreSQL     │      │  Redis Cluster        │
│  (transactions, │      │  (valid ticket set,   │
│   tickets,      │      │   idempotency keys,   │
│   passes)       │      │   rate limits)        │
└─────────────────┘      └──────────────────────┘
       │
       ▼
┌──────────────────────────────────┐
│   Notification Service           │
│   (email receipt, SMS, push)     │
└──────────────────────────────────┘
```

### Core Services

| Service | Responsibility |
|---|---|
| **Ticket Service** | Create, issue, activate, transfer, refund tickets |
| **Payment Service** | Charge card/PayPal, idempotency, reconciliation, PCI scope isolation |
| **Validation Service** | Turnstile gate control; validate ticket authenticity + usage state |
| **Kiosk Service** | Offline-capable local agent; sync with central on reconnect |
| **Fare Service** | Compute fare by ticket type, zone, time-of-day, discount eligibility |
| **Notification Service** | Email receipt, push notification, SMS |
| **Admin Service** | Agent tooling: refunds, replacements, station reporting |

---

## 5. Deep Dive

### 5.1 Payment — Idempotency & Exactly-Once (PayPal Focus)

**The hardest problem:** Network timeouts between client and payment gateway. Client retries → must not double-charge.

**Idempotency Key Flow:**
```
1. Client generates: idempotency_key = SHA256(user_id + ticket_type + amount + nonce)
   nonce = client-generated UUID, stored locally until purchase confirmed

2. Client sends: POST /v1/payments { ..., "Idempotency-Key": "<key>" }

3. Payment Service:
   a. Check Redis: GET idempotency:<key>
      - HIT → return cached response (same ticket, same payment result)
      - MISS → proceed

   b. Check PostgreSQL: SELECT * FROM payment_intents WHERE idempotency_key = $1
      - Exists + COMPLETED → return stored result (crash-safe)
      - Exists + PENDING → wait / return in-progress

   c. INSERT payment_intent (idempotency_key, status=PENDING, ...) ON CONFLICT DO NOTHING

   d. Call payment gateway (PayPal / Stripe / card network)

   e. UPDATE payment_intent SET status=COMPLETED, gateway_txn_id=..., charged_at=NOW()

   f. Cache result in Redis: SET idempotency:<key> <response> EX 86400

   g. Publish PaymentCharged event → Kafka → Ticket Service issues ticket
```

**Two-Phase Commit Avoidance (Outbox Pattern):**
```sql
BEGIN;
  -- Mark payment as charged
  UPDATE payment_intents SET status='CHARGED', charged_at=NOW() WHERE id=$1;

  -- Write outbox event (same transaction — atomicity guaranteed)
  INSERT INTO outbox (event_type, payload, status)
  VALUES ('PAYMENT_CHARGED', '{"payment_id":"...","user_id":"..."}', 'PENDING');
COMMIT;
-- Outbox relay reads PENDING rows → publishes to Kafka → deletes row
-- Ticket Service consumes Kafka → issues ticket
```

**Refund Idempotency:**
- Same pattern: `refund_idempotency_key = SHA256(payment_id + "refund" + agent_id)`
- Prevents double-refund if agent button double-clicked

---

### 5.2 Ticket Issuance — Digital vs. Physical

**Digital Ticket (QR Code):**
```
ticket_payload = {
  ticket_id:    UUID,
  ticket_type:  "SINGLE_RIDE | DAY_PASS | MONTHLY",
  user_id:      UUID,
  valid_from:   ISO8601,
  valid_until:  ISO8601,
  zone:         "A",          // fare zone
  uses_total:   1,            // for multi-ride
  uses_remaining: 1,
  issued_at:    ISO8601
}

signature = HMAC_SHA256(canonical_json(ticket_payload), SIGNING_KEY)
qr_data = base64url(ticket_payload_bytes) + "." + base64url(signature)
```

- QR rendered in app; also emailed as PDF attachment
- Key rotation: signing keys rotated monthly; validation service knows previous 3 keys

**Physical Ticket (Kiosk Thermal Print):**
```
Same QR payload + signature → printed as QR barcode on thermal paper
Ticket also shows: route, fare, expiry, serial number (human-readable)
Printer: ESC/POS protocol → local kiosk printer driver

Failure handling:
  - Paper jam / printer offline → hold payment_intent in CHARGED state
  - User presses "Reprint" → idempotency key reuse → no recharge → reprint
  - If kiosk fully fails → send digital ticket to user's email/phone as fallback
```

---

### 5.3 Offline Kiosk Architecture

**Problem:** Network outage at station. 500 passengers need tickets. Kiosk must keep working.

**Kiosk Local Agent (edge node):**
```
┌────────────────────────────────────────────┐
│                  Kiosk                     │
│                                            │
│  ┌─────────────────────────────────────┐   │
│  │  Local SQLite / embedded DB         │   │
│  │  - Pending transactions queue       │   │
│  │  - Valid ticket cache (last sync)   │   │
│  │  - Offline fare table               │   │
│  └─────────────────────────────────────┘   │
│                                            │
│  ┌─────────────────────────────────────┐   │
│  │  Local Payment Module               │   │
│  │  - Card: P2PE terminal (EMV chip)   │   │
│  │    → authorizes offline up to $25   │   │
│  │  - Cash: always offline-capable     │   │
│  │  - PayPal/Apple Pay: requires online│   │
│  └─────────────────────────────────────┘   │
│                                            │
│  ┌─────────────────────────────────────┐   │
│  │  Sync Agent                         │   │
│  │  - Heartbeat every 30s              │   │
│  │  - On reconnect: flush pending txns │   │
│  │  - Pull: revoked ticket list delta  │   │
│  └─────────────────────────────────────┘   │
└────────────────────────────────────────────┘
```

**Offline Card Authorization (EMV Floor Limit):**
- Card terminals support offline EMV authorization up to **$25** (transit standard)
- Terminal stores offline authorization cryptogram (ARQC)
- On reconnect: batch upload cryptograms to payment network for settlement
- Risk: card may be declined/stolen → offline risk accepted up to floor limit (industry standard)

**Offline Ticket Validity:**
- Kiosk caches `valid_ticket_ids` bloom filter (refreshed every 5 min when online)
- Generates tickets offline using locally cached signing key (HSM-backed, pre-loaded)
- Offline-issued tickets registered in local queue → synced to central DB on reconnect
- Turnstiles also have bloom filter cache → can validate offline-issued tickets

**Sync on Reconnect:**
```
1. Upload pending_transactions (FIFO) to Payment Service
2. Payment Service deduplicates (idempotency_key) → processes unsent
3. Ticket Service confirms/updates ticket status
4. Pull revoked_tickets delta since last_sync_timestamp
5. Update local bloom filter
6. Log sync result + duration
```

---

### 5.4 Turnstile Validation — 300ms Gate Decision

**Problem:** 3,000 scans/sec at peak across all stations. Gate must open/reject in < 300ms. Cannot afford a round-trip to central DB for each scan.

**Architecture: Local Cache + Async Invalidation**

```
Turnstile Controller (embedded Linux / ARM)
  │
  ├── Local Bloom Filter: "is this ticket_id in valid set?"
  │     Updated every 60s via delta push from Validation Service
  │     False positive rate: 0.01% (occasional valid reject → retry)
  │
  ├── Local Revocation List: LRU cache of last 10K revoked/used ticket_ids
  │     Updated via WebSocket push from central on revocation events
  │
  └── Decision Engine (< 5ms local):
        1. Verify HMAC signature (local signing key) → if invalid → REJECT
        2. Check bloom filter → not in valid set → REJECT
        3. Check revocation cache → found → REJECT
        4. Check ticket expiry (timestamp in QR payload) → expired → REJECT
        5. → OPEN GATE; publish TicketUsed event to local queue
        6. Local queue → async send to Validation Service (not in critical path)
```

**Preventing Ticket Sharing (Same QR, Two People):**
- Single-ride tickets: first use → `TicketUsed` event published
- Central Validation Service marks ticket as `USED` in Redis + DB
- Turnstile bloom filter refreshes every 60s → used ticket removed from valid set
- Race condition window: 60s where same ticket could be used twice at different stations
- Mitigation: monitor for `ticket_id` appearing at multiple stations within 60s → fraud flag

**Multi-ride / Pass Validation:**
- Monthly pass: only expiry check (no use-count depletion) → purely offline-capable
- Multi-ride (10-pack): requires decrement → online call preferred; offline: decrement locally, sync on reconnect

---

### 5.5 Ticket Data Model

```sql
CREATE TABLE tickets (
    ticket_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id      UUID NOT NULL REFERENCES payments(payment_id),
    user_id         UUID,                      -- NULL for anonymous kiosk purchase
    ticket_type     VARCHAR(30) NOT NULL,      -- SINGLE_RIDE, ROUND_TRIP, DAY_PASS, MONTHLY
    zone            VARCHAR(5) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',  -- ACTIVE | USED | EXPIRED | REVOKED | REPLACED
    valid_from      TIMESTAMPTZ NOT NULL,
    valid_until     TIMESTAMPTZ NOT NULL,
    uses_total      SMALLINT NOT NULL DEFAULT 1,
    uses_remaining  SMALLINT NOT NULL DEFAULT 1,
    issued_channel  VARCHAR(20) NOT NULL,      -- KIOSK | APP | WEB
    issued_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    qr_signature    VARCHAR(512) NOT NULL,     -- HMAC stored for audit
    kiosk_id        UUID,                      -- set if issued offline at kiosk
    INDEX idx_tickets_user   (user_id, issued_at DESC),
    INDEX idx_tickets_status (status, valid_until)
);

CREATE TABLE payments (
    payment_id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    idempotency_key     VARCHAR(64) UNIQUE NOT NULL,
    user_id             UUID,
    amount_cents        INT NOT NULL,
    currency            CHAR(3) NOT NULL DEFAULT 'USD',
    payment_method      VARCHAR(30) NOT NULL,   -- CARD | PAYPAL | APPLEPAY | CASH
    gateway_txn_id      VARCHAR(100),
    status              VARCHAR(20) NOT NULL,   -- PENDING | CHARGED | REFUNDED | FAILED
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    charged_at          TIMESTAMPTZ,
    refunded_at         TIMESTAMPTZ,
    refund_reason       TEXT
);

CREATE TABLE ticket_scans (
    scan_id     UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ticket_id   UUID NOT NULL,
    turnstile_id UUID NOT NULL,
    station_id  UUID NOT NULL,
    scanned_at  TIMESTAMPTZ NOT NULL,
    result      VARCHAR(20) NOT NULL,  -- VALID | EXPIRED | USED | REVOKED | INVALID_SIG
    INDEX idx_scans_ticket (ticket_id, scanned_at DESC),
    INDEX idx_scans_station (station_id, scanned_at DESC)
);
```

---

### 5.6 Fare Calculation

**Inputs:** ticket type, origin zone, destination zone (if applicable), time-of-day, discount type

**Fare Table (cached in Redis + kiosk local DB):**
```json
{
  "SINGLE_RIDE": {
    "base_cents": 290,
    "zones": {"A→A": 0, "A→B": 50, "A→C": 100},
    "peak_surcharge_cents": 25,
    "peak_hours": [{"start": "07:00", "end": "09:00"}, {"start": "17:00", "end": "19:00"}]
  },
  "DAY_PASS": {"price_cents": 1350, "valid_hours": 24},
  "MONTHLY_PASS": {"price_cents": 12700, "valid_days": 30},
  "discounts": {
    "SENIOR": 0.5,
    "STUDENT": 0.5,
    "DISABLED": 1.0
  }
}
```

- Fare table versioned; kiosk pulls delta on sync
- Discount eligibility verified at purchase time (credential lookup); embedded in signed ticket payload

---

### 5.7 Security & PCI-DSS Compliance

**Card Data Isolation:**
- Kiosk uses P2PE (Point-to-Point Encryption) hardware terminal → raw card data never enters kiosk software or our servers
- Card number tokenized at terminal → token sent to Payment Service → forwarded to gateway
- Payment Service is the only PCI-DSS scoped service; isolated network segment

**Ticket Forgery Prevention:**
- HMAC-SHA256 with 256-bit key stored in HSM (Hardware Security Module)
- Key never leaves HSM; signing done inside HSM boundary
- Turnstile holds day-scoped derived key (HKDF from master key + date) → compromised turnstile limits blast radius to 1 day

**Replay Attack Prevention:**
- Single-ride: ticket marked USED after first scan → second scan rejected
- Day/monthly pass: signature + expiry prevents replay after expiry
- Ticket_id in global used-set (Redis SET); bloom filter on turnstile catches most replays locally

**Sensitive Data:**
- User PII encrypted at rest (AES-256); card details never stored (tokenized)
- Logs scrubbed of card/PAN data before writing
- Access control: station agents see only their station's transactions; SOX audit log for all admin actions

---

### 5.8 Reconciliation & Financial Accuracy

**Daily Reconciliation Job:**
```
1. Pull all CHARGED payments from PostgreSQL for the day
2. Pull settlement report from payment gateway (PayPal/Stripe)
3. Match by gateway_txn_id:
   - Matched + amounts equal → OK
   - In our DB but not gateway → investigate (network error mid-charge?)
   - In gateway but not our DB → critical alert (ghost charge) → auto-refund + alert
4. Write reconciliation_report (date, matched_count, discrepancy_count, total_discrepancy_cents)
5. Alert if discrepancy > 0.001% of daily volume
```

**Offline Kiosk Reconciliation:**
- Kiosk offline transactions settled within 24h of reconnect
- EMV cryptograms submitted to card network; acquirer confirms/declines
- Declined offline auth → Ticket Service marks ticket REVOKED → turnstile rejects on next sync
- Station agent notified to handle affected passenger

---

## 6. Trade-offs Discussion

### 6.1 Payment Idempotency: Redis + PostgreSQL vs PostgreSQL Only vs Redis Only

**Problem:** Network timeout between client and Payment Service. Client retries. Must never double-charge $175M/day in transactions.

| Approach | Crash Safety | Latency | Complexity | Risk |
|----------|-------------|---------|------------|------|
| **Redis check + PostgreSQL fallback (current)** | High (PG durable) | <10ms (Redis hit) | Medium | Redis crash: falls through to PG |
| **PostgreSQL only** | Highest | 20–50ms per check | Low | Hot row contention on idempotency_key index |
| **Redis only** | Low (volatile) | <5ms | Low | Redis crash → idempotency lost → double charge |
| **Gateway-native idempotency (PayPal/Stripe)** | Highest | Adds gateway RTT | Low | Vendor-dependent; still need our own key tracking |

**Decision: Two-layer (Redis fast path + PostgreSQL durable fallback)**
```
Why not PostgreSQL only?
- At 14 TPS × 5K kiosks = 70K TPS peak idempotency lookups
- PostgreSQL SELECT WHERE idempotency_key=$1: ~20ms with index
- 70K × 20ms = 1.4M connection-seconds/sec → connection pool exhaustion
- PgBouncer helps but doesn't eliminate the index contention at this scale

Why not Redis only?
- Redis crash → all in-flight idempotency keys lost
- Any concurrent retry during crash window → duplicate charge
- At $175M/day: even 0.001% double-charge rate = $1,750/day fraudulent charges
- Unacceptable for PayPal's financial accuracy requirements

Two-layer approach:
1. Redis GET: <5ms cache hit for 99% of idempotency checks (TTL 24h)
2. PostgreSQL fallback: SELECT for fresh requests or post-Redis-crash recovery
3. INSERT ... ON CONFLICT DO NOTHING: atomic; second insert simply no-ops
4. After gateway charge: UPDATE payment_intent + INSERT outbox (single TX)
5. Cache result in Redis for 24h → future retries hit Redis only

Trade-off: Redis crash means next retry hits PostgreSQL (slower but correct).
Financial integrity preserved at cost of occasional 50ms latency spike.
```

---

### 6.2 Offline Kiosk: EMV Floor Limit vs Full Online vs Reject-If-Offline

**Problem:** Network outage at station. Passengers need tickets. Card terminals can operate offline via EMV standard, but with financial risk.

| Approach | Availability | Financial Risk | Commuter Impact |
|----------|-------------|----------------|-----------------|
| **EMV floor limit $25 offline (current)** | 99.99% | <$25 uncollectable per declined offline auth | Minimal (most fares < $25) |
| **Full online only (reject if offline)** | ~99.95% | Zero | Significant (stranded commuters) |
| **Generous floor limit ($100+)** | 99.99% | Higher uncollectable debt | Minimal |
| **Cash only when offline** | 99.99% | Zero (cash is final) | Moderate (not everyone carries cash) |

**Decision: $25 EMV floor limit (transit industry standard)**
```
Why $25?
- Industry precedent: NYC MTA, London TfL, Tokyo Metro all use similar limits
- Subway tickets typically $2.90–$13.50 → floor limit covers all ticket types
- Monthly pass ($127): requires online authorization; commuter advised to buy online
  or use cash if kiosk is offline and buying monthly

Risk quantification:
- Daily kiosk offline events: ~0.1% of kiosks × 4h avg = 200 kiosk-hours/day
- Transactions in offline window: 14 TPS × 200 hours × 3600s = ~10M offline txns/year
- Card decline rate for offline EMV: ~2% (stolen/frozen cards)
- Average fare: $5.50 → 10M × 0.02 × $5.50 = $1.1M/year uncollectable
- Offset by: reduced fraud insurance premium (EMV chip vs magnetic stripe)
- Accepted as cost of transit operations

Alternative risk accepted vs. rejected:
- Rejected: allow unlimited offline → too much uncollectable debt
- Rejected: fail closed (no tickets when offline) → commuter relations disaster
  (5K stranded commuters per outage event × PR cost)
```

---

### 6.3 Turnstile Validation: Local Bloom Filter vs Central DB Lookup vs Token-Based

**Problem:** 3,000 scans/sec at peak. Gate must open in < 300ms. Central DB round-trip alone is 50–100ms; under load it degrades.

| Approach | Latency | Availability | Duplicate Risk | Complexity |
|----------|---------|-------------|----------------|------------|
| **Local bloom filter + HMAC verify (current)** | <5ms | Offline-capable | 60s window for duplicates | Medium |
| **Central Redis lookup per scan** | 50–100ms | Network-dependent | None | Low |
| **Central PostgreSQL per scan** | 100–200ms | Network-dependent | None | Low |
| **NFC/contactless token (stateful)** | <1ms | Offline-capable | None (hardware state) | High |

**Decision: Local HMAC verification + bloom filter; async central sync**
```
Why not central Redis?
- 3,000 scans/sec × 100ms = 300 concurrent Redis connections open at all times
- Redis Cluster: 6 nodes, 50K ops/sec capacity → handles it mathematically
- But: any Redis node failure or network partition = all gates stop opening
- Transit systems cannot tolerate "gates fail closed" at rush hour (safety risk, liability)

Local validation breakdown (< 5ms):
1. Verify HMAC signature (CPU, ~1µs): if tampered → REJECT immediately
2. Check expiry (timestamp in QR payload, ~1µs): no network needed
3. Check bloom filter (local in-memory, ~1µs): not in valid set → REJECT
4. Check LRU revocation cache (local, ~1µs): recently revoked → REJECT
5. Open gate; async publish TicketUsed event (not in 300ms window)

Duplicate ticket window:
- Single-ride: TicketUsed event propagated to bloom filter within 60s
- 60s window: same ticket used at two different stations → both gates open
- Risk: at 50M rides/day × 0.001% fraud rate = 500 duplicate attempts/day
- Most caught by: geographic impossibility check (station A and station B
  simultaneously = fraud flag) → account suspended + ticket revoked
- Financial loss: ~500 × $3.50 = $1,750/day → acceptable for transit system

Monthly pass: zero duplicate risk (signature + expiry only; no use-count)
Day pass: zero duplicate risk (same model)
Multi-ride: online decrement preferred; offline decrement syncs within 4h
```

---

### 6.4 Ticket Signing: HMAC-SHA256 vs RSA/ECDSA vs Symmetric Encryption

**Problem:** QR ticket must be unforgeable offline. Turnstile verifies without network. Which cryptographic primitive?

| Approach | Verification Speed | Key Distribution | Offline Capable | If Key Compromised |
|----------|------------------|-----------------|-----------------|-------------------|
| **HMAC-SHA256 (current)** | <1µs | Symmetric: must distribute secret to all turnstiles | Yes | All turnstiles compromised |
| **ECDSA (asymmetric)** | ~10µs | Public key only on turnstiles | Yes | Only signer (HSM) compromised |
| **RSA-2048** | ~100µs (verify) | Public key only on turnstiles | Yes | Only signer compromised |
| **AES encryption (no signing)** | <1µs | Symmetric: must distribute to turnstiles | Yes | Attacker can decrypt + forge |

**Decision: HMAC-SHA256 with day-scoped derived keys (HKDF)**
```
Why HMAC over ECDSA?
- Speed: HMAC ~1µs vs ECDSA ~10µs per verification
- At 3,000 scans/sec × 10µs = 30ms CPU per second on a $50 ARM turnstile
- At 3,000 × 1µs = 3ms → ample headroom for 300ms gate decision
- Turnstile is embedded ARM running other tasks; microseconds matter

Why not pure HMAC (single key)?
- If one turnstile is physically compromised → attacker has master signing key
- Blast radius: can forge any ticket for any date at any station

Day-scoped derived keys (HKDF mitigation):
derived_key = HKDF(master_key, date_string)
- e.g., derived_key_20260828 = HKDF(master, "2026-08-28")
- Turnstile pre-loaded with derived_key for today + yesterday (in case of time drift)
- HSM holds master_key; derived_key computed inside HSM, pushed to turnstiles nightly
- If turnstile compromised: attacker can only forge tickets for that specific date
- Master key rotation: monthly; all derived keys invalidated automatically

Trade-off vs ECDSA:
- HMAC: symmetric distribution is operationally harder (secure key push to 5K kiosks)
- ECDSA: simpler key distribution (public key is public); better blast-radius containment
- ECDSA chosen by Apple Wallet, Google Pay precisely for this reason

Why we chose HMAC:
- Turnstile hardware is 2018-era ARM Cortex-A7 with no hardware crypto accelerator
- ECDSA 10µs × 3,000 scans/sec = missed 300ms SLA at busy stations
- If upgrading hardware: ECDSA is the recommended migration path
```

---

### 6.5 Outbox Pattern vs Two-Phase Commit vs Saga for Payment → Ticket Issuance

**Problem:** Payment is charged. Ticket must be issued. These are two separate operations that must both succeed or both fail.

| Approach | Consistency | Latency | Complexity | Failure Mode |
|----------|-------------|---------|------------|-------------|
| **Outbox pattern (current)** | Eventual (seconds) | Low (async) | Medium | Brief gap: charged but ticket pending |
| **Two-phase commit (2PC)** | Strong (synchronous) | High (+100–300ms) | Very high | Distributed deadlock; coordinator SPOF |
| **Saga (choreography)** | Eventual (compensating) | Medium | Medium | Compensation logic required |
| **Synchronous sequential** | Strong | Medium | Low | Payment Service calls Ticket Service inline |

**Decision: Outbox pattern**
```
Why not synchronous sequential?
- Payment Service calls Ticket Service after gateway charge
- If Ticket Service is down: payment charged, no ticket issued → worst failure
- Retry logic needed: how long? what if retry also fails?
- Coupling: Payment Service must know about Ticket Service → tight dependency

Why not 2PC?
- Requires Ticket Service to participate in distributed TX protocol
- Coordinator (Payment Service) crash mid-commit → blocked TX until recovery
- At $175M/day: any blocked transaction is a support incident
- 2PC latency: additional network round-trips between coordinator and participants

Why not Saga?
- Compensating transaction for payment: refund customer
- Refund is itself a payment operation → adds gateway RTT, possible refund failure
- Complex to implement correctly at edge cases (partial refund, gateway timeout on refund)

Outbox pattern chosen:
1. DB transaction: UPDATE payment_intent + INSERT outbox row → atomic, durable
2. Outbox relay reads PENDING rows → publishes to Kafka → deletes row
3. Ticket Service consumes Kafka → issues ticket → publishes TicketIssued
4. Notification Service consumes TicketIssued → sends receipt email

Failure scenarios:
- Payment charged, outbox relay crashes: relay restarts, replays outbox → ticket issued (delayed, not lost)
- Ticket Service down: Kafka message buffered; Ticket Service processes on recovery
- User perspective: "Processing... your ticket will arrive within 30 seconds"

Acceptable: 30-second delay on ticket issuance (Kafka consumer lag)
vs. alternative: synchronous dependency on Ticket Service adds failure surface
```

---

### 6.6 Bloom Filter Staleness: 60-Second vs 5-Second vs Real-time Push

**Problem:** Bloom filter on turnstiles is stale by up to 60 seconds. In that window, a revoked or already-used ticket could pass.

| Refresh Interval | Duplicate Window | Network Load | Battery/Resource Impact |
|-----------------|-----------------|-------------|------------------------|
| **60 seconds (current)** | Up to 60s | Low | Minimal |
| **5 seconds** | Up to 5s | 12× higher | Significant (embedded ARM) |
| **Real-time push (WebSocket)** | ~500ms | Low (event-driven) | Medium (persistent connection) |
| **1 second** | Up to 1s | 60× higher | High |

**Decision: 60-second delta pull + real-time push for critical revocations**
```
Why not 5-second polling?
- 5K turnstiles × 12 bloom filter fetches/min = 60K requests/min = 1K req/sec
  just for bloom filter maintenance
- Each delta fetch: ~10KB (compressed bitset diff) → 10 MB/sec sustained
- Embedded ARM: frequent wakeup from sleep state increases power consumption
- For battery-backup turnstiles: 5s polling drains backup faster

Why not 1-second?
- 60× more requests → 60 MB/sec → meaningful ISP cost for transit authority
- ARM CPU time on delta computation/merge saturates at busy stations
- Not worth it: 1s vs 60s window barely matters for fraud (attacker needs to
  coordinate two people at two stations within 1s — essentially impossible)

Real-time push for critical events only:
- Ticket REVOKED (stolen card, fraud detected) → WebSocket push immediately
- Ticket REFUNDED (user cancelled) → push immediately
- Normal ticket expiry / use → 60s delta is fine

Duplicate ride window analysis:
- 60s window × 3K scans/sec = 180K scans during which a used ticket could replay
- But: fraud requires attacker to physically be at two stations simultaneously
- Geographic validation: if ticket_id used at Station A then Station B within 60s
  → flag as fraud even if both scans "passed" → retroactive account action
- Financial exposure: 500 duplicates/day × $3.50 = $1,750/day → acceptable

Trade-off: 60s staleness is a deliberate availability-over-consistency choice.
For a transit system: availability (gates working) outweighs rare duplicate rides.
```

---

### 6.7 Consistency Model Across the System

**Deliberate consistency decisions per component:**

| Component | Consistency | Rationale |
|-----------|------------|-----------|
| Payment idempotency (Redis + PG) | **Strong** (two-layer durable) | $175M/day requires zero double-charge |
| Outbox → Kafka → Ticket issuance | **Eventual** (seconds lag) | Async decoupling; ticket arrives within 30s |
| Turnstile bloom filter | **Eventual** (60s stale) | Availability over consistency; gates must open |
| Offline kiosk → central sync | **Eventual** (up to 4h lag) | Offline-first; reconciled on reconnect |
| Revocation push (WebSocket) | **Near real-time** (<1s) | Stolen tickets are time-sensitive |
| Reconciliation (nightly batch) | **Strongly consistent** (PG + gateway report) | SOX compliance; financial accuracy |
| Ticket scan log (async Kafka) | **At-least-once** | Audit trail; duplicates deduplicated by scan_id |
| Fare table (kiosk cache) | **Eventual** (pulled at sync) | Fare changes infrequent; 4h lag acceptable |

**Key interview insight:** PayPal's core concern is financial exactness — but "exact" applies only to the money layer (payment_intents, reconciliation). The ticket and validation layers intentionally trade consistency for availability. A transit system's cardinal rule: the gate must open for valid passengers even during partial outages. Strong consistency everywhere would make the turnstile dependent on central DB latency — unacceptable at 300ms SLA under rush hour load.

---

### 6.8 Physical vs Digital Ticket: Why Support Both?

**Problem:** Digital-only is simpler. Why maintain physical thermal printing infrastructure?

| Approach | Cost | Coverage | Fraud Risk | Accessibility |
|----------|------|---------|------------|--------------|
| **Digital only** | Low (no printers) | Smartphone required | Low (crypto signed) | Excludes ~15% of riders |
| **Physical only** | High (5K printers) | Universal | Medium (harder to revoke) | Universal |
| **Both (current)** | Medium | Universal | Low (both crypto signed) | Universal |

**Decision: Both channels required for transit equity**
```
Why not digital only?
- 15% of transit riders in US don't own smartphones (elderly, low-income)
- Transit authorities (NYC MTA, MBTA) required by ADA/equity regulations
  to provide non-smartphone access
- Tourists: no data plan → QR in wallet app works offline; many prefer kiosk

Physical ticket challenges addressed:
- Forgery: same HMAC-SHA256 signing as digital → QR is equally secure
- Revocation: can't remotely revoke a printed ticket if lost/stolen
  → Mitigation: stamp ticket_id in used-set after scan; replacement requires station agent
- Printer maintenance: 5K printers × ESC/POS protocol → 3rd-party maintenance contracts

Physical ticket as offline fallback:
- App crash during commute → printed ticket at kiosk works independently
- No data signal in subway → printed ticket has no network dependency
- Redundancy: both channels validate the same HMAC → turnstile doesn't care which

Trade-off: ~$2M/year in kiosk printer maintenance vs. excluding 15% of ridership
(~7.5M riders/day × 15% = 1.1M riders who need physical tickets)
Transit authority legal/equity obligations make this a non-negotiable requirement.
```

---

## 7. Follow-Up Topics

### Handling Station Connectivity Loss (Full Offline Mode)
- Turnstile: 4h offline cache sufficient (bloom filter + revocation list)
- Kiosk: continues selling; cash always works; card up to $25 EMV floor limit
- Monthly/day pass: purely offline-verifiable (signature + expiry in QR)
- Single-ride: risky offline; kiosk issues with offline flag → central validates within 4h
- If station offline > 4h: turnstiles fail-open (let everyone through) + log for audit; rare and time-bounded

### Scalability Bottlenecks

| Component | Bottleneck | Solution |
|---|---|---|
| Payment Service DB | 14 TPS × 5K kiosks peak | Read replicas; async outbox; connection pooling (PgBouncer) |
| Turnstile scan fan-out | 3K scans/sec | Async write; local queue; no DB in scan critical path |
| Bloom filter refresh | 10M active tickets every 60s | Delta updates only; compressed bitset push to turnstiles |
| Redis ticket used-set | 50M entries/day | Sharded Redis; TTL eviction after ticket expiry + 1h |

### Lost / Damaged Ticket Replacement
- Agent looks up purchase by: payment confirmation code, last 4 card digits, timestamp
- Agent issues replacement ticket: original marked REPLACED, new ticket ACTIVE
- Replacement linked to original in DB (audit trail)
- Rate limit: max 2 replacements per user per month (fraud prevention)

### Accessibility
- Kiosk: screen reader support, audio prompts, low-reach card reader, large font mode
- Digital ticket: high-contrast QR; Apple Wallet / Google Pay integration
- Station agent bypass: user calls agent → agent issues ticket on behalf

### Mobile Wallet Integration (Apple Wallet / Google Pay)
- Ticket issued as `.pkpass` (Apple) or `PassKit` object
- Stored in device wallet → works offline (signature verifiable without network)
- NFC pass → turnstile reader can validate without QR camera
- Expiry auto-removed from wallet after valid_until timestamp

### Observability

| Metric | Alert |
|---|---|
| Payment success rate | < 99% → P1 |
| Turnstile rejection rate anomaly | > 3σ from baseline → P2 (possible sync issue) |
| Kiosk offline duration | > 30 min → P2 (field team dispatch) |
| Reconciliation discrepancy | Any amount > $0 → P1 |
| Ticket validation latency p99 | > 300ms → P1 |
| Bloom filter staleness | > 5 min → P2 (push mechanism down) |

---

## Summary

| Component | Technology |
|---|---|
| API Gateway | Envoy / Kong (TLS termination, auth) |
| Payment Service | Java/Go; Outbox pattern; PCI-isolated network segment |
| Payment Gateway | PayPal Braintree SDK + Stripe fallback |
| Ticket Signing | HSM (Thales / AWS CloudHSM) + HMAC-SHA256 |
| Ticket DB | PostgreSQL (primary + read replicas, PgBouncer) |
| Idempotency Store | Redis (24h TTL) + PostgreSQL (durable backup) |
| Turnstile Validation | Bloom filter + local revocation LRU; async Kafka scan events |
| Kiosk Agent | Embedded Linux + local SQLite + P2PE EMV terminal |
| Event Streaming | Apache Kafka (TicketIssued, PaymentCharged, TicketValidated) |
| Offline Sync | Kiosk sync agent; HTTPS batch upload on reconnect |
| Notifications | SendGrid (email receipt) + Twilio (SMS) + FCM/APNs |
| Reconciliation | Nightly batch job (Python/Spark); alerts via PagerDuty |
| Monitoring | Prometheus + Grafana + Jaeger + PagerDuty |
| Compliance | PCI-DSS (Payment Service), SOX audit log (all financial writes) |
