<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use App\Application\Command\CancelReservationCommand;
use App\Application\UseCase\CancelReservationUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CancelReservationController
{
    #[Route('/reservations/{id}', methods: ['DELETE'])]
    public function __invoke(string $id, Request $request, CancelReservationUseCase $useCase): Response
    {
        $data = json_decode($request->getContent(), true);

        $useCase->execute(new CancelReservationCommand(
            reservationId: $id,
            requesterId: $data['requester_id'],
            requesterEmail: $data['requester_email'] ?? '',
        ));

        return new Response(null, 204);
    }
}
