<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use DateTimeImmutable;

final class Reservation
{
    private bool $cancelled = false;

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
        return new self($id, $organizerId, $timeslot);
    }

    public function toSnapshot(): ReservationSnapshot
    {
        return new ReservationSnapshot(
            id: $this->id->value,
            roomId: '',
            organizerId: $this->organizerId,
            status: '',
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

    public function timeslotStart(): DateTimeImmutable
    {
        return $this->timeslot->start;
    }

    public function timeslotEnd(): DateTimeImmutable
    {
        return $this->timeslot->end;
    }

    public function conflictsWith(Timeslot $other): bool
    {
        return $this->timeslot->conflictsWith($other);
    }
}
