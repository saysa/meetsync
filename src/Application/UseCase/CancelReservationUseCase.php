<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Domain\Clock\ClockInterface;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;

class CancelReservationUseCase
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private ClockInterface $clock,
    ) {}

    public function execute(CancelReservationCommand $command): void
    {
        $this->reservationRepository->findById(new ReservationId($command->reservationId));
    }
}
