<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;

final class InMemoryReservationRepository implements ReservationRepositoryInterface
{
    /** @var array<string, Reservation> */
    private array $reservations = [];

    public ?Reservation $lastSaved = null;

    public function add(Reservation ...$reservations): void
    {
        foreach ($reservations as $reservation) {
            $this->reservations[$reservation->toSnapshot()->id] = $reservation;
        }
    }

    public function save(Reservation $reservation): void
    {
        $this->reservations[$reservation->toSnapshot()->id] = $reservation;
        $this->lastSaved = $reservation;
    }

    public function findById(ReservationId $id): ?Reservation
    {
        return $this->reservations[$id->value] ?? null;
    }

    /** @return list<Reservation> */
    public function findByRoomId(RoomId $roomId): array
    {
        return array_values(array_filter(
            $this->reservations,
            fn(Reservation $r) => $r->toSnapshot()->roomId === $roomId->value,
        ));
    }

    /** @return list<Reservation> */
    public function findByOrganizerId(string $organizerId): array
    {
        return array_values(array_filter(
            $this->reservations,
            fn(Reservation $r) => $r->toSnapshot()->organizerId === $organizerId,
        ));
    }
}
