<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\UseCase\BookRoomUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookRoomPersistenceTest extends TestCase
{
    #[Test]
    public function should_save_the_confirmed_booking_with_the_organizer_and_room_details_when_a_room_is_successfully_reserved_for_an_available_time_slot(): void
    {
        $captured = null;
        $capturingRepository = new class ($captured) implements ReservationRepositoryInterface {
            public ?Reservation $saved = null;

            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function findByOrganizerId(string $organizerId): array { return []; }

            public function save(Reservation $reservation): void
            {
                $this->saved = $reservation;
            }
        };

        $useCase = new BookRoomUseCase(
            roomRepository: new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return new Room(
                        capacity: 8,
                        openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
                        closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
                    );
                }
            },
            reservationRepository: $capturingRepository,
            clock: new class implements ClockInterface {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('2026-03-09 09:00:00');
                }
            },
            emailNotifier: new class implements EmailNotifierInterface {
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
            },
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        );

        $useCase->execute($command);

        self::assertNotNull($capturingRepository->saved);
        $snapshot = $capturingRepository->saved->toSnapshot();
        self::assertSame('eiffel', $snapshot->roomId);
        self::assertSame('alice@example.com', $snapshot->organizerId);
        self::assertSame('CONFIRMED', $snapshot->status);
    }
}
