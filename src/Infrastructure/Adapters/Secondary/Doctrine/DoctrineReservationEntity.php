<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Secondary\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'reservations')]
final class DoctrineReservationEntity
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(name: 'room_id', type: 'string')]
    public string $roomId;

    #[ORM\Column(name: 'organizer_id', type: 'string')]
    public string $organizerId;

    #[ORM\Column(name: 'status', type: 'string', length: 50)]
    public string $status;

    #[ORM\Column(name: 'start_at', type: 'datetimetz_immutable')]
    public DateTimeImmutable $startAt;

    #[ORM\Column(name: 'end_at', type: 'datetimetz_immutable')]
    public DateTimeImmutable $endAt;
}
