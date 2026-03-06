# SPEC – MeetSync: Enterprise Meeting Room Reservation

## Introduction – MeetSync

**MeetSync** is a SaaS platform for managing meeting room reservations across large organizations.
When an employee needs a space for a meeting, they create a reservation that goes through a process including availability checking, conflict detection, optional approval, and confirmation.

The system must enforce many business rules depending on:
- the room capacity and equipment,
- the reservation duration and recurrence pattern,
- the tenant's booking policies (advance notice, horizon, approval rules),
- the check-in behavior and no-show handling,
- and the participant count at the time of booking.

The goal of the project is to build a robust, testable and evolvable system capable of managing these business rules reliably across multiple tenants.

---

## Bounded Context 1 – Reservation Management

### Creating a reservation
1. When an employee requests a room, a reservation is created with the room, organizer, timeslot, and participant list.
2. A reservation can only be created if the room exists and belongs to the tenant.
3. A reservation starts with status PENDING, unless a rule imposes immediate rejection.
4. If the requested timeslot is already occupied by another reservation for the same room, the reservation is immediately rejected.
5. If the number of participants exceeds the room capacity, the reservation is immediately rejected.
6. A reservation cannot be created outside the building's operating hours.
7. A reservation cannot be created beyond the tenant's booking horizon (e.g. maximum 90 days in advance).
8. A reservation cannot be created with less than the tenant's minimum advance notice (e.g. at least 30 minutes before start).

### Recurring reservations
9. A reservation can follow a recurrence pattern: daily, weekly, monthly, or custom.
10. Each occurrence of a recurring series is an independent reservation linked to the series.
11. A conflict in one occurrence does not block the entire series — the system schedules what it can and reports the conflicts.
12. Modifying a single occurrence does not affect the rest of the series.
13. Modifying the series after occurrence N only affects future occurrences.
14. Cancelling the series cancels all future uncommenced occurrences.

### Approval workflow
15. Some rooms require explicit approval before a reservation is confirmed.
16. When approval is required, the reservation transitions to status PENDING_APPROVAL.
17. A pending approval holds the timeslot as a soft lock for a configurable duration.
18. If the approval window expires without a decision, the reservation is automatically cancelled.
19. A reservation cannot be confirmed without approval when the room requires it.
20. The approver can approve, reject, or propose an alternative timeslot.

### Check-in and no-show
21. After confirmation, the organizer must check in within a configurable window around the start time.
22. If no check-in is recorded within the no-show threshold, the reservation is automatically released.
23. An auto-released room becomes available again after a configurable grace period.
24. A no-show is recorded and may trigger a penalty depending on the tenant's policy.

### Waitlist
25. When a room is fully booked for a given timeslot, users can join the waitlist.
26. The waitlist is ordered by FIFO by default.
27. When a cancellation occurs, the first waitlisted user is notified.
28. The notified user has a configurable acceptance window to confirm.
29. If they decline or do not respond, the next user on the waitlist is notified.
30. The waitlist expires automatically once the timeslot becomes irrelevant.

### Reservation status lifecycle
31. A rejected reservation cannot be reactivated.
32. A confirmed reservation can be modified or cancelled before it starts.
33. A reservation in progress (started, not yet ended) cannot be cancelled.
34. A completed reservation cannot be modified.
35. A reservation pending approval can be withdrawn by the organizer.

---

## Bounded Context 2 – Room Management

### Room creation and configuration
1. A room is created with a name, capacity, equipment list, and location (building and floor).
2. A room belongs to exactly one tenant.
3. A room can be active, blocked, or decommissioned.
4. A blocked room cannot accept new reservations for the duration of the block.
5. Decommissioning a room cancels all future reservations and notifies affected organizers.

### Booking rules per room
6. A room can define its own minimum and maximum reservation duration.
7. A room can require a minimum number of participants (e.g. an auditorium requires at least 20 people).
8. A room can restrict bookings to specific roles (e.g. only managers can book the boardroom).
9. A room can require approval regardless of the tenant's default policy.

### Equipment
10. A room's equipment list can be updated at any time.
11. If equipment is removed from a room, existing reservations that requested that equipment receive a warning notification.
12. Equipment requirements are checked at reservation time, not enforced as hard blocks.

---

## Bounded Context 3 – Tenant and User Management

### Tenant onboarding
1. A tenant is created with a name, a set of buildings, and a default booking policy.
2. Each tenant operates in complete isolation — rooms, users, and reservations are not shared.
3. A tenant can configure: booking horizon, minimum advance notice, no-show policy, and approval rules.

### Users and roles
4. A user belongs to exactly one tenant.
5. A user can have one of the following roles: Employee, Room Admin, Facilities Admin, Tenant Admin.
6. A suspended user cannot create new reservations.
7. Suspending a user cancels all their future reservations and notifies affected participants.

### Policies
8. The no-show policy defines: the check-in window, the no-show threshold, and the penalty (warning, suspension, or report).
9. Policies apply to all users of the tenant unless overridden at room level.
10. A policy change does not retroactively affect existing confirmed reservations.

---

## Bounded Context 4 – Notifications

1. The organizer receives a confirmation notification when a reservation is confirmed.
2. The organizer receives a reminder notification 15 minutes before the reservation starts.
3. All participants receive a cancellation notification when a reservation is cancelled.
4. Waitlisted users receive a notification when a slot becomes available.
5. Affected organizers receive a notification when a room is decommissioned.
6. Notifications are sent by email by default; additional channels (push, Slack) are configurable per tenant.
7. A failed notification does not affect the reservation state — notifications are best-effort.
