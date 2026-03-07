<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Domain\Reservation\ReservationId;

final class BookRoomUseCase
{
    public function execute(BookRoomCommand $command): ReservationId
    {
        return new ReservationId('00000000-0000-0000-0000-000000000001');
    }
}
