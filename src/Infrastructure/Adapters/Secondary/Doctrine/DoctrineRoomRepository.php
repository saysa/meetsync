<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use App\Domain\Reservation\RoomSnapshot;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineRoomRepository implements RoomRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findById(RoomId $roomId): ?Room
    {
        $entity = $this->em->find(DoctrineRoomEntity::class, $roomId->value);

        if ($entity === null) {
            return null;
        }

        return Room::fromSnapshot(new RoomSnapshot(
            id: $entity->id,
            capacity: $entity->capacity,
            openingTime: $entity->openingTime,
            closingTime: $entity->closingTime,
        ));
    }
}
