<?php

namespace App\Controller;

use App\Document\User;
use App\DTO\AccountUpdateRequest;
use App\Service\ApiResponder;
use App\Service\ApiView;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $users,
        private readonly ApiView $view,
        private readonly ApiResponder $responder
    ) {
    }

    #[Route('/me', name: 'users_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->responder->success($user);
    }

    #[Route('/me', name: 'users_update_me', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $input = $this->deserializeUpdateRequest($request);

        $updatedUser = $this->users->update($user, $input);

        return $this->responder->success(['user' => $this->view->user($updatedUser)]);
    }

    #[Route('', name: 'users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): JsonResponse
    {
        return $this->responder->success($this->users->findAll());
    }

    #[Route('/{id}', name: 'users_show', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function show(string $id): JsonResponse
    {
        return $this->responder->success($this->users->findById($id));
    }

    #[Route('/{id}', name: 'users_update', methods: ['PATCH'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->users->findById($id);
        $input = $this->deserializeUpdateRequest($request);

        $updatedUser = $this->users->update($user, $input);

        return $this->responder->success($updatedUser);
    }

    #[Route('/{id}', name: 'users_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(string $id): JsonResponse
    {
        $user = $this->users->findById($id);

        $this->users->delete($user);

        return $this->responder->success([
            'message' => 'User deleted successfully.',
        ]);
    }

    private function deserializeUpdateRequest(Request $request): AccountUpdateRequest
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON request body.');
        }

        return new AccountUpdateRequest(
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
        );
    }
}
