<?php

namespace App\Service;

use App\Document\User;
use App\DTO\RegisterRequest;
use App\Exception\ApiException;
use App\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly DtoValidator $validator
    ) {
    }

    public function register(RegisterRequest $input): User
    {
        $this->validator->validate($input);
        if ($this->users->findByEmail($input->email) !== null) {
            throw new ApiException(409, 'email_in_use', 'An account with this email already exists.');
        }

        $user = new User($input->name, $input->email, '');
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->password));
        $this->users->save($user);

        return $user;
    }

    public function verifyCredentials(string $email, string $password): User
    {
        $user = $this->users->findByEmail($email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new ApiException(401, 'invalid_credentials', 'Email or password is incorrect.');
        }

        return $user;
    }
}
