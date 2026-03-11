<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use DateTimeImmutable;

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
