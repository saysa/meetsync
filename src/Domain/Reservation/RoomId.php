<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

final readonly class RoomId
{
    public function __construct(public string $value) {}
}
