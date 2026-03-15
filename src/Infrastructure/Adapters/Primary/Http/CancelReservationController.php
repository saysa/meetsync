<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CancelReservationController
{
    #[Route('/reservations/{id}', methods: ['DELETE'])]
    public function __invoke(string $id): Response
    {
        return new Response(null, 204);
    }
}
