<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Mailer;

use App\Infrastructure\Adapters\Secondary\Mailer\SymfonyMailerEmailNotifier;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SymfonyMailerEmailNotifierTest extends KernelTestCase
{
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
}
