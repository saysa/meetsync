<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

interface ReservationRepositoryInterface
{
    /** @return Reservation[] */
    public function findByRoomId(RoomId $roomId): array;
}
