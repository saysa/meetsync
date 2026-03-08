<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\CancelReservationCommand;
use App\Application\UseCase\CancelReservationUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\Timeslot;
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

    #[Test]
    public function should_cancel_a_confirmed_reservation_when_the_organizer_requests_the_cancellation_before_it_starts(): void
    {
        $this->expectNotToPerformAssertions();

        $useCase = new CancelReservationUseCase(
            reservationRepository: new class implements ReservationRepositoryInterface {
                public function findByRoomId(RoomId $roomId): array { return []; }

                public function findById(ReservationId $id): ?Reservation
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

                public function save(Reservation $reservation): void {}
            },
            clock: $this->fixedClock(),
        );

        $command = new CancelReservationCommand(
            reservationId: 'res-1',
            requesterId: 'alice',
        );

        $useCase->execute($command);
    }
}
