# MeetSync — Iteration 1 Gherkin Specification

---

## Why these priorities and not the others

The single riskiest assumption is **adoption**: will employees actually switch from
Slack/email/sticky notes to a dedicated booking tool?

To validate this, users need exactly one thing: **book a room and trust it is
really theirs**. Conflict detection is the entire value proposition — without it,
a shared spreadsheet does the job.

Everything else was deliberately cut:

| Cut feature | Reason |
|---|---|
| Approval workflow | Adds friction before we know anyone wants to book at all |
| Recurring reservations | Complex engine — validate single-booking habit first |
| Check-in / no-show | Only matters once adoption creates ghost-room pressure |
| Waitlist | Only matters once rooms are actually saturated |
| Role restrictions | Single pilot tenant, single building — no need yet |
| Room admin UI | Seed data is enough; rooms don't change during the pilot |
| Reminder emails | Nice-to-have; confirmation email is enough signal |
| Multi-tenancy | One real tenant for the pilot; isolation can be built later |

**Status lifecycle in Iteration 1** is deliberately flat:

```
create ──► CONFIRMED ──► CANCELLED
               └── (rule violation) ──► REJECTED
```

No PENDING, no PENDING_APPROVAL. A valid booking is confirmed immediately.
This is intentional: we are not building the approval engine yet.

---

## Seed data (shared across all features)

```
Building   : HQ Paris
Timezone   : Europe/Paris
Hours      : 08:00 – 19:00
Horizon    : 90 days
Min notice : 30 minutes

Rooms
┌─────────────┬───────┬──────────┬──────────────────────────────────┐
│ Name        │ Floor │ Capacity │ Equipment                        │
├─────────────┼───────┼──────────┼──────────────────────────────────┤
│ Eiffel      │ 3     │ 8        │ projector, whiteboard            │
│ Louvre      │ 1     │ 20       │ video conferencing, projector    │
│ Montmartre  │ 2     │ 4        │ whiteboard                       │
└─────────────┴───────┴──────────┴──────────────────────────────────┘

Personas
- Alice Martin   alice.martin@acme.com    Employee
- Bob Chen       bob.chen@acme.com        Employee
- Carol Dubois   carol.dubois@acme.com    Employee
```

---

## Feature 1 — Browse available rooms

```gherkin
Feature: Browse available rooms for a given timeslot

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place

  Scenario: All rooms are free — Alice sees all three options
    Given no reservations exist on 2026-03-09
    When Alice searches for rooms available on 2026-03-09 from 10:00 to 11:00
    Then she sees exactly 3 rooms:
      | Name        | Floor | Capacity | Equipment                        |
      | Eiffel      | 3     | 8        | projector, whiteboard            |
      | Louvre      | 1     | 20       | video conferencing, projector    |
      | Montmartre  | 2     | 4        | whiteboard                       |

  Scenario: One room is already booked — Alice sees only the free ones
    Given Bob has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 10:00 to 11:00
    When Alice searches for rooms available on 2026-03-09 from 10:00 to 11:00
    Then she sees exactly 2 rooms: "Louvre" and "Montmartre"
    And "Eiffel" does not appear in the results

  Scenario: All rooms are booked — Alice sees an empty list
    Given "Eiffel"      is CONFIRMED for 2026-03-09 from 10:00 to 11:00
    And   "Louvre"      is CONFIRMED for 2026-03-09 from 09:30 to 10:30
    And   "Montmartre"  is CONFIRMED for 2026-03-09 from 10:00 to 11:00
    When Alice searches for rooms available on 2026-03-09 from 10:00 to 11:00
    Then she sees 0 rooms
    And the message "No rooms available for this timeslot" is displayed

  Scenario: A back-to-back booking does not hide the room
    Given Bob has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 09:00 to 10:00
    When Alice searches for rooms available on 2026-03-09 from 10:00 to 11:00
    Then "Eiffel" appears in the results
```

---

## Feature 2 — Book a room (happy path)

```gherkin
Feature: Successfully book a free room

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place
    And no reservations exist on 2026-03-09

  Scenario: Alice books Eiffel for a team meeting — reservation is immediately confirmed
    When Alice books room "Eiffel" on 2026-03-09 from 14:00 to 15:30
      With participants: bob.chen@acme.com, carol.dubois@acme.com
    Then a reservation is created with status CONFIRMED
    And the reservation contains:
      | field        | value                                   |
      | room         | Eiffel                                  |
      | organizer    | alice.martin@acme.com                   |
      | start        | 2026-03-09 14:00                        |
      | end          | 2026-03-09 15:30                        |
      | participants | bob.chen@acme.com, carol.dubois@acme.com |
    And room "Eiffel" is no longer available on 2026-03-09 from 14:00 to 15:30

  Scenario: Alice books Montmartre alone — no participants required
    When Alice books room "Montmartre" on 2026-03-09 from 09:00 to 09:30
      With no participants
    Then a reservation is created with status CONFIRMED
    And the organizer is alice.martin@acme.com
```

---

## Feature 3 — Conflict detection

```gherkin
Feature: Two reservations cannot overlap for the same room

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place
    And Alice has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 10:00 to 12:00

  Scenario: Bob tries to book the exact same timeslot — rejected
    When Bob books room "Eiffel" on 2026-03-09 from 10:00 to 12:00
    Then the reservation is REJECTED
    And the error message is "Timeslot conflict: Eiffel is already booked from 10:00 to 12:00"

  Scenario: Bob tries to book a timeslot that starts inside Alice's slot — rejected
    When Bob books room "Eiffel" on 2026-03-09 from 11:00 to 13:00
    Then the reservation is REJECTED
    And the error message is "Timeslot conflict: Eiffel is already booked from 10:00 to 12:00"

  Scenario: Bob tries to book a timeslot that ends inside Alice's slot — rejected
    When Bob books room "Eiffel" on 2026-03-09 from 09:00 to 11:00
    Then the reservation is REJECTED
    And the error message is "Timeslot conflict: Eiffel is already booked from 10:00 to 12:00"

  Scenario: Bob tries to book a timeslot that completely contains Alice's slot — rejected
    When Bob books room "Eiffel" on 2026-03-09 from 09:00 to 13:00
    Then the reservation is REJECTED
    And the error message is "Timeslot conflict: Eiffel is already booked from 10:00 to 12:00"

  Scenario: Bob books immediately after Alice — allowed (half-open interval rule)
    When Bob books room "Eiffel" on 2026-03-09 from 12:00 to 13:00
    Then the reservation is CONFIRMED

  Scenario: Bob books immediately before Alice — allowed
    When Bob books room "Eiffel" on 2026-03-09 from 08:00 to 10:00
    Then the reservation is CONFIRMED

  Scenario: Conflict on Eiffel does not affect Louvre
    When Bob books room "Louvre" on 2026-03-09 from 10:00 to 12:00
    Then the reservation is CONFIRMED
```

---

## Feature 4 — Capacity validation

```gherkin
Feature: A reservation cannot exceed the room's capacity

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place
    And no reservations exist on 2026-03-09
    # Montmartre capacity = 4, Eiffel capacity = 8

  Scenario: Alice tries to book Montmartre for 5 people — rejected
    When Alice books room "Montmartre" on 2026-03-09 from 10:00 to 11:00
      With participants: bob.chen@acme.com, carol.dubois@acme.com,
                         dave.white@acme.com, eve.brown@acme.com
      # 1 organizer + 4 participants = 5 people > capacity 4
    Then the reservation is REJECTED
    And the error message is "Participant count (5) exceeds room capacity (4)"

  Scenario: Alice books Montmartre for exactly 4 people — allowed
    When Alice books room "Montmartre" on 2026-03-09 from 10:00 to 11:00
      With participants: bob.chen@acme.com, carol.dubois@acme.com, dave.white@acme.com
      # 1 organizer + 3 participants = 4 people = capacity 4
    Then the reservation is CONFIRMED

  Scenario: Alice books Eiffel for 8 people — allowed
    When Alice books room "Eiffel" on 2026-03-09 from 10:00 to 11:00
      With participants: bob.chen@acme.com, carol.dubois@acme.com,
                         dave.white@acme.com, eve.brown@acme.com,
                         frank.lee@acme.com, grace.kim@acme.com, henry.jones@acme.com
      # 1 organizer + 7 participants = 8 people = capacity 8
    Then the reservation is CONFIRMED
```

---

## Feature 5 — Operating hours enforcement

```gherkin
Feature: Reservations must fall within building operating hours (08:00–19:00)

  Background:
    Given the current time is 2026-03-09 07:00 (Europe/Paris)
    And the seed rooms and building are in place
    And no reservations exist on 2026-03-09

  Scenario: Alice tries to start before opening time — rejected
    When Alice books room "Eiffel" on 2026-03-09 from 07:30 to 09:00
    Then the reservation is REJECTED
    And the error message is "HQ Paris opens at 08:00 — your booking cannot start before then"

  Scenario: Alice tries to end after closing time — rejected
    When Alice books room "Eiffel" on 2026-03-09 from 17:30 to 19:30
    Then the reservation is REJECTED
    And the error message is "HQ Paris closes at 19:00 — your booking must end by then"

  Scenario: Alice books the last possible slot of the day — allowed
    When Alice books room "Eiffel" on 2026-03-09 from 18:00 to 19:00
    Then the reservation is CONFIRMED

  Scenario: Alice books the first slot of the day — allowed
    When Alice books room "Eiffel" on 2026-03-09 from 08:00 to 09:00
    Then the reservation is CONFIRMED
```

---

## Feature 6 — Booking horizon (90 days maximum)

```gherkin
Feature: A reservation cannot be created more than 90 days in advance

  Background:
    Given the current time is 2026-03-09 09:00 (Europe/Paris)
    And the seed rooms and building are in place

  Scenario: Alice tries to book 91 days in advance — rejected
    # 2026-03-09 + 91 days = 2026-06-08
    When Alice books room "Eiffel" on 2026-06-08 from 10:00 to 11:00
    Then the reservation is REJECTED
    And the error message is "Cannot book more than 90 days in advance (limit: 2026-06-07)"

  Scenario: Alice books exactly 90 days in advance — allowed
    # 2026-03-09 + 90 days = 2026-06-07
    When Alice books room "Eiffel" on 2026-06-07 from 10:00 to 11:00
    Then the reservation is CONFIRMED

  Scenario: Alice books tomorrow — allowed
    When Alice books room "Eiffel" on 2026-03-10 from 10:00 to 11:00
    Then the reservation is CONFIRMED
```

---

## Feature 7 — Minimum advance notice (30 minutes)

```gherkin
Feature: A reservation must be created at least 30 minutes before its start time

  Background:
    Given the seed rooms and building are in place
    And no reservations exist for today

  Scenario: Alice tries to book a slot starting in 20 minutes — rejected
    Given the current time is 2026-03-09 09:40 (Europe/Paris)
    When Alice books room "Eiffel" on 2026-03-09 from 10:00 to 11:00
    Then the reservation is REJECTED
    And the error message is "Bookings must be made at least 30 minutes in advance (earliest start: 10:10)"

  Scenario: Alice books a slot starting in exactly 30 minutes — allowed
    Given the current time is 2026-03-09 09:30 (Europe/Paris)
    When Alice books room "Eiffel" on 2026-03-09 from 10:00 to 11:00
    Then the reservation is CONFIRMED

  Scenario: Alice books a slot starting in 2 hours — allowed
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    When Alice books room "Eiffel" on 2026-03-09 from 10:00 to 11:00
    Then the reservation is CONFIRMED
```

---

## Feature 8 — View my reservations

```gherkin
Feature: An employee can view their upcoming reservations

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place
    And Alice has the following CONFIRMED reservations:
      | Room        | Date       | Start | End   |
      | Eiffel      | 2026-03-09 | 14:00 | 15:00 |
      | Louvre      | 2026-03-10 | 10:00 | 12:00 |
      | Montmartre  | 2026-03-05 | 09:00 | 10:00 |  ← past
    And Bob has a CONFIRMED reservation for "Eiffel" on 2026-03-11 from 09:00 to 10:00

  Scenario: Alice sees only her own future reservations — past ones excluded
    When Alice views her upcoming reservations
    Then she sees exactly 2 reservations:
      | Room    | Date       | Start | End   | Status    |
      | Eiffel  | 2026-03-09 | 14:00 | 15:00 | CONFIRMED |
      | Louvre  | 2026-03-10 | 10:00 | 12:00 | CONFIRMED |
    And the Montmartre reservation from 2026-03-05 does not appear
    And Bob's reservation for 2026-03-11 does not appear

  Scenario: Alice has no upcoming reservations
    Given Alice has no future reservations
    When Alice views her upcoming reservations
    Then she sees an empty list
    And the message "You have no upcoming reservations" is displayed
```

---

## Feature 9 — Cancel a reservation

```gherkin
Feature: An organizer can cancel a future confirmed reservation

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place

  Scenario: Alice cancels her reservation — slot is freed for others
    Given Alice has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 14:00 to 15:00
    When Alice cancels that reservation
    Then the reservation status is CANCELLED
    And room "Eiffel" is available on 2026-03-09 from 14:00 to 15:00
    And Bob can now book "Eiffel" on 2026-03-09 from 14:00 to 15:00 and get CONFIRMED

  Scenario: Alice cannot cancel a reservation that has already started
    Given the current time is 2026-03-09 14:20 (Europe/Paris)
    And Alice has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 14:00 to 15:00
    When Alice tries to cancel that reservation
    Then the cancellation is rejected
    And the error message is "Cannot cancel a reservation that has already started"
    And the reservation status remains CONFIRMED

  Scenario: Bob cannot cancel Alice's reservation
    Given Alice has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 14:00 to 15:00
    When Bob tries to cancel Alice's reservation
    Then the cancellation is rejected
    And the error message is "You are not the organizer of this reservation"
    And the reservation status remains CONFIRMED
```

---

## Feature 10 — Email notifications

```gherkin
Feature: Email notifications are sent on booking and cancellation

  Background:
    Given the current time is 2026-03-09 08:00 (Europe/Paris)
    And the seed rooms and building are in place
    And no reservations exist on 2026-03-09

  Scenario: Alice receives a confirmation email after booking
    When Alice books room "Eiffel" on 2026-03-09 from 14:00 to 15:00
      With participants: bob.chen@acme.com
    Then a confirmation email is sent to alice.martin@acme.com containing:
      | field   | value                        |
      | subject | Reservation confirmed: Eiffel – 2026-03-09 14:00–15:00 |
      | room    | Eiffel, Floor 3              |
      | start   | Monday 9 March 2026 at 14:00 |
      | end     | Monday 9 March 2026 at 15:00 |

  Scenario: A failed notification does not affect the reservation
    Given the email service is unavailable
    When Alice books room "Eiffel" on 2026-03-09 from 14:00 to 15:00
    Then the reservation is CONFIRMED
    And a notification failure is logged
    And Alice's reservation is not rolled back

  Scenario: Alice receives a cancellation email after cancelling
    Given Alice has a CONFIRMED reservation for "Eiffel" on 2026-03-09 from 14:00 to 15:00
    When Alice cancels that reservation
    Then a cancellation email is sent to alice.martin@acme.com containing:
      | field   | value                        |
      | subject | Reservation cancelled: Eiffel – 2026-03-09 14:00–15:00 |
      | room    | Eiffel, Floor 3              |
      | start   | Monday 9 March 2026 at 14:00 |
```

---

## Summary — 10 features, 33 scenarios

| Feature | Scenarios | Core rule tested |
|---|---|---|
| 1. Browse available rooms | 4 | Availability query, half-open interval |
| 2. Book a room (happy path) | 2 | CONFIRMED on valid booking |
| 3. Conflict detection | 7 | Half-open interval `[A,B)∩[C,D)` |
| 4. Capacity validation | 3 | Participants ≤ capacity |
| 5. Operating hours | 4 | Start ≥ 08:00, end ≤ 19:00 |
| 6. Booking horizon | 3 | Start ≤ today + 90 days |
| 7. Minimum advance notice | 3 | Start ≥ now + 30 min |
| 8. View my reservations | 2 | Scoped to organizer, future only |
| 9. Cancel a reservation | 3 | Future only, organizer only |
| 10. Email notifications | 3 | Best-effort, never blocks state |
| **Total** | **34** | |
