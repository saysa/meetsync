<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Application\Exception\ReservationNotFoundException;
use App\Domain\Clock\ClockInterface;
use App\Domain\Exception\NotTheOrganizerException;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;

final class CancelReservationUseCase
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(CancelReservationCommand $command): void
    {
        $reservation = $this->reservationRepository->findById(new ReservationId($command->reservationId));
        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }
        if (!$reservation->isOrganizedBy($command->requesterId)) {
            throw new NotTheOrganizerException();
        }
        $reservation->cancel();
        $this->reservationRepository->save($reservation);
    }
}
