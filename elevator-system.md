# System Design: Elevator System (OCI)

> Context: Design an elevator control system — both the low-level object-oriented design for a single building's elevator bank, and the high-level distributed system for monitoring and managing elevators across thousands of buildings (think Otis ONE / KONE 24/7 Connected Services).

---

## 1. Functional Requirements

**Building-Level (LLD focus)**
- Passengers can press up/down call buttons on any floor
- Passengers inside elevator can press destination floor buttons
- Elevator moves to requested floors in optimal order
- Elevator doors open/close automatically; re-open on obstruction detection
- Display shows current floor and direction inside and above elevator doors
- Elevator respects capacity limits (weight sensor)
- Emergency stop button halts elevator and alerts building security
- Maintenance mode: take individual elevator offline

**Fleet Management System (HLD focus)**
- Monitor all elevators across thousands of buildings in real-time
- Detect malfunctions, door faults, sensor anomalies automatically
- Alert field technicians with fault details and elevator location
- Predictive maintenance: surface elevators likely to fail before they do
- Usage analytics: peak hours, most-used floors, average wait times
- Remote diagnostics and configuration updates (firmware OTA)
- SLA tracking: uptime % per elevator per building

**Out of Scope**
- Building access control / security integration
- Fire service mode (separate safety system)
- Elevator installation / commissioning tooling

---

## 2. Non-Functional Requirements

| Requirement | Target |
|---|---|
| Responsiveness | Elevator dispatched within **30 seconds** of call in normal load |
| Availability | 99.9% per elevator; fleet management 99.99% |
| Fault Detection | Anomaly detected and technician alerted within **2 minutes** |
| Telemetry Ingestion | 1M elevators × 1 event/sec = **1M events/sec** |
| Safety | Emergency stop always overrides software; hardware-first safety |
| Scalability | Support 1M+ elevators globally |
| Latency | Building controller decision loop < **100ms** |
| Offline Resilience | Building controller operates standalone without cloud connectivity |

---

## 3. Back of Envelope Calculation

**Building Scale**
- Large building: 10 elevators, 50 floors, ~2,000 occupants
- Peak morning: 200 people need elevators in 30 min = **6.7 people/min**
- Each trip: avg 45 sec → each elevator handles ~40 trips/hour → 10 elevators = 400 trips/hour
- Capacity check: 400 trips/hour × 10 people/trip = 4,000 person-trips/hour > peak demand ✓

**Fleet Scale (Cloud System)**
- 1M elevators globally (Otis has ~2M in service)
- Telemetry: 1M elevators × 10 sensor readings/sec = **10M events/sec**
- Simplified: 1M elevators × 1 aggregated event/sec = **1M events/sec** (after edge aggregation)
- Telemetry payload: ~500 bytes; 1M/sec = **500 MB/sec** ingress
- Storage: 1M elevators × 1 KB/min × 60 × 24 × 365 = **525 TB/year** → compressed ~100 TB/year
- Fault events: avg 1 fault/elevator/month = **~380 faults/sec** globally → very manageable

**Alert Volume**
- 1M elevators × 0.1% fault rate = **1,000 faults/day** → ~1/min globally
- Technician dispatch: 5,000 field technicians; each handles ~1 call/day

---

## 4. High-Level Design

### 4A. Building-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Building                               │
│                                                             │
│  Floor Call Buttons (Up/Down per floor)                     │
│       │                                                     │
│       ▼                                                     │
│  ┌────────────────────────────────────┐                     │
│  │   Building Controller (BMS node)  │  ← Safety PLC       │
│  │   - Dispatches elevators          │    (hardware backup) │
│  │   - Runs scheduling algorithm     │                     │
│  │   - Manages elevator states       │                     │
│  └──────────────┬─────────────────────┘                     │
│                 │ CAN Bus / RS-485                          │
│       ┌─────────┼─────────┐                                 │
│       ▼         ▼         ▼                                 │
│  ┌────────┐ ┌────────┐ ┌────────┐                           │
│  │ Elev 1 │ │ Elev 2 │ │ Elev N │                           │
│  │ (MCU)  │ │ (MCU)  │ │ (MCU)  │                           │
│  └────────┘ └────────┘ └────────┘                           │
│       │                                                     │
│       ▼ (telemetry, aggregated)                             │
│  ┌────────────────┐                                         │
│  │  Edge Gateway  │ → Cloud Fleet Management System        │
│  └────────────────┘                                         │
└─────────────────────────────────────────────────────────────┘
```

### 4B. Cloud Fleet Management Architecture

```
Edge Gateways (per building)
       │ MQTT / HTTPS (TLS)
       ▼
┌──────────────────────────────────────────────────────┐
│              IoT Ingestion Layer                     │
│         (AWS IoT Core / Azure IoT Hub)               │
└──────────────────────┬───────────────────────────────┘
                       │
                       ▼
                 Apache Kafka
         (telemetry, faults, commands)
                       │
         ┌─────────────┼──────────────┐
         ▼             ▼              ▼
┌──────────────┐ ┌──────────┐ ┌─────────────────┐
│  Telemetry   │ │  Fault   │ │  Analytics      │
│  Processor   │ │  Detect. │ │  Service        │
│  (Flink)     │ │  Service │ │  (ClickHouse)   │
└──────┬───────┘ └────┬─────┘ └─────────────────┘
       │              │
       ▼              ▼
┌──────────────┐ ┌──────────────────────────────┐
│  Time-Series │ │  Alert / Dispatch Service    │
│  DB          │ │  → Technician Mobile App     │
│  (InfluxDB)  │ │  → SMS / Email / PagerDuty   │
└──────────────┘ └──────────────────────────────┘
                       │
                       ▼
              ┌──────────────────┐
              │  Operations      │
              │  Dashboard       │
              │  (React + WS)    │
              └──────────────────┘
```

### Core Services

| Service | Responsibility |
|---|---|
| **Building Controller** | Runs dispatching algorithm; manages elevator state machine; operates offline |
| **Edge Gateway** | Aggregates building telemetry; buffers during connectivity loss; OTA updates |
| **Telemetry Processor** | Ingests 1M events/sec; normalizes; writes to time-series DB |
| **Fault Detection Service** | Rule-based + ML anomaly detection on sensor streams |
| **Alert Service** | Routes faults to nearest available technician; SLA tracking |
| **Analytics Service** | Usage patterns, wait time analysis, predictive maintenance scoring |
| **Remote Config Service** | OTA firmware; scheduling algorithm parameter tuning per building |
| **Operations Dashboard** | Real-time fleet view; technician dispatch; SLA reporting |

---

## 5. Deep Dive — Part 1: Low-Level OOP Design

### 5.1 Core Domain Model

```
┌──────────────┐         ┌──────────────────┐
│   Building   │1──────*│     Elevator      │
└──────────────┘         └──────────────────┘
                                │
                    ┌───────────┼───────────┐
                    ▼           ▼           ▼
             ┌──────────┐ ┌──────────┐ ┌────────────────┐
             │  Motor   │ │  Door    │ │  FloorPanel    │
             └──────────┘ └──────────┘ │  (dest buttons)│
                                       └────────────────┘
┌──────────────────┐
│   FloorCallPanel │  ← per floor (up/down buttons)
└──────────────────┘
┌──────────────────┐
│  ElevatorController │ ← dispatching brain (one per building)
└──────────────────┘
```

### 5.2 Enums & Value Objects

```python
from enum import Enum, auto
from dataclasses import dataclass
from typing import Optional

class Direction(Enum):
    UP   = auto()
    DOWN = auto()
    IDLE = auto()

class DoorState(Enum):
    OPEN    = auto()
    CLOSED  = auto()
    OPENING = auto()
    CLOSING = auto()

class ElevatorState(Enum):
    IDLE        = auto()
    MOVING      = auto()
    DOOR_OPEN   = auto()
    MAINTENANCE = auto()
    EMERGENCY   = auto()

@dataclass(frozen=True)
class FloorRequest:
    """A request to go to a floor, with its origin context."""
    floor: int
    direction: Optional[Direction] = None   # None if internal (destination) request

@dataclass(frozen=True)
class CallRequest:
    """External hall call: floor + desired direction."""
    floor:     int
    direction: Direction
```

### 5.3 Elevator Class (State Machine)

```python
class Elevator:
    def __init__(self, elevator_id: str, min_floor: int, max_floor: int, capacity_kg: int):
        self.elevator_id   = elevator_id
        self.current_floor = min_floor
        self.direction     = Direction.IDLE
        self.state         = ElevatorState.IDLE
        self.door          = Door(self)
        self.current_weight_kg = 0
        self.capacity_kg   = capacity_kg
        self.min_floor     = min_floor
        self.max_floor     = max_floor

        # Internal destination requests (pressed inside elevator)
        self._up_stops:   set[int] = set()   # floors to stop while going UP
        self._down_stops: set[int] = set()   # floors to stop while going DOWN

    # ── Public API ──────────────────────────────────────────

    def add_stop(self, floor: int) -> None:
        """Passenger inside pressed a destination button."""
        if floor == self.current_floor:
            self.door.open()
            return
        if floor > self.current_floor:
            self._up_stops.add(floor)
        else:
            self._down_stops.add(floor)

    def add_external_call(self, floor: int, direction: Direction) -> None:
        """Dispatcher assigned a hall call to this elevator."""
        if direction == Direction.UP:
            self._up_stops.add(floor)
        else:
            self._down_stops.add(floor)

    def step(self) -> None:
        """Called by controller loop every tick (~100ms). Advances elevator logic."""
        if self.state in (ElevatorState.MAINTENANCE, ElevatorState.EMERGENCY):
            return

        if self.state == ElevatorState.DOOR_OPEN:
            return  # door controller manages close timing

        next_stop = self._next_stop()
        if next_stop is None:
            self.direction = Direction.IDLE
            self.state     = ElevatorState.IDLE
            return

        self.state = ElevatorState.MOVING
        if next_stop > self.current_floor:
            self.direction = Direction.UP
            self.current_floor += 1
        else:
            self.direction = Direction.DOWN
            self.current_floor -= 1

        if self.current_floor == next_stop:
            self._arrive_at_floor(next_stop)

    def is_overweight(self) -> bool:
        return self.current_weight_kg >= self.capacity_kg * 0.95  # 95% threshold

    def estimated_stops_to(self, floor: int) -> int:
        """Estimate steps to reach floor (used by dispatcher for scoring)."""
        if self.direction == Direction.IDLE:
            return abs(floor - self.current_floor)
        # SCAN-based estimate: count stops already committed on path
        en_route_stops = self._stops_between(self.current_floor, floor)
        return abs(floor - self.current_floor) + len(en_route_stops)

    # ── Internal ────────────────────────────────────────────

    def _next_stop(self) -> Optional[int]:
        """LOOK algorithm: continue in current direction; reverse when no more stops that way."""
        if self.direction == Direction.UP or self.direction == Direction.IDLE:
            ahead = [f for f in self._up_stops if f >= self.current_floor]
            if ahead:
                return min(ahead)
            # No more up stops → switch down
            if self._down_stops:
                return max(self._down_stops)
        elif self.direction == Direction.DOWN:
            ahead = [f for f in self._down_stops if f <= self.current_floor]
            if ahead:
                return max(ahead)
            if self._up_stops:
                return min(self._up_stops)
        return None

    def _arrive_at_floor(self, floor: int) -> None:
        self._up_stops.discard(floor)
        self._down_stops.discard(floor)
        self.state = ElevatorState.DOOR_OPEN
        self.door.open()
        self._notify_arrival(floor)

    def _stops_between(self, start: int, end: int) -> set[int]:
        stops = self._up_stops | self._down_stops
        lo, hi = min(start, end), max(start, end)
        return {f for f in stops if lo <= f <= hi}

    def _notify_arrival(self, floor: int) -> None:
        # Observer: notify display, passengers, building controller
        pass
```

### 5.4 Door Class

```python
class Door:
    OPEN_DURATION_SEC = 5
    OBSTRUCTION_REOPEN_LIMIT = 3

    def __init__(self, elevator: 'Elevator'):
        self._elevator         = elevator
        self._state            = DoorState.CLOSED
        self._obstruction_count = 0

    def open(self) -> None:
        if self._state in (DoorState.OPEN, DoorState.OPENING):
            return
        self._state = DoorState.OPENING
        # ... drive motor, transition to OPEN after delay

    def close(self) -> None:
        if self._obstruction_detected():
            self._obstruction_count += 1
            if self._obstruction_count >= self.OBSTRUCTION_REOPEN_LIMIT:
                self._trigger_alarm()
                return
            self.open()   # re-open
            return
        self._obstruction_count = 0
        self._state = DoorState.CLOSING
        # ... drive motor, transition to CLOSED

    def _obstruction_detected(self) -> bool:
        # Light curtain / safety edge sensor
        return False  # hardware abstraction

    def _trigger_alarm(self) -> None:
        self._elevator.state = ElevatorState.EMERGENCY
```

### 5.5 ElevatorController — Dispatcher (Strategy Pattern)

```python
from abc import ABC, abstractmethod

class DispatchStrategy(ABC):
    @abstractmethod
    def assign(self, call: CallRequest, elevators: list[Elevator]) -> Optional[Elevator]:
        ...

class NearestElevatorStrategy(DispatchStrategy):
    """
    Assign to elevator with lowest cost:
      cost = estimated_stops_to(floor) + direction_penalty + overweight_penalty
    """
    def assign(self, call: CallRequest, elevators: list[Elevator]) -> Optional[Elevator]:
        candidates = [e for e in elevators
                      if e.state not in (ElevatorState.MAINTENANCE, ElevatorState.EMERGENCY)
                      and not e.is_overweight()]
        if not candidates:
            return None

        def cost(e: Elevator) -> float:
            base = e.estimated_stops_to(call.floor)
            # Prefer elevator already moving in the requested direction
            direction_penalty = 0 if (
                e.direction == call.direction or e.direction == Direction.IDLE
            ) else 3
            return base + direction_penalty

        return min(candidates, key=cost)

class ZoneStrategy(DispatchStrategy):
    """Assign floors to elevator zones (high-rise buildings)."""
    def __init__(self, zones: dict[str, tuple[int, int]]):
        # zones = {"A": (1, 20), "B": (21, 40), "C": (41, 60)}
        self.zones = zones

    def assign(self, call: CallRequest, elevators: list[Elevator]) -> Optional[Elevator]:
        for elev_id, (lo, hi) in self.zones.items():
            if lo <= call.floor <= hi:
                zone_elevators = [e for e in elevators if e.elevator_id.startswith(elev_id)]
                return NearestElevatorStrategy().assign(call, zone_elevators)
        return NearestElevatorStrategy().assign(call, elevators)


class ElevatorController:
    def __init__(self, elevators: list[Elevator], strategy: DispatchStrategy):
        self._elevators  = elevators
        self._strategy   = strategy
        self._pending: list[CallRequest] = []   # unassigned calls (all elevators busy)

    def on_hall_call(self, floor: int, direction: Direction) -> None:
        call = CallRequest(floor=floor, direction=direction)
        elevator = self._strategy.assign(call, self._elevators)
        if elevator:
            elevator.add_external_call(floor, direction)
        else:
            self._pending.append(call)   # retry on next tick

    def on_cabin_request(self, elevator_id: str, floor: int) -> None:
        elev = self._find(elevator_id)
        if elev:
            elev.add_stop(floor)

    def tick(self) -> None:
        """Main control loop; called every 100ms."""
        for elev in self._elevators:
            elev.step()

        # Retry pending calls (an elevator may have freed up)
        still_pending = []
        for call in self._pending:
            elev = self._strategy.assign(call, self._elevators)
            if elev:
                elev.add_external_call(call.floor, call.direction)
            else:
                still_pending.append(call)
        self._pending = still_pending

    def set_maintenance(self, elevator_id: str) -> None:
        elev = self._find(elevator_id)
        if elev:
            elev.state = ElevatorState.MAINTENANCE

    def _find(self, elevator_id: str) -> Optional[Elevator]:
        return next((e for e in self._elevators if e.elevator_id == elevator_id), None)
```

### 5.6 Observer Pattern — Event Bus

```python
from typing import Callable

class ElevatorEvent:
    def __init__(self, elevator_id: str, event_type: str, payload: dict):
        self.elevator_id = elevator_id
        self.event_type  = event_type    # ARRIVED, DOOR_OPENED, OVERWEIGHT, FAULT
        self.payload     = payload

class EventBus:
    _subscribers: dict[str, list[Callable]] = {}

    @classmethod
    def subscribe(cls, event_type: str, handler: Callable) -> None:
        cls._subscribers.setdefault(event_type, []).append(handler)

    @classmethod
    def publish(cls, event: ElevatorEvent) -> None:
        for handler in cls._subscribers.get(event.event_type, []):
            handler(event)

# Usage: display board, telemetry sender, alarm system all subscribe
EventBus.subscribe("ARRIVED",     lambda e: update_floor_display(e))
EventBus.subscribe("OVERWEIGHT",  lambda e: sound_alarm(e))
EventBus.subscribe("FAULT",       lambda e: alert_technician(e))
```

### 5.7 Scheduling Algorithm — LOOK (Elevator Algorithm)

```
LOOK algorithm (improvement over SCAN):
  - Elevator moves in one direction, servicing all stops in that direction
  - When no more stops ahead → reverse direction (unlike SCAN, doesn't go to end of shaft)
  - Minimizes unnecessary travel; optimal for typical office traffic patterns

Example:
  Elevator at floor 5, moving UP
  Pending stops: {2, 7, 10, 3, 8}
  Up stops: {7, 8, 10}
  Down stops: {2, 3}

  Sequence: 5 → 7 → 8 → 10 (reverse) → 3 → 2

Variants:
  - SSTF (Shortest Seek Time First): greedy, fast but can starve distant floors
  - FCFS: fair but inefficient
  - LOOK: balanced; used in this design
  - Zone-based (high-rise): elevators assigned to floor bands; reduces wait time
```

---

## 6. Deep Dive — Part 2: High-Level System Design

### 6.1 Telemetry Ingestion (1M Events/Sec)

**Edge Gateway (per building):**
- Aggregates all elevator sensor readings every 1 second
- Publishes single building-level event batch (reduces 10K sensor readings → 1 MQTT message)
- Buffers up to 24h of data on-device (SD card) during cloud outages → uploads on reconnect

**Telemetry Schema (Protobuf for efficiency):**
```protobuf
message ElevatorTelemetry {
  string building_id    = 1;
  string elevator_id    = 2;
  int64  timestamp_ms   = 3;
  int32  current_floor  = 4;
  string state          = 5;  // IDLE, MOVING_UP, MOVING_DOWN, DOOR_OPEN
  int32  weight_kg      = 6;
  float  motor_current_a = 7;
  float  door_sensor_ms  = 8; // time for door to close (leading indicator of door failure)
  int32  vibration_rms   = 9; // bearing health indicator
  repeated FaultCode faults = 10;
}
```

**Kafka Topics:**
```
elevator.telemetry        → raw telemetry (1M events/sec, retention 7 days)
elevator.faults           → fault events only (low volume, retention 1 year)
elevator.commands         → remote commands to buildings (firmware update, config change)
elevator.alerts           → dispatched alerts to technicians
```

---

### 6.2 Fault Detection Service

**Rule-Based (deterministic, fast):**
```python
FAULT_RULES = [
    {"name": "DOOR_SLOW",       "condition": "door_sensor_ms > 3000",    "severity": "MEDIUM"},
    {"name": "OVERWEIGHT",      "condition": "weight_kg > capacity_kg",  "severity": "HIGH"},
    {"name": "MOTOR_OVERCURRENT","condition": "motor_current_a > 40",    "severity": "HIGH"},
    {"name": "STUCK_BETWEEN_FLOORS","condition": "no floor change in 60s AND state=MOVING","severity": "CRITICAL"},
    {"name": "OFFLINE",         "condition": "no telemetry for 120s",    "severity": "CRITICAL"},
]
```

**ML-Based (predictive, Flink + TensorFlow Serving):**
- Feature vector: rolling 24h of motor_current, vibration_rms, door_close_time, trip_count
- Model: Isolation Forest / LSTM for time-series anomaly detection
- Output: `anomaly_score` (0–1); score > 0.85 → predictive maintenance alert
- "Bearing will fail in ~7 days" → pre-schedule technician, avoid unplanned downtime

**Alert Routing:**
```
CRITICAL: page on-call technician immediately (PagerDuty P1)
HIGH:     dispatch nearest available technician within 2h
MEDIUM:   schedule preventive visit within 7 days
LOW:      log; include in next monthly maintenance report
```

---

### 6.3 Predictive Maintenance

**Key Indicators:**
| Sensor | What It Predicts |
|---|---|
| `door_sensor_ms` trending up | Door mechanism wear → door failure in ~30 days |
| `vibration_rms` spike | Bearing wear → motor failure |
| `motor_current_a` drift | Brake lining wear, rope stretch |
| Trip count since last service | Calendar-based service interval trigger |

**Pipeline:**
```
InfluxDB (30-day rolling) → Feature Engineering (Spark) → ML Model Inference
                                                              │
                                                              ▼
                                                   Maintenance Work Order
                                                   (ServiceNow / CMMS)
```

---

### 6.4 Remote Configuration & OTA Updates

**Problem:** 1M elevators. Scheduling algorithm update or safety patch must roll out safely.

**Staged Rollout:**
1. Deploy to 0.1% of fleet (test buildings) → monitor 24h for regressions
2. Expand to 1% → 10% → 50% → 100% (canary release)
3. Each stage: compare KPIs (wait time, fault rate) against baseline cohort
4. Automatic rollback if fault rate increases > 5%

**OTA Delivery:**
- Firmware pushed via MQTT command topic: `elevator.commands/<building_id>`
- Edge gateway downloads binary from S3 pre-signed URL
- Applies to elevators one at a time (never whole building simultaneously)
- Checksum verified (SHA-256) before apply; signed with vendor private key

---

### 6.5 Operations Dashboard (Real-Time)

**Key Views:**
- **Fleet Map:** world map; dot per building; color = worst elevator status (green/yellow/red)
- **Building Drill-Down:** floor-by-floor elevator positions, live state, pending calls
- **Fault Queue:** active faults by severity; technician assignment status
- **SLA Report:** uptime % per building per month; breach alerts

**Tech:**
- React frontend + WebSocket (live updates)
- Backend: Go service subscribes to Kafka fault + telemetry topics → pushes to WebSocket
- Read queries: InfluxDB for time-series charts; ClickHouse for usage analytics

---

### 6.6 Data Model (Fleet DB)

```sql
CREATE TABLE buildings (
    building_id    UUID PRIMARY KEY,
    name           VARCHAR(200),
    address        TEXT,
    lat            DECIMAL(10,7),
    lng            DECIMAL(10,7),
    timezone       VARCHAR(60),
    customer_id    UUID
);

CREATE TABLE elevators (
    elevator_id    UUID PRIMARY KEY,
    building_id    UUID REFERENCES buildings,
    shaft_id       VARCHAR(20),
    min_floor      SMALLINT,
    max_floor      SMALLINT,
    capacity_kg    SMALLINT,
    firmware_ver   VARCHAR(20),
    installed_at   DATE,
    last_service_at DATE,
    status         VARCHAR(20)  -- OPERATIONAL, MAINTENANCE, FAULT, OFFLINE
);

CREATE TABLE faults (
    fault_id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    elevator_id    UUID REFERENCES elevators,
    fault_code     VARCHAR(50),
    severity       VARCHAR(20),
    detected_at    TIMESTAMPTZ NOT NULL,
    resolved_at    TIMESTAMPTZ,
    technician_id  UUID,
    root_cause     TEXT,
    INDEX idx_faults_open (elevator_id, resolved_at)
        WHERE resolved_at IS NULL
);

CREATE TABLE maintenance_visits (
    visit_id       UUID PRIMARY KEY,
    elevator_id    UUID REFERENCES elevators,
    technician_id  UUID,
    scheduled_at   TIMESTAMPTZ,
    completed_at   TIMESTAMPTZ,
    findings       TEXT,
    parts_replaced JSONB
);
```

---

## 7. Trade-offs Discussion

### 7.1 Dispatching Algorithm: LOOK vs SSTF vs FCFS vs Zone-Based

**Problem:** Multiple elevators, multiple simultaneous call requests. Which algorithm minimizes wait time without starving passengers?

| Algorithm | Avg Wait Time | Starvation Risk | CPU Cost | Fairness |
|-----------|--------------|-----------------|----------|---------|
| **FCFS (First Come First Served)** | High | None | O(1) | Perfect |
| **SSTF (Shortest Seek Time First)** | Low | High (far floors starved) | O(n) | Poor |
| **LOOK (current)** | Medium-low | Low | O(n) | Good |
| **Zone-based (high-rise)** | Low (within zone) | None (dedicated) | O(1) | Good |
| **ML-based dispatch** | Lowest | None | High | Depends |

**Decision: LOOK for general purpose, Zone for high-rise, with starvation guard**
```
Why not SSTF?
- SSTF always picks nearest floor → elevator bounces between nearby floors
- Floor 40 in a 50-floor building during busy ground-floor rush
  → may not be served for 10+ minutes (starvation)
- Transit elevator with 50 floors: unacceptable SLA violation

Why not FCFS?
- FCFS processes calls in arrival order regardless of direction
- Elevator at floor 5, calls at floors 3, 7, 9, 2, 8 in that order:
  FCFS path: 5→3→7→9→2→8 (wasteful backtracking)
  LOOK path:  5→7→8→9→2→3 (sweep + reverse; 40% fewer floors traveled)

LOOK chosen:
- Balances efficiency (sweeps in direction) and fairness (reversal serves all floors)
- Deterministic, predictable, easy to reason about for safety certification

Zone strategy for buildings > 20 floors:
- Floors 1–20 → elevators A, B; floors 21–40 → C, D; floors 41–60 → E, F
- Reduces avg wait time ~40% vs. shared pool (all elevators chasing same calls)

Starvation guard:
- Track pending_call_timestamp per request
- If any call unserviced > 90s → override LOOK, assign idle elevator immediately
- Prevents floor starvation during uneven demand patterns
```

---

### 7.2 Building Controller: On-Premise vs Cloud vs Hybrid

**Problem:** Should elevator dispatching logic run on a local building controller or be delegated to the cloud fleet management system?

| Approach | Latency | Offline Resilience | Complexity | Security |
|----------|---------|-------------------|------------|---------|
| **On-premise controller only** | <1ms | 100% offline | Low (no cloud dep) | High (air-gapped) |
| **Cloud-only dispatch** | 50–200ms + RTT | Zero (needs internet) | Medium | Medium |
| **Hybrid (current): local control + cloud monitoring** | <1ms (local) | 100% offline | Medium | High |

**Decision: Hybrid — local controller with autonomous operation, cloud for monitoring only**
```
Why not cloud-only dispatch?
- Round-trip latency: local call button → cloud API → dispatch decision → motor command
  = 100–300ms round trip (acceptable) but fails on internet outage
- Internet outage = elevator stops responding to calls → unacceptable safety liability
- Regulatory: elevator safety codes (ASME A17.1) require local redundant control

Why not on-premise only?
- No visibility into fleet-wide patterns, predictive maintenance, remote diagnostics
- 1,000-building deployment: technician has to physically visit each building for any config change
- No SLA tracking, no anomaly correlation across buildings

Hybrid chosen:
- Building Controller (edge node): runs dispatching independently, 100ms control loop
- Cloud: receives telemetry, issues advisory config updates (scheduling parameters)
- Advisory commands: building controller can accept or reject cloud suggestions
  → Cloud sends "shift zone boundary from floor 20 to floor 22" → controller applies on next idle
- Safety commands: never cloud-routed (only hardware PLC / local emergency stop)

Offline resilience:
- Edge gateway buffers 24h of telemetry during outage → uploads on reconnect
- Building controller continues operating indefinitely without cloud
- Cloud reconnect: no replay of missed commands (state-based, not event-based dispatch)
```

---

### 7.3 Telemetry Frequency: 1 Second vs 100ms vs Batch-Per-Trip

**Problem:** Each elevator produces 10 sensor readings/sec. Sending raw to cloud = 10M events/sec globally. Edge aggregation reduces this but at cost of latency.

| Approach | Ingress Volume | Fault Detection Latency | Storage Cost |
|----------|---------------|------------------------|-------------|
| **Raw 100ms (every reading)** | 100M events/sec | <200ms | 10× higher |
| **Aggregated 1-second (current)** | 1M events/sec | <2s | Baseline |
| **5-second aggregate** | 200K events/sec | <10s | 80% less |
| **Per-trip batch** | ~580K trips/sec | Minutes | Very low |

**Decision: 1-second aggregated at edge gateway**
```
Why not raw 100ms?
- 100M events/sec × 500 bytes = 50 GB/sec ingest
- Kafka: 10,000 partitions minimum; cluster cost 10× current
- Storage: 525 TB/year × 10 = 5.25 PB/year
- Most 100ms samples are redundant: elevator moving between floors is predictable
- Cost completely unjustified for marginal fault detection improvement

Why not 5-second?
- Door-close fault: door sensor anomaly lasts 500ms
  → At 5s aggregation, fault may be average-smoothed away
- Motor overcurrent spike: lasts 1–3 seconds → missed at 5s aggregation
- 2-minute fault detection SLA requires sub-5s resolution for meaningful root cause

1-second chosen:
- Captures transient faults (door, motor) without raw data volume
- Edge gateway runs lightweight aggregation: min/max/avg of sensor readings per second
- Exceptional events (FAULT, EMERGENCY) bypass aggregation → sent immediately as raw event

Adaptive telemetry:
- Normal operation: 1-second heartbeat
- Anomaly detected locally (threshold breach): switch to 100ms for 60s
  → Cloud gets burst of detail when something interesting happens
- After anomaly clears: return to 1-second
- Reduces average volume while providing detail when needed
```

---

### 7.4 Fault Detection: Rule-Based vs ML vs Hybrid

**Problem:** Detect elevator faults before they cause downtime. Two signal types: deterministic (threshold breach) and predictive (pattern leading to future failure).

| Approach | Detects Known Faults | Detects Emerging Faults | False Positive Rate | Latency |
|----------|---------------------|------------------------|---------------------|---------|
| **Rules only** | Yes (all coded ones) | No | Low (tuned rules) | <100ms |
| **ML only** | Usually | Yes | Medium (needs tuning) | 200ms–2s |
| **Hybrid (rules + ML, current)** | Yes (rules catch fast) | Yes (ML catches early) | Low | <2s |

**Decision: Rule-based for immediate faults, ML for predictive maintenance**
```
Why rules for immediate faults?
- Door stuck open: deterministic, instant detection needed (safety)
- Motor overcurrent: threshold breach, clear signal, no ambiguity
- Rules execute in <1ms; ML inference at 200ms delays critical alerts
- Safety-critical faults must have deterministic detection → certifiable, auditable

Why ML for predictive?
- Bearing wear: 30-day degradation pattern; no single-threshold rule captures it
- Motor current slowly drifting up over months → rules can't detect trend without baseline
- ML features: 30-day rolling statistics → anomaly score 0–1 → "failure likely in 7 days"
- Pre-schedule technician before failure → avoid unplanned downtime (much cheaper)

Why not ML for everything?
- ML false positive rate: ~5% (flags healthy elevator as anomalous)
- CRITICAL fault ML alert at 5% false positive = 5,000 false emergency dispatches/day (1M elevators × 0.5%)
- Technician trust erodes quickly; alerts ignored
- Rule-based CRITICAL alerts at <0.1% false positive → technicians respond

Hybrid decision matrix:
Rule breached (CRITICAL) → immediate alert, no ML wait
ML anomaly score > 0.85 → MEDIUM alert for scheduled maintenance
Both rule breach + ML flag → HIGH alert, priority dispatch

False positive management:
- Rules: tuned with 1-year of labeled fault data per fault type
- ML: probability calibrated; alert only at >0.85 threshold (high precision)
- Feedback loop: technician closes fault with "false positive" label → retrains model quarterly
```

---

### 7.5 OTA Update Delivery: MQTT Push vs S3 Pull vs Peer-to-Peer

**Problem:** Rolling out firmware update to 1M elevators. Large binary (50 MB). How do we deliver without overloading infrastructure?

| Approach | Delivery Speed | Infrastructure Load | Failure Isolation |
|----------|---------------|---------------------|-------------------|
| **MQTT push (server → device)** | Fast (server controls) | High (server pushes to 1M simultaneously) | Poor (server becomes bottleneck) |
| **S3 pull (device downloads on schedule, current)** | Controllable (rate-limited) | Low (S3 scales horizontally) | Good (independent downloads) |
| **Peer-to-peer (BitTorrent-style)** | Fast at scale | Very low (devices share) | Excellent | Complex |

**Decision: S3 pull with staged canary rollout and command via MQTT**
```
Why not MQTT push for binary?
- MQTT designed for small control messages (<256 KB typical)
- 50 MB firmware × 1M elevators simultaneously = 50 PB of outbound data in one window
- Broker overloaded; connections time out; partial downloads corrupt binary
- MQTT broker: not built as CDN

S3 pull chosen:
1. MQTT command: "new firmware available at s3://firmware/v2.1.0/edge-gateway.bin"
   (small message, fast delivery)
2. Edge gateway downloads from S3 pre-signed URL (scheduled during off-peak hours)
3. SHA-256 checksum verified → apply; rollback binary kept (dual partition)
4. Status reported back via MQTT: "build_id applied" or "failed: checksum mismatch"

Staged rollout prevents simultaneous download:
- Stage 1 (0.1%): 1K gateways download → validate → 24h soak period
- Stage 2 (1%): 10K gateways
- Stage 3 (10% → 100%): rate-limited by 10K devices/hour window

Rate limiting:
- S3 can handle 50M requests/day → 1M devices downloading once = fine
- But spread across 48h window → avoids S3 hot-spot on same prefix

Safety:
- Elevator-level: update applied to elevators one at a time (not all simultaneously)
  → Building never loses all elevators at once during OTA
- During update: elevator taken to ground floor → doors open → OTA applied → restart
  → Passengers cleared before each individual elevator update
```

---

### 7.6 Telemetry Storage: InfluxDB vs TimescaleDB vs ClickHouse for Time-Series

**Problem:** 1M events/sec ingestion. Two query patterns: (1) "show last 24h for elevator X" and (2) "fleet-wide motor current anomaly dashboard."

| Database | Write Throughput | Query Type | Storage Efficiency | Operational Cost |
|----------|-----------------|-----------|-------------------|-----------------|
| **InfluxDB (current, hot)** | 1M+ points/sec | Point queries by tag | High (TSM compression) | Medium |
| **TimescaleDB** | ~500K rows/sec | SQL + time extensions | Medium | Low (PostgreSQL-based) |
| **ClickHouse (analytics, current)** | 1M+ rows/sec | Aggregation, OLAP | Very high | Medium |
| **Apache Druid** | Very high | Low-latency OLAP | High | High |

**Decision: InfluxDB for hot telemetry (30 days), ClickHouse for analytics, S3 Parquet for cold**
```
Why two stores?
Query pattern divergence:
  Pattern 1: "Show motor_current for elevator_id=X over last 6 hours"
    → Tag-based time-series lookup; InfluxDB excels (indexed by tag, time)
    → InfluxDB query: < 100ms

  Pattern 2: "What % of elevators in EU have vibration_rms > 50 in last 7 days?"
    → Fleet-wide aggregation across millions of rows
    → InfluxDB: full table scan; slow (~10s for 1M elevators)
    → ClickHouse: columnar scan, SIMD vectorized; ~500ms

Storing everything in ClickHouse:
- ClickHouse write throughput: 1M rows/sec → feasible
- But: ClickHouse not optimized for point lookups by tag (full scan per elevator)
- Tag cardinality: 1M elevator_ids → ClickHouse index bloat

Tiered approach:
  Hot (< 30 days): InfluxDB
  → Full resolution, fast tag lookup for dashboard drill-down
  → 30-day retention policy (TTL auto-deletes old data)

  Analytics (any range): ClickHouse
  → Populated by Kafka consumer (parallel to InfluxDB write)
  → Pre-aggregated: floor_bucket (1-min), daily rollups
  → Fleet-wide queries: vibration trends, fault rate by model, SLA uptime

  Cold (> 30 days): S3 Parquet
  → Query via Athena for ad-hoc historical analysis (e.g., "last 3 years for building X")
  → Cost: ~$23/TB/month vs InfluxDB/ClickHouse ~$100/TB/month

Trade-off: Dual write (Kafka → two consumers) adds operational complexity.
Justified by: 100× query speed difference between InfluxDB and ClickHouse
for respective query patterns.
```

---

### 7.7 LLD: Strategy Pattern vs Hardcoded Algorithm vs Configuration-Driven

**Problem (LLD):** Dispatching algorithm varies per building type (office vs hospital vs hotel). How do we support different strategies without code changes?

| Approach | Flexibility | Complexity | Testability |
|----------|------------|------------|------------|
| **Hardcoded LOOK** | None (change = redeploy) | Low | High |
| **Strategy pattern (current)** | High (swap at runtime) | Medium | High (each strategy testable independently) |
| **Configuration-driven rules** | Very high (no code change) | High (DSL needed) | Medium |

**Decision: Strategy pattern with runtime-injectable strategies**
```python
# Strategy pattern enables:
# 1. Unit test each algorithm independently
# 2. Swap algorithm without touching ElevatorController
# 3. A/B test algorithms in production buildings

# Example: hospital building
controller = ElevatorController(
    elevators=hospital_elevators,
    strategy=PriorityStrategy(priority_floors={1: "EMERGENCY", 3: "ICU"})
)

# Example: office building with zones
controller = ElevatorController(
    elevators=office_elevators,
    strategy=ZoneStrategy(zones={"A": (1, 20), "B": (21, 40)})
)
```

Why not configuration-driven (DSL)?
- "If current_floor > 20 AND direction == UP AND pending_calls_up > 3: weight = 0.8"
- Powerful but: DSL interpreter adds complexity; debugging is harder
- Safety-critical code with runtime-parsed DSL: risk of misconfiguration → unsafe behavior
- For elevator dispatch (safety-adjacent), prefer explicit, auditable code over flexible DSL

Trade-off: Strategy pattern requires code change to add new algorithm.
Configuration-driven would allow field teams to tune behavior without deploys.
Current decision: accept code-change constraint; gain testability and auditability.
```

---

### 7.8 Consistency Model Across the System

**Deliberate consistency decisions per component:**

| Component | Consistency | Rationale |
|-----------|------------|-----------|
| Building controller dispatch (local) | **Immediate** (in-process) | Safety-critical; no network latency acceptable |
| Elevator motor command | **Synchronous** (CAN bus) | Physical actuation; must confirm receipt |
| Telemetry → InfluxDB | **Eventual** (1s batch) | Monitoring; 1s lag acceptable |
| Fault alert → PagerDuty | **At-least-once** (Kafka) | Missing alert = missed fault = SLA breach |
| OTA command → edge gateway | **At-least-once** (MQTT QoS 1) | Missed update = firmware drift risk |
| Predictive maintenance score | **Eventual** (nightly batch) | 24h lag acceptable; scheduled maintenance |
| Fleet dashboard (WebSocket) | **Eventual** (Kafka push, ~1–2s) | Operations team; 2s lag acceptable |
| Fault record → PostgreSQL | **Strongly consistent** | Audit trail; SLA calculation requires accuracy |
| SLA reporting (ClickHouse) | **Eventual** (aggregated hourly) | SLA reports are retrospective; hourly lag fine |

**Key interview insight:** This system has two radically different consistency domains:
1. **Safety domain (building-level, sub-100ms):** Strong consistency, local-only, hardware-backed. The cloud is irrelevant to safety. An elevator must respond to an emergency stop in milliseconds, not after a Kafka round-trip.
2. **Monitoring domain (cloud-level, seconds-to-minutes):** Eventual consistency throughout. Fault detection at 2-minute SLA, predictive maintenance at 24h — these tolerances make eventual consistency cheap and correct.

---

## 8. Follow-Up Topics

### High-Rise Zone Optimization
- Divide 60-floor building into 3 zones: low (1–20), mid (21–40), high (41–60)
- Each zone served by dedicated elevators; reduces average wait time by ~40%
- Sky lobby pattern (Burj Khalifa): express elevators to sky lobbies, then local elevators
- Zone boundaries dynamically adjusted by time-of-day (morning rush → bias low floors)

### Energy Optimization
- Regenerative drives: elevator going down generates electricity → fed back to grid
- Peak shaving: avoid scheduling all elevators simultaneously during peak demand
- Sleep mode: if no calls for 5 min → elevator parks at designated floor (e.g., lobby), motor standby

### Earthquake / Emergency Mode
- Seismic sensor triggers → all elevators immediately return to ground floor
- Fire mode: firefighter control panel overrides scheduling; elevators held at lobby
- Power outage: UPS runs elevators to nearest floor → open doors → shutdown safely

### Fairness / Wait Time SLA
- Monitor: if any floor request unserviced > 90 seconds → elevate priority (override LOOK)
- Starvation prevention: keep timestamp of each pending call; oldest call gets guaranteed next pickup
- Report: per-floor wait time percentiles (p50, p99) per hour → capacity planning signal

### Multi-Tenant SLA (Fleet Management)
- Premium customers: SLA = 99.9% uptime; fault response < 1h
- Standard: SLA = 99.5%; response < 4h
- SLA tracking automated: InfluxDB query → breach detected → auto-credit calculation
- Customer portal: self-serve SLA dashboard, historical uptime, maintenance history

### Simulation & Testing
- Digital twin: simulate building traffic patterns → test scheduling algorithms before deployment
- Chaos testing: inject fault events into simulated fleet → validate alert/dispatch pipeline
- Load test: replay peak-day telemetry at 10× speed → validate Kafka + Flink at 10M events/sec

---

## Summary

### LLD (Building-Level OOP)

| Pattern | Usage |
|---|---|
| **State Machine** | Elevator states (IDLE, MOVING, DOOR_OPEN, MAINTENANCE, EMERGENCY) |
| **Strategy** | Pluggable dispatch algorithms (Nearest, Zone, LOOK) |
| **Observer / Event Bus** | Decouple elevator events from consumers (display, alarm, telemetry) |
| **Command** | Remote cabin button presses → ElevatorController |
| **LOOK Algorithm** | Scheduling: continue direction, reverse when no more ahead |

### HLD (Fleet Management)

| Component | Technology |
|---|---|
| Edge Gateway | Embedded Linux (Raspberry Pi / custom MCU); MQTT; local SQLite buffer |
| IoT Ingestion | AWS IoT Core / Azure IoT Hub |
| Event Bus | Apache Kafka (3 topics, 1M events/sec) |
| Stream Processing | Apache Flink (fault detection, aggregation) |
| Time-Series Storage | InfluxDB (30-day hot) → S3 Parquet (cold) |
| Analytics OLAP | ClickHouse (usage patterns, SLA reports) |
| ML Inference | TensorFlow Serving (predictive maintenance) |
| Alert Dispatch | PagerDuty + custom technician mobile app |
| Operations Dashboard | React + WebSocket + Go backend |
| OTA Updates | S3 + MQTT + staged canary rollout |
| Fleet DB | PostgreSQL (buildings, elevators, faults, visits) |
