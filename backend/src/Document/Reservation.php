<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use MongoDB\BSON\ObjectId;

#[ODM\Document(collection: 'reservations')]
#[ODM\Index(keys: ['userId' => 'asc', 'sessionId' => 'asc'], options: ['unique' => true])]
#[ODM\Index(keys: ['userId' => 'asc', 'reservedAt' => 'desc'])]
#[ODM\Index(keys: ['sessionId' => 'asc', 'status' => 'asc'])]
class Reservation
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';

    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'object_id')]
    private string $sessionId;

    #[ODM\Field(type: 'object_id')]
    private string $userId;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $reservedAt;

    #[ODM\Field(type: 'string')]
    private string $status = self::STATUS_ACTIVE;

    #[ODM\Field(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(string $sessionId, string $userId)
    {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->reservedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getReservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function cancel(): void
    {
        if (!$this->isActive()) {
            throw new \DomainException('Reservation is already cancelled.');
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable();
    }
}
