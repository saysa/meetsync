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
}
