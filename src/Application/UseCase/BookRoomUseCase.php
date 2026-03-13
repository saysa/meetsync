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
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;

final class BookRoomUseCase
{
    private const string BOOKING_HORIZON = '+90 days';
    private const string MIN_ADVANCE_NOTICE = '+30 minutes';

    public function __construct(
        private readonly RoomRepositoryInterface $roomRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly ClockInterface $clock,
        private readonly EmailNotifierInterface $emailNotifier,
    ) {}

    public function execute(BookRoomCommand $command): ReservationId
    {
        $roomId = new RoomId($command->roomId);
        $now = $this->clock->now();

        $room = $this->roomRepository->findById($roomId);
        if ($room === null) {
            throw new RoomNotFoundException();
        }

        if (!$room->canAccommodate($command->participantCount)) {
            throw new RoomCapacityExceededException();
        }

        if ($command->start > $now->modify(self::BOOKING_HORIZON)) {
            throw new BookingHorizonExceededException();
        }

        $newTimeslot = $room->createTimeslot($command->start, $command->end);

        if ($command->start < $now->modify(self::MIN_ADVANCE_NOTICE)) {
            throw new InsufficientAdvanceNoticeException();
        }

        foreach ($this->reservationRepository->findByRoomId($roomId) as $existing) {
            if ($existing->conflictsWith($newTimeslot)) {
                throw new TimeslotConflictException();
            }
        }

        $reservationId = ReservationId::generate();

        $reservation = Reservation::create(
            id: $reservationId,
            roomId: $roomId,
            organizerId: $command->organizerEmail,
            timeslot: $newTimeslot,
        );

        $this->reservationRepository->save($reservation);

        try {
            $this->emailNotifier->sendConfirmation(
                $command->organizerEmail,
                $command->roomId,
                $command->start,
                $command->end,
            );
        } catch (\Throwable) {
            // Best-effort: notification failure must not abort the reservation
        }

        return $reservationId;
    }
}
