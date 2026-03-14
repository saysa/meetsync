<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;

final class InMemoryRoomRepository implements RoomRepositoryInterface
{
    /** @var array<string, Room> */
    private array $rooms = [];

    public function add(Room $room): void
    {
        $this->rooms[$room->toSnapshot()->id] = $room;
    }

    public function findById(RoomId $roomId): ?Room
    {
        return $this->rooms[$roomId->value] ?? null;
    }
}
