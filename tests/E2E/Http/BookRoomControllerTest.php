<?php

declare(strict_types=1);

namespace App\Tests\E2E\Http;

use App\Domain\Reservation\Reservation;
use App\Domain\Reservation\ReservationSnapshot;
use App\Domain\Reservation\Room;
use App\Domain\Reservation\RoomSnapshot;
use App\Tests\Fixtures\FakeClock;
use App\Tests\Fixtures\InMemoryReservationRepository;
use App\Tests\Fixtures\InMemoryRoomRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BookRoomControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InMemoryRoomRepository $roomRepository;
    private InMemoryReservationRepository $reservationRepository;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->roomRepository = $container->get(InMemoryRoomRepository::class);
        $this->reservationRepository = $container->get(InMemoryReservationRepository::class);
        $this->clock = $container->get(FakeClock::class);
    }

    #[Test]
    public function should_return_201_with_a_reservation_identifier_when_booking_a_free_room_with_valid_data(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        $this->roomRepository->add(Room::fromSnapshot(new RoomSnapshot(
            id: 'eiffel',
            capacity: 20,
            openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
            closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
        )));

        // When
        $this->client->request(
            method: 'POST',
            uri: '/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'room_id' => 'eiffel',
                'start' => '2026-03-09T14:00:00+00:00',
                'end' => '2026-03-09T15:30:00+00:00',
                'participant_count' => 2,
                'organizer_email' => 'alice.martin@acme.com',
            ]),
        );

        // Then
        self::assertResponseStatusCodeSame(201);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('reservation_id', $data);
        self::assertNotEmpty($data['reservation_id']);
    }

    #[Test]
    public function should_return_404_when_booking_a_room_that_does_not_exist_in_the_system(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        // No room seeded

        // When
        $this->client->request(
            method: 'POST',
            uri: '/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'room_id' => 'unknown-room',
                'start' => '2026-03-09T14:00:00+00:00',
                'end' => '2026-03-09T15:30:00+00:00',
                'participant_count' => 2,
                'organizer_email' => 'alice.martin@acme.com',
            ]),
        );

        // Then
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function should_return_409_when_the_requested_timeslot_conflicts_with_an_existing_confirmed_reservation(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        $this->roomRepository->add(Room::fromSnapshot(new RoomSnapshot(
            id: 'eiffel',
            capacity: 20,
            openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
            closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
        )));
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'existing-res',
            roomId: 'eiffel',
            organizerId: 'bob.chen@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-09 10:00:00'),
            end: new DateTimeImmutable('2026-03-09 12:00:00'),
        )));

        // When
        $this->client->request(
            method: 'POST',
            uri: '/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'room_id' => 'eiffel',
                'start' => '2026-03-09T10:30:00+00:00',
                'end' => '2026-03-09T11:30:00+00:00',
                'participant_count' => 1,
                'organizer_email' => 'alice.martin@acme.com',
            ]),
        );

        // Then
        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function should_return_422_when_the_participant_count_exceeds_the_room_capacity(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        $this->roomRepository->add(Room::fromSnapshot(new RoomSnapshot(
            id: 'montmartre',
            capacity: 4,
            openingTime: new DateTimeImmutable('2026-03-09 08:00:00'),
            closingTime: new DateTimeImmutable('2026-03-09 19:00:00'),
        )));

        // When
        $this->client->request(
            method: 'POST',
            uri: '/reservations',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'room_id' => 'montmartre',
                'start' => '2026-03-09T14:00:00+00:00',
                'end' => '2026-03-09T15:30:00+00:00',
                'participant_count' => 5,
                'organizer_email' => 'alice.martin@acme.com',
            ]),
        );

        // Then
        self::assertResponseStatusCodeSame(422);
    }
}
