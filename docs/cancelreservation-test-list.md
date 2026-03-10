# Test List — CancelReservation Use Case

**Requirement:** An organizer can cancel a future confirmed reservation
**Type:** UNIT · **BC:** Reservation Management
**Target:** `src/Application/UseCase/CancelReservationUseCase.php`
**Agent:** tdd-analyze output — 2026-03-09

---

## Files to create

| File | Role |
|---|---|
| `tests/Unit/Application/UseCase/CancelReservationUseCaseTest.php` | Test class (write first) |
| `src/Application/UseCase/CancelReservationUseCase.php` | Use case |
| `src/Application/Command/CancelReservationCommand.php` | Input DTO |
| `src/Application/Exception/ReservationNotFoundException.php` | Application exception |
| `src/Domain/Exception/NotTheOrganizerException.php` | Domain exception |
| `src/Domain/Exception/ReservationAlreadyStartedException.php` | Domain exception |

## Files to extend

| File | What TDD will force |
|---|---|
| `src/Domain/Reservation/Reservation.php` | `id()`, `organizerId()`, `cancel()`, `isCancelled()` |
| `src/Domain/Reservation/ReservationRepositoryInterface.php` | `findById(ReservationId): ?Reservation`, `save(Reservation): void` |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should cancel a confirmed reservation when the organizer requests the cancellation before it starts — capturing fake, isCancelled() asserted |
| 2 | ✅ DONE | should reject the cancellation when the reservation does not exist |
| 3 | NOT DONE | should reject the cancellation when the requester is not the organizer of the reservation |
| 4 | NOT DONE | should reject the cancellation when the reservation has already started |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — void, no exception, can be satisfied by doing nothing |
| 2 | conditional (4) | Forces `findById()` on repo + null check → ReservationNotFoundException |
| 3 | conditional (4) | Forces `organizerId` on Reservation + identity check → NotTheOrganizerException |
| 4 | conditional (4) | Forces clock + timeslot start comparison → ReservationAlreadyStartedException |

---

## Design Notes

### Existing code to reuse
- `ClockInterface` — injected for "already started" check (test #4)
- `ReservationId`, `Timeslot` — already implemented
- `DomainException` base — all new domain exceptions extend this
- Pattern `BookRoomUseCaseTest` — same inline fake anonymous classes approach

### Command signature
```php
CancelReservationCommand(string $reservationId, string $requesterId)
```

### Reservation evolution (driven by tests)
- Test #1 forces: `findById()` + basic wiring
- Test #3 forces: `organizerId()` on Reservation
- Test #4 forces: `timeslot().start()` readable + clock comparison

---

## Next step

```
use the tdd agent to implement: CancelReservation use case — use the test list in docs/cancelreservation-test-list.md
```
