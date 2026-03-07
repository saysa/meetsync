<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

final readonly class Room
{
    public function __construct(public int $capacity = 0) {}
}
