<?php

declare(strict_types=1);

namespace App\Application\Query;

final readonly class GetMyReservationsQuery
{
    public function __construct(
        public string $organizerId,
    ) {}
}
