<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use App\Application\Query\GetMyReservationsQuery;
use App\Application\UseCase\GetMyReservationsUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class GetMyReservationsController
{
    #[Route('/reservations', methods: ['GET'])]
    public function __invoke(Request $request, GetMyReservationsUseCase $useCase): JsonResponse
    {
        $reservations = $useCase->execute(new GetMyReservationsQuery(
            organizerId: $request->query->get('organizer_id', ''),
        ));

        $result = [];
        foreach ($reservations as $reservation) {
            $snapshot = $reservation->toSnapshot();
            $result[] = [
                'reservation_id' => $snapshot->id,
                'room_id' => $snapshot->roomId,
                'start' => $snapshot->start->format('c'),
                'end' => $snapshot->end->format('c'),
                'status' => $snapshot->status,
            ];
        }

        return new JsonResponse($result);
    }
}
