<?php

namespace App\Repository;

use App\Document\Session;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\Regex;

final class SessionRepository
{
    public function __construct(private readonly DocumentManager $documents)
    {
    }

    public function find(string $id): ?Session
    {
        if (!\MongoDB\BSON\ObjectId::isValid($id)) {
            return null;
        }

        $session = $this->documents->getRepository(Session::class)->find($id);
        return $session instanceof Session && !$session->isArchived() ? $session : null;
    }

    public function findIncludingArchived(string $id): ?Session
    {
        if (!\MongoDB\BSON\ObjectId::isValid($id)) {
            return null;
        }

        $session = $this->documents->getRepository(Session::class)->find($id);
        return $session instanceof Session ? $session : null;
    }

    /** @return array{items: list<Session>, total: int} */
    public function search(
        int $page,
        int $limit,
        ?string $language,
        ?\DateTimeImmutable $from,
        ?\DateTimeImmutable $to,
        bool $available,
        string $sort,
        string $direction
    ): array {
        $filter = ['archivedAt' => null];
        if ($language !== null && $language !== '') {
            $filter['language'] = new Regex('^'.preg_quote($language, '/').'$', 'i');
        }

        $range = [];
        if ($from !== null) {
            $range['$gte'] = $from;
        }
        if ($to !== null) {
            $range['$lte'] = $to;
        }
        if ($range !== []) {
            $filter['startsAt'] = $range;
        } elseif ($from === null && $to === null) {
            $filter['startsAt'] = ['$gte' => new \DateTimeImmutable()];
        }
        if ($available) {
            $filter['$expr'] = ['$lt' => ['$reservedCount', '$capacity']];
        }

        $collection = $this->documents->getDocumentCollection(Session::class);
        $cursor = $collection->find($filter, [
            'sort' => [$sort => $direction === 'desc' ? -1 : 1, '_id' => 1],
            'skip' => ($page - 1) * $limit,
            'limit' => $limit,
        ]);
        $items = [];
        foreach ($cursor as $row) {
            $session = $this->documents->getRepository(Session::class)->find((string) $row['_id']);
            if ($session instanceof Session) {
                $items[] = $session;
            }
        }

        return ['items' => $items, 'total' => $collection->countDocuments($filter)];
    }

    public function save(Session $session): void
    {
        $this->documents->persist($session);
        $this->documents->flush();
    }

    public function clearIdentityMap(): void
    {
        $this->documents->clear(Session::class);
    }
}
