<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Adapters\Secondary\Doctrine;

use App\Domain\Reservation\RoomId;
use App\Domain\Reservation\RoomRepositoryInterface;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineRoomEntity;
use App\Infrastructure\Adapters\Secondary\Doctrine\DoctrineRoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineRoomRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RoomRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository    = new DoctrineRoomRepository($this->entityManager);
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

    private function seedRoom(
        string $id,
        int $capacity = 8,
        string $openingTime = '08:00',
        string $closingTime = '19:00',
    ): void {
        $entity              = new DoctrineRoomEntity();
        $entity->id          = $id;
        $entity->capacity    = $capacity;
        $entity->openingTime = new \DateTimeImmutable($openingTime);
        $entity->closingTime = new \DateTimeImmutable($closingTime);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    #[Test]
    public function should_preserve_the_capacity_and_the_operating_hours_of_a_room_exactly_when_it_is_retrieved(): void
    {
        // Given
        $this->seedRoom(id: 'louvre', capacity: 20, openingTime: '08:00', closingTime: '19:00');

        // When
        $snapshot = $this->repository->findById(new RoomId('louvre'))->toSnapshot();

        // Then
        self::assertSame('louvre', $snapshot->id);
        self::assertSame(20,       $snapshot->capacity);
        self::assertSame('08:00',  $snapshot->openingTime->format('H:i'));
        self::assertSame('19:00',  $snapshot->closingTime->format('H:i'));
    }

    #[Test]
    public function should_return_nothing_when_looking_up_a_room_identifier_that_does_not_exist_in_the_database(): void
    {
        // Given — empty table (guaranteed by transaction rollback from tearDown)

        // When
        $result = $this->repository->findById(new RoomId('unknown'));

        // Then
        self::assertNull($result);
    }

    #[Test]
    public function should_make_a_room_retrievable_by_its_identifier_when_it_has_been_seeded_in_the_database(): void
    {
        // Given
        $this->seedRoom('eiffel');

        // When
        $result = $this->repository->findById(new RoomId('eiffel'));

        // Then
        self::assertNotNull($result);
        self::assertSame('eiffel', $result->toSnapshot()->id);
    }
}
