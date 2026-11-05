<?php

namespace App\Repository;

use App\Document\User;
use App\Exception\ApiException;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\BulkWriteException;

final class UserRepository
{
    public function __construct(private readonly DocumentManager $documents)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->documents
            ->getRepository(User::class)
            ->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function find(string $id): ?User
    {
        return $this->documents
            ->getRepository(User::class)
            ->find($id);
    }

    /**
     * @return User[]
     */
    public function findAll(): array
    {
        return $this->documents
            ->getRepository(User::class)
            ->findAll();
    }

    public function save(User $user): void
    {
        $this->documents->persist($user);

        try {
            $this->documents->flush();
        } catch (BulkWriteException $exception) {
            if ($exception->getCode() === 11000) {
                throw new ApiException(
                    409,
                    'email_in_use',
                    'An account with this email already exists.'
                );
            }

            throw $exception;
        }
    }

    public function delete(User $user): void
    {
        $this->documents->remove($user);
        $this->documents->flush();
    }
}