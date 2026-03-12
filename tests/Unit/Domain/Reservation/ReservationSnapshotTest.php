<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservation;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\Timeslot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReservationSnapshotTest extends TestCase
{
    #[Test]
    public function should_record_the_organizer_the_booked_room_a_confirmed_status_and_the_reserved_time_window_when_a_booking_is_created(): void
    {
        $start = new DateTimeImmutable('2026-03-20 10:00:00');
        $end   = new DateTimeImmutable('2026-03-20 11:00:00');

        $reservation = Reservation::create(
            id: new ReservationId('res-001'),
            roomId: new RoomId('eiffel'),
            organizerId: 'alice@example.com',
            timeslot: new Timeslot($start, $end),
        );

        $snapshot = $reservation->toSnapshot();

        self::assertInstanceOf(ReservationSnapshot::class, $snapshot);
        self::assertSame('eiffel', $snapshot->roomId);
        self::assertSame('alice@example.com', $snapshot->organizerId);
        self::assertSame('CONFIRMED', $snapshot->status);
        self::assertSame($start, $snapshot->start);
        self::assertSame($end, $snapshot->end);
    }

    #[Test]
    public function should_restore_all_booking_details_organizer_room_confirmed_status_and_reserved_time_window_when_a_reservation_is_loaded_from_the_systems_records(): void
    {
        $start = new DateTimeImmutable('2026-03-20 14:00:00');
        $end   = new DateTimeImmutable('2026-03-20 15:30:00');

        $original = new ReservationSnapshot(
            id: 'res-042',
            roomId: 'louvre',
            organizerId: 'bob@example.com',
            status: 'CONFIRMED',
            start: $start,
            end: $end,
        );

        $restored = Reservation::fromSnapshot($original);

        self::assertEquals($original, $restored->toSnapshot());
    }
}
