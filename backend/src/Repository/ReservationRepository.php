<?php

namespace App\Repository;

use App\Document\Reservation;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;

final class ReservationRepository
{
    public function __construct(private readonly DocumentManager $documents)
    {
    }

    public function find(string $id): ?Reservation
    {
        if (!ObjectId::isValid($id)) {
            return null;
        }

        $reservation = $this->documents->getRepository(Reservation::class)->find($id);
        return $reservation instanceof Reservation ? $reservation : null;
    }

    /** @return list<Reservation> */
    public function findForUser(string $userId, int $page, int $limit): array
    {
        if (!ObjectId::isValid($userId)) {
            return [];
        }

        return $this->documents->getRepository(Reservation::class)->findBy(
            ['userId' => new ObjectId($userId)],
            ['reservedAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );
    }

    public function save(Reservation $reservation): void
    {
        $this->documents->persist($reservation);
        $this->documents->flush();
    }
}
