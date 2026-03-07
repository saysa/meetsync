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
        public ?DateTimeImmutable $openingTime = null,
        public ?DateTimeImmutable $closingTime = null,
    ) {
        if ($start == $end) {
            throw new InvalidTimeslotException();
        }
    }

    public function conflictsWith(Timeslot $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
