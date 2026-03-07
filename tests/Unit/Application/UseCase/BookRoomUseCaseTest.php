<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\Exception\RoomNotFoundException;
use App\Application\UseCase\BookRoomUseCase;
use App\Domain\Exception\TimeslotConflictException;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use App\Domain\Reservation\Timeslot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookRoomUseCaseTest extends TestCase
{
    #[Test]
    public function should_create_a_confirmed_reservation_when_a_room_is_available_and_all_booking_rules_are_satisfied(): void
    {
        $useCase = new BookRoomUseCase(
            new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return new Room();
                }
            }
        );
        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_reject_the_booking_when_the_requested_timeslot_overlaps_with_an_existing_reservation(): void
    {
        $this->expectException(TimeslotConflictException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return new Room();
                }
            },
            reservationRepository: new class implements ReservationRepositoryInterface {
                public function findByRoomId(RoomId $roomId): array
                {
                    return [
                        new Reservation(
                            new Timeslot(
                                new DateTimeImmutable('2026-03-09 10:00:00'),
                                new DateTimeImmutable('2026-03-09 11:00:00'),
                            )
                        ),
                    ];
                }
            },
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:30:00'),
            end: new DateTimeImmutable('2026-03-09 11:30:00'),
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_booking_when_the_requested_room_does_not_exist(): void
    {
        $this->expectException(RoomNotFoundException::class);

        $useCase = new BookRoomUseCase(
            new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return null;
                }
            }
        );

        $command = new BookRoomCommand(
            roomId: 'unknown-room',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        $useCase->execute($command);
    }
}
