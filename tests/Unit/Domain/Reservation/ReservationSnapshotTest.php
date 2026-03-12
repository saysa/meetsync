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
    public function should_expose_a_snapshot_with_the_correct_organizer_identifier_confirmed_status_timeslot_start_and_timeslot_end_when_a_reservation_is_created_via_the_named_constructor(): void
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
        self::assertSame('alice@example.com', $snapshot->organizerId);
        self::assertSame('CONFIRMED', $snapshot->status);
        self::assertSame($start, $snapshot->start);
        self::assertSame($end, $snapshot->end);
    }
}
