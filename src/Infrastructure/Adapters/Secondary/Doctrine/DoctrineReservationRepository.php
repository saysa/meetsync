<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Reservation $reservation): void
    {
        // scaffold — does nothing
    }

    public function findById(ReservationId $id): ?Reservation
    {
        return null;
    }

    /** @return list<Reservation> */
    public function findByRoomId(RoomId $roomId): array
    {
        return [];
    }

    /** @return list<Reservation> */
    public function findByOrganizerId(string $organizerId): array
    {
        return [];
    }
}
