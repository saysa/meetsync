<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BookRoomController
{
    #[Route('/reservations', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse(['reservation_id' => 'fake-id'], 201);
    }
}
