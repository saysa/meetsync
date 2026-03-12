# Test List — Email Notifications (BookRoom + CancelReservation)

**Requirement:** The organizer receives an email confirmation when a reservation is confirmed, and a cancellation email when a reservation is cancelled
**Type:** UNIT · **BC:** Reservation Management
**Target:** `BookRoomUseCase` + `CancelReservationUseCase` (email as a secondary port side-effect)
**Agent:** tdd-analyze output — 2026-03-11

---

## Design framing

The spec (BC4 rule 8) mandates best-effort notifications: "A failed notification does not affect
the reservation state." This means:

- The email port is a secondary port injected via constructor.
- The use case catches the notifier's exception silently after the domain action succeeds.
- Tests use an inline Spy (anonymous class) that records calls without real I/O.

The confirmation email is sent from `BookRoomUseCase`, the cancellation email from
`CancelReservationUseCase`. Both use cases receive `EmailNotifierInterface` as a new
constructor dependency.

Test 1 forces `organizerEmail` to be added to `BookRoomCommand`.
Test 4 forces `requesterEmail` to be added to `CancelReservationCommand`.

---

## Files to create

| File | Role |
|---|---|
| `src/Domain/Notification/EmailNotifierInterface.php` | Secondary port — `sendConfirmation()` + `sendCancellation()` |
| `tests/Unit/Application/UseCase/BookRoomEmailNotificationTest.php` | Tests 1–3 |
| `tests/Unit/Application/UseCase/CancelReservationEmailNotificationTest.php` | Tests 4–6 |

## Files to extend

| File | What TDD will force |
|---|---|
| `src/Application/Command/BookRoomCommand.php` | Add `organizerEmail: string` |
| `src/Application/Command/CancelReservationCommand.php` | Add `requesterEmail: string` |
| `src/Application/UseCase/BookRoomUseCase.php` | Inject `EmailNotifierInterface`, call after success, catch on failure |
| `src/Application/UseCase/CancelReservationUseCase.php` | Inject `EmailNotifierInterface`, call after cancel+save, catch on failure |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should send a confirmation email to the organizer when a room booking succeeds |
| 2 | ✅ DONE | should not send a confirmation email when the booking fails because the room is already taken |
| 3 | ✅ DONE | should confirm the reservation when the notification cannot be delivered |
| 4 | ✅ DONE | should send a cancellation email to the organizer when a reservation is successfully cancelled |
| 5 | NOT DONE | should not send any cancellation email when the cancellation is rejected because the requester is not the organizer |
| 6 | NOT DONE | should cancel the reservation when the notification cannot be delivered |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — establishes that the notifier's `sendConfirmation()` is called once on the happy path; can be satisfied by always calling it |
| 2 | unconditional → conditional (4) | The unconditional call from test 1 is wrong when the use case throws — forces the notifier call to be placed only after all domain guards pass |
| 3 | unconditional → conditional (4) | Forces try/catch around the notifier call — without this test, a notifier that throws would abort the reservation |
| 4 | nil → constant (2) | New use case context (`CancelReservationUseCase`) — establishes that `sendCancellation()` is called once on the happy cancel path |
| 5 | unconditional → conditional (4) | Forces the notifier call to be placed only after the authorization guard (`isOrganizedBy`) passes |
| 6 | unconditional → conditional (4) | Mirrors test 3 for the cancel path — forces try/catch around the notifier call in `CancelReservationUseCase` |

---

## Interface sketch (for reference — do NOT implement before tests force it)

```php
// src/Domain/Notification/EmailNotifierInterface.php
interface EmailNotifierInterface
{
    public function sendConfirmation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void;

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void;
}
```

The Spy anonymous class pattern (same style as existing tests):

```php
$spy = new class implements EmailNotifierInterface {
    public ?string $confirmationSentTo = null;
    public ?string $cancellationSentTo = null;

    public function sendConfirmation(string $organizerEmail, ...): void
    {
        $this->confirmationSentTo = $organizerEmail;
    }

    public function sendCancellation(string $organizerEmail, ...): void
    {
        $this->cancellationSentTo = $organizerEmail;
    }
};
```

The "unavailable" notifier (tests 3 and 6):

```php
$failingNotifier = new class implements EmailNotifierInterface {
    public function sendConfirmation(string $organizerEmail, ...): void
    {
        throw new \RuntimeException('SMTP unreachable');
    }

    public function sendCancellation(string $organizerEmail, ...): void
    {
        throw new \RuntimeException('SMTP unreachable');
    }
};
```

---

## Design Notes

### Existing code to reuse
- `BookRoomUseCase` — receives `EmailNotifierInterface` as 4th constructor dependency
- `CancelReservationUseCase` — receives `EmailNotifierInterface` as 3rd constructor dependency
- `fixedClock()`, `eiffelRoom()`, `emptyReservationRepository()` — same helpers as `BookRoomUseCaseTest`
- `DomainException` base — `TimeslotConflictException` already extends it (used to trigger rejection in test 2)
- Inline anonymous class fakes — consistent with all existing use case tests (no standalone `Fakes/` files)

### Architecture decisions
- **Best-effort**: try/catch in the use case around `$this->emailNotifier->sendX(...)` — failure is swallowed (Iteration 1: no logging port yet)
- **Port location**: `src/Domain/Notification/EmailNotifierInterface.php` — notifications belong to the domain layer as an outbound port
- **Data passed to notifier**: `organizerEmail`, `roomId`, `start`, `end` — minimal set forced by the Gherkin subject line (`"Reservation confirmed: Eiffel – 2026-03-09 14:00–15:00"`)
- **`BookRoomCommand` extension**: adding `organizerEmail` is the minimal change; the `BookRoomUseCase` test suite (#1–#12) does not assert on email, so existing tests remain green after the command gains a new named constructor argument with a default of `''` during the green phase

### Test isolation strategy
- Tests 1–3 go in `BookRoomEmailNotificationTest.php` (separate file from `BookRoomUseCaseTest.php`) to avoid coupling the happy-path suite to the notification suite
- Tests 4–6 go in `CancelReservationEmailNotificationTest.php` for the same reason
- Each test constructs its own fresh use case instance — no `setUp()` shared state

---

## Next step

```
use the tdd agent to implement: Email notifications — use the test list in docs/email-test-list.md
```
