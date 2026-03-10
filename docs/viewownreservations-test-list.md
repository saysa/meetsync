# Test List — GetMyReservations Use Case

**Requirement:** A user can view their own reservations — organizer sees their own confirmed and cancelled future reservations, ordered by start time
**Type:** UNIT · **BC:** Reservation Management
**Target:** `src/Application/UseCase/GetMyReservationsUseCase.php`
**Agent:** tdd-analyze output — 2026-03-10

---

## Files to create

| File | Role |
|---|---|
| `tests/Unit/Application/UseCase/GetMyReservationsUseCaseTest.php` | Test class (write first) |
| `src/Application/UseCase/GetMyReservationsUseCase.php` | Use case |
| `src/Application/Query/GetMyReservationsQuery.php` | Input DTO (read-only query) |
| `src/Domain/Reservation/ReservationRepositoryInterface.php` | Add `findByOrganizerId(string $organizerId): array` |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should return an empty list when the organizer has no reservations |
| 2 | ✅ DONE | should return the organizer's confirmed future reservation when they have exactly one |
| 3 | ✅ DONE | should exclude reservations that belong to a different organizer |
| 4 | ✅ DONE | should exclude a confirmed reservation whose start time is in the past |
| 5 | NOT DONE | should include a cancelled reservation when its start time is still in the future |
| 6 | NOT DONE | should return multiple reservations ordered chronologically by start time when the organizer has several future ones |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — can be satisfied by always returning `[]` |
| 2 | constant → variable (3) | Empty-array constant fails when a reservation exists; forces repository query + return of matching reservation |
| 3 | unconditional → conditional (4) | Returning all repo reservations satisfies test 2 but leaks other organizers' data; forces organizer-identity filter |
| 4 | unconditional → conditional (4) | Returning all matching-organizer reservations returns past ones too; forces start >= now guard |
| 5 | unconditional → conditional (4) | Filtering on `isCancelled()` would wrongly hide future cancelled ones; forces filter to be time-based only, not status-based |
| 6 | scalar → collection (5) | Single-item return satisfies all prior tests; now multiple items force a sort by start time (ascending) |

---

## Design Notes

### Existing code to reuse
- `ClockInterface` — inject for "now" comparison; fake via anonymous class (same pattern as `BookRoomUseCaseTest`)
- `Reservation::isOrganizedBy(string)` — delegate organizer check to aggregate
- `Reservation::hasStarted(DateTimeImmutable)` — delegate past-start check to aggregate
- `Timeslot::$start` — public field, used for ascending sort
- `ReservationId`, `Timeslot` — use directly in test fixtures (anonymous class constructor)

### New port method needed
Add to `ReservationRepositoryInterface`:
```php
/** @return Reservation[] */
public function findByOrganizerId(string $organizerId): array;
```

### Architecture decisions
- **Sociable unit test** — real `Reservation` + `Timeslot` aggregates, fake repository via anonymous class
- **No mocking frameworks** — inline anonymous class fakes only
- **Clock abstraction** — `ClockInterface` injected, faked with `fixedClock()` helper
- **Ordering** — use case is responsible for sorting; fake repository returns items in reverse order in test 6 to force this
- **Status neutrality** — the filter is time-based only: a future CANCELLED reservation appears; a past CONFIRMED one does not
- **Return type** — `Reservation[]` (array); the use case returns the filtered, sorted array directly

### Seed data for tests
```
"now" (FixedClock): 2026-03-09 08:00:00
Past reservation start: 2026-03-05 09:00:00 (before now)
Future reservation 1 start: 2026-03-09 14:00:00 (same day, after now)
Future reservation 2 start: 2026-03-10 10:00:00 (next day)
```

---

## Next step

```
use the tdd agent to implement: A user can view their own reservations — use the test list in docs/viewownreservations-test-list.md
```
