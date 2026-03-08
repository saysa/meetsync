<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

final class Reservation
{
    public function __construct(
        private ReservationId $id,
        private string $organizerId,
        private Timeslot $timeslot,
    ) {}

    public function conflictsWith(Timeslot $other): bool
    {
        return $this->timeslot->conflictsWith($other);
    }
}
