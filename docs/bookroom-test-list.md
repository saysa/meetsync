# Test List — BookRoom Use Case

**Requirement:** Book a room for a specific timeslot — reservation is immediately CONFIRMED when all rules pass
**Type:** UNIT · **BC:** Reservation Management
**Target:** `src/Application/UseCase/BookRoomUseCase.php`
**Agent:** tdd-analyze output — 2026-03-07

---

## Files to create

| File | Role |
|---|---|
| `tests/Unit/Application/UseCase/BookRoomUseCaseTest.php` | Test class (write first) |
| `src/Application/UseCase/BookRoomUseCase.php` | Use case |
| `src/Application/Command/BookRoomCommand.php` | Input DTO |
| `src/Domain/Reservation/Reservation.php` | Aggregate (`final class`) |
| `src/Domain/Reservation/ReservationId.php` | Value object |
| `src/Domain/Reservation/Room.php` | Value object (capacity, hours) |
| `src/Domain/Reservation/RoomId.php` | Value object |
| `src/Domain/Exception/TimeslotConflictException.php` | Domain exception |
| `src/Domain/Exception/RoomCapacityExceededException.php` | Domain exception |
| `src/Domain/Exception/BookingHorizonExceededException.php` | Domain exception |
| `src/Domain/Exception/InsufficientAdvanceNoticeException.php` | Domain exception |
| `src/Application/Exception/RoomNotFoundException.php` | Application exception |
| `src/Domain/Reservation/ReservationRepositoryInterface.php` | Port |
| `src/Domain/Reservation/RoomRepositoryInterface.php` | Port |
| `tests/Fakes/InMemoryReservationRepository.php` | Fake adapter |
| `tests/Fakes/InMemoryRoomRepository.php` | Fake adapter |
| `tests/Fakes/FixedClock.php` | Fake clock |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should create a confirmed reservation when a room is available and all booking rules are satisfied |
| 2 | ✅ DONE | should reject the booking when the requested room does not exist |
| 3 | ✅ DONE | should reject the booking when the requested timeslot overlaps with an existing reservation |
| 4 | ✅ DONE | should confirm the booking when a second reservation starts exactly when an existing one ends |
| 5 | ✅ DONE | should reject the booking when the number of participants exceeds the room capacity |
| 6 | NOT DONE | should confirm the booking when the number of participants equals the room capacity |
| 7 | NOT DONE | should reject the booking when the start time is before the building opening time |
| 8 | NOT DONE | should reject the booking when the end time is after the building closing time |
| 9 | NOT DONE | should reject the booking when the start date is more than 90 days in the future |
| 10 | NOT DONE | should confirm the booking when the start date is exactly 90 days in the future |
| 11 | NOT DONE | should reject the booking when the start time is less than 30 minutes from now |
| 12 | NOT DONE | should confirm the booking when the start time is exactly 30 minutes from now |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — establishes ReservationId returned on success |
| 2 | → conditional (4) | Forces room lookup — can't always succeed if room unknown |
| 3 | → conditional (4) | Forces reservation repository lookup + `conflictsWith()` call |
| 4 | → conditional (4) | Regression guard — forces half-open interval strict `<`, not `<=` |
| 5 | → conditional (4) | Forces participant count validation against room capacity |
| 6 | → conditional (4) | Forces `>` not `>=` on capacity check |
| 7 | → conditional (4) | Forces operating hours validation (delegates to Timeslot VO) |
| 8 | → conditional (4) | Second operating hours guard (closing time) |
| 9 | → conditional (4) | Forces booking horizon check against `now + 90 days` |
| 10 | → conditional (4) | Forces `>` not `>=` on horizon check |
| 11 | → conditional (4) | Forces clock dependency + advance notice check against `now + 30min` |
| 12 | → conditional (4) | Forces `<` not `<=` on advance notice check |

---

## Design Notes

### Existing code to reuse
- `Timeslot` VO — conflict detection + operating hours validation already implemented
- `InvalidTimeslotException` — thrown by Timeslot constructor (operating hours tests #7, #8)
- `DomainException` abstract base — all new domain exceptions extend this

### Architecture decisions
- **Sociable unit test** — uses real `Timeslot`, real `Reservation` aggregate, fake repositories + fake clock
- **No mocking frameworks** — fakes are hand-written in-memory adapters
- **Clock abstraction** — `FixedClock` (fake) injected into use case for tests #11, #12
- **Operating hours** — delegated to `Timeslot` constructor (passes `room->openingTime`, `room->closingTime`)
- **Reservation status** — Iteration 1: `CONFIRMED` only (no PENDING, no approval engine)

### Fake adapters to build
| Fake | Role |
|---|---|
| `InMemoryReservationRepository` | Stores reservations in array, exposes `findByRoomId()` |
| `InMemoryRoomRepository` | Pre-seeded with Eiffel/Louvre/Montmartre rooms |
| `FixedClock` | Returns a configurable `DateTimeImmutable` as "now" |

### Seed data for tests
```
Room Eiffel:   capacity 8,  opening 08:00, closing 19:00
Room Montmartre: capacity 4, opening 08:00, closing 19:00
Current time (FixedClock): 2026-03-09 09:00:00 Europe/Paris
```

---

## Next step

```
use the tdd agent to implement: BookRoom use case — use the test list in docs/bookroom-test-list.md
```
