# Event Storming Workshop Report

**Domain:** MeetSync — Enterprise Meeting Room Reservation
**Scope:** Big Picture
**Date:** 2026-03-06
**Input:** `/event-storming big-picture: the entire MeetSync platform based on meetsync-refined-spec.md`
**Source:** Greenfield project — domain grounded in `meetsync-refined-spec.md`

---

## 1. Color Legend

| Symbol | Concept | Description |
|--------|---------|-------------|
| 🟧 | Domain Event | Something that happened (past tense) |
| 🟦 | Command | Intention to act (imperative) |
| 🟨 | Actor | Who/what triggers the command |
| 🟪 | Policy | Automated reaction to an event |
| 🟩 | Read Model | Information needed for a decision |
| 🟥 | Hot Spot | Open question or conflict |
| 📋 | Aggregate | Consistency boundary |
| 🔶 | External System | Outside dependency |
| ⏰ | Temporal Trigger | Time-based trigger |

---

## 2. Domain Events — Chaotic Discovery

All events extracted from the four bounded contexts, unordered.

### Reservation Management
```
🟧 ReservationRequested
🟧 ReservationConfirmed
🟧 ReservationRejected          ← conflict / capacity / hours / horizon / notice / role / duration
🟧 ApprovalRequested
🟧 ReservationApprovedByAdmin
🟧 ReservationRejectedByAdmin
🟧 AlternativeTimeslotProposed
🟧 AlternativeTimeslotAccepted
🟧 AlternativeTimeslotDeclined
🟧 ApprovalWindowExpired
🟧 ReservationCancelled         ← by organizer / by system / by suspension / by decommission
🟧 ReservationWithdrawn         ← organizer withdraws from PENDING_APPROVAL
🟧 ReservationStarted           ← start time reached
🟧 CheckInRecorded
🟧 NoShowDetected
🟧 ReservationReleased          ← auto-released after no-show threshold
🟧 RoomReleasedToMarket         ← after grace period, slot becomes bookable again
🟧 ReservationCompleted
🟧 NoShowPenaltyWarningIssued
🟧 NoShowPenaltySuspensionTriggered
🟧 NoShowPenaltyReportFiled
🟧 WaitlistJoined
🟧 WaitlistOfferCreated         ← slot freed, pre-filled reservation created
🟧 WaitlistOfferAccepted
🟧 WaitlistOfferDeclined
🟧 WaitlistOfferExpired         ← acceptance window elapsed, no response
🟧 WaitlistExpired              ← timeslot start time reached
🟧 RecurringSeriesCreated
🟧 RecurringOccurrenceScheduled
🟧 RecurringOccurrenceRejected  ← conflict on that specific occurrence
🟧 RecurringOccurrenceModified  ← single occurrence change
🟧 RecurringSeriesModifiedFromOccurrenceN
🟧 RecurringSeriesCancelled
```

### Room Management
```
🟧 RoomCreated
🟧 RoomBlocked
🟧 RoomUnblocked
🟧 RoomDecommissioned
🟧 RoomEquipmentAdded
🟧 RoomEquipmentRemoved
🟧 RoomApprovalRequirementEnabled
🟧 RoomApprovalRequirementDisabled
🟧 RoomRoleRestrictionSet
🟧 RoomDurationLimitsSet
🟧 RoomMinParticipantsSet
```

### Tenant & User Management
```
🟧 TenantCreated
🟧 TenantPolicyUpdated          ← horizon / notice / no-show / approval triggers
🟧 UserCreated
🟧 UserRoleAssigned
🟧 UserSuspended
🟧 UserReinstated
```

### Notifications
```
🟧 ConfirmationNotificationSent
🟧 ReminderNotificationSent
🟧 CancellationNotificationSent
🟧 WaitlistAvailabilityNotificationSent
🟧 RoomDecommissionedNotificationSent
🟧 EquipmentRemovedWarningNotificationSent
🟧 NotificationFailed           ← best-effort, logged, never blocks domain state
```

---

## 3. Event Storming Flows

### Flow 1 — Simple Room Booking (Happy Path, Iteration 1 Core)

```
🟩 Room Availability View
(free slots, capacity, equipment)
        │
        ▼
🟨 Employee ──► 🟦 BookRoom ──► 📋 Reservation ──► 🟧 ReservationConfirmed
                    │                  │
                    │ checks:          │ invariants:
                    │ • no conflict    │ • [start,end) half-open interval
                    │ • capacity ok    │ • start ≥ opening time
                    │ • within hours   │ • end ≤ closing time
                    │ • within horizon │ • start ≤ now + 90 days
                    │ • notice ok      │ • start ≥ now + 30 min
                    │                  │
                    └──── any fail ───► 🟧 ReservationRejected
                                            (reason in payload)

🟧 ReservationConfirmed
    └── 🟪 Policy: "When confirmed, send confirmation email"
        └── 🟦 SendNotification ──► 🔶 Email Service ──► 🟧 ConfirmationNotificationSent
```

---

### Flow 2 — Room Booking with Approval

```
🟨 Employee ──► 🟦 BookRoom ──► 📋 Reservation ──► 🟧 ReservationRequested
                                                          │
                                    🟩 Room Config         │ room requires approval?
                                    (requires_approval)   ▼
                                                    🟧 ApprovalRequested
                                                          │
                        🟪 Policy: "When approval requested, notify Room Admin"
                                                          │
                                              🟦 SendNotification
                                                          │
                                              🔶 Email Service
                                                          │
                                                          ▼
                                              🟨 Room Admin
                                              🟩 Reservation Details
                                                    │
                    ┌───────────────────────────────┼──────────────────────────┐
                    │                               │                          │
                    ▼                               ▼                          ▼
        🟦 ApproveReservation        🟦 RejectReservation      🟦 ProposeAlternativeTimeslot
                    │                               │                          │
                    ▼                               ▼                          ▼
        🟧 ReservationApprovedByAdmin   🟧 ReservationRejectedByAdmin  🟧 AlternativeTimeslotProposed
                    │                                                          │
        🟧 ReservationConfirmed                                    🟨 Employee notified
                                                                              │
                                                                  🟦 AcceptAlternative or Decline
                                                                              │
                                                            🟧 AlternativeTimeslotAccepted / Declined

⏰ After 24h (tenant-configurable)
    └── 🟦 ExpireApprovalWindow ──► 📋 Reservation ──► 🟧 ApprovalWindowExpired
                                                              │
                                                        🟧 ReservationCancelled
                                                              │
                                        🟪 Policy: "When cancelled, notify organizer"
                                                              │
                                                  🔶 Email Service ──► 🟧 CancellationNotificationSent
```

---

### Flow 3 — Check-in and No-show

```
🟧 ReservationConfirmed
        │
⏰ At start time
        │
        ▼
🟦 StartReservation ──► 📋 Reservation ──► 🟧 ReservationStarted
        │
        │         🟩 Check-in window (opens 15min before, closes 15min after start)
        │
        ├─── 🟨 Employee checks in ───► 🟦 RecordCheckIn ──► 🟧 CheckInRecorded
        │
        └─── ⏰ No check-in by threshold (15min after start)
                    │
                    ▼
        🟦 DetectNoShow ──► 📋 Reservation ──► 🟧 NoShowDetected
                                                      │
                                               🟧 ReservationReleased
                                                      │
                    ┌─────────────────────────────────┤
                    │                                 │
        🟪 Policy: "When no-show, apply penalty"     ⏰ After grace period (15min)
                    │                                 │
        🟦 ApplyNoShowPenalty                🟦 MakeSlotAvailable
                    │                                 │
         📋 User (penalty logic)              🟧 RoomReleasedToMarket
                    │
        ┌──────────┬┴──────────────────┐
        │          │                   │
🟧 Warning   🟧 Suspension     🟧 ReportFiled
  Issued       Triggered
                │
        🟧 UserSuspended (→ triggers Flow 7)
```

---

### Flow 4 — Waitlist

```
🟨 Employee ──► 🟩 Full Timeslot View ──► 🟦 JoinWaitlist ──► 📋 Waitlist ──► 🟧 WaitlistJoined
                (room is fully booked)

🟧 ReservationCancelled / ReservationReleased
        │
        └── 🟪 Policy: "When slot freed, notify first on waitlist"
                    │
        🟦 CreateWaitlistOffer ──► 📋 Reservation ──► 🟧 WaitlistOfferCreated
                                    (PENDING_WAITLIST_CONFIRM)
                                                              │
                                            🔶 Email Service ──► 🟧 WaitlistAvailabilityNotificationSent
                                                              │
                                                   🟨 Waitlisted Employee
                                                              │
                                            ┌─────────────────┴──────────────────┐
                                            │                                    │
                                🟦 AcceptWaitlistOffer              🟦 DeclineWaitlistOffer
                                            │                                    │
                                🟧 WaitlistOfferAccepted            🟧 WaitlistOfferDeclined
                                            │                                    │
                                (→ normal booking flow)              notify next on waitlist

⏰ After 30min (no response)
        └── 🟦 ExpireWaitlistOffer ──► 🟧 WaitlistOfferExpired
                    │
                    └── notify next on waitlist (cascade)

⏰ At timeslot start time
        └── 🟦 ExpireWaitlist ──► 📋 Waitlist ──► 🟧 WaitlistExpired
```

---

### Flow 5 — Recurring Series Booking

```
🟨 Employee ──► 🟩 Room Availability (multiple dates)
                    │
        🟦 BookRecurringSeries ──► 📋 RecurringSeries ──► 🟧 RecurringSeriesCreated
                    │
                    ├── For each occurrence:
                    │       🟦 ScheduleOccurrence ──► 📋 Reservation ──► 🟧 RecurringOccurrenceScheduled
                    │                                                  OR 🟧 RecurringOccurrenceRejected (conflict)
                    │
                    └── Partial success: series created, rejected occurrences reported

🟧 RecurringOccurrenceScheduled (×N)
        │
        [Each occurrence follows Flow 1 / Flow 2 / Flow 3 independently]

🟨 Employee ──► 🟦 ModifyOccurrence (single) ──► 📋 Reservation ──► 🟧 RecurringOccurrenceModified

🟨 Employee ──► 🟦 ModifySeriesFromOccurrenceN ──► 📋 RecurringSeries
                    │ (detach 1..N-1, apply to N..end)
                    └──► 🟧 RecurringSeriesModifiedFromOccurrenceN

🟨 Employee ──► 🟦 CancelSeries ──► 📋 RecurringSeries
                    │ (cancel all future, not IN_PROGRESS, not COMPLETED)
                    └──► 🟧 RecurringSeriesCancelled
                              │
                    (each future occurrence → 🟧 ReservationCancelled)
```

---

### Flow 6 — Room Decommissioning

```
🟨 Facilities Admin ──► 🟦 DecommissionRoom ──► 📋 Room ──► 🟧 RoomDecommissioned
                                                                    │
                    ╔═══════════════════════════════════════════════╝
                    ║  PIVOTAL EVENT — crosses into BC1
                    ╚══════════════════════════════════════════════════╗
                                                                       ▼
                    🟪 Policy: "When room decommissioned,              BC1
                                cancel all future reservations"         │
                    └── 🟦 CancelReservation (for each future) ──► 📋 Reservation
                                                                       │
                                                              🟧 ReservationCancelled
                                                                       │
                    🟪 Policy: "When reservation cancelled by decommission, notify organizer"
                                                                       │
                                                          🔶 Email Service
                                                                       │
                                                🟧 RoomDecommissionedNotificationSent
```

---

### Flow 7 — User Suspension

```
🟨 Tenant Admin ──► 🟩 User Profile + Policy
                    │
        🟦 SuspendUser ──► 📋 User ──► 🟧 UserSuspended
                                              │
                    ╔═════════════════════════╝
                    ║  PIVOTAL EVENT — crosses into BC1
                    ╚══════════════════════════════════╗
                                                        ▼
                    🟪 Policy: "When user suspended,    BC1
                               cancel organizer's       │
                               future reservations"     │
                    └── For each future reservation where user = organizer:
                            🟦 CancelReservation ──► 📋 Reservation ──► 🟧 ReservationCancelled
                                │
                                └── 🟪 Policy: "When cancelled by suspension,
                                               notify participants"
                                        └── 🔶 Email Service ──► 🟧 CancellationNotificationSent

                    Note: IN_PROGRESS reservations are NOT cancelled.
                    Note: Reservations where user is participant only are NOT cancelled.
                          The organizer of those receives a participant-suspended warning.
```

---

### Flow 8 — Equipment Removal Warning

```
🟨 Facilities Admin ──► 🟦 RemoveEquipment ──► 📋 Room ──► 🟧 RoomEquipmentRemoved
                                                                    │
                    🟪 Policy: "When equipment removed,
                               warn organizers of affected reservations"
                                                                    │
                    🟩 Reservations requiring that equipment
                                                                    │
                    └── 🔶 Email Service ──► 🟧 EquipmentRemovedWarningNotificationSent
                              (reservation remains CONFIRMED — equipment is informational)
```

---

### Flow 9 — Policy Change (Lazy Re-evaluation)

```
🟨 Tenant Admin ──► 🟦 UpdateBookingPolicy ──► 📋 TenantPolicy ──► 🟧 TenantPolicyUpdated
                                                        │
                                    No immediate effect on CONFIRMED reservations (shielded).
                                    PENDING / PENDING_APPROVAL: re-evaluated lazily at next transition.
                                        │
                                        └── 🟥 Hot Spot #5: See below
```

---

## 4. Aggregate Map

### 📋 Reservation

```
📋 Aggregate: Reservation
├── Root Entity: Reservation (id, roomId, tenantId, organizerId, timeslot, status, participantIds)
├── Value Objects: Timeslot([start,end)), ReservationStatus
├── Child Entities: — (participants referenced by ID only)
├── Commands Handled:
│   ├── 🟦 BookRoom            → 🟧 ReservationConfirmed | 🟧 ReservationRejected
│   ├── 🟦 RequestApproval     → 🟧 ApprovalRequested
│   ├── 🟦 ApproveReservation  → 🟧 ReservationApprovedByAdmin → 🟧 ReservationConfirmed
│   ├── 🟦 RejectReservation   → 🟧 ReservationRejectedByAdmin
│   ├── 🟦 ProposeAlternative  → 🟧 AlternativeTimeslotProposed
│   ├── 🟦 AcceptAlternative   → 🟧 AlternativeTimeslotAccepted
│   ├── 🟦 WithdrawReservation → 🟧 ReservationWithdrawn
│   ├── 🟦 CancelReservation   → 🟧 ReservationCancelled
│   ├── 🟦 ExpireApprovalWindow→ 🟧 ApprovalWindowExpired → 🟧 ReservationCancelled
│   ├── 🟦 StartReservation    → 🟧 ReservationStarted
│   ├── 🟦 RecordCheckIn       → 🟧 CheckInRecorded
│   ├── 🟦 DetectNoShow        → 🟧 NoShowDetected → 🟧 ReservationReleased
│   └── 🟦 CompleteReservation → 🟧 ReservationCompleted
├── Invariants:
│   ├── Timeslot is half-open [start, end): no overlap with existing CONFIRMED/PENDING reservations
│   ├── start ≥ building opening time (building timezone)
│   ├── end ≤ building closing time (building timezone)
│   ├── start ≤ now + booking horizon (default 90 days)
│   ├── start ≥ now + minimum advance notice (default 30 min)
│   ├── participantCount + 1 ≤ room.capacity
│   ├── REJECTED cannot be reactivated
│   ├── IN_PROGRESS cannot be cancelled
│   └── COMPLETED cannot be modified
├── Read Models Required:
│   ├── 🟩 Room Availability (existing reservations for same room+timeslot)
│   ├── 🟩 Room Config (capacity, approval requirement, role restrictions, duration limits)
│   ├── 🟩 Tenant Policy (horizon, notice, approval triggers)
│   └── 🟩 User Role (for role-restricted rooms)
└── Collaborators (by ID):
    ├── roomId → Room (BC2)
    ├── tenantId → Tenant (BC3)
    └── organizerId → User (BC3)
```

### 📋 RecurringSeries

```
📋 Aggregate: RecurringSeries
├── Root Entity: RecurringSeries (id, roomId, tenantId, organizerId, rrule, occurrenceIds[])
├── Value Objects: RecurrenceRule (RRULE: freq + interval + byday + until/count)
├── Commands Handled:
│   ├── 🟦 BookRecurringSeries    → 🟧 RecurringSeriesCreated + N × 🟧 RecurringOccurrenceScheduled
│   ├── 🟦 ModifyOccurrence       → 🟧 RecurringOccurrenceModified (single, detached)
│   ├── 🟦 ModifySeriesFromN      → 🟧 RecurringSeriesModifiedFromOccurrenceN
│   └── 🟦 CancelSeries           → 🟧 RecurringSeriesCancelled
├── Invariants:
│   ├── Conflicts in individual occurrences do not block the series
│   ├── Modifying a single occurrence does not affect siblings
│   └── Cancelling series only cancels future, non-commenced occurrences
└── Collaborators (by ID):
    └── occurrenceIds[] → Reservation (BC1)
```

### 📋 Waitlist

```
📋 Aggregate: Waitlist
├── Root Entity: Waitlist (id, roomId, timeslot, entries: WaitlistEntry[])
├── Value Objects: WaitlistEntry (userId, joinedAt, status), Timeslot
├── Commands Handled:
│   ├── 🟦 JoinWaitlist         → 🟧 WaitlistJoined
│   ├── 🟦 CreateWaitlistOffer  → 🟧 WaitlistOfferCreated (pre-filled Reservation)
│   ├── 🟦 AcceptWaitlistOffer  → 🟧 WaitlistOfferAccepted
│   ├── 🟦 DeclineWaitlistOffer → 🟧 WaitlistOfferDeclined → notify next entry
│   ├── 🟦 ExpireWaitlistOffer  → 🟧 WaitlistOfferExpired → notify next entry
│   └── 🟦 ExpireWaitlist       → 🟧 WaitlistExpired
├── Invariants:
│   ├── Order is FIFO by joinedAt
│   └── Waitlist expires automatically at timeslot start time
└── Collaborators:
    └── roomId, timeslot → Reservation (BC1)
```

### 📋 Room

```
📋 Aggregate: Room
├── Root Entity: Room (id, tenantId, name, capacity, floor, buildingId, status, equipment[], rules)
├── Value Objects: RoomStatus (ACTIVE/BLOCKED/DECOMMISSIONED), Equipment, BookingRules
├── Commands Handled:
│   ├── 🟦 CreateRoom              → 🟧 RoomCreated
│   ├── 🟦 BlockRoom               → 🟧 RoomBlocked
│   ├── 🟦 UnblockRoom             → 🟧 RoomUnblocked
│   ├── 🟦 DecommissionRoom        → 🟧 RoomDecommissioned
│   ├── 🟦 AddEquipment            → 🟧 RoomEquipmentAdded
│   ├── 🟦 RemoveEquipment         → 🟧 RoomEquipmentRemoved
│   ├── 🟦 SetApprovalRequirement  → 🟧 RoomApprovalRequirementEnabled/Disabled
│   ├── 🟦 SetRoleRestriction      → 🟧 RoomRoleRestrictionSet
│   └── 🟦 SetDurationLimits       → 🟧 RoomDurationLimitsSet
├── Invariants:
│   ├── BLOCKED: no new reservations accepted
│   ├── DECOMMISSIONED: no new reservations; all future reservations cascade-cancelled
│   └── Equipment removal: existing CONFIRMED reservations stay CONFIRMED (equipment is informational)
└── Collaborators:
    └── tenantId → Tenant (BC3)
```

### 📋 Tenant

```
📋 Aggregate: Tenant
├── Root Entity: Tenant (id, name, buildings[], policy: TenantPolicy)
├── Value Objects: TenantPolicy (horizon, minNotice, approvalTriggers, approvalWindowDuration,
│                                noShowPolicy, waitlistAcceptanceWindow, gracePeriod)
├── Commands Handled:
│   ├── 🟦 CreateTenant        → 🟧 TenantCreated
│   └── 🟦 UpdateBookingPolicy → 🟧 TenantPolicyUpdated
├── Invariants:
│   ├── Complete isolation — no sharing of rooms/users/reservations across tenants
│   └── Policy changes shield CONFIRMED reservations; lazy re-evaluation for PENDING/PENDING_APPROVAL
└── Collaborators: none (root context)
```

### 📋 User

```
📋 Aggregate: User
├── Root Entity: User (id, tenantId, email, name, role, status, noShowRecord[])
├── Value Objects: UserRole (EMPLOYEE/ROOM_ADMIN/FACILITIES_ADMIN/TENANT_ADMIN), UserStatus
├── Commands Handled:
│   ├── 🟦 CreateUser          → 🟧 UserCreated
│   ├── 🟦 AssignRole          → 🟧 UserRoleAssigned
│   ├── 🟦 SuspendUser         → 🟧 UserSuspended
│   └── 🟦 ReinstatUser        → 🟧 UserReinstated
├── Invariants:
│   ├── SUSPENDED user cannot create new reservations
│   ├── Suspension cancels organizer's future reservations (not IN_PROGRESS)
│   └── Suspension does not affect reservations where user is participant only
└── Collaborators:
    └── tenantId → Tenant (BC3)
```

---

## 5. Bounded Context Map

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                    BC3 — Tenant & User Management                            │
│                         [SUPPORTING DOMAIN]                                  │
│                                                                              │
│  📋 Tenant  📋 User                                                          │
│                                                                              │
│  🟧 TenantCreated    🟧 TenantPolicyUpdated                                  │
│  🟧 UserSuspended    🟧 UserReinstated   🟧 UserRoleAssigned                 │
└────────────────────────────┬─────────────────────────────────────────────────┘
                             │  [U] Upstream
                             │  🟧 UserSuspended ════════════════════╗
                             │  🟧 TenantPolicyUpdated ══════════════╗║
                             ▼  [ACL]                               ║║
┌──────────────────────────────────────────────────────────────────────────────┐
│                    BC2 — Room Management                                     │◄─────────────────────┐
│                         [SUPPORTING DOMAIN]                                  │                      │
│                                                                              │                      │
│  📋 Room                                                                     │  [U] Upstream        │
│                                                                              │                      │
│  🟧 RoomCreated     🟧 RoomBlocked      🟧 RoomDecommissioned ═══════╗       │                      │
│  🟧 RoomEquipmentRemoved ═══════════════════════════════════════╗   ║       │  🟨 FacilitiesAdmin  │
└──────────────────────────────────────────────────────────┬──────║───╬───────┘  🟨 TenantAdmin      │
                                                           │      ║   ║                               │
                             [D] Downstream [ACL]          │      ║   ║                               │
                             queries room availability     │      ║   ║   PIVOTAL EVENTS              │
┌──────────────────────────────────────────────────────────▼──────╬───╬───────────────────────────────┐
│                    BC1 — Reservation Management                  ║   ║                               │
│                         [CORE DOMAIN] ★                         ║   ║                               │
│                                                                  ║   ║                               │
│  📋 Reservation  📋 RecurringSeries  📋 Waitlist                ║   ║                               │
│                                                                  ║   ║                               │
│  🟧 ReservationConfirmed  🟧 ReservationCancelled ══════════════╬═══╬═══════════════╗               │
│  🟧 ReservationReleased   🟧 NoShowDetected ════════════════════╬═══╬═══╗           ║               │
│  🟧 CheckInRecorded       🟧 WaitlistOfferCreated               ║   ║   ║           ║               │
└─────────────────────────────────────────────────────────────────╬───╬───╬───────────╬───────────────┘
                                                                   ║   ║   ║           ║
                             [D] Downstream Conformist             ║   ║   ║           ║   PIVOTAL EVENTS
┌──────────────────────────────────────────────────────────────────╬───╬───╬───────────╬───────────────┐
│                    BC4 — Notifications                           ║   ║   ║           ║               │
│                         [GENERIC SUBDOMAIN]                      ║   ║   ║           ║               │
│                                                                   ▼   ▼   ▼           ▼               │
│  Reacts to all domain events ─────────────────────────────────────────────────────────────────────── │
│                                                              🔶 Email Service                         │
│                                                              🔶 Push Notifications                    │
│                                                              🔶 Slack (optional)                      │
│                                                                                                       │
│  🟧 ConfirmationNotificationSent     🟧 CancellationNotificationSent                                 │
│  🟧 ReminderNotificationSent         🟧 WaitlistAvailabilityNotificationSent                         │
│  🟧 EquipmentRemovedWarningNotificationSent   🟧 NotificationFailed                                  │
└───────────────────────────────────────────────────────────────────────────────────────────────────────┘

Legend:
  ★  Core Domain — competitive advantage, highest investment
  [U] Upstream    — provides data / events
  [D] Downstream  — consumes data / events
  [ACL] Anti-Corruption Layer — BC1 translates upstream models into its own language
  ════► Pivotal Event crossing a context boundary
```

### Context Profiles

| Context | Classification | Aggregates | Autonomy |
|---|---|---|---|
| BC1 Reservation Management | **Core Domain** | Reservation, RecurringSeries, Waitlist | High — can evolve independently once interfaces are stable |
| BC2 Room Management | Supporting Domain | Room | High — room rules can change without affecting reservation logic |
| BC3 Tenant & User Management | Supporting Domain | Tenant, User | High — user lifecycle is independent |
| BC4 Notifications | Generic Subdomain | — (stateless reactor) | Total — can be replaced by any notification service |

---

## 6. Policy Chains

### Chain A — Booking Confirmation → Email
```
🟧 ReservationConfirmed
    └── 🟪 "When confirmed, send confirmation"
        └── 🟦 SendConfirmationNotification → 🔶 Email → 🟧 ConfirmationNotificationSent
```

### Chain B — No-show → Release → Penalty → Possible Suspension → Cascade Cancellation
```
🟧 NoShowDetected
    └── 🟪 "When no-show, release reservation"
        └── 🟧 ReservationReleased
                ├── 🟪 "When released, apply penalty per tenant policy"
                │       └── 🟧 NoShowPenaltySuspensionTriggered
                │               └── 🟧 UserSuspended (BC3)   ◄── PIVOTAL
                │                       └── 🟪 "When suspended, cancel organizer reservations"
                │                               └── 🟧 ReservationCancelled (×N) ← long chain!
                │
                └── ⏰ After grace period (15min)
                        └── 🟧 RoomReleasedToMarket
                                └── 🟪 "When slot freed, notify waitlist"
                                        └── 🟧 WaitlistOfferCreated
```

### Chain C — Decommission → Cascade Cancellation → Notifications
```
🟧 RoomDecommissioned (BC2)  ◄── PIVOTAL
    └── 🟪 "Cancel all future reservations for this room" (BC1)
        └── 🟧 ReservationCancelled (×N)
                └── 🟪 "When cancelled by decommission, notify organizer"
                        └── 🟧 RoomDecommissionedNotificationSent (BC4)
```

### Chain D — Suspension → Cascade Cancellation → Participant Notifications
```
🟧 UserSuspended (BC3)  ◄── PIVOTAL
    └── 🟪 "Cancel organizer's future reservations" (BC1)
        └── 🟧 ReservationCancelled (×N)
                └── 🟪 "When cancelled by suspension, notify participants"
                        └── 🟧 CancellationNotificationSent (BC4)
```

### Chain E — Approval Expiry → Cancellation → Waitlist Notification
```
⏰ Approval window elapsed
    └── 🟧 ApprovalWindowExpired
            └── 🟧 ReservationCancelled
                    └── 🟪 "When slot freed, notify waitlist"
                            └── 🟧 WaitlistOfferCreated
```

---

## 7. Sagas Identified

### Saga 1 — Approval Saga

```
Saga: ApprovalSaga
├── Trigger: 🟧 ApprovalRequested
├── Step 1: Wait for approver decision
│   ├── 🟧 ReservationApprovedByAdmin → 🟧 ReservationConfirmed → Saga complete ✓
│   ├── 🟧 ReservationRejectedByAdmin → Saga complete (rejected) ✗
│   └── 🟧 AlternativeTimeslotProposed → wait for organizer response
│           ├── 🟧 AlternativeTimeslotAccepted → restart booking flow
│           └── 🟧 AlternativeTimeslotDeclined → Saga complete (cancelled) ✗
├── Timeout: 24h (tenant-configurable)
│   └── 🟧 ApprovalWindowExpired → 🟧 ReservationCancelled → Saga complete ✗
└── Compensation: none needed (timeslot was soft-locked, auto-released on expiry)
```

### Saga 2 — Waitlist Saga

```
Saga: WaitlistSaga
├── Trigger: 🟧 ReservationCancelled / 🟧 ReservationReleased (slot freed)
├── Step 1: Notify first waitlisted user (30min window)
│   ├── 🟧 WaitlistOfferAccepted → normal booking flow → Saga complete ✓
│   ├── 🟧 WaitlistOfferDeclined → go to Step 1 with next user
│   └── 🟧 WaitlistOfferExpired  → go to Step 1 with next user
├── Completion: 🟧 ReservationConfirmed OR 🟧 WaitlistExpired (no more entries)
└── Timeout: slot start time → 🟧 WaitlistExpired → Saga complete ✗
```

### Saga 3 — No-show Saga

```
Saga: NoShowSaga
├── Trigger: 🟧 ReservationStarted
├── Step 1: Wait for check-in within window
│   ├── 🟧 CheckInRecorded → Saga complete ✓
│   └── ⏰ No-show threshold passed → 🟧 NoShowDetected
│           ├── 🟧 ReservationReleased
│           ├── Apply penalty (stateless policy)
│           └── ⏰ Grace period → 🟧 RoomReleasedToMarket → trigger Waitlist Saga
└── Compensation: none (release is the compensation)
```

---

## 8. Hot Spots & Open Questions

| # | Hot Spot | Context | Impact |
|---|---|---|---|
| 1 | 🟥 **How does BC1 read room rules at booking time?** Synchronous query to BC2 or denormalized cache? If sync: what if BC2 is down? If cached: stale approval requirements could allow unauthorized bookings. | BC1/BC2 boundary | Consistency vs availability trade-off at the system's most critical path |
| 2 | 🟥 **Who triggers the Waitlist Saga when a slot is released?** The `ReservationCancelled` event must be consumed by a Waitlist listener. Is this within BC1, or does it cross to a coordination layer? Risk of missed events. | BC1 internal | Could result in waitlisted users never being notified |
| 3 | 🟥 **RecurringSeries: eager vs lazy occurrence creation.** For a weekly series over 1 year, 52 Reservation aggregates are created immediately. Potential write burst. Alternative: lazy generation per occurrence. Spec is silent on this. | BC1 / RecurringSeries | Performance and storage concern at scale |
| 4 | 🟥 **No-show penalty causing user suspension flows back into BC1 via UserSuspended.** This creates a circular event chain: BC1 → BC3 → BC1. Who orchestrates this? Risk of infinite loops if not guarded. | BC1/BC3 cross-context | Data consistency and loop prevention |
| 5 | 🟥 **Policy lazy re-evaluation: which transition re-checks?** Spec says "at next state transition." But if a PENDING reservation is for an approver who never acts, it stays PENDING forever. Is there a maximum PENDING duration? | BC1/BC3 | Reservations could block timeslots indefinitely if stuck in PENDING |
| 6 | 🟥 **Alternative timeslot proposal: does it create a new reservation?** When a Room Admin proposes an alternative timeslot, is a new PENDING_APPROVAL reservation created for the alternative? Or is the original mutated? Immutability concern. | BC1 Approval flow | Audit trail and reservation identity |
| 7 | 🟥 **Reminder notification timing (15min before start).** BC4 must schedule a future notification. This implies either a scheduled job polling BC1 events, or BC1 emitting a `ReminderScheduled` event. Architecture not specified. | BC1/BC4 | Implementation choice affects BC4 autonomy |

---

## 9. Recommendations

### Implementation Order (TDD-guided)

```
Phase 1 — Foundation (Iteration 1: MVP)
  BC3: Tenant (hardcoded) + User (identity only, no roles, no suspension)
  BC2: Room (creation, seed data only, no blocking/decommission)
  BC1: Reservation (BookRoom → CONFIRMED, conflict, capacity, hours, horizon, notice, cancel)
  BC4: Notifications (email confirmation + cancellation only)

Phase 2 — Approval & Policy
  BC1: Approval Saga (PENDING_APPROVAL, approval window, approver decision)
  BC3: Roles (Room Admin, Facilities Admin, Tenant Admin)
  BC3: Policy configuration (horizon, notice, approval triggers)
  BC2: Room approval requirement, role restrictions, duration limits

Phase 3 — Lifecycle & Resilience
  BC1: Check-in + No-show Saga
  BC1: No-show penalties
  BC3: User suspension + cascade cancellation
  BC2: Room blocking and decommissioning

Phase 4 — Advanced Features
  BC1: Recurring reservations (RecurringSeries aggregate)
  BC1: Waitlist Saga
  BC4: Reminder scheduling, multi-channel (push, Slack)

Phase 5 — Multi-tenancy & Scale
  BC3: Tenant isolation enforcement
  BC2: Equipment management with warnings
  BC1: Policy change lazy re-evaluation
  Performance: eager vs lazy RecurringSeries occurrence generation
```

### Key Aggregates to Start With (Iteration 1 TDD)

Priority order for TDD implementation:
1. **Timeslot** (Value Object) — half-open interval, conflict detection — pure domain logic, no dependencies
2. **Reservation** (Aggregate) — BookRoom command with all rejection rules
3. **Room** (read-only query for Iteration 1) — availability check
4. **Notification** (side-effect) — email on confirmation and cancellation

### Architectural Notes

- **BC4 (Notifications)** should use `symfony/messenger` (already in `composer.json`) for async event handling. Failures must never propagate back to BC1.
- **ApprovalSaga** and **WaitlistSaga** are candidates for Symfony Messenger's `after()` middleware or a dedicated Process Manager.
- **BC1 → BC2 reads**: for Iteration 1, a direct query is acceptable. From Phase 2, consider an ACL with a dedicated `RoomBookingPolicy` read model in BC1 (denormalized from BC2 events).
- **RecurringSeries**: recommend lazy occurrence generation (generate occurrences on-demand per scheduling window) to avoid write bursts. Resolve Hot Spot #3 before Phase 4.
- **Cross-context events** (UserSuspended, RoomDecommissioned, TenantPolicyUpdated) should use Symfony Messenger with a durable transport (PostgreSQL-backed) to ensure delivery and prevent the loop risk identified in Hot Spot #4.
