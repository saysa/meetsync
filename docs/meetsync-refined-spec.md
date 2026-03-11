# MeetSync — Refined Specification
# (BDD Three Amigos Workshop Output)

> This document supersedes the initial README.md specification.
> All ambiguities identified during the Three Amigos workshop have been resolved.
> Rules are written in plain language followed by Gherkin examples for the most critical behaviours.

---

## Glossary

| Term | Definition |
|---|---|
| **Organizer** | The user who creates the reservation |
| **Participant** | A user invited to a reservation (not the organizer) |
| **Timeslot** | A half-open interval `[start, end)` — a slot ending at 10:00 does not conflict with one starting at 10:00 |
| **Booking horizon** | Maximum number of days in advance a reservation can be created |
| **Minimum advance notice** | Minimum time between now and the reservation start |
| **Soft lock** | A timeslot hold that blocks other reservations for a configurable duration pending an approval decision |
| **No-show threshold** | Time after start after which a reservation with no check-in is auto-released |
| **Grace period** | Buffer after an auto-release before the slot appears as bookable again |
| **Operating hours** | Opening and closing times of the building, expressed in the building's local timezone |
| **Building timezone** | All time-based rules (horizon, notice, operating hours, conflicts) are evaluated in the timezone of the building where the room is located |

---

## Reservation Status Lifecycle

```
                        ┌─────────────────────────────────────────┐
                        │                                         │
                 ┌──────▼──────┐                                  │
    create ────► │   PENDING   │──── conflict / rule violation ──►│ REJECTED
                 └──────┬──────┘                                  │
                        │                                         │
          room requires │ approval                                │
                        │                                         │
              ┌─────────▼──────────┐                              │
              │  PENDING_APPROVAL  │──── window expires ─────────►│ CANCELLED
              └─────────┬──────────┘                              │
                        │ approved                                │
                        │                                         │
                 ┌──────▼──────┐                                  │
                 │  CONFIRMED  │──── organizer cancels ──────────►│ CANCELLED
                 └──────┬──────┘                                  │
                        │ start time reached                      │
                        │                                         │
                 ┌──────▼──────┐                                  │
                 │ IN_PROGRESS │──── no check-in ────────────────►│ RELEASED
                 └──────┬──────┘                                  │
                        │ end time reached                        │
                        │                                         │
                 ┌──────▼──────┐                                  │
                 │  COMPLETED  │                                  │
                 └─────────────┘                                  │
                                                                  │
              ┌──────────────────────────┐                        │
              │ PENDING_WAITLIST_CONFIRM │── no response ────────►│ CANCELLED
              └──────────────────────────┘                        │
                                                                  └
```

---

## Bounded Context 1 — Reservation Management

### 1.1 Creating a Reservation

1. A reservation is created with: room, organizer, timeslot `[start, end)`, and participant list.
2. A reservation can only be created if the room exists and belongs to the same tenant as the organizer.
3. All time-based validations are performed in the **building's local timezone**.
4. A new reservation starts with status **PENDING**. PENDING is a **hard lock** — it immediately blocks the timeslot. No two reservations can hold an overlapping timeslot for the same room simultaneously.
5. **Conflict rule (half-open interval):** A timeslot `[A, B)` conflicts with `[C, D)` if and only if `A < D AND C < B`. A reservation ending at 10:00 does not conflict with one starting at 10:00.
6. If the requested timeslot conflicts with an existing reservation for the same room, the new reservation is immediately **REJECTED**.
7. If the number of participants exceeds the room's capacity, the new reservation is immediately **REJECTED**.
8. A reservation cannot start before the building's opening time or end after the building's closing time. **If a reservation would end outside operating hours it is rejected entirely — the system never auto-trims.** The organizer receives an explicit error indicating the closing time.
9. A reservation cannot be created with a start time more than the tenant's **booking horizon** days in the future (default: 90 days).
10. A reservation cannot be created with a start time less than the tenant's **minimum advance notice** from now (default: 30 minutes).

```gherkin
Feature: Reservation conflict detection

  Scenario: Back-to-back bookings are allowed
    Given room "Alpha" is booked from 09:00 to 10:00
    When I request room "Alpha" from 10:00 to 11:00
    Then the reservation is created with status PENDING

  Scenario: Overlapping booking is rejected
    Given room "Alpha" is booked from 09:00 to 10:00
    When I request room "Alpha" from 09:30 to 10:30
    Then the reservation is rejected with reason "Timeslot conflict"

  Scenario: Reservation ending outside operating hours is rejected
    Given building "HQ" closes at 18:00
    When I request room "Alpha" from 17:30 to 19:00
    Then the reservation is rejected with reason "Room closes at 18:00 — please end your booking by then"
```

### 1.2 Approval Workflow

11. Approval is required when either:
    - The room is configured to require approval (BC2 rule 9), **or**
    - The tenant policy defines an approval trigger (e.g. duration > N hours, participant count > threshold).
    Room-level configuration always takes precedence over tenant policy.
12. When approval is required, the reservation transitions from PENDING to **PENDING_APPROVAL**.
13. PENDING_APPROVAL acts as a **soft lock**: the timeslot is held exclusively for the approval duration.
14. The approval window duration is configured at **tenant level** (default: **24 hours**), with optional override per room.
15. If the approval window expires without a decision, the reservation is automatically **CANCELLED**.
16. **Approvers**: the Room Admin(s) assigned to the room. If no Room Admin is assigned, the Facilities Admin of the building acts as fallback approver. Any single approver can act.
17. The approver can: **approve** (→ CONFIRMED), **reject** (→ REJECTED), or **propose an alternative timeslot** (organizer is notified and must re-confirm).
18. A reservation cannot be confirmed without approval when approval is required.

```gherkin
Feature: Approval workflow

  Scenario: Approval window expires
    Given room "Boardroom" requires approval with a 24-hour window
    And I created a reservation at 09:00 on Monday
    When 24 hours pass with no approver decision
    Then the reservation status becomes CANCELLED
    And the organizer receives a cancellation notification

  Scenario: Approver proposes alternative timeslot
    Given a reservation is PENDING_APPROVAL
    When the Room Admin proposes an alternative timeslot
    Then the organizer is notified with the proposed timeslot
    And the organizer must re-confirm to proceed
```

### 1.3 Recurring Reservations

19. A reservation can follow a recurrence pattern: **daily**, **weekly**, **monthly**, or **custom**.
20. **Custom recurrence** is defined by: frequency unit (days or weeks) + interval + optional day-of-week mask (e.g. Mon/Wed/Fri) + end condition (end date or max occurrence count). Patterns conform to iCalendar RRULE semantics.
21. Each occurrence of a recurring series is an **independent reservation** linked to the series by a `seriesId`.
22. A conflict in one occurrence does not block the entire series — the system schedules what it can and reports which occurrences were rejected.
23. **Modifying a single occurrence** does not affect the rest of the series.
24. **Modifying the series from occurrence N onwards** ("this and following") detaches occurrences 1 to N-1 (they remain unchanged) and applies the change to occurrences N and beyond. N is the occurrence being edited.
25. Cancelling the series cancels all future occurrences that have not yet commenced (status ≠ IN_PROGRESS, ≠ COMPLETED).

### 1.4 Check-in and No-show

26. After confirmation, the organizer must check in within the **check-in window** (configurable per tenant, e.g. opens 15 minutes before start, closes 15 minutes after start).
27. If no check-in is recorded by the **no-show threshold** (configurable per tenant), the reservation status becomes **RELEASED** and the room is freed immediately.
28. Once released, the timeslot is available again after a **grace period** (default: **15 minutes**). During the grace period the slot does not appear as bookable, giving time for the no-show notification to propagate.
29. A no-show event is recorded and may trigger a penalty per the tenant's no-show policy (warning, temporary suspension, or report).
30. A reservation that is **IN_PROGRESS** cannot be cancelled.

```gherkin
Feature: No-show handling

  Scenario: No check-in triggers auto-release
    Given a CONFIRMED reservation for room "Alpha" starting at 10:00
    And the no-show threshold is 15 minutes after start
    And the grace period is 15 minutes
    When 10:15 passes with no check-in recorded
    Then the reservation status becomes RELEASED
    And a no-show event is recorded for the organizer
    And room "Alpha" becomes bookable again at 10:30
```

### 1.5 Waitlist

31. When a room is fully booked for a given timeslot, users may join the waitlist.
32. The waitlist is ordered **FIFO** by default.
33. When a cancellation or release frees the timeslot, the first waitlisted user is notified.
34. A pre-filled reservation is created for the notified user in status **PENDING_WAITLIST_CONFIRM**. The user confirms or declines in a **single action** — they do not re-enter the full booking form.
35. The acceptance window is configurable per tenant (default: **30 minutes**).
36. If the user declines or does not respond within the acceptance window, their pre-filled reservation is cancelled and the next user on the waitlist is notified.
37. If the user confirms, the reservation transitions into the normal flow (PENDING, or PENDING_APPROVAL if the room requires it).
38. The waitlist expires automatically once the timeslot start time is reached.

### 1.6 Reservation Status Rules

39. A **REJECTED** reservation cannot be reactivated.
40. A **CONFIRMED** reservation can be modified or cancelled before it starts.
41. An **IN_PROGRESS** reservation cannot be cancelled.
42. A **COMPLETED** reservation cannot be modified.
43. A reservation in **PENDING_APPROVAL** can be withdrawn by the organizer (→ CANCELLED).

---

## Bounded Context 2 — Room Management

### 2.1 Room Creation and Configuration

1. A room is created with: name, capacity, equipment list, location (building + floor).
2. A room belongs to exactly one tenant.
3. A room can be in one of three states: **active**, **blocked**, or **decommissioned**.
4. A **blocked** room cannot accept new reservations for the duration of the block. Existing confirmed reservations are **not** cancelled — organizers are notified of the block.
5. **Decommissioning** a room cancels all future reservations (status ≠ IN_PROGRESS, ≠ COMPLETED) and notifies all affected organizers.

### 2.2 Booking Rules per Room

6. A room can define its own minimum and maximum reservation duration.
7. A room can require a minimum number of participants.
8. A room can restrict bookings to specific tenant roles (e.g. only users with role `MANAGER` can book the boardroom).
9. A room can require approval regardless of the tenant's default policy.

### 2.3 Equipment

10. A room's equipment list can be updated at any time.
11. If equipment is removed from a room, the reservation status of existing bookings that requested that equipment **remains CONFIRMED**. The organizer receives a warning notification and may cancel or modify at their discretion.
12. Equipment requirements are **informational** at reservation time — they generate warnings, not hard blocks.

---

## Bounded Context 3 — Tenant and User Management

### 3.1 Tenant Onboarding

1. A tenant is created with: name, set of buildings, and a default booking policy.
2. Each tenant operates in complete isolation — rooms, users, and reservations are never shared across tenants.
3. Configurable per tenant: booking horizon, minimum advance notice, no-show policy (check-in window, threshold, penalty), approval trigger rules, approval window duration, waitlist acceptance window, grace period.

### 3.2 Users and Roles

4. A user belongs to exactly one tenant.
5. Roles: **Employee**, **Room Admin**, **Facilities Admin**, **Tenant Admin**.
6. A **suspended** user cannot create new reservations.
7. When a user is suspended:
   - All future reservations where the user is **organizer** are cancelled (status ≠ IN_PROGRESS, ≠ COMPLETED). Affected participants are notified.
   - Reservations where the user is only a **participant** are unaffected — the organizer receives a notification that the participant has been suspended.
   - Reservations already **IN_PROGRESS** where the user is organizer are **not** cancelled.

```gherkin
Feature: User suspension

  Scenario: Suspended organizer's future reservations are cancelled
    Given user Alice has 3 future CONFIRMED reservations as organizer
    And Alice has 1 IN_PROGRESS reservation as organizer
    When Alice is suspended
    Then her 3 future reservations are CANCELLED
    And participants of those 3 reservations are notified
    And her IN_PROGRESS reservation is unaffected

  Scenario: Suspended participant does not cancel the reservation
    Given user Bob is a participant in Alice's CONFIRMED reservation
    When Bob is suspended
    Then Alice's reservation remains CONFIRMED
    And Alice is notified that Bob has been suspended
```

### 3.3 Policies

8. The no-show policy defines: check-in window, no-show threshold, and penalty (warning / temporary suspension / report).
9. Policies apply to all users of the tenant unless overridden at room level.
10. **Policy changes and retroactivity:**
    - **CONFIRMED** reservations are **shielded** — a policy change does not affect them.
    - **PENDING** and **PENDING_APPROVAL** reservations are **re-evaluated lazily** at their next state transition (e.g. when an approver acts, or when the system attempts confirmation). They are not retroactively cancelled at the moment of the policy change.

```gherkin
Feature: Policy change retroactivity

  Scenario: Confirmed reservation is shielded from new policy
    Given Alice has a CONFIRMED reservation booked 60 days in advance
    When the tenant changes the booking horizon to 30 days
    Then Alice's reservation remains CONFIRMED and unaffected

  Scenario: Pending reservation is re-evaluated at transition
    Given Alice has a PENDING_APPROVAL reservation booked 60 days in advance
    When the tenant changes the booking horizon to 30 days
    And the approver attempts to approve Alice's reservation
    Then the system rejects the approval with reason "Booking horizon exceeded under current policy"
```

---

## Bounded Context 4 — Notifications

1. The organizer receives a **confirmation** notification when a reservation is confirmed.
2. The organizer receives a **reminder** notification 15 minutes before the reservation starts.
3. All participants receive a **cancellation** notification when a reservation is cancelled.
4. Waitlisted users receive an **availability** notification when a slot opens up.
5. Affected organizers receive a notification when a room is decommissioned.
6. The organizer receives a **warning** notification when equipment is removed from their booked room.
7. Notifications are sent by **email** by default. Additional channels (push notification, Slack) are configurable per tenant.
8. A failed notification does **not** affect the reservation state — notifications are best-effort. Failures are logged for observability.

---

## Appendix — Workshop Decisions Summary

| # | Question | Decision |
|---|---|---|
| 1 | Conflict interval | Half-open `[start, end)` — back-to-back allowed |
| 2 | PENDING lock type | Hard lock — blocks timeslot immediately |
| 3 | Approver identity | Room Admin(s); fallback to Facilities Admin |
| 4 | Approval triggers | Room config OR tenant policy (both) |
| 5 | Custom recurrence | iCalendar RRULE: N-day/week interval + weekday mask |
| 6 | Timezone | Building's local timezone for all time rules |
| 7 | Soft lock duration | Tenant-level default (24h), overridable per room |
| 8 | Series modification scope | "This and following" — occurrence N onwards |
| 9 | Grace period semantics | Immediate release + 15-min buffer before re-bookable |
| 10 | Waitlist confirmation | One-click via PENDING_WAITLIST_CONFIRM; 30-min window |
| 11 | Reservation outside hours | Rejected entirely — no auto-trimming |
| 12 | Suspended user scope | Organizer's future reservations only; IN_PROGRESS untouched |
| 13 | Equipment removal impact | Reservation stays CONFIRMED; warning sent to organizer |
| 14 | Policy change retroactivity | CONFIRMED shielded; PENDING/PENDING_APPROVAL re-evaluated lazily |
