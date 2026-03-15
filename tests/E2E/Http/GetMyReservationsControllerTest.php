<?php

declare(strict_types=1);

namespace App\Tests\E2E\Http;

use App\Tests\Fixtures\FakeClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetMyReservationsControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private FakeClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

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
}
