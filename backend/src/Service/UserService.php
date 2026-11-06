<?php

namespace App\Service;

use App\Document\User;
use App\DTO\AccountUpdateRequest;
use App\Exception\ApiException;
use App\Repository\UserRepository;

final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DtoValidator $validator
    ) {
    }

    public function findById(string $id): User
    {
        $user = $this->users->find($id);

        if ($user === null) {
            throw new ApiException(404, 'user_not_found', 'User not found.');
        }

        return $user;
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->users->findAll();
    }

    public function update(User $user, AccountUpdateRequest $input): User
    {
        $this->validator->validate($input);

        if ($input->name !== null) {
            $user->rename($input->name);
        }

        if ($input->email !== null) {
            $emailOwner = $this->users->findByEmail($input->email);

            if ($emailOwner !== null && $emailOwner->getId() !== $user->getId()) {
                throw new ApiException(409, 'email_in_use', 'An account with this email already exists.');
            }

            $user->changeEmail($input->email);
        }

        $this->users->save($user);

        return $user;
    }

    public function delete(User $user): void
    {
        $this->users->delete($user);
    }
}
