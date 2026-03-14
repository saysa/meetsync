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
    private const string CONFIRMATION_SUBJECT = 'Booking confirmed';

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
                ->subject(self::CONFIRMATION_SUBJECT)
                ->text($this->buildBody($roomId, $start, $end)),
        );
    }

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
        $this->mailer->send(
            (new Email())
                ->from(self::SENDER)
                ->to($organizerEmail)
                ->subject('Booking cancelled')
                ->text($this->buildBody($roomId, $start, $end)),
        );
    }

    private function buildBody(string $roomId, DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        return sprintf(
            'Room: %s on %s from %s to %s',
            $roomId,
            $start->format('Y-m-d'),
            $start->format('H:i'),
            $end->format('H:i'),
        );
    }
}
