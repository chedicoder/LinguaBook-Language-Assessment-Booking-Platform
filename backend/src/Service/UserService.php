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

    public function update(User $user, AccountUpdateRequest $input): User
    {
        $this->validator->validate($input);
        $emailOwner = $this->users->findByEmail($input->email);
        if ($emailOwner !== null && $emailOwner->getId() !== $user->getId()) {
            throw new ApiException(409, 'email_in_use', 'An account with this email already exists.');
        }

        $user->rename($input->name);
        $user->changeEmail($input->email);
        $this->users->save($user);

        return $user;
    }
}
