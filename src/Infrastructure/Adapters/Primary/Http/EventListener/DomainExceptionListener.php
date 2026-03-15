<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Primary\Http\EventListener;

use App\Application\Exception\ReservationNotFoundException;
use App\Application\Exception\RoomNotFoundException;
use App\Domain\Exception\BookingHorizonExceededException;
use App\Domain\Exception\InsufficientAdvanceNoticeException;
use App\Domain\Exception\InvalidTimeslotException;
use App\Domain\Exception\NotTheOrganizerException;
use App\Domain\Exception\ReservationAlreadyStartedException;
use App\Domain\Exception\RoomCapacityExceededException;
use App\Domain\Exception\TimeslotConflictException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class DomainExceptionListener
{
    private const array STATUS_MAP = [
        RoomNotFoundException::class => 404,
        ReservationNotFoundException::class => 404,
        NotTheOrganizerException::class => 403,
        TimeslotConflictException::class => 409,
        ReservationAlreadyStartedException::class => 409,
        RoomCapacityExceededException::class => 422,
        BookingHorizonExceededException::class => 422,
        InsufficientAdvanceNoticeException::class => 422,
        InvalidTimeslotException::class => 422,
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = self::STATUS_MAP[$exception::class] ?? null;

        if ($status === null) {
            return;
        }

        $event->setResponse(new JsonResponse(null, $status));
    }
}
