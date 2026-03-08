<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

class Reservation
{
    public function __construct(
        private ReservationId $id,
        private string $organizerId,
        private Timeslot $timeslot,
    ) {}

    public function timeslot(): Timeslot
    {
        return $this->timeslot;
    }
}
