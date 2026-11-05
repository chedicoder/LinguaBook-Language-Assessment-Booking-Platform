<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

#[ODM\Document(collection: 'sessions')]
#[ODM\Index(keys: ['startsAt' => 'asc'])]
#[ODM\Index(keys: ['language' => 'asc', 'startsAt' => 'asc'])]
#[ODM\Index(keys: ['archivedAt' => 'asc', 'startsAt' => 'asc'])]
class Session
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $language;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $startsAt;

    #[ODM\Field(type: 'string')]
    private string $location;

    #[ODM\Field(type: 'int')]
    private int $capacity;

    #[ODM\Field(type: 'int')]
    private int $reservedCount = 0;

    #[ODM\Field(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $language, \DateTimeImmutable $startsAt, string $location, int $capacity)
    {
        if ($capacity < 1) {
            throw new \DomainException('Capacity must be positive.');
        }

        $this->language = trim($language);
        $this->startsAt = $startsAt;
        $this->location = trim($location);
        $this->capacity = $capacity;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string { return $this->id; }
    public function getLanguage(): string { return $this->language; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function getLocation(): string { return $this->location; }
    public function getCapacity(): int { return $this->capacity; }
    public function getReservedCount(): int { return $this->reservedCount; }
    public function getAvailablePlaces(): int { return max(0, $this->capacity - $this->reservedCount); }
    public function isArchived(): bool { return $this->archivedAt !== null; }

    public function update(string $language, \DateTimeImmutable $startsAt, string $location, int $capacity): void
    {
        if ($capacity < 1 || $capacity < $this->reservedCount) {
            throw new \DomainException('Capacity must be positive and cannot be lower than active reservations.');
        }

        $this->language = trim($language);
        $this->startsAt = $startsAt;
        $this->location = trim($location);
        $this->capacity = $capacity;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        $this->archivedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->archivedAt;
    }
}
