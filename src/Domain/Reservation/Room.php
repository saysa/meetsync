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

    public function canAccommodate(int $participantCount): bool
    {
        return $participantCount <= $this->capacity;
    }

    public function createTimeslot(DateTimeImmutable $start, DateTimeImmutable $end): Timeslot
    {
        return new Timeslot($start, $end, $this->openingTime, $this->closingTime);
    }
}
