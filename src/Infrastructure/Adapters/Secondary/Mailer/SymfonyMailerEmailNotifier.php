<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Mailer;

use App\Domain\Notification\EmailNotifierInterface;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyMailerEmailNotifier implements EmailNotifierInterface
{
    private const string SENDER = 'noreply@meetsync.app';

    public function __construct(private readonly MailerInterface $mailer) {}

    public function sendConfirmation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
        $this->mailer->send(
            (new Email())
                ->from(self::SENDER)
                ->to($organizerEmail)
                ->subject('Booking confirmed')
                ->text('ok'),
        );
    }

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
    }
}
