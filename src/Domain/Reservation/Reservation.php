<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final class Reservation
{
    private const string STATUS_CONFIRMED = 'CONFIRMED';
    private const string STATUS_CANCELLED = 'CANCELLED';

    private function __construct(
        private ReservationId $id,
        private string $organizerId,
        private Timeslot $timeslot,
        private RoomId $roomId,
        private bool $cancelled = false,
    ) {}

    public static function create(
        ReservationId $id,
        RoomId $roomId,
        string $organizerId,
        Timeslot $timeslot,
    ): self {
        return new self($id, $organizerId, $timeslot, $roomId);
    }

    public static function fromSnapshot(ReservationSnapshot $snapshot): self
    {
        return new self(
            id: new ReservationId($snapshot->id),
            organizerId: $snapshot->organizerId,
            timeslot: new Timeslot($snapshot->start, $snapshot->end),
            roomId: new RoomId($snapshot->roomId),
            cancelled: $snapshot->status === self::STATUS_CANCELLED,
        );
    }

    public function toSnapshot(): ReservationSnapshot
    {
        return new ReservationSnapshot(
            id: $this->id->value,
            roomId: $this->roomId->value,
            organizerId: $this->organizerId,
            status: $this->cancelled ? self::STATUS_CANCELLED : self::STATUS_CONFIRMED,
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
