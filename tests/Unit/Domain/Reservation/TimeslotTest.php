<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Reservation;

use App\Domain\Exception\InvalidTimeslotException;
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

    #[Test]
    public function should_report_no_conflict_when_first_timeslot_ends_before_second_begins(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 11:00:00'),
            new DateTimeImmutable('2026-03-09 12:00:00'),
        );

        self::assertFalse($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_a_conflict_when_two_timeslots_occupy_the_exact_same_interval(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );

        self::assertTrue($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_a_conflict_when_second_timeslot_starts_inside_existing_and_ends_after(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:30:00'),
            new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        self::assertTrue($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_a_conflict_when_second_timeslot_starts_before_existing_and_ends_inside(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 08:00:00'),
            new DateTimeImmutable('2026-03-09 09:30:00'),
        );

        self::assertTrue($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_a_conflict_when_second_timeslot_completely_contains_existing(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 08:00:00'),
            new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        self::assertTrue($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_no_conflict_when_second_timeslot_begins_exactly_when_existing_ends(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 10:00:00'),
            new DateTimeImmutable('2026-03-09 11:00:00'),
        );

        self::assertFalse($first->conflictsWith($second));
    }

    #[Test]
    public function should_report_no_conflict_when_second_timeslot_ends_exactly_when_existing_starts(): void
    {
        $first  = new Timeslot(
            new DateTimeImmutable('2026-03-09 09:00:00'),
            new DateTimeImmutable('2026-03-09 10:00:00'),
        );
        $second = new Timeslot(
            new DateTimeImmutable('2026-03-09 08:00:00'),
            new DateTimeImmutable('2026-03-09 09:00:00'),
        );

        self::assertFalse($first->conflictsWith($second));
    }

    #[Test]
    public function should_reject_a_zero_duration_timeslot_when_start_equals_end(): void
    {
        $this->expectException(InvalidTimeslotException::class);

        $moment = new DateTimeImmutable('2026-03-09 09:00:00');
        new Timeslot($moment, $moment);
    }
}
