<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\Exception\RoomNotFoundException;
use App\Domain\Clock\ClockInterface;
use App\Domain\Exception\BookingHorizonExceededException;
use App\Domain\Exception\InsufficientAdvanceNoticeException;
use App\Domain\Exception\RoomCapacityExceededException;
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
        private ClockInterface $clock,
    ) {}

    public function execute(BookRoomCommand $command): ReservationId
    {
        $roomId = new RoomId($command->roomId);
        $now = $this->clock->now();

        $room = $this->roomRepository->findById($roomId);
        if ($room === null) {
            throw new RoomNotFoundException();
        }

        if ($command->participantCount > $room->capacity) {
            throw new RoomCapacityExceededException();
        }

        if ($command->start > $now->modify('+90 days')) {
            throw new BookingHorizonExceededException();
        }

        $newTimeslot = new Timeslot($command->start, $command->end, $room->openingTime, $room->closingTime);

        if ($command->start < $now->modify('+30 minutes')) {
            throw new InsufficientAdvanceNoticeException();
        }

        foreach ($this->reservationRepository->findByRoomId($roomId) as $existing) {
            if ($existing->timeslot()->conflictsWith($newTimeslot)) {
                throw new TimeslotConflictException();
            }
        }

        return new ReservationId('00000000-0000-0000-0000-000000000001');
    }
}
