<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Application\UseCase\CancelReservationUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Exception\NotTheOrganizerException;
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\RoomId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CancelReservationEmailNotificationTest extends TestCase
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

    private function reservationRepository(Reservation $reservation): ReservationRepositoryInterface
    {
        return new class ($reservation) implements ReservationRepositoryInterface {
            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array { return []; }

            public function findById(ReservationId $id): ?Reservation
            {
                return $this->reservation;
            }

            public function save(Reservation $reservation): void {}

            public function findByOrganizerId(string $organizerId): array { return []; }
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
    public function should_send_a_cancellation_email_to_the_organizer_when_a_reservation_is_successfully_cancelled(): void
    {
        $spy = new class implements EmailNotifierInterface {
            public ?string $cancellationSentTo = null;

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
            ): void {
                $this->cancellationSentTo = $organizerEmail;
            }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $this->reservationRepository($this->aliceConfirmedReservation()),
            clock: $this->fixedClock(),
            emailNotifier: $spy,
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
            requesterEmail: 'alice@example.com',
        );

        $useCase->execute($command);

        self::assertSame('alice@example.com', $spy->cancellationSentTo);
    }

    #[Test]
    public function should_not_send_any_cancellation_email_when_the_cancellation_is_rejected_because_the_requester_is_not_the_organizer(): void
    {
        $spy = new class implements EmailNotifierInterface {
            public ?string $cancellationSentTo = null;

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
            ): void {
                $this->cancellationSentTo = $organizerEmail;
            }
        };

        $useCase = new CancelReservationUseCase(
            reservationRepository: $this->reservationRepository($this->aliceConfirmedReservation()),
            clock: $this->fixedClock(),
            emailNotifier: $spy,
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'bob',
            requesterEmail: 'bob@example.com',
        );

        $this->expectException(NotTheOrganizerException::class);
        $useCase->execute($command);

        self::assertNull($spy->cancellationSentTo);
    }

    #[Test]
    public function should_cancel_the_reservation_when_the_notification_cannot_be_delivered(): void
    {
        $failingNotifier = new class implements EmailNotifierInterface {
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
            ): void {
                throw new \RuntimeException('SMTP unreachable');
            }
        };

        $capturingRepository = new class ($this->aliceConfirmedReservation()) implements ReservationRepositoryInterface {
            public ?Reservation $saved = null;

            public function __construct(private Reservation $reservation) {}

            public function findByRoomId(RoomId $roomId): array { return []; }

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
            emailNotifier: $failingNotifier,
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
            requesterEmail: 'alice@example.com',
        );

        $useCase->execute($command);

        self::assertNotNull($capturingRepository->saved);
        self::assertTrue($capturingRepository->saved->isCancelled());
    }
}
