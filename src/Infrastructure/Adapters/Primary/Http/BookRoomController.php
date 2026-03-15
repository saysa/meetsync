<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BookRoomController
{
    #[Route('/reservations', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return new Response();
    }
}
