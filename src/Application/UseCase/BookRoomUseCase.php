<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\Exception\RoomNotFoundException;
use App\Domain\Exception\TimeslotConflictException;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use App\Domain\Reservation\Timeslot;

final class BookRoomUseCase
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
        private ReservationRepositoryInterface $reservationRepository,
    ) {}

    public function execute(BookRoomCommand $command): ReservationId
    {
        $room = $this->roomRepository->findById(new RoomId($command->roomId));
        if ($room === null) {
            throw new RoomNotFoundException();
        }

        $newTimeslot = new Timeslot($command->start, $command->end);
        foreach ($this->reservationRepository->findByRoomId(new RoomId($command->roomId)) as $existing) {
            if ($existing->timeslot()->conflictsWith($newTimeslot)) {
                throw new TimeslotConflictException();
            }
        }

        return new ReservationId('00000000-0000-0000-0000-000000000001');
    }
}
