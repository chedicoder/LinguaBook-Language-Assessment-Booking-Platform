<?php

namespace App\Controller;

use App\DTO\SessionRequest;
use App\Exception\ApiException;
use App\Repository\SessionRepository;
use App\Service\ApiResponder;
use App\Service\ApiView;
use App\Service\JsonBody;
use App\Service\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/sessions')]
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly SessionService $service,
        private readonly JsonBody $jsonBody,
        private readonly ApiResponder $responder,
        private readonly ApiView $view
    ) {
    }

    #[Route('', name: 'sessions_list', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $this->positiveInt($request->query->get('page', '1'), 'page');
        $limit = min(50, $this->positiveInt($request->query->get('limit', '10'), 'limit'));
        $language = $request->query->get('language');
        $available = filter_var($request->query->get('available', 'false'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($available === null) {
            throw new ApiException(422, 'invalid_filter', 'available must be true or false.');
        }
        $sort = $request->query->get('sort', 'startsAt');
        if (!in_array($sort, ['startsAt', 'language'], true)) {
            throw new ApiException(422, 'invalid_sort', 'sort must be startsAt or language.');
        }
        $direction = strtolower($request->query->get('direction', 'asc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new ApiException(422, 'invalid_sort', 'direction must be asc or desc.');
        }

        $from = $this->queryDate($request->query->get('from'));
        $to = $this->queryDate($request->query->get('to'));
        if ($from !== null && $to !== null && $from > $to) {
            throw new ApiException(422, 'invalid_date_range', 'from must be earlier than or equal to to.');
        }

        $result = $this->sessions->search($page, $limit, $language, $from, $to, $available, $sort, $direction);
        return $this->responder->success(
            array_map(fn ($session) => $this->view->session($session), $result['items']),
            200,
            ['page' => $page, 'limit' => $limit, 'total' => $result['total'], 'pages' => (int) ceil($result['total'] / $limit)]
        );
    }

    #[Route('/{id}', name: 'sessions_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $session = $this->sessions->find($id);
        if ($session === null) {
            throw new ApiException(404, 'session_not_found', 'Session was not found.');
        }
        return $this->responder->success(['session' => $this->view->session($session)]);
    }

    #[Route('', name: 'sessions_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request): JsonResponse
    {
        $data = $this->jsonBody->decode($request);
        $this->assertFields($data);
        $input = $this->sessionInput($data);
        $session = $this->service->create($input);
        return $this->responder->success(['session' => $this->view->session($session)], 201);
    }

    #[Route('/{id}', name: 'sessions_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request): JsonResponse
    {
        $session = $this->sessions->find($id);
        if ($session === null) {
            throw new ApiException(404, 'session_not_found', 'Session was not found.');
        }
        $session = $this->service->update($session, $this->jsonBody->decode($request));
        return $this->responder->success(['session' => $this->view->session($session)]);
    }

    #[Route('/{id}', name: 'sessions_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $session = $this->sessions->find($id);
        if ($session === null) {
            throw new ApiException(404, 'session_not_found', 'Session was not found.');
        }
        $this->service->archive($session);
        return new JsonResponse(null, 204);
    }

    private function sessionInput(array $data): SessionRequest
    {
        foreach (['language', 'startsAt', 'location', 'capacity'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
            }
        }
        if (!is_string($data['language']) || !is_string($data['startsAt'])
            || !is_string($data['location']) || !is_int($data['capacity'])) {
            throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
        }
        return new SessionRequest($data['language'], $data['startsAt'], $data['location'], $data['capacity']);
    }

    private function assertFields(array $data): void
    {
        if (array_diff(array_keys($data), ['language', 'startsAt', 'location', 'capacity']) !== []) {
            throw new ApiException(422, 'unknown_fields', 'The request contains unsupported fields.');
        }
    }

    private function positiveInt(string $value, string $field): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) {
            throw new ApiException(422, 'invalid_pagination', "$field must be a positive integer.");
        }
        return $parsed;
    }

    private function queryDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new ApiException(422, 'invalid_date', 'Date filters must be ISO-8601 dates with a timezone.');
        }
        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new \UnexpectedValueException('Invalid calendar date.');
            }
            return $date;
        } catch (\Throwable) {
            throw new ApiException(422, 'invalid_date', 'Date filters must be valid ISO-8601 dates with a timezone.');
        }
    }
}
