<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Query\GetMyReservationsQuery;
use App\Application\UseCase\GetMyReservationsUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationSnapshot;
use App\Tests\Fixtures\InMemoryReservationRepository;
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
        return Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-alice-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));
    }

    #[Test]
    public function should_return_the_organizers_confirmed_future_reservation_when_they_have_exactly_one(): void
    {
        $reservation = $this->aFutureReservationForAlice();

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($reservation);

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
        $bobsReservation   = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-bob-1',
            roomId: 'eiffel',
            organizerId: 'bob',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 16:00:00'),
            end: new DateTimeImmutable('2026-03-09 17:00:00'),
        ));

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($alicesReservation, $bobsReservation);

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
        $pastReservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-alice-past',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-05 09:00:00'),
            end: new DateTimeImmutable('2026-03-05 10:00:00'),
        ));

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($pastReservation);

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

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($cancelledFutureReservation);

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertCount(1, $result);
        self::assertSame($cancelledFutureReservation, $result[0]);
    }

    #[Test]
    public function should_return_reservations_ordered_chronologically_by_start_time_when_the_organizer_has_several_future_ones(): void
    {
        $reservationToday    = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));
        $reservationTomorrow = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-2',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-10 10:00:00'),
            end: new DateTimeImmutable('2026-03-10 11:00:00'),
        ));

        // Seeded in reverse order to force the use case to sort
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($reservationTomorrow, $reservationToday);

        $useCase = new GetMyReservationsUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertCount(2, $result);
        self::assertSame($reservationToday,    $result[0]);
        self::assertSame($reservationTomorrow, $result[1]);
    }

    #[Test]
    public function should_return_an_empty_list_when_the_organizer_has_no_reservations(): void
    {
        $useCase = new GetMyReservationsUseCase(
            reservationRepository: new InMemoryReservationRepository(),
            clock: $this->fixedClock(),
        );

        $result = $useCase->execute(new GetMyReservationsQuery(organizerId: 'alice'));

        self::assertSame([], $result);
    }
}
