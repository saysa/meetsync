# HTTP Controllers — E2E Test List

Generated: 2026-03-15

---

## Test List Analysis

**Requirement:** "HTTP controllers E2E — POST /reservations (BookRoom), DELETE /reservations/{id} (CancelReservation), GET /reservations (GetMyReservations)"
**Test Type:** E2E (HTTP endpoints, WebTestCase, request/response assertions)
**Bounded Context:** Reservation (single BC in Iteration 1)
**Feature Area:** Primary HTTP adapters — three controllers wiring use cases to HTTP

---

## Architecture & Wiring Notes

**Test mode:** `fake` — WebTestCase with InMemory adapters injected via `static::getContainer()->set(...)`.
No real database. No real mailer. Both repos and the email notifier are replaced in-container per test.

**Golden rule (tdd-e2e-patterns):** Tests interact ONLY via HTTP. Zero domain imports in test code.
Assertions are HTTP-only: status codes + JSON body keys/values.
Repository access in tests is allowed solely to pre-seed state (given) — never to assert on domain objects.

**Clock:** The real `ClockInterface` implementation will be used by the kernel.
Tests that depend on "now" (min-notice, horizon) must control the system clock.
Pattern: wire a fixed-clock fake via `static::getContainer()->set(ClockInterface::class, ...)` in setUp or per-test.

---

## Endpoint Contracts (inferred from use cases + Gherkin)

### POST /reservations
Request body (JSON):
```json
{
  "room_id": "eiffel",
  "start": "2026-03-09T14:00:00+01:00",
  "end": "2026-03-09T15:30:00+01:00",
  "participant_count": 3,
  "organizer_email": "alice.martin@acme.com"
}
```
Success response: `201 Created` + `{ "reservation_id": "<uuid>" }`

### DELETE /reservations/{id}
Request body (JSON):
```json
{
  "requester_id": "alice.martin@acme.com",
  "requester_email": "alice.martin@acme.com"
}
```
Success response: `204 No Content`

### GET /reservations
Query parameter: `?organizer_id=alice.martin@acme.com`
Success response: `200 OK` + JSON array of reservation objects

---

## Ordered Test List (TPP + FLFI)

### SECTION A — POST /reservations (BookRoom)

---

1. **should return 201 with a reservation identifier when booking a free room with valid data**
   - TPP: nil → constant (2) — the endpoint does not exist yet; first test establishes the baseline response shape
   - Contradiction: none (establishes baseline — can be satisfied by returning a hard-coded 201 + dummy UUID)
   - Given: Eiffel room seeded in `InMemoryRoomRepository`; empty `InMemoryReservationRepository`; fixed clock at 2026-03-09 08:00
   - When: `POST /reservations` with `room_id=eiffel`, `start=2026-03-09T14:00:00`, `end=2026-03-09T15:30:00`, `participant_count=2`, `organizer_email=alice.martin@acme.com`
   - Then: HTTP 201; body contains a non-empty string `reservation_id`

---

2. **should return 404 when booking a room that does not exist in the system**
   - TPP: unconditional → conditional (4)
   - Contradiction: the unconditional 201 from test 1 is wrong when the room is unknown → forces a conditional branch on room lookup
   - Given: empty `InMemoryRoomRepository`; fixed clock at 2026-03-09 08:00
   - When: `POST /reservations` with `room_id=unknown-room`, any valid dates
   - Then: HTTP 404

---

3. **should return 409 when the requested timeslot conflicts with an existing confirmed reservation for the same room**
   - TPP: unconditional → conditional (4)
   - Contradiction: the happy-path code from test 1 always books successfully — it cannot handle a pre-existing reservation that overlaps → forces conflict detection path
   - Given: Eiffel seeded; existing confirmed reservation for Eiffel on 2026-03-09 from 10:00 to 12:00 pre-loaded in `InMemoryReservationRepository`; fixed clock at 2026-03-09 08:00
   - When: `POST /reservations` with `room_id=eiffel`, `start=2026-03-09T10:30:00`, `end=2026-03-09T11:30:00`, `participant_count=1`
   - Then: HTTP 409

---

4. **should return 422 when the participant count exceeds the room capacity**
   - TPP: unconditional → conditional (4)
   - Contradiction: the code that passes test 3 still doesn't validate capacity → forces a capacity-check branch
   - Given: Montmartre room seeded (capacity 4); empty reservation repo; fixed clock at 2026-03-09 08:00
   - When: `POST /reservations` with `room_id=montmartre`, valid dates, `participant_count=5`
   - Then: HTTP 422

---

5. **should return 422 when the booking start time is more than 90 days in the future**
   - TPP: unconditional → conditional (4)
   - Contradiction: capacity checks pass but the horizon is not yet enforced → forces a booking-horizon branch
   - Given: Eiffel seeded; empty repo; fixed clock at 2026-03-09 09:00
   - When: `POST /reservations` with `room_id=eiffel`, `start=2026-06-08T10:00:00` (91 days ahead), valid end
   - Then: HTTP 422

---

6. **should return 422 when the booking start time is fewer than 30 minutes from now**
   - TPP: unconditional → conditional (4)
   - Contradiction: horizon check passes but minimum-notice is not yet enforced → forces the advance-notice branch
   - Given: Eiffel seeded; empty repo; fixed clock at 2026-03-09 09:40
   - When: `POST /reservations` with `room_id=eiffel`, `start=2026-03-09T10:00:00` (only 20 minutes ahead), valid end
   - Then: HTTP 422

---

7. **should return 422 when the requested timeslot falls outside the building operating hours**
   - TPP: unconditional → conditional (4)
   - Contradiction: all previous checks pass for in-hours bookings; operating-hours enforcement has not been exercised via HTTP yet → forces the out-of-hours branch through the controller
   - Given: Eiffel seeded; empty repo; fixed clock at 2026-03-09 07:00
   - When: `POST /reservations` with `room_id=eiffel`, `start=2026-03-09T07:30:00`, `end=2026-03-09T09:00:00`
   - Then: HTTP 422

---

### SECTION B — DELETE /reservations/{id} (CancelReservation)

---

8. **should return 204 when the organizer cancels a confirmed reservation before it starts**
   - TPP: nil → constant (2) — the DELETE endpoint does not exist yet; first test establishes the baseline 204 response
   - Contradiction: none (establishes baseline — can be satisfied by always returning 204)
   - Given: confirmed reservation (id=`res-001`) for alice.martin@acme.com, starting 2026-03-09 at 14:00, pre-seeded; fixed clock at 2026-03-09 08:00
   - When: `DELETE /reservations/res-001` with body `{ "requester_id": "alice.martin@acme.com", "requester_email": "alice.martin@acme.com" }`
   - Then: HTTP 204

---

9. **should return 404 when attempting to cancel a reservation that does not exist**
   - TPP: unconditional → conditional (4)
   - Contradiction: the unconditional 204 from test 8 is wrong when the reservation is unknown → forces a lookup branch
   - Given: empty reservation repo; fixed clock at 2026-03-09 08:00
   - When: `DELETE /reservations/non-existent-id` with any requester body
   - Then: HTTP 404

---

10. **should return 403 when someone other than the organizer attempts to cancel a reservation**
    - TPP: unconditional → conditional (4)
    - Contradiction: the 204 code from test 8 never checks identity — it succeeds for any requester → forces the organizer-check branch
    - Given: confirmed reservation for alice.martin@acme.com, starting 2026-03-09 at 14:00, pre-seeded; fixed clock at 2026-03-09 08:00
    - When: `DELETE /reservations/res-001` with body `{ "requester_id": "bob.chen@acme.com", "requester_email": "bob.chen@acme.com" }`
    - Then: HTTP 403

---

11. **should return 409 when the organizer attempts to cancel a reservation that has already started**
    - TPP: unconditional → conditional (4)
    - Contradiction: the organizer check passes but the already-started rule is not yet enforced through HTTP → forces the started-reservation branch
    - Given: confirmed reservation for alice.martin@acme.com, starting 2026-03-09 at 14:00, pre-seeded; fixed clock at 2026-03-09 14:20 (after start)
    - When: `DELETE /reservations/res-001` with body `{ "requester_id": "alice.martin@acme.com", "requester_email": "alice.martin@acme.com" }`
    - Then: HTTP 409

---

### SECTION C — GET /reservations (GetMyReservations)

---

12. **should return 200 with an empty list when the organizer has no upcoming reservations**
    - TPP: nil → constant (2) — the GET endpoint does not exist yet; an empty list is the simplest response
    - Contradiction: none (establishes baseline — can be satisfied by always returning `[]`)
    - Given: empty reservation repo; fixed clock at 2026-03-09 08:00
    - When: `GET /reservations?organizer_id=alice.martin@acme.com`
    - Then: HTTP 200; body is an empty JSON array `[]`

---

13. **should return 200 with the organizer's upcoming reservations ordered by start time when reservations exist**
    - TPP: constant → variable (3)
    - Contradiction: the constant empty-array response from test 12 is wrong when reservations exist → forces real lookup and return of reservation data
    - Given: two confirmed future reservations for alice.martin@acme.com (Eiffel 2026-03-09 14:00–15:00 and Louvre 2026-03-10 10:00–12:00), in reverse insertion order; fixed clock at 2026-03-09 08:00
    - When: `GET /reservations?organizer_id=alice.martin@acme.com`
    - Then: HTTP 200; body is a JSON array with 2 items; first item has `start` corresponding to 2026-03-09 14:00 (chronological order); each item includes at minimum `reservation_id`, `room_id`, `start`, `end`, `status`

---

14. **should return 200 with only future reservations when past reservations also exist for the organizer**
    - TPP: unconditional → conditional (4)
    - Contradiction: the code from test 13 returns all reservations — it does not filter out past ones → forces the time-filter branch
    - Given: one past reservation for alice.martin@acme.com (Montmartre 2026-03-05 09:00–10:00) and one future reservation (Eiffel 2026-03-09 14:00–15:00); fixed clock at 2026-03-09 08:00
    - When: `GET /reservations?organizer_id=alice.martin@acme.com`
    - Then: HTTP 200; body is a JSON array with exactly 1 item; the Montmartre past reservation does not appear

---

15. **should return 200 with only the requesting organizer's reservations when multiple organizers have bookings**
    - TPP: unconditional → conditional (4)
    - Contradiction: the filter from test 14 filters by time but has not yet been proven to scope per organizer through HTTP → forces the organizer-isolation branch to be exercised end-to-end
    - Given: one future reservation for alice.martin@acme.com (Eiffel 2026-03-09 14:00–15:00) and one future reservation for bob.chen@acme.com (Louvre 2026-03-11 09:00–10:00); fixed clock at 2026-03-09 08:00
    - When: `GET /reservations?organizer_id=alice.martin@acme.com`
    - Then: HTTP 200; body contains exactly 1 item; Bob's reservation does not appear

---

## Design Notes

### Existing code to reuse
- `App\Tests\Fixtures\InMemoryReservationRepository` (`tests/Fixtures/InMemoryReservationRepository.php`) — has `add()` for seeding, `findById()`, `findByRoomId()`, `findByOrganizerId()`
- `App\Tests\Fixtures\InMemoryRoomRepository` (`tests/Fixtures/InMemoryRoomRepository.php`) — has `add()` for seeding, `findById()`
- `App\Application\UseCase\BookRoomUseCase`, `CancelReservationUseCase`, `GetMyReservationsUseCase` — fully implemented use cases
- `App\Domain\Reservation\ReservationSnapshot` — used in test given-setup via `Reservation::fromSnapshot()`
- `App\Domain\Reservation\RoomSnapshot` — used in test given-setup via `Room::fromSnapshot()`
- All domain exceptions already exist and extend either `DomainException` or `ApplicationException`

### New code likely needed
1. **Three controllers** (primary adapters, none exist yet):
   - `src/Infrastructure/Adapters/Primary/Http/BookRoomController.php` — handles `POST /reservations`
   - `src/Infrastructure/Adapters/Primary/Http/CancelReservationController.php` — handles `DELETE /reservations/{id}`
   - `src/Infrastructure/Adapters/Primary/Http/GetMyReservationsController.php` — handles `GET /reservations`
2. **Exception listener** to translate domain/application exceptions to HTTP status codes:
   - `src/Infrastructure/Adapters/Primary/Http/EventListener/DomainExceptionListener.php`
   - Must map: `RoomNotFoundException` → 404, `ReservationNotFoundException` → 404, `TimeslotConflictException` → 409, `RoomCapacityExceededException` → 422, `BookingHorizonExceededException` → 422, `InsufficientAdvanceNoticeException` → 422, `InvalidTimeslotException` → 422, `NotTheOrganizerException` → 403, `ReservationAlreadyStartedException` → 409
3. **services.yaml `when@test` additions** to wire InMemory fakes and a fixed clock:
   - `App\Tests\Fixtures\InMemoryReservationRepository: ~` (public: true)
   - `App\Tests\Fixtures\InMemoryRoomRepository: ~` (public: true)
   - Bindings for `ReservationRepositoryInterface` and `RoomRepositoryInterface` to their InMemory fakes
4. **Test file**:
   - `tests/E2E/Http/BookRoomControllerTest.php` (tests 1–7)
   - `tests/E2E/Http/CancelReservationControllerTest.php` (tests 8–11)
   - `tests/E2E/Http/GetMyReservationsControllerTest.php` (tests 12–15)

### Patterns observed
- **WebTestCase**: `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`; `static::createClient()` in `setUp()`; `static::getContainer()->set(InterfaceFQCN::class, $fakeInstance)` for adapter injection
- **Test naming**: `should_[outcome]_when_[condition]` snake_case, `#[Test]` attribute
- **Class**: `final class XxxControllerTest extends WebTestCase`
- **Namespace**: `App\Tests\E2E\Http\` (parallel to `tests/E2E/Http/`)
- **Given seeding**: use `InMemoryReservationRepository::add(Reservation::fromSnapshot(new ReservationSnapshot(...)))` and `InMemoryRoomRepository::add(Room::fromSnapshot(new RoomSnapshot(...)))` — never use `Reservation::create()` in test setup
- **No domain imports** except the two fixture classes and port interfaces (for `::set()` calls)
- **Clock control**: inject a fixed-clock anonymous class implementing `ClockInterface` via `static::getContainer()->set(ClockInterface::class, ...)` for time-sensitive tests
- **Email notifier**: wire a no-op fake for `EmailNotifierInterface` to prevent real mail attempts in E2E tests

### HTTP status code mapping strategy
| Exception class | HTTP status |
|---|---|
| `RoomNotFoundException` | 404 Not Found |
| `ReservationNotFoundException` | 404 Not Found |
| `NotTheOrganizerException` | 403 Forbidden |
| `TimeslotConflictException` | 409 Conflict |
| `ReservationAlreadyStartedException` | 409 Conflict |
| `RoomCapacityExceededException` | 422 Unprocessable Entity |
| `BookingHorizonExceededException` | 422 Unprocessable Entity |
| `InsufficientAdvanceNoticeException` | 422 Unprocessable Entity |
| `InvalidTimeslotException` | 422 Unprocessable Entity |

---

## How to Proceed

- Interactive: `use the tdd agent to implement: HTTP controllers E2E — POST /reservations (BookRoom), DELETE /reservations/{id} (CancelReservation), GET /reservations (GetMyReservations) — use the test list in docs/http-controllers-test-list.md`
- Autonomous: `use the tdd-auto agent to implement: HTTP controllers E2E — POST /reservations (BookRoom), DELETE /reservations/{id} (CancelReservation), GET /reservations (GetMyReservations) — use the test list in docs/http-controllers-test-list.md`

---

## Test Progress

| # | Test | Status |
|---|---|---|
| 1 | should return 201 with a reservation identifier when booking a free room with valid data | ✅ DONE |
| 2 | should return 404 when booking a room that does not exist in the system | ✅ DONE |
| 3 | should return 409 when the requested timeslot conflicts with an existing confirmed reservation for the same room | ✅ DONE |
| 4 | should return 422 when the participant count exceeds the room capacity | ✅ DONE |
| 5 | should return 422 when the booking start time is more than 90 days in the future | ✅ DONE |
| 6 | should return 422 when the booking start time is fewer than 30 minutes from now | ✅ DONE |
| 7 | should return 422 when the requested timeslot falls outside the building operating hours | ✅ DONE |
| 8 | should return 204 when the organizer cancels a confirmed reservation before it starts | ✅ DONE |
| 9 | should return 404 when attempting to cancel a reservation that does not exist | ✅ DONE |
| 10 | should return 403 when someone other than the organizer attempts to cancel a reservation | ✅ DONE |
| 11 | should return 409 when the organizer attempts to cancel a reservation that has already started | ✅ DONE |
| 12 | should return 200 with an empty list when the organizer has no upcoming reservations | ✅ DONE |
| 13 | should return 200 with the organizer's upcoming reservations ordered by start time when reservations exist | ✅ DONE |
| 14 | should return 200 with only future reservations when past reservations also exist for the organizer | ✅ DONE |
| 15 | should return 200 with only the requesting organizer's reservations when multiple organizers have bookings | ✅ DONE |
