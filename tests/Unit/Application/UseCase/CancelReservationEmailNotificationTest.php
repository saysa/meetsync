<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Application\UseCase\CancelReservationUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\Timeslot;
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
        return new Reservation(
            id: new ReservationId('res-1'),
            organizerId: 'alice',
            timeslot: new Timeslot(
                new DateTimeImmutable('2026-03-09 14:00:00'),
                new DateTimeImmutable('2026-03-09 15:00:00'),
                new DateTimeImmutable('2026-03-09 08:00:00'),
                new DateTimeImmutable('2026-03-09 19:00:00'),
            ),
        );
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
}
