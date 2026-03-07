<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase;

use App\Application\Command\BookRoomCommand;
use App\Application\UseCase\BookRoomUseCase;
use App\Domain\Reservation\ReservationId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BookRoomUseCaseTest extends TestCase
{
    #[Test]
    public function should_create_a_confirmed_reservation_when_a_room_is_available_and_all_booking_rules_are_satisfied(): void
    {
        $useCase = new BookRoomUseCase();
        $command = new BookRoomCommand(
            roomId: 'eiffel',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        $reservationId = $useCase->execute($command);

        self::assertInstanceOf(ReservationId::class, $reservationId);
    }
}
