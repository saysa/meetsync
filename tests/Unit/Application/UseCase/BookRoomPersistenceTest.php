<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\UseCase\BookRoomUseCase;
use App\Domain\Clock\ClockInterface;
use App\Domain\Exception\TimeslotConflictException;
use App\Domain\Notification\EmailNotifierInterface;
use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Tests\Fixtures\InMemoryReservationRepository;
use App\Tests\Fixtures\InMemoryRoomRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookRoomPersistenceTest extends TestCase
{
    private function eiffelRoomRepository(): InMemoryRoomRepository
    {
        $repository = new InMemoryRoomRepository();
        $repository->add(new Room(
            id: new RoomId('eiffel'),
            capacity: 8,
            openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
            closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
        ));

        return $repository;
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
    public function should_save_the_confirmed_booking_with_the_organizer_and_room_details_when_a_room_is_successfully_reserved_for_an_available_time_slot(): void
    {
        $reservationRepository = new InMemoryReservationRepository();

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));

        self::assertNotNull($reservationRepository->lastSaved);
        $snapshot = $reservationRepository->lastSaved->toSnapshot();
        self::assertSame('eiffel',             $snapshot->roomId);
        self::assertSame('alice@example.com',  $snapshot->organizerId);
        self::assertSame('CONFIRMED',          $snapshot->status);
    }

    #[Test]
    public function should_give_each_booking_a_unique_reference_number_when_the_same_room_is_reserved_twice_for_two_different_time_slots(): void
    {
        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: new InMemoryReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $firstId = $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));

        $secondId = $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));

        self::assertNotSame($firstId->value, $secondId->value);
    }

    #[Test]
    public function should_enforce_booking_rules_correctly_against_a_reservation_that_already_exists_in_the_system(): void
    {
        $this->expectException(TimeslotConflictException::class);

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-existing',
            roomId: 'eiffel',
            organizerId: 'bob@example.com',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
        )));

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:30:00'),
            end: new DateTimeImmutable('2026-03-09 11:30:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));
    }

    #[Test]
    public function should_not_save_any_booking_when_the_reservation_is_refused_because_the_room_is_already_taken_for_that_time_slot(): void
    {
        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'existing-res-1',
            roomId: 'eiffel',
            organizerId: 'bob@example.com',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
        )));

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $this->noOpEmailNotifier(),
        );

        try {
            $useCase->execute(new BookRoomCommand(
                roomId: 'eiffel',
                start: new DateTimeImmutable('2026-03-09 10:30:00'),
                end: new DateTimeImmutable('2026-03-09 11:30:00'),
                participantCount: 3,
                organizerEmail: 'alice@example.com',
            ));
            self::fail('Expected TimeslotConflictException was not thrown');
        } catch (TimeslotConflictException) {
            // expected
        }

        self::assertNull($reservationRepository->lastSaved);
    }
}
