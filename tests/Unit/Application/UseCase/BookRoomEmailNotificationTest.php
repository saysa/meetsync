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

final class BookRoomEmailNotificationTest extends TestCase
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

    #[Test]
    public function should_send_a_confirmation_email_to_the_organizer_when_a_room_booking_succeeds(): void
    {
        $spy = new class implements EmailNotifierInterface {
            public ?string $confirmationSentTo = null;

            public function sendConfirmation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {
                $this->confirmationSentTo = $organizerEmail;
            }

            public function sendCancellation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {}
        };

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: new InMemoryReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $spy,
        );

        $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));

        self::assertSame('alice@example.com', $spy->confirmationSentTo);
    }

    #[Test]
    public function should_not_send_a_confirmation_email_when_the_booking_fails_because_the_room_is_already_taken(): void
    {
        $spy = new class implements EmailNotifierInterface {
            public ?string $confirmationSentTo = null;

            public function sendConfirmation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {
                $this->confirmationSentTo = $organizerEmail;
            }

            public function sendCancellation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {}
        };

        $reservationRepository = new InMemoryReservationRepository();
        $reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-existing',
            roomId: 'eiffel',
            organizerId: 'bob',
            status: 'CONFIRMED',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        )));

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: $reservationRepository,
            clock: $this->fixedClock(),
            emailNotifier: $spy,
        );

        try {
            $useCase->execute(new BookRoomCommand(
                roomId: 'eiffel',
                start: new DateTimeImmutable('2026-03-09 14:00:00'),
                end: new DateTimeImmutable('2026-03-09 15:00:00'),
                participantCount: 3,
                organizerEmail: 'alice@example.com',
            ));
            self::fail('Expected TimeslotConflictException was not thrown');
        } catch (TimeslotConflictException) {
            // expected
        }

        self::assertNull($spy->confirmationSentTo);
    }

    #[Test]
    public function should_confirm_the_reservation_when_the_notification_cannot_be_delivered(): void
    {
        $failingNotifier = new class implements EmailNotifierInterface {
            public function sendConfirmation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {
                throw new \RuntimeException('SMTP unreachable');
            }

            public function sendCancellation(
                string $organizerEmail,
                string $roomId,
                DateTimeImmutable $start,
                DateTimeImmutable $end,
            ): void {
                throw new \RuntimeException('SMTP unreachable');
            }
        };

        $useCase = new BookRoomUseCase(
            roomRepository: $this->eiffelRoomRepository(),
            reservationRepository: new InMemoryReservationRepository(),
            clock: $this->fixedClock(),
            emailNotifier: $failingNotifier,
        );

        $reservationId = $useCase->execute(new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
            participantCount: 3,
            organizerEmail: 'alice@example.com',
        ));

        self::assertNotNull($reservationId);
    }
}
