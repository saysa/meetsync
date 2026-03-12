<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final class Reservation
{
    private const string STATUS_CONFIRMED = 'CONFIRMED';

    private bool $cancelled = false;
    private string $roomId = '';

    public function __construct(
        private ReservationId $id,
        private string $organizerId,
        private Timeslot $timeslot,
    ) {}

    public static function create(
        ReservationId $id,
        RoomId $roomId,
        string $organizerId,
        Timeslot $timeslot,
    ): self {
        $reservation = new self($id, $organizerId, $timeslot);
        $reservation->roomId = $roomId->value;
        return $reservation;
    }

    public static function fromSnapshot(ReservationSnapshot $snapshot): self
    {
        $reservation = new self(
            new ReservationId($snapshot->id),
            $snapshot->organizerId,
            new Timeslot($snapshot->start, $snapshot->end),
        );
        $reservation->roomId = $snapshot->roomId;
        $reservation->cancelled = $snapshot->status === 'CANCELLED';
        return $reservation;
    }

    public function toSnapshot(): ReservationSnapshot
    {
        return new ReservationSnapshot(
            id: $this->id->value,
            roomId: $this->roomId,
            organizerId: $this->organizerId,
            status: self::STATUS_CONFIRMED,
            start: $this->timeslot->start,
            end: $this->timeslot->end,
        );
    }

    public function compareStartTimeTo(self $other): int
    {
        return $this->timeslot->start <=> $other->timeslot->start;
    }

    public function hasStarted(DateTimeImmutable $now): bool
    {
        return $now >= $this->timeslot->start;
    }

    public function isOrganizedBy(string $requesterId): bool
    {
        return $this->organizerId === $requesterId;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function conflictsWith(Timeslot $other): bool
    {
        return $this->timeslot->conflictsWith($other);
    }
}
