<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'rooms')]
final class DoctrineRoomEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 255)]
    public string $id;

    #[ORM\Column(name: 'capacity', type: 'integer')]
    public int $capacity;

    #[ORM\Column(name: 'opening_time', type: 'time_immutable')]
    public DateTimeImmutable $openingTime;

    #[ORM\Column(name: 'closing_time', type: 'time_immutable')]
    public DateTimeImmutable $closingTime;
}
