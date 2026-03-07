<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

interface RoomRepositoryInterface
{
    public function findById(RoomId $roomId): ?Room;
}
