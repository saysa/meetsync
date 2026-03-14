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
    private const string CANCELLATION_SUBJECT = 'Booking cancelled';

    public function __construct(private readonly MailerInterface $mailer) {}

    public function sendConfirmation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
        $this->dispatch($organizerEmail, self::CONFIRMATION_SUBJECT, $this->buildBody($roomId, $start, $end));
    }

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
        $this->dispatch($organizerEmail, self::CANCELLATION_SUBJECT, $this->buildBody($roomId, $start, $end));
    }

    private function dispatch(string $to, string $subject, string $body): void
    {
        $this->mailer->send(
            (new Email())
                ->from(self::SENDER)
                ->to($to)
                ->subject($subject)
                ->text($body),
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
