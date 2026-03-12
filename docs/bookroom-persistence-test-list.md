# Test List — BookRoom Hexagon Completion (Persistence + Snapshots)

**Requirement:** Complete the BookRoom hexagon — `BookRoomUseCase` must construct a `Reservation` aggregate and call `repository->save()` with correct state; `Reservation` must gain `toSnapshot()`/`fromSnapshot()`/named constructor/private constructor/`roomId` field; `ReservationSnapshot` and `RoomSnapshot` DTOs must exist; `Room` must gain `toSnapshot()`/`fromSnapshot()`
**Type:** UNIT · **BC:** Reservation Management
**Agent:** tdd-analyze output — 2026-03-12

---

## Design framing

`BookRoomUseCase` currently returns a hardcoded `ReservationId` and never calls `save()`. This is a silent persistence bug that only surfaces when wired to a real database. The hexagon must be complete before any Infrastructure code can be written.

Two sets of tests are needed:
- **Aggregate tests** (`ReservationSnapshotTest`, `RoomSnapshotTest`) — drive `toSnapshot()`/`fromSnapshot()` and the snapshot DTOs
- **Persistence test** (`BookRoomPersistenceTest`) — drive `Reservation::create()` + `repository->save()` in the use case

Test 6 is the regression guard: making the constructor `private` will break all existing inline fakes that use `new Reservation(...)` directly. This forces the migration to `Reservation::fromSnapshot()` in those fakes.

---

## Files to create

| File | Role |
|---|---|
| `src/Domain/Reservation/ReservationSnapshot.php` | DTO: id, roomId, organizerId, status, start, end |
| `src/Domain/Reservation/RoomSnapshot.php` | DTO: id, capacity, openingTime, closingTime |
| `tests/Unit/Domain/Reservation/ReservationSnapshotTest.php` | Tests 1, 2, 6 |
| `tests/Unit/Domain/Reservation/RoomSnapshotTest.php` | Tests 7, 8 |
| `tests/Unit/Application/UseCase/BookRoomPersistenceTest.php` | Tests 3, 4, 5 |

## Files to extend

| File | What TDD will force |
|---|---|
| `src/Domain/Reservation/Reservation.php` | Add `private` ctor, `static create()`, `toSnapshot()`, `fromSnapshot()`, `roomId: RoomId`, `status: string` |
| `src/Domain/Reservation/Room.php` | Add `id` field (`RoomId`), `toSnapshot()`, `fromSnapshot()` |
| `src/Application/UseCase/BookRoomUseCase.php` | Replace hardcoded `ReservationId` with `Reservation::create()` + `$this->reservationRepository->save()` |
| `tests/Unit/Application/UseCase/BookRoomUseCaseTest.php` | Migrate inline fakes from `new Reservation(...)` to `Reservation::fromSnapshot(new ReservationSnapshot(...))` |
| `tests/Unit/Application/UseCase/BookRoomEmailNotificationTest.php` | Same migration |
| `tests/Unit/Application/UseCase/CancelReservationUseCaseTest.php` | Same migration |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should record the organizer, the booked room, a confirmed status, and the reserved time window when a booking is created |
| 2 | ✅ DONE | should restore all booking details — organizer, room, confirmed status, and reserved time window — when a reservation is loaded from the system's records |
| 3 | ✅ DONE | should save the confirmed booking with the organizer and room details when a room is successfully reserved for an available time slot |
| 4 | ☐ | should not save any booking when the reservation is refused because the room is already taken for that time slot |
| 5 | ☐ | should give each booking a unique reference number when the same room is reserved twice for two different time slots |
| 6 | ☐ | should enforce booking rules correctly against a reservation that already exists in the system |
| 7 | ☐ | should record the room capacity and operating hours when a room is saved |
| 8 | ☐ | should preserve the room capacity and operating hours when a room is saved and loaded back |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — establishes `ReservationSnapshot`, `Reservation::create()`, `toSnapshot()` |
| 2 | constant → variable (3) | Hard-coded fields in `toSnapshot()` cannot survive a load round-trip — forces `fromSnapshot()` to wire every field (including `roomId`) to real instance state |
| 3 | unconditional → conditional (4) | Use case never calls `save()` — capturing fake fails immediately; forces `Reservation::create()` + `save()` on happy path |
| 4 | unconditional → conditional (4) | Naive always-save fails on rejection — forces `save()` only after all guards pass |
| 5 | constant → variable (3) | Hardcoded `ReservationId` makes all bookings identical — forces unique ID generation |
| 6 | unconditional → conditional (4) | Making constructor `private` breaks all `new Reservation(...)` inline fakes — forces migration to `fromSnapshot()` |
| 7 | nil → constant (2) | Baseline — establishes `RoomSnapshot`, `Room::toSnapshot()` |
| 8 | constant → variable (3) | Hard-coded fields cannot survive round-trip — forces all Room fields wired to real state |

---

## Breaking change notice (Test 6)

When `Reservation` constructor becomes `private`, these files MUST be migrated:
- `tests/Unit/Application/UseCase/BookRoomUseCaseTest.php` — lines 124–129, 163–168 (`new Reservation(...)` in inline fakes)
- `tests/Unit/Application/UseCase/BookRoomEmailNotificationTest.php` — check for `new Reservation(...)`
- `tests/Unit/Application/UseCase/CancelReservationUseCaseTest.php` — check for `new Reservation(...)`

Migration pattern:
```php
// Before
new Reservation(
    id: new ReservationId('res-1'),
    organizerId: 'alice',
    timeslot: new Timeslot(...),
)

// After
Reservation::fromSnapshot(new ReservationSnapshot(
    id: 'res-1',
    roomId: 'eiffel',
    organizerId: 'alice',
    status: 'CONFIRMED',
    start: new DateTimeImmutable('2026-03-09 10:00:00'),
    end: new DateTimeImmutable('2026-03-09 11:00:00'),
))
```

---

## Next step

```
use the tdd agent to implement: Complete the BookRoom hexagon — persistence + Reservation/Room snapshots — use the test list in docs/bookroom-persistence-test-list.md
```
