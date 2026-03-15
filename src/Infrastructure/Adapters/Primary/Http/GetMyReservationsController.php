<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetMyReservationsController
{
    #[Route('/reservations', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([]);
    }
}
