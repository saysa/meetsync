<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Query\GetMyReservationsQuery;
use App\Domain\Clock\ClockInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationRepositoryInterface;

final class GetMyReservationsUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ClockInterface $clock,
    ) {}

    /** @return Reservation[] */
    public function execute(GetMyReservationsQuery $query): array
    {
        return [];
    }
}
