<?php

namespace App\Controller;

use App\Document\User;
use App\DTO\AccountUpdateRequest;
use App\Exception\ApiException;
use App\Service\ApiResponder;
use App\Service\ApiView;
use App\Service\JsonBody;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly ApiResponder $responder,
        private readonly ApiView $view,
        private readonly JsonBody $jsonBody,
        private readonly UserService $users
    ) {
    }

    #[Route('/me', name: 'users_me', methods: ['GET'])]
    public function show(): JsonResponse
    {
        return $this->responder->success(['user' => $this->view->user($this->currentUser())]);
    }

    #[Route('/me', name: 'users_update_me', methods: ['PATCH'])]
    public function update(Request $request): JsonResponse
    {
        $data = $this->jsonBody->decode($request);
        if (array_diff(array_keys($data), ['name', 'email']) !== []) {
            throw new ApiException(422, 'unknown_fields', 'The request contains unsupported fields.');
        }
        if ($data === []) {
            throw new ApiException(422, 'validation_failed', 'At least one profile field must be provided.');
        }
        $user = $this->currentUser();
        $name = array_key_exists('name', $data) ? $data['name'] : $user->getName();
        $email = array_key_exists('email', $data) ? $data['email'] : $user->getEmail();
        if (!is_string($name) || !is_string($email)) {
            throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
        }
        $input = new AccountUpdateRequest($name, $email);
        $user = $this->users->update($user, $input);
        return $this->responder->success(['user' => $this->view->user($user)]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new ApiException(401, 'unauthorized', 'Authentication is required.');
        }
        return $user;
    }
}
