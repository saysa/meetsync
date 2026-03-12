<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final readonly class ReservationSnapshot
{
    public function __construct(
        public string $id,
        public string $roomId,
        public string $organizerId,
        public string $status,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {}
}
