<?php

namespace App\Service;

use App\Document\Reservation;
use App\Document\Session;
use App\Document\User;
use App\Repository\SessionRepository;

final class ApiView
{
    public function __construct(private readonly SessionRepository $sessions)
    {
    }

    public function user(User $user): array
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getName(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    public function session(Session $session): array
    {
        return [
            'id' => $session->getId(),
            'language' => $session->getLanguage(),
            'startsAt' => $session->getStartsAt()->format(DATE_ATOM),
            'location' => $session->getLocation(),
            'capacity' => $session->getCapacity(),
            'reservedCount' => $session->getReservedCount(),
            'availablePlaces' => $session->getAvailablePlaces(),
        ];
    }

    public function reservation(Reservation $reservation): array
    {
        $session = $this->sessions->findIncludingArchived($reservation->getSessionId());

        return [
            'id' => $reservation->getId(),
            'sessionId' => $reservation->getSessionId(),
            'session' => $session === null ? null : $this->session($session),
            'reservedAt' => $reservation->getReservedAt()->format(DATE_ATOM),
            'status' => $reservation->getStatus(),
        ];
    }
}
