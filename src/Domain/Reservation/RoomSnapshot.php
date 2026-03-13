<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final readonly class RoomSnapshot
{
    public function __construct(
        public string $id,
        public int $capacity,
        public DateTimeImmutable $openingTime,
        public DateTimeImmutable $closingTime,
    ) {}
}
