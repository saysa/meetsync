<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final readonly class Room
{
    public function __construct(
        public int $capacity,
        public DateTimeImmutable $openingTime,
        public DateTimeImmutable $closingTime,
    ) {}
}
