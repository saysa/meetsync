<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

class Timeslot
{
    public $start;
    public $end;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end = $end;
    }
}
