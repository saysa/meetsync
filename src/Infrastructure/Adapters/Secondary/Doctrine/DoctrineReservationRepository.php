<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\RoomId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Reservation $reservation): void
    {
        $snapshot = $reservation->toSnapshot();

        $entity             = new DoctrineReservationEntity();
        $entity->id         = $snapshot->id;
        $entity->roomId     = $snapshot->roomId;
        $entity->organizerId = $snapshot->organizerId;
        $entity->status     = $snapshot->status;
        $entity->startAt    = $snapshot->start;
        $entity->endAt      = $snapshot->end;

        $this->em->persist($entity);
    }

    public function findById(ReservationId $id): ?Reservation
    {
        $entity = $this->em->find(DoctrineReservationEntity::class, $id->value);

        return $entity !== null ? $this->toReservation($entity) : null;
    }

    /** @return list<Reservation> */
    public function findByRoomId(RoomId $roomId): array
    {
        $entities = $this->em->getRepository(DoctrineReservationEntity::class)
            ->findBy(['roomId' => $roomId->value]);

        return array_map($this->toReservation(...), $entities);
    }

    /** @return list<Reservation> */
    public function findByOrganizerId(string $organizerId): array
    {
        $entities = $this->em->getRepository(DoctrineReservationEntity::class)
            ->findBy(['organizerId' => $organizerId]);

        return array_map($this->toReservation(...), $entities);
    }

    private function toReservation(DoctrineReservationEntity $entity): Reservation
    {
        return Reservation::fromSnapshot(new ReservationSnapshot(
            id: $entity->id,
            roomId: $entity->roomId,
            organizerId: $entity->organizerId,
            status: $entity->status,
            start: $entity->startAt,
            end: $entity->endAt,
        ));
    }
}
