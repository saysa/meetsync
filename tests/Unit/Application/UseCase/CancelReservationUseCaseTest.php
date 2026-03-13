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
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\RoomId;
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

    #[Test]
    public function should_cancel_a_confirmed_reservation_when_the_organizer_requests_the_cancellation_before_it_starts(): void
    {
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));

        $capturingRepository = new class ($reservation) implements ReservationRepositoryInterface {
            public ?Reservation $saved = null;

            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array
            {
                return [];
            }

            public function findById(ReservationId $id): ?Reservation
            {
                return $this->reservation;
            }

            public function save(Reservation $reservation): void
            {
                $this->saved = $reservation;
            }

            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $capturingRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        );

        $useCase->execute($command);

        self::assertNotNull($capturingRepository->saved);
        self::assertTrue($capturingRepository->saved->isCancelled());
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_reservation_does_not_exist(): void
    {
        $emptyRepository = new class implements ReservationRepositoryInterface {
            public function findByRoomId(RoomId $roomId): array
            {
                return [];
            }

            public function findById(ReservationId $id): ?Reservation
            {
                return null;
            }

            public function save(Reservation $reservation): void {}

            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $emptyRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'unknown-id',
            requesterId: 'alice',
        );

        $this->expectException(ReservationNotFoundException::class);

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_requester_is_not_the_organizer_of_the_reservation(): void
    {
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));

        $repository = new class ($reservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array
            {
                return [];
            }

            public function findById(ReservationId $id): ?Reservation
            {
                return $this->reservation;
            }

            public function save(Reservation $reservation): void {}

            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $repository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'bob',
        );

        $this->expectException(NotTheOrganizerException::class);

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_reservation_has_already_started(): void
    {
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));

        $repository = new class ($reservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array
            {
                return [];
            }

            public function findById(ReservationId $id): ?Reservation
            {
                return $this->reservation;
            }

            public function save(Reservation $reservation): void {}

            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $repository,
            clock: $this->fixedClock(now: '2026-03-09 14:30:00'),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        );

        $this->expectException(ReservationAlreadyStartedException::class);

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_cancellation_when_the_organizer_cancels_exactly_at_the_reservation_start_time(): void
    {
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-1',
            roomId: 'eiffel',
            organizerId: 'alice',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        ));

        $repository = new class ($reservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array
            {
                return [];
            }

            public function findById(ReservationId $id): ?Reservation
            {
                return $this->reservation;
            }

            public function save(Reservation $reservation): void {}

            public function findByOrganizerId(string $organizerId): array { return []; }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $repository,
            clock: $this->fixedClock(now: '2026-03-09 14:00:00'),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        );

        $this->expectException(ReservationAlreadyStartedException::class);

        $useCase->execute($command);
    }
}
