<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\Exception\RoomNotFoundException;
use App\Application\UseCase\BookRoomUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Exception\BookingHorizonExceededException;
use App\Domain\Exception\InsufficientAdvanceNoticeException;
use App\Domain\Exception\InvalidTimeslotException;
use App\Domain\Exception\RoomCapacityExceededException;
use App\Domain\Exception\TimeslotConflictException;
use App\Domain\Notification\EmailNotifierInterface;
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
    private function roomRepository(Room $room): RoomRepositoryInterface
    {
        return new class ($room) implements RoomRepositoryInterface {
            public function __construct(private Room $room) {}

            public function findById(RoomId $roomId): ?Room
            {
                return $this->room;
            }
        };
    }

    private function emptyReservationRepository(): ReservationRepositoryInterface
    {
        return new class implements ReservationRepositoryInterface {
            public function findByRoomId(RoomId $roomId): array { return []; }
            public function findById(ReservationId $id): ?Reservation { return null; }
            public function save(Reservation $reservation): void {}
            public function findByOrganizerId(string $organizerId): array { return []; }
        };
    }

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

    private function eiffelRoom(): Room
    {
        return new Room(
            capacity: 8,
            openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
            closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
        );
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
    public function should_create_a_confirmed_reservation_when_a_room_is_available_and_all_booking_rules_are_satisfied(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );
        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 3,
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_reject_the_booking_when_the_requested_timeslot_overlaps_with_an_existing_reservation(): void
    {
        $this->expectException(TimeslotConflictException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: new class implements ReservationRepositoryInterface {
                public function findByRoomId(RoomId $roomId): array
                {
                    return [
                        new Reservation(
                            id: new ReservationId('res-1'),
                            organizerId: 'alice',
                            timeslot: new Timeslot(
                                new DateTimeImmutable('2026-03-09 10:00:00'),
                                new DateTimeImmutable('2026-03-09 11:00:00'),
                            ),
                        ),
                    ];
                }
                public function findById(ReservationId $id): ?Reservation { return null; }
                public function save(Reservation $reservation): void {}
                public function findByOrganizerId(string $organizerId): array { return []; }
            },
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:30:00'),
            end: new DateTimeImmutable('2026-03-09 11:30:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_confirm_the_booking_when_a_second_reservation_starts_exactly_when_an_existing_one_ends(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: new class implements ReservationRepositoryInterface {
                public function findByRoomId(RoomId $roomId): array
                {
                    return [
                        new Reservation(
                            id: new ReservationId('res-2'),
                            organizerId: 'alice',
                            timeslot: new Timeslot(
                                new DateTimeImmutable('2026-03-09 10:00:00'),
                                new DateTimeImmutable('2026-03-09 11:00:00'),
                            ),
                        ),
                    ];
                }
                public function findById(ReservationId $id): ?Reservation { return null; }
                public function save(Reservation $reservation): void {}
                public function findByOrganizerId(string $organizerId): array { return []; }
            },
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 11:00:00'),
            end: new DateTimeImmutable('2026-03-09 12:00:00'),
            participantCount: 3,
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_confirm_the_booking_when_the_number_of_participants_equals_the_room_capacity(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 8,
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_reject_the_booking_when_the_number_of_participants_exceeds_the_room_capacity(): void
    {
        $this->expectException(RoomCapacityExceededException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 9,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_booking_when_the_start_time_is_before_the_building_opening_time(): void
    {
        $this->expectException(InvalidTimeslotException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 07:00:00'),
            end: new DateTimeImmutable('2026-03-09 09:00:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_booking_when_the_end_time_is_after_the_building_closing_time(): void
    {
        $this->expectException(InvalidTimeslotException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 17:00:00'),
            end: new DateTimeImmutable('2026-03-09 20:00:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_reject_the_booking_when_the_start_date_is_more_than_90_days_in_the_future(): void
    {
        $this->expectException(BookingHorizonExceededException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return new Room(
                        capacity: 8,
                        openingTime: new DateTimeImmutable('2026-06-08 08:00:00'),
                        closingTime: new DateTimeImmutable('2026-06-08 19:00:00'),
                    );
                }
            },
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-06-08 10:00:00'),
            end: new DateTimeImmutable('2026-06-08 11:00:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_confirm_the_booking_when_the_start_date_is_exactly_90_days_in_the_future(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return new Room(
                        capacity: 8,
                        openingTime: new DateTimeImmutable('2026-06-07 08:00:00'),
                        closingTime: new DateTimeImmutable('2026-06-07 19:00:00'),
                    );
                }
            },
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-06-07 09:00:00'),
            end: new DateTimeImmutable('2026-06-07 10:00:00'),
            participantCount: 3,
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_reject_the_booking_when_the_start_time_is_less_than_30_minutes_from_now(): void
    {
        $this->expectException(InsufficientAdvanceNoticeException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 09:20:00'),
            end: new DateTimeImmutable('2026-03-09 10:20:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }

    #[Test]
    public function should_confirm_the_booking_when_the_start_time_is_exactly_30_minutes_from_now(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: $this->roomRepository($this->eiffelRoom()),
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 09:30:00'),
            end: new DateTimeImmutable('2026-03-09 10:30:00'),
            participantCount: 3,
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }

    #[Test]
    public function should_reject_the_booking_when_the_requested_room_does_not_exist(): void
    {
        $this->expectException(RoomNotFoundException::class);

        $useCase = new BookRoomUseCase(
            roomRepository: new class implements RoomRepositoryInterface {
                public function findById(RoomId $roomId): ?Room
                {
                    return null;
                }
            },
            reservationRepository: $this->emptyReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $command = new BookRoomCommand(
            roomId: 'unknown-room',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 3,
        );

        $useCase->execute($command);
    }
}
