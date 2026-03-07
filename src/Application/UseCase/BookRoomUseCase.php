<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\Exception\RoomNotFoundException;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;

final class BookRoomUseCase
{
    public function __construct(
        private RoomRepositoryInterface $roomRepository,
    ) {}

    public function execute(BookRoomCommand $command): ReservationId
    {
        $room = $this->roomRepository->findById(new RoomId($command->roomId));
        if ($room === null) {
            throw new RoomNotFoundException();
        }

        return new ReservationId('00000000-0000-0000-0000-000000000001');
    }
}
