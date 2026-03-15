<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Domain\Clock\ClockInterface;
use DateTimeImmutable;

final class FakeClock implements ClockInterface
{
    private DateTimeImmutable $now;

    public function __construct()
    {
        $this->now = new DateTimeImmutable();
    }

    public function setNow(DateTimeImmutable $now): void
    {
        $this->now = $now;
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
