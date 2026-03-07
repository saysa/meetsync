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
        ?DateTimeImmutable $openingTime = null,
        ?DateTimeImmutable $closingTime = null,
    ) {
        if ($start == $end) {
            throw new InvalidTimeslotException();
        }

        if ($openingTime !== null && $start < $openingTime) {
            throw new InvalidTimeslotException();
        }
    }

    public function conflictsWith(Timeslot $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
