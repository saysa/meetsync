<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reservation;

use App\Domain\Reservation\Timeslot;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimeslotTest extends TestCase
{
    #[Test]
    public function should_represent_a_valid_booking_interval_when_start_is_strictly_before_end(): void
    {
        $start = new DateTimeImmutable('2026-03-09 09:00:00');
        $end   = new DateTimeImmutable('2026-03-09 10:00:00');

        $timeslot = new Timeslot($start, $end);

        self::assertSame($start, $timeslot->start);
        self::assertSame($end, $timeslot->end);
    }
}
