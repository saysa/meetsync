<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final readonly class Room
{
    public function __construct(
        private RoomId $id,
        private int $capacity,
        private DateTimeImmutable $openingTime,
        private DateTimeImmutable $closingTime,
    ) {}

    public function toSnapshot(): RoomSnapshot
    {
        return new RoomSnapshot(
            id: $this->id->value,
            capacity: $this->capacity,
            openingTime: $this->openingTime,
            closingTime: $this->closingTime,
        );
    }

    public static function fromSnapshot(RoomSnapshot $snapshot): self
    {
        return new self(
            id: new RoomId($snapshot->id),
            capacity: $snapshot->capacity,
            openingTime: $snapshot->openingTime,
            closingTime: $snapshot->closingTime,
        );
    }

    public function canAccommodate(int $participantCount): bool
    {
        return $participantCount <= $this->capacity;
    }

    public function createTimeslot(DateTimeImmutable $start, DateTimeImmutable $end): Timeslot
    {
        return new Timeslot($start, $end, $this->openingTime, $this->closingTime);
    }
}
