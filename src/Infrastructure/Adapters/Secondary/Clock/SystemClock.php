<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Clock;

use App\Domain\Clock\ClockInterface;
use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
