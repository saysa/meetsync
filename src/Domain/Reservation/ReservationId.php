<?php

declare(strict_types=1);

namespace App\Domain\Reservation;

use Symfony\Component\Uid\Uuid;

final readonly class ReservationId
{
    public function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
    }
}
