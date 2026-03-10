<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Query\GetMyReservationsQuery;
use App\Application\UseCase\GetMyReservationsUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\Timeslot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMyReservationsUseCaseTest extends TestCase
{
    private function fixedClock(string $now = '2026-03-09 08:00:00'): ClockInterface
    {
        return new class ($now) implements ClockInterface {
            public function __construct(private string $now) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now);
            }
        };
    }

    #[Test]
    public function should_return_an_empty_list_when_the_organizer_has_no_reservations(): void
    {
        $reservationRepository = new class implements ReservationRepositoryInterface {
            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $query = new GetMyReservationsQuery(organizerId: 'alice');

        $result = $useCase->execute($query);

        self::assertSame([], $result);
    }
}
