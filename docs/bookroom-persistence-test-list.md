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
| 1 | ✅ DONE | should expose a snapshot with the correct organizer identifier, confirmed status, timeslot start, and timeslot end when a reservation is created via the named constructor |
| 2 | ☐ | should reconstruct a reservation with identical organizer, status, and timeslot data when a snapshot is round-tripped through fromSnapshot |
| 3 | ☐ | should persist a confirmed reservation carrying the correct room identifier and organizer identifier when all booking rules are satisfied |
| 4 | ☐ | should not persist any reservation when the booking is rejected because the requested timeslot conflicts with an existing one |
| 5 | ☐ | should assign a distinct reservation identifier to each new booking when the same room is booked for two non-overlapping timeslots |
| 6 | ☐ | should expose a complete snapshot — including room identifier — and allow full reconstitution via fromSnapshot when a reservation's constructor is accessible only through the named constructor |
| 7 | ☐ | should expose a snapshot with the correct capacity, opening time, and closing time when a room's data is serialised |
| 8 | ☐ | should reconstruct a room with identical capacity and operating hours when a room snapshot is round-tripped through fromSnapshot |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — establishes `ReservationSnapshot`, `Reservation::create()`, `toSnapshot()` |
| 2 | constant → variable (3) | Hard-coded snapshot cannot survive a round-trip — forces all fields wired to real instance state |
| 3 | unconditional → conditional (4) | Use case never calls `save()` — capturing fake fails immediately; forces `Reservation::create()` + `save()` on happy path |
| 4 | unconditional → conditional (4) | Naive always-save fails on rejection — forces `save()` placed only after all guards pass |
| 5 | constant → variable (3) | Hardcoded `ReservationId` makes all bookings identical — forces unique ID generation |
| 6 | unconditional → conditional (4) | Making constructor `private` breaks all `new Reservation(...)` inline fakes — forces migration to `fromSnapshot()` in all existing tests |
| 7 | nil → constant (2) | Baseline — establishes `RoomSnapshot`, `Room::toSnapshot()` |
| 8 | constant → variable (3) | Hard-coded snapshot cannot survive round-trip — forces all fields wired to real Room state |

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
