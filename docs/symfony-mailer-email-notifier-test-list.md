# Test List — SymfonyMailerEmailNotifier (Integration)

**Requirement:** `SymfonyMailerEmailNotifier` implements `EmailNotifierInterface` using Symfony Mailer — emails are dispatched with the correct recipient, subject, and body content
**Type:** INTEGRATION (validates a secondary adapter against real Symfony Mailer infrastructure)
**BC:** Reservation Management
**Feature Area:** Email notification adapter
**Agent:** tdd-analyze output — 2026-03-14

---

## Prerequisites (before the first `go red`)

`symfony/mailer` is **not yet in `composer.json`**. The following infrastructure setup must be done once before running any test in this list — these are configuration steps, not TDD cycles:

1. `composer require symfony/mailer` (adds the package and creates `config/packages/mailer.yaml`)
2. Add the `when@test` DSN override to `config/packages/mailer.yaml`:
   ```yaml
   framework:
       mailer:
           dsn: '%env(MAILER_DSN)%'

   when@test:
       framework:
           mailer:
               dsn: 'null://null'
   ```
3. Verify `MailerAssertionsTrait` is available via `KernelTestCase` (it is, in Symfony 7.4).

No DB is involved — no transaction setup or teardown needed.

---

## Design framing

`SymfonyMailerEmailNotifier` is a pure infrastructure adapter. It has no business logic of its own. Each test:
- Calls one method on the adapter (either `sendConfirmation` or `sendCancellation`)
- Asserts on what Symfony's in-memory transport captured

The adapter receives `MailerInterface` (Symfony) via constructor injection. In the test environment, the `null://null` DSN routes all emails to an in-memory transport that `MailerAssertionsTrait` can inspect — no SMTP server needed.

Seed data follows the project personas:
- Organizer: `alice@example.com`
- Room: `eiffel`
- Start: `2026-03-09 10:00:00 UTC`
- End: `2026-03-09 11:00:00 UTC`

The `from` address is an adapter-level concern (not mandated by the port interface). A fixed sender such as `noreply@meetsync.app` is expected; tests do not assert on it to avoid over-specifying adapter internals beyond what the port contract requires.

---

## Files to create

| File | Role |
|---|---|
| `src/Infrastructure/Adapters/Secondary/Mailer/SymfonyMailerEmailNotifier.php` | Implements `EmailNotifierInterface` using `MailerInterface` + `Email` |
| `tests/Integration/Infrastructure/Adapters/Secondary/Mailer/SymfonyMailerEmailNotifierTest.php` | Integration tests using `MailerAssertionsTrait` |

## Files to modify

| File | What TDD will force |
|---|---|
| `config/packages/mailer.yaml` | Created by `composer require symfony/mailer`; add `when@test` DSN |
| `composer.json` | Add `symfony/mailer` dependency |

---

## Ordered Test List (TPP + FLFI)

| # | Status | Test |
|---|---|---|
| 1 | ✅ DONE | should deliver exactly one email to the organizer when a booking confirmation is requested |
| 2 | | should use a subject that identifies the reservation as confirmed when a booking confirmation is requested |
| 3 | | should include the room identifier and the time window in the body when a booking confirmation is requested |
| 4 | | should deliver exactly one email to the organizer when a cancellation notification is requested |
| 5 | | should use a subject that identifies the reservation as cancelled when a cancellation notification is requested |
| 6 | | should include the room identifier and the time window in the body when a cancellation notification is requested |

---

## TPP + Contradiction Notes

| # | TPP | Contradiction introduced |
|---|---|---|
| 1 | nil → constant (2) | Baseline — establishes that `sendConfirmation()` causes exactly one email to be dispatched; can be satisfied by calling `$mailer->send(new Email())` unconditionally |
| 2 | constant → variable (3) | The subject returned by the stub from Test 1 is wrong (empty or static); forces the implementation to build a subject string from the method arguments |
| 3 | constant → variable (3) | The body produced by Test 1/2 stubs omits required data; forces the body to incorporate `$roomId`, `$start`, and `$end` |
| 4 | nil → constant (2) | New method context (`sendCancellation`) — establishes that calling it also dispatches exactly one email; can be satisfied by delegating to the same internal send helper (no new TPP step beyond the baseline of the new method) |
| 5 | constant → variable (3) | The "confirmed" subject from Tests 1–2 is wrong for cancellations; forces the subject to carry the "cancelled" label distinct from the "confirmed" one |
| 6 | constant → variable (3) | Mirrors Test 3 for the cancellation path — forces the cancellation body to incorporate `$roomId`, `$start`, and `$end` |

---

## Detailed test specifications

### Test 1 — one email delivered to the organizer on confirmation

```php
#[Test]
public function should_deliver_exactly_one_email_to_the_organizer_when_a_booking_confirmation_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendConfirmation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailAddressContains($email, 'to', 'alice@example.com');
}
```

### Test 2 — subject contains "confirmed" on confirmation

```php
#[Test]
public function should_use_a_subject_that_identifies_the_reservation_as_confirmed_when_a_booking_confirmation_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendConfirmation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailSubjectContains($email, 'confirmed');
}
```

### Test 3 — body contains room and time window on confirmation

```php
#[Test]
public function should_include_the_room_identifier_and_the_time_window_in_the_body_when_a_booking_confirmation_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendConfirmation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailTextBodyContains($email, 'eiffel');
    self::assertEmailTextBodyContains($email, '2026-03-09');
    self::assertEmailTextBodyContains($email, '10:00');
    self::assertEmailTextBodyContains($email, '11:00');
}
```

### Test 4 — one email delivered to the organizer on cancellation

```php
#[Test]
public function should_deliver_exactly_one_email_to_the_organizer_when_a_cancellation_notification_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendCancellation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailAddressContains($email, 'to', 'alice@example.com');
}
```

### Test 5 — subject contains "cancelled" on cancellation

```php
#[Test]
public function should_use_a_subject_that_identifies_the_reservation_as_cancelled_when_a_cancellation_notification_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendCancellation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailSubjectContains($email, 'cancelled');
}
```

### Test 6 — body contains room and time window on cancellation

```php
#[Test]
public function should_include_the_room_identifier_and_the_time_window_in_the_body_when_a_cancellation_notification_is_requested(): void
{
    // Given
    $notifier = static::getContainer()->get(SymfonyMailerEmailNotifier::class);

    // When
    $notifier->sendCancellation(
        organizerEmail: 'alice@example.com',
        roomId: 'eiffel',
        start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
        end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
    );

    // Then
    self::assertEmailCount(1);
    $email = $this->getMailerMessage(0);
    self::assertEmailTextBodyContains($email, 'eiffel');
    self::assertEmailTextBodyContains($email, '2026-03-09');
    self::assertEmailTextBodyContains($email, '10:00');
    self::assertEmailTextBodyContains($email, '11:00');
}
```

---

## Design Notes

### Existing code to reuse
- `EmailNotifierInterface` — `src/Domain/Notification/EmailNotifierInterface.php` (already implemented)
- `KernelTestCase` + `MailerAssertionsTrait` — both available in `symfony/framework-bundle` 7.4; `MailerAssertionsTrait` is used via `KernelTestCase` (the trait is mixed in automatically when the mailer component is present)
- Namespace convention observed in `DoctrineReservationRepositoryTest`: `App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine\` — apply the same pattern: `App\Tests\Integration\Infrastructure\Adapters\Secondary\Mailer\`

### New code to create
- `SymfonyMailerEmailNotifier` — constructor receives `Symfony\Component\Mailer\MailerInterface`; each method builds a `Symfony\Component\Mime\Email` object and calls `$this->mailer->send($email)`
- `config/packages/mailer.yaml` — created by Flex recipe; add `when@test: dsn: 'null://null'` block
- No Doctrine entity, no transaction management — the adapter is stateless

### Test class structure
```php
// tests/Integration/Infrastructure/Adapters/Secondary/Mailer/SymfonyMailerEmailNotifierTest.php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Mailer;

use App\Infrastructure\Adapters\Secondary\Mailer\SymfonyMailerEmailNotifier;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SymfonyMailerEmailNotifierTest extends KernelTestCase
{
    // No setUp/tearDown needed:
    // - No DB → no transaction to manage
    // - KernelTestCase::tearDown() resets the mailer transport between tests automatically
    // - getMailerMessage() and assertEmailCount() come from MailerAssertionsTrait,
    //   mixed into KernelTestCase when symfony/mailer is installed

    // Six tests as specified above
}
```

### Patterns observed from existing integration tests
- `self::bootKernel()` is called implicitly by `static::getContainer()` — no explicit `bootKernel()` call needed when using `getContainer()` directly
- `static::getContainer()->get(ClassName::class)` retrieves autowired services
- `final class` for the test class (consistent with all existing tests)
- `PHPUnit\Framework\Attributes\Test` attribute (never `@test` annotation)
- Method names: `should_[outcome]_when_[condition]` (snake_case)
- Seed data uses project personas: `alice@example.com`, room `eiffel`, `2026-03-09 10:00/11:00 UTC`

### Why no TPP jump between tests 1→2 and 1→3
Tests 2 and 3 do NOT contradict each other — they refine orthogonal properties (subject vs body). The natural grouping is: Test 1 forces the email to exist and reach the correct recipient; Test 2 forces the subject to carry semantic meaning; Test 3 forces the body to carry data. Each requires one additional `constant → variable` step from the previous.

### `getMailerMessage()` vs `getMailerMessages()`
- `$this->getMailerMessage(0)` — retrieves the first (index 0) captured message as a `RawMessage`
- `self::assertEmailCount(1)` — asserts exactly one email was dispatched since the kernel booted for this test
- Both come from `Symfony\Component\Mailer\Test\Constraint\EmailCount` and `MailerAssertionsTrait`

---

## Next step

```
use the tdd agent to implement: SymfonyMailerEmailNotifier integration tests — use the test list in docs/symfony-mailer-email-notifier-test-list.md
```
