<?php

declare(strict_types=1);

namespace App\Application\Command;

class CancelReservationCommand
{
    public function __construct(
        public string $reservationId,
        public string $requesterId,
    ) {}
}
