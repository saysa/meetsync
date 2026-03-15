<?php

declare(strict_types=1);

namespace App\Tests\E2E\Http;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationSnapshot;
use App\Tests\Fixtures\FakeClock;
use App\Tests\Fixtures\InMemoryReservationRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetMyReservationsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InMemoryReservationRepository $reservationRepository;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->reservationRepository = $container->get(InMemoryReservationRepository::class);
        $this->clock = $container->get(FakeClock::class);
    }

    #[Test]
    public function should_return_200_with_an_empty_list_when_the_organizer_has_no_upcoming_reservations(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        // No reservations seeded

        // When
        $this->client->request(
            method: 'GET',
            uri: '/reservations?organizer_id=alice.martin@acme.com',
        );

        // Then
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([], $data);
    }

    #[Test]
    public function should_return_200_with_the_organizers_upcoming_reservations_ordered_by_start_time(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        // Insert in reverse chronological order to test sorting
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-louvre',
            roomId: 'louvre',
            organizerId: 'alice.martin@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-10 10:00:00'),
            end: new DateTimeImmutable('2026-03-10 12:00:00'),
        )));
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-eiffel',
            roomId: 'eiffel',
            organizerId: 'alice.martin@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        )));

        // When
        $this->client->request(
            method: 'GET',
            uri: '/reservations?organizer_id=alice.martin@acme.com',
        );

        // Then
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(2, $data);
        self::assertSame('res-eiffel', $data[0]['reservation_id']);
        self::assertArrayHasKey('room_id', $data[0]);
        self::assertArrayHasKey('start', $data[0]);
        self::assertArrayHasKey('end', $data[0]);
        self::assertArrayHasKey('status', $data[0]);
    }

    #[Test]
    public function should_return_200_with_only_future_reservations_when_past_reservations_also_exist(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-past',
            roomId: 'montmartre',
            organizerId: 'alice.martin@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-05 09:00:00'),
            end: new DateTimeImmutable('2026-03-05 10:00:00'),
        )));
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-future',
            roomId: 'eiffel',
            organizerId: 'alice.martin@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        )));

        // When
        $this->client->request(
            method: 'GET',
            uri: '/reservations?organizer_id=alice.martin@acme.com',
        );

        // Then
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame('res-future', $data[0]['reservation_id']);
    }
}
