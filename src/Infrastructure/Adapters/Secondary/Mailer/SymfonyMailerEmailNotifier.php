<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Mailer;

use App\Domain\Notification\EmailNotifierInterface;
use DateTimeImmutable;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SymfonyMailerEmailNotifier implements EmailNotifierInterface
{
    public function __construct(private readonly MailerInterface $mailer) {}

    public function sendConfirmation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
        $email = (new Email())->from('noreply@meetsync.app')->to($organizerEmail)->subject('Booking confirmed')->text('ok');
        $this->mailer->send($email);
    }

    public function sendCancellation(
        string $organizerEmail,
        string $roomId,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
    ): void {
    }
}
