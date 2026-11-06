<?php

namespace App\Security;

use App\Document\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class UserProvider implements UserProviderInterface
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $isObjectId = preg_match('/^[a-f\d]{24}$/i', $identifier) === 1;

        $user = $isObjectId
            ? $this->users->find($identifier)
            : $this->users->findByEmail($identifier);

        if ($user === null) {
            $exception = new UserNotFoundException('User not found.');
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User || $user->getId() === null) {
            throw new \InvalidArgumentException('Unsupported user class.');
        }

        return $this->users->find($user->getId())
            ?? throw new UserNotFoundException('User no longer exists.');
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }
}