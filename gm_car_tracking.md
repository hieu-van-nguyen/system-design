# GM Car Tracking System Design - FAANG Interview Level

## Overview
Design a comprehensive vehicle tracking and remote management system for General Motors that collects real-time telemetry from millions of connected vehicles, monitors their health, and sends remote instructions (OTA updates, diagnostics, recalls, route guidance). The system must handle high-volume IoT data ingestion, real-time monitoring, and reliable command distribution at global scale.

---

## 1. Functional Requirements

### Core Features

#### 1.1 Data Collection & Telemetry
- **Real-time Vehicle Data**
  - GPS location (latitude, longitude, timestamp)
  - Velocity, acceleration, brake pressure
  - Engine metrics (RPM, fuel level, temperature)
  - Battery status (for EVs: SOC, voltage, current)
  - Tire pressure, brake wear, oil condition
  - Door status, seatbelt state, light status
  - Diagnostic trouble codes (DTCs)

- **Data Frequency**
  - Critical metrics: every 5-10 seconds
  - Standard metrics: every 30-60 seconds
  - Diagnostic data: on-demand or triggered by events
  - Event-driven: instant on warning/error

#### 1.2 Monitoring & Alerts
- **Vehicle Health Monitoring**
  - Real-time engine/battery health
  - Predictive maintenance alerts
  - Safety anomalies (harsh acceleration, speeding)
  - Geofence breach detection
  - Driver behavior scoring

- **Alert Management**
  - Immediate alerts (safety, critical faults)
  - Scheduled alerts (maintenance due)
  - User-configurable alert thresholds
  - Multi-channel notifications (app, email, SMS)

#### 1.3 Remote Instruction Management
- **Over-The-Air (OTA) Updates**
  - Software updates for infotainment, firmware, maps
  - Staged rollout with rollback capability
  - Progress tracking and status reporting

- **Remote Commands**
  - Lock/unlock doors
  - Start/stop engine
  - Climate control adjustment
  - Charging schedule (for EVs)
  - Route guidance

- **Diagnostic Commands**
  - Run remote diagnostics
  - Collect specific sensor data
  - Trigger health reports
  - Historical data retrieval

- **Recall Management**
  - Push safety-critical recalls
  - Track recall completion
  - Schedule service appointments

#### 1.4 Fleet Management (for fleet operators)
- **Multi-vehicle Dashboards**
  - Real-time location view (map)
  - Fleet health overview
  - Utilization metrics
  - Driver performance analytics

- **Batch Operations**
  - Send commands to multiple vehicles
  - Schedule OTA updates across fleet
  - Route optimization

#### 1.5 Analytics & Insights
- **Usage Analytics**
  - Mileage tracking
  - Utilization rates
  - Fuel/energy consumption
  - Driver behavior patterns

- **Predictive Analytics**
  - Maintenance prediction
  - Failure forecasting
  - Anomaly detection

---

## 2. Non-Functional Requirements

### Scale Requirements
- **Connected Vehicles**: 10+ million active vehicles globally
- **Data Ingestion Rate**: 100+ million telemetry events/day
- **Peak Event Rate**: 500,000+ events/second
- **Active Users**: 20+ million mobile app users
- **Geographic Coverage**: 150+ countries

### Performance Requirements
- **Telemetry Latency**: <5 seconds (data available in dashboard)
- **Command Delivery Latency**: <30 seconds to vehicle (95th percentile)
- **Vehicle Responsiveness**: Vehicle responds to command within 5 minutes
- **API Response Time**: <500ms (95th percentile)
- **Mobile App Load**: <2 seconds
- **Historical Data Query**: <10 seconds for 1 year of data

### Reliability & Consistency
- **System Availability**: 99.99% uptime (52 minutes downtime/year)
- **Data Durability**: No loss of critical telemetry or commands
- **Command Delivery Guarantee**: At-least-once delivery
- **Data Consistency**: Eventual consistency for telemetry, strong for critical commands
- **RTO (Recovery Time Objective)**: <15 minutes
- **RPO (Recovery Point Objective)**: <5 minutes

### Security Requirements
- **Encryption**: TLS 1.3 for all data in transit
- **Vehicle Authentication**: Mutual authentication (certificate-based)
- **Command Authorization**: Multi-layer authorization (user → fleet → vehicle)
- **Data Privacy**: GDPR/CCPA compliant
- **Audit Logging**: All actions logged and immutable

### Scalability
- **Horizontal Scaling**: Handle 10x growth without redesign
- **Regional Isolation**: Independent regional deployments
- **Connection Scaling**: Support millions of concurrent WebSocket connections

---

## 3. High-Level Design

### System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                    VEHICLE LAYER                                    │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Connected Vehicle (Vehicle OS + Cellular Modem)             │  │
│  │ - Collect telemetry                                         │  │
│  │ - Process commands                                          │  │
│  │ - Send status updates                                       │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────────────────┘
                             │ (MQTT/WebSocket)
                             │ (Real-time bidirectional)
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│              COMMUNICATION LAYER (Edge/CDN)                         │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │ Regional IoT Gateway Clusters                               │  │
│  │ - 5G/LTE connection termination                             │  │
│  │ - TLS/mTLS handling                                         │  │
│  │ - Message queuing                                           │  │
│  │ - Compression/decompression                                │  │
│  └──────────────────────────────────────────────────────────────┘  │
└────────────────────────────┬─────────────────────────────────────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│   MESSAGE QUEUE  │ │   MESSAGE QUEUE  │ │   MESSAGE QUEUE  │
│   (Kafka/Pulsar)│ │   (Kafka/Pulsar)│ │   (Kafka/Pulsar)│
│   US-EAST       │ │   EU-WEST       │ │   ASIA-PACIFIC   │
└────────┬─────────┘ └────────┬─────────┘ └────────┬─────────┘
         │                    │                    │
    ┌────▼────────────────────▼────────────────────▼────┐
    │         Stream Processing Layer                    │
    │  ┌─────────────────────────────────────────────┐  │
    │  │ Real-time Processing (Flink/Kafka Streams) │  │
    │  │ - Data enrichment                           │  │
    │  │ - Anomaly detection                         │  │
    │  │ - Aggregation                               │  │
    │  │ - Alert generation                          │  │
    │  └─────────────────────────────────────────────┘  │
    │  ┌─────────────────────────────────────────────┐  │
    │  │ Time-Series Database (InfluxDB/TimescaleDB)│  │
    │  │ - High-cardinality vehicle data             │  │
    │  │ - Fast queries (seconds to minutes)         │  │
    │  └─────────────────────────────────────────────┘  │
    └─────────────────────┬──────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
┌───────────────────┐ ┌──────────────┐ ┌─────────────────┐
│ API Gateway       │ │ Command Svc  │ │ Notification Svc│
│ (REST/GraphQL)    │ │ (Queue)      │ │ (Email/SMS/Push)│
└───────┬───────────┘ └──────┬───────┘ └────────┬────────┘
        │                    │                   │
    ┌───▼────────────────────▼───────────────────▼────┐
    │         CACHE LAYER (Redis Cluster)             │
    │ - Session data                                   │
    │ - Last known vehicle state                      │
    │ - User preferences                              │
    │ - Hot query results                             │
    └────────────────────┬─────────────────────────────┘
                         │
    ┌────────────────────▼─────────────────────┐
    │     PRIMARY DATA STORE LAYER              │
    │  ┌────────────────────────────────────┐  │
    │  │ SQL Database (PostgreSQL)          │  │
    │  │ - User accounts                    │  │
    │  │ - Vehicles metadata                │  │
    │  │ - Subscription & billing           │  │
    │  │ - Fleet management                 │  │
    │  │ - Commands & status                │  │
    │  │ Sharding: by vehicle_id/region     │  │
    │  └────────────────────────────────────┘  │
    │  ┌────────────────────────────────────┐  │
    │  │ Document Store (MongoDB)           │  │
    │  │ - Unstructured telemetry variants  │  │
    │  │ - User preferences                 │  │
    │  │ - Custom configurations            │  │
    │  │ Sharding: by vehicle_id            │  │
    │  └────────────────────────────────────┘  │
    │  ┌────────────────────────────────────┐  │
    │  │ Data Lake (HDFS/S3)                │  │
    │  │ - Raw telemetry archive (7 years)  │  │
    │  │ - Analytics & ML training          │  │
    │  │ - Historical audit logs            │  │
    │  └────────────────────────────────────┘  │
    └────────────────────┬──────────────────────┘
                         │
┌────────────────────────▼──────────────────────────────┐
│         Analytics & ML Pipeline                       │
│  - Predictive maintenance models                      │
│  - Driver behavior analysis                           │
│  - Fleet optimization                                 │
│  - Anomaly detection                                  │
└─────────────────────────────────────────────────────┘
```

### Core Services Architecture

#### 1. **Vehicle Connection Service**
- Manages MQTT broker clusters (geographically distributed)
- Handles vehicle authentication and TLS handshakes
- Manages connection pooling and session state
- Implements connection keep-alive and heartbeat
- Scales to 100M+ concurrent connections

#### 2. **Telemetry Ingestion Service**
- Receives telemetry from vehicles
- Validates schema and data quality
- Deduplicates messages
- Routes to message queue (Kafka)
- Monitors ingestion rates and latency

#### 3. **Stream Processing Service**
- Real-time telemetry processing
- Anomaly detection (harsh braking, speeding, geofence breaches)
- Data enrichment (weather, traffic, map data)
- Metric aggregation (avg speed, fuel efficiency)
- Alert generation and triggering

#### 4. **Command & Control Service**
- Manages command queue and delivery
- Tracks command status
- Implements retry logic with backoff
- Handles command versioning
- Supports command scheduling

#### 5. **User & Fleet Management Service**
- User authentication and authorization
- Fleet management (add/remove vehicles)
- Role-based access control
- Subscription and billing management

#### 6. **Analytics & Reporting Service**
- Processes historical telemetry data
- Generates analytics reports
- Feeds ML models for predictions
- Supports custom queries

---

## 4. Back of Envelope Calculation

### Data Volume Estimation

#### Telemetry Data
```
10M vehicles × 1 telemetry message per 30 seconds 
= 10M / 30 = 333,333 messages/second
= 20M messages/minute
= 28.8B messages/day

Average message size: 2 KB
Daily telemetry: 28.8B × 2KB = 57.6 TB/day
Monthly: 57.6 × 30 = 1.73 PB/month
Yearly: 1.73 × 12 = 20.7 PB/year

With compression (4:1): 5.2 PB/year
With data retention (7 years): 36.4 PB
```

#### Command Data
```
Assume: 10% of vehicles receive a command/month
= 10M × 0.1 = 1M commands/month
= 30,000 commands/day
= 1,250 commands/hour

Average command size: 500 bytes
Monthly: 1M × 500B = 500 GB
Yearly: 6 TB
7-year retention: 42 TB
```

#### Metadata & References
```
Users: 20M × 10KB = 200 GB
Vehicles metadata: 10M × 5KB = 50 GB
Fleet management: 10M × 1KB = 10 GB
Commands history: 365M commands/year × 1KB = 365 GB/year
Subscriptions: 10M × 2KB = 20 GB
Total metadata: ~500 GB (relatively small)
```

### Storage Breakdown
```
Hot storage (last 30 days):
- Telemetry: 1.7 TB (compressed)
- Commands: 50 GB
- Metadata: 500 GB
Total hot: ~2.3 TB

Warm storage (31-365 days):
- Telemetry: 17 TB (compressed)
- Commands: 1 TB
Total warm: ~18 TB

Cold storage (2-7 years):
- Telemetry: 34 TB (compressed)
- Commands: 7 TB
Total cold: ~41 TB (S3 Glacier)

Total: ~61 TB (including current year)
```

### Compute & Network Estimation

#### Request Processing
```
Peak QPS: 500,000 telemetry events/second

Assuming:
- 10,000 RPS per stream processing instance
- 500,000 / 10,000 = 50 processing instances

API requests (dashboard queries):
- 20M users, 10% daily active = 2M DAU
- Avg 5 queries/user/day = 10M queries/day
- Peak hour: 10M / 24 × 4 = 1.67M queries/hour
- Peak QPS: 1.67M / 3600 = ~465 req/s
- Assuming 1000 RPS per API instance: ~1 instance

Connection Management:
- 10M concurrent vehicle connections
- Each connection: ~10 KB memory (session + buffers)
- Total: 100 GB memory for connection state
- Across 100 connection servers: 1 GB per server
- Feasible with modern hardware (256 GB servers)
```

#### Network Bandwidth
```
Inbound (telemetry):
- 500k events/sec × 2 KB = 1 GB/s = 8 Tbps
- With compression: 2 Tbps effective

Outbound (commands + responses):
- Assume 2% of vehicles receiving commands: 200k vehicles
- 100 bytes/command = 20 MB/s = 160 Mbps

Total: ~2.2 Tbps inbound, manageable with regional distribution
```

### Database Sizing

#### Time-Series Database (Telemetry)
```
Hot (30 days):
- 28.8B events × 2KB = 57.6 TB
- With compression: 14.4 TB
- With 1-hour rollups: 500 GB

Indexes:
- vehicle_id: ~100 GB
- timestamp: ~50 GB
- Properties: ~50 GB

Total TS DB: ~15 TB hot

Warm: 150 TB (365 days at compress/rollup)
```

#### SQL Database (Metadata)
```
Users table: 20M × 2KB = 40 GB
Vehicles table: 10M × 3KB = 30 GB
Commands table: 365M/year × 1KB = 365 GB (hot = 30 GB)
Fleets table: 1M × 5KB = 5 GB
Subscriptions: 10M × 3KB = 30 GB
Transactions/audit: 10 TB (for compliance)

Total SQL: ~11.5 TB (heavily sharded by vehicle_id/user_id)
```

---

## 5. Trade-offs Discussion

### 1. **Real-time vs Latency vs Bandwidth**

**Problem**: Sending telemetry every 5 seconds vs 30 seconds

| Frequency | Latency | Bandwidth | Freshness | Cost |
|-----------|---------|-----------|-----------|------|
| 5 sec | <5s | 12 TB/day | Real-time | $100M/year |
| 30 sec | <30s | 1.7 TB/day | Near real-time | $20M/year |
| 60 sec | <60s | 850 GB/day | Delayed | $10M/year |

**Recommendation**: **Adaptive Frequency**
```
Default: 30-second intervals (balance cost & latency)
On-demand: 5-second during active monitoring
Critical metrics: 10-second always
Event-triggered: Immediate on warning/error

This reduces 60% of unnecessary bandwidth while maintaining
responsiveness for critical use cases.
```

---

### 2. **Command Delivery: Guaranteed vs Best-Effort**

**Problem**: Reliability vs complexity

| Approach | Reliability | Latency | Complexity |
|----------|-------------|---------|------------|
| **At-Least-Once** | 99.99% | 30s | High (idempotency needed) |
| **At-Most-Once** | 95% | 5s | Low (fire & forget) |
| **Exactly-Once** | 99.95% | 50s | Very High |

**Recommendation**: **At-Least-Once with Idempotency**
```
Implementation:
- Each command gets UUID (idempotency key)
- Vehicle stores recently processed command IDs (Bloom filter)
- Duplicate commands ignored but acked
- Retry timeout: exponential backoff (5s, 10s, 30s, 60s, 300s)
- Max retries: 10 times over 30 minutes

For critical commands (unlock door):
- Add explicit user confirmation on vehicle
- Timeout: 5 minutes
- Log all interactions
```

---

### 3. **Telemetry Storage: Hot vs Archive**

**Problem**: Query performance vs storage cost

| Strategy | Hot (30d) | Warm (365d) | Cold (7yr) | Query Speed |
|----------|-----------|------------|-----------|-------------|
| **All in DB** | 15 TB | 150 TB | 1 PB | <1s | $500k/month |
| **Hot in DB + Archive** | 15 TB | 50 TB | 500 GB (glacier) | <10s / 5min | $50k/month |
| **Sampled** | 1 TB | 5 TB | 50 TB | <100ms | $5k/month |

**Recommendation**: **Tiered Storage**
```
Tier 1 (Last 30 days): Time-series DB
- Full resolution
- Query latency: <1 second
- Cost: $30k/month

Tier 2 (31-365 days): Compressed + Sampled
- 1-hour rollups for standard queries
- Full data available for deep analysis
- Query latency: 5-30 seconds
- Cost: $10k/month

Tier 3 (2-7 years): S3 Glacier
- Archival for compliance/legal
- Query latency: hours (restore needed)
- Cost: $1k/month

Total: $41k/month vs $500k/month = 92% savings
```

---

### 4. **Consistency: Strong vs Eventual**

**Problem**: Vehicle state accuracy vs latency

| Approach | Consistency | Latency | Complexity |
|----------|-------------|---------|------------|
| **Strong** | Immediate | 500ms+ | High |
| **Eventual** | 30 seconds | 50ms | Low |
| **Hybrid** | Recent for critical | 100ms | Medium |

**Recommendation**: **Hybrid Consistency**
```
Critical operations (locks, payment):
- Strong consistency (pessimistic locking)
- Latency: 500ms acceptable

Telemetry & non-critical commands:
- Eventual consistency (event-sourced)
- Latency: <100ms
- Stale data: <30 seconds

Implementation:
- Cache vehicle state in Redis (last known)
- Real-time updates via Kafka streams
- DB read-your-writes guarantee
- Eventual sync with background job
```

---

### 5. **Centralized vs Decentralized Architecture**

**Problem**: Single point of failure vs operational complexity

| Approach | Availability | Latency | Complexity |
|----------|--------------|---------|------------|
| **Centralized** | 99.95% | 50ms | Low |
| **Regional** | 99.99% | 100ms | Medium |
| **Edge** | 99.999% | 10ms | Very High |

**Recommendation**: **Regional Architecture**
```
Global Setup:
- 4 regional clusters (US, EU, Asia, Others)
- Each region independent (no cross-region coupling)
- Vehicles connect to nearest region
- Regional failover: <5 minutes

Within region:
- Active-active setup (no single point of failure)
- Data replication: within region (50ms RPO)
- Command routing: per-region queues

Cross-region:
- Asynchronous replication (eventual consistency)
- User can switch regions (travel across continents)
```

---

### 6. **Vehicle Connection: Persistent WebSocket vs Polling**

**Problem**: Real-time updates vs connection overhead

| Approach | Latency | Overhead | Scalability | Complexity |
|----------|---------|----------|------------|------------|
| **WebSocket** | <1s | Low (keep-alive) | 100M conn/cluster | Medium |
| **Polling** | 5-30s | High (frequent conn) | 1M conn/cluster | Low |
| **Hybrid** | <5s | Medium | 50M conn/cluster | High |

**Recommendation**: **Persistent WebSocket with Polling Fallback**
```
Primary:
- MQTT over TLS (lightweight binary protocol)
- Keep-alive: 30 seconds
- Connection timeout: 5 minutes idle

Fallback (if MQTT fails):
- HTTP long-polling
- Poll interval: 30 seconds
- Timeout: 5 seconds
- Automatic reconnection

Benefits:
- Real-time for most cases
- Works on restricted networks
- Mobile-friendly (battery efficient with keep-alive)
```

---

## 6. Deep Dive: Critical Components

### 6.1 Vehicle Connection Management

```
┌─────────────────────────────────────────────┐
│       Vehicle Startup Sequence              │
└─────────────────────────────────────────────┘
         ▼
1. Vehicle boots, starts cellular modem
2. Modem connects to carrier network
3. Vehicle queries DNS: iot.gm.com
   └─→ Geo-routed to nearest regional gateway
         ▼
4. TLS handshake with gateway
   - Vehicle cert validation
   - Mutual authentication
   - Establish secure channel
         ▼
5. MQTT connection (over TLS)
   - Send vehicle_id, version, firmware
   - Subscribe to command topic: /vehicles/{vehicle_id}/commands
   └─→ Subscribe to broadcast topic: /fleets/{fleet_id}/commands
         ▼
6. Send initial telemetry
   - Location
   - Battery/fuel status
   - Known faults
         ▼
7. Gateway registers vehicle in central registry
   - vehicle_id → region, gateway, connection_id
   - Used for command routing
         ▼
8. Begin telemetry stream
   - Every 30 seconds (configurable)
   - With compression (gzip)

Connection State Machine:
┌─────────────┐
│  CONNECTED  │
└──────┬──────┘
       │
       │ (heartbeat miss x 3)
       ▼
┌─────────────────────┐
│ RECONNECTING        │
│ - Exponential backoff│
│ - 5s, 10s, 30s, 60s │
└─────────┬───────────┘
          │ (success)
          ▼
      CONNECTED
```

**Scaling Considerations**:
```
Single gateway limitations:
- 1M concurrent connections per gateway
- 500 Mbps bidirectional bandwidth
- 64 GB RAM for connection state

To scale to 100M vehicles:
- 100 gateway instances
- Distributed across 4 regions (25 per region)
- Each region: 5 data centers (5 per DC)
- Auto-scaling: add/remove based on connection count
```

**Resilience Strategies**:
```
Connection Loss Recovery:
1. Vehicle detects no ACK for 3 heartbeats (90 seconds)
2. Closes connection, waits 5 seconds
3. Attempts reconnection
4. If fails: exponential backoff to 5 minutes
5. Eventually tries alternative gateway (if configured)

Gateway Failure Recovery:
1. Central registry detects gateway missing heartbeat (30s)
2. Marks gateway unhealthy
3. Redirects new connections to other gateways
4. Existing connections: vehicle automatically reconnects (30-90s)
5. Gateway recovery: re-establishes registry on restart
```

---

### 6.2 Telemetry Processing Pipeline

```
Vehicle sends:
{
  vehicle_id: "VIN_12345",
  timestamp: 1693494000,
  location: {lat: 37.7749, lon: -122.4194},
  speed: 45.2,  // km/h
  rpm: 2500,
  fuel_level: 0.75,  // 75%
  battery_soc: 0.45,  // EV: 45%
  dtc_codes: ["P0401", "P0101"],
  door_status: {driver: "closed", passenger: "open"}
}
         │
         ▼
┌──────────────────────────────┐
│ Telemetry Validation         │
├──────────────────────────────┤
│ - Schema validation          │
│ - Data type check            │
│ - Range validation           │
│ - Timestamp sanity check     │
│ - Deduplication (5 min window)
└──────────────────────────────┘
         │
         ▼
┌──────────────────────────────┐
│ Kafka Ingestion              │
├──────────────────────────────┤
│ Topic: telemetry-raw         │
│ Partitions: 1000 (by VIN)    │
│ Replication: 3               │
│ Retention: 24 hours          │
└──────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────┐
│ Stream Processing (Real-time)            │
├──────────────────────────────────────────┤
│                                          │
│ 1. Data Enrichment                       │
│    └─ Add weather, traffic, road data   │
│    └─ Add maintenance schedule          │
│                                          │
│ 2. Anomaly Detection                     │
│    └─ Harsh acceleration: accel > 5 m/s²│
│    └─ Speeding: speed > limit           │
│    └─ Geofence breach                   │
│    └─ Battery/fuel anomaly              │
│    └─ DTC code interpretation           │
│                                          │
│ 3. Metrics Aggregation                   │
│    └─ 1-minute window aggregates:       │
│       - avg_speed, max_speed            │
│       - fuel_consumption_rate           │
│       - hard_brakes (>0.3g)             │
│       - idling_time                     │
│                                          │
│ 4. Alert Generation                      │
│    └─ Severity levels: Critical, Warning│
│    └─ De-duplication: don't spam        │
│    └─ Routing: to notification service  │
│                                          │
│ 5. Real-time Dashboard Updates           │
│    └─ WebSocket push via pub/sub         │
│    └─ Vehicle location updates           │
│    └─ Fleet health summary               │
│                                          │
└──────────────────────────────────────────┘
         │
    ┌────┴─────────────────────────┐
    │                              │
    ▼                              ▼
┌──────────────────┐         ┌──────────────────┐
│ InfluxDB         │         │ Kafka            │
│ (Time-series)    │         │ (Events)         │
│                  │         │                  │
│ Points stored:   │         │ Topics:          │
│ - raw telemetry  │         │ - anomalies      │
│ - aggregates     │         │ - alerts         │
│ - metrics        │         │ - maintenance    │
│ Retention: 30d   │         │ - billing-events │
└──────────────────┘         └──────────────────┘
    │
    ▼
┌──────────────────────────────┐
│ S3 Archive                   │
├──────────────────────────────┤
│ - Daily parquet snapshots    │
│ - Partitioned by date        │
│ - Retention: 7 years         │
│ - Accessible via Athena      │
└──────────────────────────────┘
```

**Performance Metrics**:
```
End-to-end latency (from vehicle to dashboard):
- Vehicle sends: T0
- Kafka ingestion: T0 + 0.1s
- Stream processing: T0 + 0.5s
- Dashboard update: T0 + 1.0s
- User sees on mobile: T0 + 2.0s (network + app)

Target: <5 seconds (95th percentile)
Actual: ~2-3 seconds in practice
```

---

### 6.3 Command & Control System

```
┌──────────────────────────────────────┐
│ User initiates command via app       │
│ Example: "Lock all doors"            │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ API Gateway                          │
├──────────────────────────────────────┤
│ - Authentication (JWT)               │
│ - Authorization (user → fleet → veh) │
│ - Rate limiting: 100 req/sec/user    │
│ - Input validation                   │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Command Service                      │
├──────────────────────────────────────┤
│ Creates command record:              │
│ {                                    │
│   id: UUID,                          │
│   vehicle_id: "VIN_12345",           │
│   command_type: "lock_doors",        │
│   parameters: {doors: "all"},        │
│   priority: "normal",                │
│   initiated_by: "user_123",          │
│   timestamp: 1693494000,             │
│   status: "PENDING",                 │
│   retries: 0,                        │
│   expires_at: 1693494300  (5 min)   │
│ }                                    │
│                                      │
│ Stores in DB                         │
│ Publishes to command queue           │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Command Queue (Kafka)                │
├──────────────────────────────────────┤
│ Topic: commands-{region}             │
│ Partitions: 10000 (by vehicle_id)    │
│ Replication: 3                       │
│ Key: vehicle_id (same partition)     │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Command Router Service               │
├──────────────────────────────────────┤
│ 1. Check vehicle online status       │
│    - Registry: vehicle_id → gateway  │
│ 2. If online: route to MQTT topic    │
│    └─ /vehicles/{id}/commands        │
│ 3. If offline: queue for retry       │
│    └─ Retry scheduler job            │
│ 4. Set retry policy:                 │
│    └─ Attempt at: T+5s, T+10s,       │
│       T+30s, T+60s, T+5min, etc.     │
│ 5. Update command status → SENT      │
│                                      │
│ Message format (MQTT):               │
│ {                                    │
│   command_id: "uuid",                │
│   type: "lock_doors",                │
│   params: {doors: "all"},            │
│   correlation_id: "uuid"             │
│ }                                    │
└────────────────┬─────────────────────┘
                 │
                 ▼
      (MQTT over TLS to vehicle)
                 │
                 ▼
┌──────────────────────────────────────┐
│ Vehicle Receives Command             │
├──────────────────────────────────────┤
│ 1. Validate signature                │
│ 2. Check idempotency (seen before?)  │
│ 3. Execute command                   │
│ 4. Generate status update:           │
│    {                                 │
│      command_id: "uuid",             │
│      status: "SUCCESS" or "FAILED",  │
│      result: {...},                  │
│      timestamp: 1693494002,          │
│      error: null or "error message"  │
│    }                                 │
│ 5. Send ACK back via MQTT            │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Command Status Update (MQTT ACK)     │
├──────────────────────────────────────┤
│ Topic: /vehicles/{id}/status         │
│ Contains: command_id + result        │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Gateway receives ACK                 │
├──────────────────────────────────────┤
│ - Publishes to command-status topic  │
│ - Used by status aggregation service │
└────────────────┬─────────────────────┘
                 │
                 ▼
┌──────────────────────────────────────┐
│ Command Status Service               │
├──────────────────────────────────────┤
│ 1. Update command record in DB       │
│    status → "SUCCESS" or "FAILED"    │
│ 2. Publish completion event          │
│ 3. Push notification to user app     │
│ 4. Update user dashboard             │
│ 5. Clear from retry queue            │
└────────────────┬─────────────────────┘
                 │
                 ▼
      (User sees: "Doors locked" ✓)
```

**Retry Logic**:
```
Initial delivery: T0
Retry 1 (vehicle offline): T0 + 5s
Retry 2: T0 + 10s
Retry 3: T0 + 30s
Retry 4: T0 + 60s
Retry 5: T0 + 5min
...
Retry 10: T0 + 30min

Max retries: 10 (30 minutes total window)
Timeout: If no ACK after 30 min, mark as TIMEOUT

For critical commands (unlock):
- Max retries: 5 (5 minutes)
- User confirmation required on vehicle for safety
- Log all interactions for audit
```

---

### 6.4 Anomaly Detection Engine

```
Real-time stream of telemetry events
         │
         ▼
┌─────────────────────────────────────────┐
│ Windowed Aggregation (Kafka Streams)    │
├─────────────────────────────────────────┤
│ Tumbling windows: 1 minute              │
│                                         │
│ For each vehicle, aggregate:            │
│ - avg_speed                             │
│ - max_acceleration                      │
│ - hard_brakes (count, severity)         │
│ - harsh_turns (count)                   │
│ - idling_time                           │
│ - rpm_revs (rapid RPM changes)          │
│ - fuel_consumption (rate)               │
│ - battery_discharge_rate (EVs)          │
│ - diagnostic_codes (new, cleared)       │
│                                         │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Anomaly Detection Rules (ML + Rules)    │
├─────────────────────────────────────────┤
│                                         │
│ Rule-based (immediate):                 │
│ ├─ Speeding: speed > speed_limit + 20%│
│ ├─ Excessive accel: a > 0.5g          │
│ ├─ Harsh brakes: a < -0.4g            │
│ ├─ Harsh turn: lateral_a > 0.3g       │
│ ├─ Battery critical: soc < 5%         │
│ ├─ Geofence breach                    │
│ ├─ DTC code: P0XXX (fault codes)      │
│ └─ Unusual idle: >30min at rest       │
│                                         │
│ ML-based (anomaly detection):           │
│ ├─ Isolation Forest (multivariate)     │
│ ├─ LSTM for sequential patterns        │
│ ├─ One-class SVM for outliers          │
│ └─ Trained on 3 months of vehicle data │
│                                         │
│ Behavioral (driver profiling):          │
│ ├─ Driver risk score (harsh events)    │
│ ├─ Vehicle usage patterns              │
│ ├─ Maintenance needs prediction        │
│ └─ Fuel/energy efficiency trends       │
│                                         │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Alert Generation & Deduplication       │
├─────────────────────────────────────────┤
│                                         │
│ Alert types:                            │
│ - CRITICAL (immediate action needed)    │
│   └─ Battery <5%, brake fault, recall  │
│ - WARNING (review ASAP)                 │
│   └─ Speeding, harsh driving           │
│ - INFO (notification)                   │
│   └─ Oil change due, tire rotation     │
│                                         │
│ Deduplication:                          │
│ ├─ Same alert type for vehicle: wait   │
│   └─ 5 min (critical) / 60 min (warn)  │
│ ├─ Suppress if more recent alert       │
│ ├─ Batch related alerts                │
│ └─ Correlation: if 10+ vehicles        │
│    affected → broadcast alert          │
│                                         │
└─────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Alert Routing                           │
├─────────────────────────────────────────┤
│                                         │
│ CRITICAL:                               │
│ └─ Immediate push notification          │
│    + Email + SMS                        │
│                                         │
│ WARNING:                                │
│ └─ Push notification + in-app alert     │
│    + Email (if opted in)                │
│                                         │
│ INFO:                                   │
│ └─ In-app notification only             │
│    + Email summary (weekly)             │
│                                         │
│ Fleet alerts:                           │
│ └─ Route to fleet manager               │
│    (different user)                     │
│                                         │
└─────────────────────────────────────────┘
```

---

## 7. Follow-up Questions & Answers

### Q1: How do you handle vehicle offline scenarios?

**A**: Offline-First Architecture

```
Vehicle Offline Detection:
1. Gateway marks vehicle offline after 3 missed heartbeats (90s)
2. Timeout updated in central registry
3. New command requests → routed to command queue (not MQTT)
4. Vehicle attempts reconnection exponentially

When vehicle comes online:
1. Reconnects to gateway (new TLS session)
2. Downloads pending commands from queue
3. Executes in order (respecting priority)
4. Sends status updates in batch

Handling pending commands:
┌─────────────────────────────────┐
│ Commands queued while offline   │
│ {                               │
│   priority: "high",             │
│   expires_at: 24 hours,         │
│   idempotent_key: uuid,         │
│   data: {...}                   │
│ }                               │
│                                 │
│ Vehicle stores cache (SQLite):  │
│ ├─ Last 100 commands processed  │
│ ├─ Prevent duplicate execution  │
│ └─ On reconnect: check before   │
│    executing                    │
│                                 │
│ If offline > 24 hours:          │
│ ├─ Commands expire              │
│ ├─ User notified (command failed)
│ ├─ For critical: retry on next  │
│   online window                 │
│ └─ Critical cmd: set "pending"  │
│    status (not failed)          │
│                                 │
└─────────────────────────────────┘

Handling data loss:
- Vehicle maintains 10 MB buffer of telemetry
- On reconnect: sends buffered data
- Server deduplicates (timestamp + hash)
- Over-the-air sync optimizes bandwidth
```

---

### Q2: How do you manage OTA (Over-The-Air) updates securely?

**A**: Staged Rollout with Verification

```
OTA Update Flow:
┌─────────────────────────────┐
│ GM releases new firmware    │
│ v2.1.0 for Model X         │
└────────────────┬────────────┘
                 │
                 ▼
┌─────────────────────────────┐
│ Create Staged Rollout Plan  │
│ {                           │
│   firmware_id: uuid,        │
│   target_vehicles: 100k,    │
│   version: "2.1.0",         │
│   staging: [                │
│     {stage: 1, pct: 1%},    │
│     {stage: 2, pct: 5%},    │
│     {stage: 3, pct: 25%},   │
│     {stage: 4, pct: 100%}   │
│   ],                        │
│   metrics_threshold: {      │
│     error_rate: 0.001,      │
│     rollback_threshold: 0.5%│
│   }                         │
│ }                           │
└────────────────┬────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│ Stage 1: Canary (1%, ~1000 vehicles)    │
├─────────────────────────────────────────┤
│ 1. Select vehicles (random sample)      │
│ 2. Send update command                  │
│ 3. Monitor for:                         │
│    - Crash rate, restart loops          │
│    - Battery drain anomaly              │
│    - Connectivity issues                │
│    - Error logs in telemetry            │
│ 4. Duration: 6 hours                    │
│ 5. Decision:                            │
│    - If error rate > 0.5%: ROLLBACK     │
│    - If OK: proceed to stage 2          │
│                                         │
└────────────────┬────────────────────────┘
                 │
    (repeat for stages 2, 3)
                 │
                 ▼
┌─────────────────────────────┐
│ Stage 4: Full Rollout       │
│ Deploy to all 100k vehicles │
│ Monitor for 1 week          │
│                             │
│ Rollback triggers:          │
│ - Crash rate > 0.1%         │
│ - Manual escalation         │
│ - Safety-related issue      │
│                             │
└─────────────────────────────┘

Security measures:
┌────────────────────────────────────────┐
│ 1. Firmware Signing                    │
│    └─ RSA-2048 signature of firmware   │
│    └─ Vehicle validates signature      │
│    └─ Prevents tampering               │
│                                        │
│ 2. Differential Updates                │
│    └─ Only changed bytes sent          │
│    └─ Reduces data: 500 MB → 50 MB    │
│    └─ Lower bandwidth, faster delivery │
│                                        │
│ 3. Dual Partitions                     │
│    └─ Vehicle partition A: current     │
│    └─ Vehicle partition B: staging     │
│    └─ Update on partition B            │
│    └─ Test then switch                 │
│    └─ Rollback: switch back to A       │
│                                        │
│ 4. Update Verification                 │
│    └─ Hash verification after download │
│    └─ Verify disk space before update  │
│    └─ Test in sandbox before deploy    │
│                                        │
│ 5. Rollback Capability                 │
│    └─ Keep previous 2 versions         │
│    └─ Quick switch within 30s          │
│    └─ Rollback duration: <5 minutes    │
│                                        │
└────────────────────────────────────────┘

Typical OTA Scenario:
T0: Command sent (vehicle online)
T0+10s: Vehicle acknowledges
T0+30s: Firmware download starts
T0+5min: Download completes (50 MB at 1 Mbps avg)
T0+5:30: Verification passed
T0+6min: Update applied, vehicle reboots
T0+7min: Vehicle back online, old version
T0+7:30: Switch partition → new version active
User sees: v2.1.0 after 7.5 minutes
```

---

### Q3: How do you handle geographic distribution and data residency?

**A**: Regional Deployment with Compliance

```
Global Infrastructure:
┌──────────────────────────────────────────────────────┐
│ North America                  (US-EAST, US-WEST)    │
│ Europe                         (EU-WEST, EU-CENTRAL) │
│ Asia Pacific                   (APAC-SG, APAC-AU)    │
│ China (if applicable)          (CN-NORTH)            │
│ South America                  (SA-SOUTH)            │
└──────────────────────────────────────────────────────┘

Regional Architecture (independent per region):
┌────────────────────────────────────────────────────┐
│ Region: US-EAST                                    │
├────────────────────────────────────────────────────┤
│                                                    │
│ Ingestion Layer (edge):                           │
│ └─ 5 MQTT gateways (50M conn capacity)            │
│                                                    │
│ Processing Layer (hot):                           │
│ └─ Kafka cluster (10 brokers)                     │
│ └─ Stream processors (50 instances)               │
│ └─ InfluxDB (20 nodes)                            │
│                                                    │
│ Storage Layer (warm):                             │
│ └─ PostgreSQL (10 shards, replicated)             │
│ └─ MongoDB (5 nodes)                              │
│ └─ Redis cluster (16 nodes)                       │
│                                                    │
│ API & Services:                                   │
│ └─ REST API (100 instances)                       │
│ └─ Notification service (20 instances)            │
│ └─ Command service (30 instances)                 │
│                                                    │
│ Data Flow (within region):                        │
│ └─ All processing happens in region               │
│ └─ No data crosses region boundary                │
│ └─ Regional failover: within 5 minutes            │
│                                                    │
│ Cross-region sync (asynchronous):                 │
│ └─ User metadata: replicate to other regions      │
│ └─ Telemetry: summarized only (not raw)           │
│ └─ Audit logs: async to data lake (central)       │
│                                                    │
└────────────────────────────────────────────────────┘

Data Residency Compliance:
┌──────────────────────────────────────────────────────┐
│ GDPR (EU):                                           │
│ ├─ Personal data stays in EU-WEST/EU-CENTRAL        │
│ ├─ User has right to data export                    │
│ ├─ Data deletion enforced within 30 days            │
│ └─ DPIA (Data Protection Impact Assessment) done    │
│                                                      │
│ CCPA (California):                                  │
│ ├─ Consumer personal data stays in US               │
│ ├─ Opt-out mechanism implemented                    │
│ ├─ Data sale disclosure in privacy policy           │
│ └─ Data deletion: 45 days                           │
│                                                      │
│ China (Multi-Level Protection Scheme):             │
│ ├─ PII stays in CN-NORTH                            │
│ ├─ Separate security audit                          │
│ ├─ Local partner for data handling                  │
│ └─ Government access handling                       │
│                                                      │
└──────────────────────────────────────────────────────┘

Vehicle Connection Routing:
1. Vehicle determines current region (IP geolocation)
2. Connects to nearest regional gateway
3. Gateway validates: vehicle VIN → expected region
4. Exception handling:
   - Vehicle crosses region (travel): switch to new region
   - Gateway failure: failover to next closest gateway
   - Graceful: sync user preferences within 30s

User Data Access:
┌────────────────────────────────────────────────┐
│ User in Europe (GDPR-bound)                   │
│                                               │
│ 1. User authenticates → EU-WEST              │
│ 2. User's vehicles may be in multiple regions │
│    e.g., Car 1 (EU), Car 2 (US)              │
│ 3. Query aggregation service                  │
│    ├─ EU-WEST: fetch all EU vehicles         │
│    ├─ US-EAST: fetch all US vehicles         │
│    │    (permission check: belongs to user)   │
│    └─ Merge results, show unified dashboard   │
│ 4. Data export (GDPR right):                  │
│    └─ Trigger async export job               │
│    └─ Collect data from all regions          │
│    └─ Generate ZIP file (7 days max)         │
│    └─ User downloads from secure URL         │
│                                               │
└────────────────────────────────────────────────┘
```

---

### Q4: How do you prevent unauthorized access to vehicles?

**A**: Multi-Layer Security

```
Attack Scenarios & Mitigations:

1. MAN-IN-THE-MIDDLE ATTACK
┌───────────────────────────────────────┐
│ Attacker intercepts MQTT message      │
│ → Send fake "unlock door" command     │
│                                       │
│ Mitigation:                           │
│ ├─ TLS 1.3 encryption (AES-256-GCM)  │
│ ├─ Certificate pinning (vehicle cert) │
│ ├─ Perfect forward secrecy (ECDHE)    │
│ ├─ No plaintext messages              │
│ └─ Result: Cannot decrypt             │
│                                       │
└───────────────────────────────────────┘

2. REPLAY ATTACK
┌───────────────────────────────────────┐
│ Attacker records old "lock door" msg   │
│ → Replays it to lock doors again      │
│                                       │
│ Mitigation:                           │
│ ├─ Each command has unique UUID       │
│ ├─ Vehicle stores recently processed  │
│   command IDs (Bloom filter)          │
│ ├─ Timestamp check: msg age < 5min    │
│ ├─ Nonce in command (random)          │
│ └─ Result: Duplicate rejected         │
│                                       │
└───────────────────────────────────────┘

3. UNAUTHORIZED COMMAND
┌───────────────────────────────────────┐
│ Attacker (no auth) sends command:     │
│ → Send "unlock door"                  │
│                                       │
│ Mitigation:                           │
│ ├─ JWT token required                 │
│ │  └─ Signed by GM key, vehicle       │
│ │     validates signature              │
│ ├─ Owner check:                       │
│ │  └─ Command includes vehicle_id     │
│ │  └─ Vehicle verifies owner          │
│ │  └─ User must be owner/fleet mgr    │
│ ├─ Authorization check on vehicle:    │
│ │  └─ Can user unlock? (subscription) │
│ │  └─ Rate limit: 10 unlock/hour      │
│ └─ Result: Command rejected           │
│                                       │
└───────────────────────────────────────┘

4. API COMPROMISE
┌───────────────────────────────────────┐
│ Attacker steals API credentials       │
│ → Make API calls as legitimate user   │
│                                       │
│ Mitigation:                           │
│ ├─ JWT tokens: 1 hour expiration      │
│ ├─ Refresh tokens: 7 days             │
│ ├─ OAuth 2.0 + PKCE for mobile apps  │
│ ├─ API key rotation: quarterly        │
│ ├─ IP whitelisting for fleet APIs     │
│ ├─ Rate limiting per user/IP          │
│ ├─ Anomaly detection:                 │
│ │  └─ Command from unusual location   │
│ │  └─ Burst requests (50 in 5 min)    │
│ │  └─ Trigger MFA challenge           │
│ └─ Result: Suspicious activity blocked│
│                                       │
└───────────────────────────────────────┘

5. VEHICLE TAMPERING
┌───────────────────────────────────────┐
│ Attacker physically accesses vehicle  │
│ → Modifies firmware for diagnostics   │
│                                       │
│ Mitigation:                           │
│ ├─ Secure boot (TPM 2.0):             │
│ │  └─ Only signed firmware executes    │
│ ├─ Anti-rollback:                     │
│ │  └─ Can't downgrade to old version   │
│ ├─ OTA signature validation            │
│ ├─ Diagnostics requires MFA           │
│ ├─ Tamper detection sensors           │
│ └─ Result: Tampering detected         │
│                                       │
└───────────────────────────────────────┘

Authorization Matrix (Role-based):
┌──────────────────────────────────────────────────┐
│                                                  │
│ User Role       Can Lock  Can Unlock  Can View   │
│ ─────────────────────────────────────────────    │
│ Owner           ✓         ✓          ✓          │
│ Family user     ✓         ✓          ✓          │
│ Fleet manager   ✓         ✗          ✓          │
│ Service center  ✓         ✓          ✓*         │
│ GM diagnostics  ✗         ✗          ✓ (live)   │
│ Insurance       ✗         ✗          ✓ (claims) │
│                                                  │
│ * Service center: only when vehicle at service  │
│   (geofence check)                              │
│                                                  │
└──────────────────────────────────────────────────┘
```

---

### Q5: How do you predict vehicle maintenance needs?

**A**: Predictive Maintenance Engine

```
Data Collection Phase:
┌─────────────────────────────────────────┐
│ Collect signals from vehicle:           │
│                                         │
│ Engine metrics:                         │
│ ├─ Oil quality (TBN, viscosity)        │
│ ├─ Coolant temperature trends          │
│ ├─ Fuel efficiency degradation         │
│ ├─ Engine noise (FFT analysis)         │
│                                         │
│ Drivetrain:                             │
│ ├─ Transmission fluid condition        │
│ ├─ Shift quality degradation           │
│ ├─ Differential oil temps              │
│                                         │
│ Suspension & Brakes:                    │
│ ├─ Tire pressure + wear patterns       │
│ ├─ Brake pad thickness (acoustic)      │
│ ├─ Suspension stiffness (from accel)   │
│ ├─ ABS activation frequency            │
│                                         │
│ Electrical:                             │
│ ├─ Battery voltage under load          │
│ ├─ Alternator output                   │
│ ├─ Starter motor performance           │
│ ├─ Door lock mechanism (current draw)  │
│                                         │
│ Environmental:                          │
│ ├─ Ambient temperature (stress)        │
│ ├─ Humidity (corrosion)                │
│ ├─ Salt exposure (winter road salt)    │
│                                         │
└─────────────────────────────────────────┘

Feature Engineering:
┌─────────────────────────────────────────┐
│ Raw signals → Engineered features      │
│                                         │
│ Time-based aggregates (1 day window):   │
│ ├─ Mean, std, min, max of each signal  │
│ ├─ Rate of change                      │
│ ├─ Outlier count (>3σ)                │
│                                         │
│ Historical trends:                      │
│ ├─ 7-day slope (degradation rate)      │
│ ├─ Seasonal adjustment                 │
│ ├─ Anomaly count (per week)            │
│                                         │
│ Vehicle-specific:                       │
│ ├─ Vehicle age (years)                 │
│ ├─ Total mileage                       │
│ ├─ Climate zone (affects wear)         │
│ ├─ Usage pattern (city vs highway)     │
│                                         │
│ Similarity features:                    │
│ ├─ Find similar vehicles               │
│ ├─ Ensemble of similar vehicles'       │
│   remaining useful life (RUL)          │
│                                         │
└─────────────────────────────────────────┘

ML Model Training:
┌─────────────────────────────────────────┐
│ Historical data (5+ years):             │
│ ├─ Vehicles that had oil changes       │
│ ├─ Time-to-service labels              │
│ ├─ Features 90 days before service     │
│                                         │
│ Model architectures:                    │
│ ├─ Gradient Boosting (XGBoost)         │
│ │  └─ Predicts days-to-maintenance     │
│ │  └─ Feature importance for ranking   │
│ ├─ LSTM (RNN) for time series          │
│ │  └─ Captures temporal patterns       │
│ ├─ Survival analysis                   │
│ │  └─ Weibull distribution (RUL)       │
│                                         │
│ Training data:                          │
│ ├─ 3M vehicles × 90 days = 270M        │
│   training examples                    │
│ ├─ Cross-validation: 80/20 split      │
│ ├─ Threshold tuning (precision/recall) │
│                                         │
│ Performance metrics:                    │
│ ├─ Early warning rate: 85%             │
│   (predict 30+ days before failure)    │
│ ├─ False positive rate: <15%           │
│ ├─ Cost per false alert: $50           │
│   (unnecessary inspection)              │
│                                         │
└─────────────────────────────────────────┘

Deployment & Inference:
┌─────────────────────────────────────────────┐
│ Every night (batch):                        │
│ 1. Pull last 90 days telemetry             │
│ 2. Extract + engineer features              │
│ 3. Run inference (XGBoost model)            │
│ 4. Generate predictions:                    │
│    {                                        │
│      vehicle_id: "VIN",                     │
│      maintenance_type: "oil_change",        │
│      days_remaining: 23,                    │
│      confidence: 0.87,                      │
│      failure_risk: 0.05,                    │
│      last_service: "2023-01-15"             │
│    }                                        │
│ 5. Store in database                        │
│ 6. Generate alerts for <30 days              │
│                                             │
│ Real-time (on telemetry event):             │
│ 1. Critical fault detected (DTC code)       │
│ 2. Lookup ML model prediction               │
│ 3. If confidence > 0.9: immediate alert     │
│ 4. Suggest nearby service centers           │
│                                             │
└─────────────────────────────────────────────┘

Example Predictions:
┌─────────────────────────────────────────┐
│ Vehicle: 2019 Chevy Bolt                │
│ Mileage: 85,000 miles                   │
│ Owner: John D.                          │
│                                         │
│ Prediction 1:                           │
│ ├─ Oil change: 21 days remaining        │
│ ├─ Confidence: 92%                      │
│ ├─ History: last oil change 6 months    │
│   ago (normal interval)                 │
│ └─ Action: notify user in 2 weeks       │
│                                         │
│ Prediction 2:                           │
│ ├─ Brake pad replacement: 45 days       │
│ ├─ Confidence: 78%                      │
│ ├─ Signals: increased brake dust,       │
│   harsh braking detected (10 events)    │
│ └─ Action: schedule inspection          │
│                                         │
│ Prediction 3:                           │
│ ├─ Battery degradation (EV)             │
│ ├─ Current SOC decline: -2% per month   │
│ ├─ Estimated replacement: 18 months     │
│ └─ Action: inform for warranty planning │
│                                         │
└─────────────────────────────────────────┘

Cost-Benefit Analysis:
┌─────────────────────────────────────────┐
│ Without predictive maintenance:         │
│ ├─ Unexpected failures: 5% of fleet     │
│ ├─ Cost per failure: $500 (towing,      │
│   repair) + lost time                  │
│ ├─ Total annual: 500k × $500 = $250M   │
│                                         │
│ With predictive maintenance:            │
│ ├─ Prevented failures: 85% of 5%        │
│ ├─ Early warnings prevent breakdown     │
│ ├─ Cost per alert: $50 (service cost)   │
│ ├─ Total annual: 500k × 0.85 × $50     │
│   = $21.2M                             │
│                                         │
│ Savings: $250M - $21.2M = $228.8M/year │
│                                         │
└─────────────────────────────────────────┘
```

---

### Q6: How do you handle high cardinality telemetry data efficiently?

**A**: Cardinality Management Strategy

```
Problem: 10M vehicles × 100+ metrics × time = HUGE

Example cardinality explosion:
┌────────────────────────────────────────────┐
│ Naive approach (no optimization):          │
│                                            │
│ Series: {vehicle_id, metric}               │
│ = 10M vehicles × 100 metrics = 1B series   │
│ Each metric: 1 point per 30s                │
│ = 1B × 2880 points/day = 2.88T points      │
│ = 2.88T × 200 bytes/point = 576 PB/day!   │
│                                            │
│ Storage cost: impossible                   │
│ Query performance: extremely slow          │
│                                            │
└────────────────────────────────────────────┘

Solution 1: INTELLIGENT DOWNSAMPLING
┌────────────────────────────────────────────┐
│ Real-time (hot):                           │
│ ├─ Keep full resolution: 30-second points │
│ ├─ Retention: 24 hours                    │
│ ├─ Storage: ~500 GB/day                   │
│                                            │
│ Warm (1-30 days):                          │
│ ├─ 5-minute aggregates (rollups)          │
│ │  └─ avg, min, max, p95, p99             │
│ ├─ 10x compression: 50 GB/day              │
│                                            │
│ Archive (31-365 days):                     │
│ ├─ 1-hour aggregates                      │
│ ├─ 100x compression: 5 GB/day              │
│                                            │
│ Long-term (2-7 years):                     │
│ ├─ Daily aggregates (S3 Parquet)          │
│ ├─ 1000x compression: 500 MB/day           │
│                                            │
│ Total storage: ~100 TB/year (vs 200 PB!)  │
│                                            │
└────────────────────────────────────────────┘

Solution 2: DIMENSION DROPPING
┌────────────────────────────────────────────┐
│ Not all metrics matter equally:            │
│                                            │
│ High-value metrics (store at high res):    │
│ ├─ Speed, location, fuel level (driver)   │
│ ├─ Battery SOC (critical for EV)          │
│ ├─ Fault codes (diagnostics)              │
│ ├─ 100 metrics × 10M vehicles             │
│                                            │
│ Medium-value metrics (sample):             │
│ ├─ Engine temp, RPM (sample 1/10)         │
│ ├─ Interior light status                  │
│ ├─ Store 1M sample vehicles               │
│                                            │
│ Low-value metrics (aggregate only):        │
│ ├─ Electrical details                     │
│ ├─ Store only hourly summaries            │
│ ├─ Not per-vehicle                        │
│                                            │
│ Effective cardinality reduction: 10x       │
│                                            │
└────────────────────────────────────────────┘

Solution 3: PARTITIONING & SHARDING
┌────────────────────────────────────────────┐
│ Time-Series DB: InfluxDB setup             │
│                                            │
│ Measurement: vehicle_telemetry            │
│ {                                          │
│   time: timestamp,                         │
│   vehicle_id: "VIN",                       │
│   fleet_id: "fleet_123",                   │
│   region: "us-east",                       │
│   metric_type: "speed",  ← cardinality    │
│   value: 45.2                             │
│ }                                          │
│                                            │
│ Sharding strategy:                         │
│ ├─ Shard by: (vehicle_id % 100)           │
│ ├─ Create 100 shards                      │
│ ├─ Each shard: 100k vehicles              │
│ ├─ Single shard storage: ~1 TB/year      │
│                                            │
│ Bucketing by time:                         │
│ ├─ Daily buckets (each day separate)      │
│ ├─ Enables fast retention management      │
│ ├─ Easy deletion of old data              │
│                                            │
└────────────────────────────────────────────┘

Solution 4: SELECTIVE INDEXING
┌────────────────────────────────────────────┐
│ InfluxDB tag indexes:                      │
│                                            │
│ Tags (indexed, searchable):                │
│ ├─ vehicle_id (required)                  │
│ ├─ fleet_id (optional)                    │
│ ├─ region                                 │
│ └─ status (active/inactive)               │
│                                            │
│ Fields (not indexed):                      │
│ ├─ All numeric metrics                    │
│ └─ Searchable by range, not equality      │
│                                            │
│ Index size: ~10 GB (vs 1 TB for naive)    │
│                                            │
└────────────────────────────────────────────┘

Solution 5: QUERY OPTIMIZATION
┌────────────────────────────────────────────┐
│ Typical query pattern:                     │
│ "Show me avg speed for vehicle VIN_123"   │
│                                            │
│ Optimized query:                           │
│ SELECT                                     │
│   time_bucket('5 minutes', time) AS bucket,│
│   AVG(value) AS avg_speed                 │
│ FROM vehicle_telemetry                    │
│ WHERE vehicle_id = 'VIN_123'              │
│   AND time > now() - '7 days'             │
│   AND metric_type = 'speed'               │
│ GROUP BY bucket                            │
│ ORDER BY bucket DESC                       │
│ LIMIT 100                                  │
│                                            │
│ Optimization techniques:                   │
│ ├─ Partition pruning (by date)            │
│ │  └─ Only scan last 7 days               │
│ ├─ Tag filtering first (vehicle_id)       │
│ │  └─ Reduces data scanned 10000x        │
│ ├─ Pre-aggregates (5-min rollups)         │
│ │  └─ Scan aggregates, not raw points    │
│ ├─ Query cache (Redis)                    │
│ │  └─ Cache results for 5 minutes         │
│                                            │
│ Query latency: 10-100ms (vs 10s naive)    │
│                                            │
└────────────────────────────────────────────┘
```

---

## Conclusion

This system design demonstrates:

1. **Scale**: 10M vehicles, 500k events/sec, 20M users
2. **Reliability**: 99.99% uptime, disaster recovery
3. **Real-time**: <5 second telemetry latency, <30 second command delivery
4. **Security**: Multi-layer authentication, encryption, authorization
5. **Complexity**: OTA updates, predictive maintenance, multi-region deployment

**Key Takeaways**:
- Decouple with message queues (Kafka) for reliability
- Use regional architecture for compliance & latency
- Implement adaptive strategies (compression, sampling, tiering)
- Design for offline-first (vehicles lose connectivity)
- Monitor everything (telemetry is the lifeblood)
- Plan for 10x growth without redesign

**Architecture Principles**:
- **Eventual Consistency**: For telemetry/state
- **Strong Consistency**: For commands/safety
- **Event-driven**: Decouple components via Kafka
- **Geo-distributed**: Regions independent, federated user data
- **Resilient**: Offline capability, graceful degradation
- **Auditable**: Immutable logs, compliance built-in

---

**References**:
- IoT at Scale (AWS whitepaper)
- Designing Data-Intensive Applications (Kleppmann)
- Building Scalable Web Services (Lebrun)
- Connected Vehicle Architecture (OTA standards)
- NIST Cybersecurity Framework (vehicle security)
