<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http;

use App\Application\Command\BookRoomCommand;
use App\Application\UseCase\BookRoomUseCase;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BookRoomController
{
    #[Route('/reservations', methods: ['POST'])]
    public function __invoke(Request $request, BookRoomUseCase $useCase): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $reservationId = $useCase->execute(new BookRoomCommand(
            roomId: $data['room_id'],
            start: new DateTimeImmutable($data['start']),
            end: new DateTimeImmutable($data['end']),
            participantCount: $data['participant_count'],
            organizerEmail: $data['organizer_email'],
        ));

        return new JsonResponse(['reservation_id' => $reservationId->value], 201);
    }
}
