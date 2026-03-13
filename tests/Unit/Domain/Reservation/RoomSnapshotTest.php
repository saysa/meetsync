<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation;

use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomSnapshot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoomSnapshotTest extends TestCase
{
    #[Test]
    public function should_record_the_room_capacity_and_operating_hours_when_a_room_is_saved(): void
    {
        $room = new Room(
            id: new RoomId('eiffel'),
            capacity: 10,
            openingTime: new DateTimeImmutable('08:00:00'),
            closingTime: new DateTimeImmutable('19:00:00'),
        );

        $snapshot = $room->toSnapshot();

        self::assertInstanceOf(RoomSnapshot::class, $snapshot);
        self::assertSame('eiffel', $snapshot->id);
        self::assertSame(10, $snapshot->capacity);
        self::assertSame('08:00:00', $snapshot->openingTime->format('H:i:s'));
        self::assertSame('19:00:00', $snapshot->closingTime->format('H:i:s'));
    }

    #[Test]
    public function should_preserve_the_room_capacity_and_operating_hours_when_a_room_is_saved_and_loaded_back(): void
    {
        $originalSnapshot = new RoomSnapshot(
            id: 'eiffel',
            capacity: 10,
            openingTime: new DateTimeImmutable('08:00:00'),
            closingTime: new DateTimeImmutable('19:00:00'),
        );

        $restored = Room::fromSnapshot($originalSnapshot);

        self::assertEquals($originalSnapshot, $restored->toSnapshot());
    }
}
