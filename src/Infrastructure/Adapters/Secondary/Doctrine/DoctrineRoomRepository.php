<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRoomRepository implements RoomRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findById(RoomId $roomId): ?Room
    {
        return null;
    }
}
