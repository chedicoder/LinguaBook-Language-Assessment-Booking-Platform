<?php

namespace App\Controller;

use App\Document\User;
use App\Exception\ApiException;
use App\Repository\ReservationRepository;
use App\Service\ApiResponder;
use App\Service\ApiView;
use App\Service\JsonBody;
use App\Service\ReservationService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/reservations')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly ReservationService $service,
        private readonly DocumentManager $documents,
        private readonly JsonBody $jsonBody,
        private readonly ApiResponder $responder,
        private readonly ApiView $view
    ) {
    }

    #[Route('', name: 'reservations_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody->decode($request);
        if (array_diff(array_keys($data), ['sessionId']) !== []
            || !isset($data['sessionId']) || !is_string($data['sessionId'])) {
            throw new ApiException(422, 'validation_failed', 'sessionId is required and must be a string.');
        }
        $reservation = $this->service->reserve($this->currentUser(), $data['sessionId']);
        return $this->responder->success(['reservation' => $this->view->reservation($reservation)], 201);
    }

    #[Route('', name: 'reservations_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request->query->get('page', '1'));
        $limit = min(50, $this->positiveInt($request->query->get('limit', '10')));
        $user = $this->currentUser();
        $items = $this->reservations->findForUser((string) $user->getId(), $page, $limit);
        $total = $this->documents->getDocumentCollection(\App\Document\Reservation::class)->countDocuments([
            'userId' => new \MongoDB\BSON\ObjectId($user->getId()),
        ]);
        return $this->responder->success(
            array_map(fn ($item) => $this->view->reservation($item), $items),
            200,
            ['page' => $page, 'limit' => $limit, 'total' => $total, 'pages' => (int) ceil($total / $limit)]
        );
    }

    #[Route('/{id}', name: 'reservations_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $reservation = $this->reservations->find($id);
        if ($reservation === null) {
            throw new ApiException(404, 'reservation_not_found', 'Reservation was not found.');
        }
        if ($reservation->getUserId() !== $this->currentUser()->getId()) {
            throw new ApiException(404, 'reservation_not_found', 'Reservation was not found.');
        }
        return $this->responder->success(['reservation' => $this->view->reservation($reservation)]);
    }

    #[Route('/{id}', name: 'reservations_cancel', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->service->cancel($this->currentUser(), $id);
        return new JsonResponse(null, 204);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'unauthorized', 'Authentication is required.');
        }
        return $user;
    }

    private function positiveInt(string $value): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new ApiException(422, 'invalid_pagination', 'page and limit must be positive integers.');
        }
        return $parsed;
    }
}
