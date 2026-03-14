<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Mailer;

use App\Domain\Notification\EmailNotifierInterface;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;

final class SymfonyMailerEmailNotifier implements EmailNotifierInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function sendConfirmation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
    }

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
    }
}
