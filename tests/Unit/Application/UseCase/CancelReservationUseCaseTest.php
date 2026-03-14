<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Application\Exception\ReservationNotFoundException;
use App\Application\UseCase\CancelReservationUseCase;
use App\Domain\Exception\NotTheOrganizerException;
use App\Domain\Exception\ReservationAlreadyStartedException;
use App\Domain\Clock\ClockInterface;
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationSnapshot;
use App\Tests\Fixtures\InMemoryReservationRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CancelReservationUseCaseTest extends TestCase
{
    private function fixedClock(string $now = '2026-03-09 09:00:00'): ClockInterface
    {
        return new class ($now) implements ClockInterface {
            public function __construct(private string $now) {}

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now);
            }
        };
    }

    private function noOpEmailNotifier(): EmailNotifierInterface
    {
        return new class implements EmailNotifierInterface {
            public function sendConfirmation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {}

            public function sendCancellation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {}
        };
    }

    private function aliceConfirmedReservation(): Reservation
    {
        return Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));
    }

    #[Test]
    public function should_cancel_a_confirmed_reservation_when_the_organizer_requests_the_cancellation_before_it_starts(): void
    {
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($this->aliceConfirmedReservation());

        $useCase = new CancelReservationUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $useCase->execute(new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        ));

        self::assertNotNull($reservationRepository->lastSaved);
        self::assertTrue($reservationRepository->lastSaved->isCancelled());
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_reservation_does_not_exist(): void
    {
        $useCase = new CancelReservationUseCase(
            reservationRepository: new InMemoryReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $this->expectException(ReservationNotFoundException::class);

        $useCase->execute(new CancelReservationCommand(
            reservationId: 'unknown-id',
            requesterId: 'alice',
        ));
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_requester_is_not_the_organizer_of_the_reservation(): void
    {
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($this->aliceConfirmedReservation());

        $useCase = new CancelReservationUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $this->expectException(NotTheOrganizerException::class);

        $useCase->execute(new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'bob',
        ));
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_reservation_has_already_started(): void
    {
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($this->aliceConfirmedReservation());

        $useCase = new CancelReservationUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(now: '2026-03-09 14:30:00'),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $this->expectException(ReservationAlreadyStartedException::class);

        $useCase->execute(new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        ));
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_organizer_cancels_exactly_at_the_reservation_start_time(): void
    {
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add($this->aliceConfirmedReservation());

        $useCase = new CancelReservationUseCase(
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(now: '2026-03-09 14:00:00'),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $this->expectException(ReservationAlreadyStartedException::class);

        $useCase->execute(new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        ));
    }
}
