<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationId;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\ReservationRepositoryInterface;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineReservationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ReservationRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = new DoctrineReservationRepository($this->entityManager);
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        $this->entityManager->close();
        parent::tearDown();
    }

    #[Test]
    public function should_preserve_the_organizer_the_room_and_the_exact_time_window_of_a_confirmed_reservation_when_it_is_stored_and_retrieved(): void
    {
        // Given
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-001',
            roomId: 'eiffel',
            organizerId: 'alice@example.com',
            status: 'CONFIRMED',
            start: new \DateTimeImmutable('2026-03-09 10:00:00 UTC'),
            end: new \DateTimeImmutable('2026-03-09 11:00:00 UTC'),
        ));

        $this->repository->save($reservation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // When
        $snapshot = $this->repository->findById(new ReservationId('res-001'))->toSnapshot();

        // Then
        self::assertSame('res-001',              $snapshot->id);
        self::assertSame('eiffel',               $snapshot->roomId);
        self::assertSame('alice@example.com',    $snapshot->organizerId);
        self::assertSame('CONFIRMED',            $snapshot->status);
        self::assertSame('2026-03-09T10:00:00+00:00', $snapshot->start->format(\DateTimeInterface::ATOM));
        self::assertSame('2026-03-09T11:00:00+00:00', $snapshot->end->format(\DateTimeInterface::ATOM));
    }

    #[Test]
    public function should_return_nothing_when_looking_up_a_reservation_identifier_that_has_never_been_recorded(): void
    {
        // Given — empty table (guaranteed by transaction rollback from tearDown)

        // When
        $result = $this->repository->findById(new ReservationId('00000000-0000-0000-0000-000000000000'));

        // Then
        self::assertNull($result);
    }

    #[Test]
    public function should_make_a_confirmed_reservation_retrievable_by_its_identifier_after_it_has_been_recorded(): void
    {
        // Given
        $reservation = Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-001',
            roomId: 'eiffel',
            organizerId: 'alice@example.com',
            status: 'CONFIRMED',
            start: new \DateTimeImmutable('2026-03-09 10:00:00'),
            end: new \DateTimeImmutable('2026-03-09 11:00:00'),
        ));

        $this->repository->save($reservation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // When
        $result = $this->repository->findById(new ReservationId('res-001'));

        // Then
        self::assertNotNull($result);
        self::assertSame('res-001', $result->toSnapshot()->id);
    }

}
