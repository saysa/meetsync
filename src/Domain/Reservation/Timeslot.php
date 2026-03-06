<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use App\Domain\Exception\InvalidTimeslotException;
use DateTimeImmutable;

final readonly class Timeslot
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($start == $end) {
            throw new InvalidTimeslotException();
        }
    }

    public function conflictsWith(Timeslot $other): bool
    {
        return false;
    }
}
