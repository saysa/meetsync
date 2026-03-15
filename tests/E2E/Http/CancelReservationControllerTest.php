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

final class CancelReservationControllerTest extends WebTestCase
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
    public function should_return_204_when_the_organizer_cancels_a_confirmed_reservation_before_it_starts(): void
    {
        // Given
        $this->clock->setNow(new DateTimeImmutable('2026-03-09 08:00:00 UTC'));
        $this->reservationRepository->add(Reservation::fromSnapshot(new ReservationSnapshot(
            id: 'res-001',
            roomId: 'eiffel',
            organizerId: 'alice.martin@acme.com',
            status: 'confirmed',
            start: new DateTimeImmutable('2026-03-09 14:00:00'),
            end: new DateTimeImmutable('2026-03-09 15:00:00'),
        )));

        // When
        $this->client->request(
            method: 'DELETE',
            uri: '/reservations/res-001',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'requester_id' => 'alice.martin@acme.com',
                'requester_email' => 'alice.martin@acme.com',
            ]),
        );

        // Then
        self::assertResponseStatusCodeSame(204);
    }
}
