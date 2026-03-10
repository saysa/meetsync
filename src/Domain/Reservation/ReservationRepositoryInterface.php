<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

interface ReservationRepositoryInterface
{
    /** @return list<Reservation> */
    public function findByRoomId(RoomId $roomId): array;

    public function findById(ReservationId $id): ?Reservation;

    public function save(Reservation $reservation): void;

    /** @return list<Reservation> */
    public function findByOrganizerId(string $organizerId): array;
}
