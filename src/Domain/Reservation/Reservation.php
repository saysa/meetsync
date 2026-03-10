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
