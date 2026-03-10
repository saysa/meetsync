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

    private function aFutureReservationForAlice(): Reservation
    {
        return new Reservation(
            id: new ReservationId('res-alice-1'),
            organizerId: 'alice',
            timeslot: new Timeslot(
                new DateTimeImmutable('2026-03-09 14:00:00'),
                new DateTimeImmutable('2026-03-09 15:00:00'),
            ),
        );
    }

    #[Test]
    public function should_return_the_organizers_confirmed_future_reservation_when_they_have_exactly_one(): void
    {
        $reservation = $this->aFutureReservationForAlice();

        $reservationRepository = new class ($reservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array
            {
                return [$this->reservation];
            }
        };

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertCount(1, $result);
        self::assertSame($reservation, $result[0]);
    }

    #[Test]
    public function should_exclude_reservations_that_belong_to_a_different_organizer(): void
    {
        $alicesReservation = $this->aFutureReservationForAlice();
        $bobsReservation = new Reservation(
            id: new ReservationId('res-bob-1'),
            organizerId: 'bob',
            timeslot: new Timeslot(
                new DateTimeImmutable('2026-03-09 16:00:00'),
                new DateTimeImmutable('2026-03-09 17:00:00'),
            ),
        );

        $reservationRepository = new class ($alicesReservation, $bobsReservation) implements ReservationRepositoryInterface {
            public function __construct(
                private Reservation $alicesReservation,
                private Reservation $bobsReservation,
            ) {}

            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array
            {
                return [$this->alicesReservation, $this->bobsReservation];
            }
        };

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertCount(1, $result);
        self::assertSame($alicesReservation, $result[0]);
    }

    #[Test]
    public function should_exclude_a_confirmed_reservation_whose_start_time_is_in_the_past(): void
    {
        $pastReservation = new Reservation(
            id: new ReservationId('res-alice-past'),
            organizerId: 'alice',
            timeslot: new Timeslot(
                new DateTimeImmutable('2026-03-05 09:00:00'),
                new DateTimeImmutable('2026-03-05 10:00:00'),
            ),
        );

        $reservationRepository = new class ($pastReservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $pastReservation) {}

            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array
            {
                return [$this->pastReservation];
            }
        };

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertSame([], $result);
    }

    #[Test]
    public function should_include_a_cancelled_reservation_when_its_start_time_is_still_in_the_future(): void
    {
        $cancelledFutureReservation = $this->aFutureReservationForAlice();
        $cancelledFutureReservation->cancel();

        $reservationRepository = new class ($cancelledFutureReservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array
            {
                return [$this->reservation];
            }
        };

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertCount(1, $result);
        self::assertSame($cancelledFutureReservation, $result[0]);
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
