<?php

declare(strict_types=1);

namespace App\Application\Command;

use DateTimeImmutable;

class BookRoomCommand
{
    public function __construct(
        public string $roomId,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {}
}
