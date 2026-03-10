<?php

declare(strict_types=1);

namespace App\Application\Query;

final class GetMyReservationsQuery
{
    public function __construct(
        public readonly string $organizerId,
    ) {}
}
