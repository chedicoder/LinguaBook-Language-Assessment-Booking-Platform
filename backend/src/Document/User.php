<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'users')]
#[ODM\Index(keys: ['email' => 'asc'], options: ['unique' => true])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private string $name;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email;

    #[ODM\Field(type: 'string')]
    private string $password;

    #[ODM\Field(type: 'collection')]
    private array $roles = ['ROLE_USER'];

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, string $email, string $password)
    {
        $this->name = trim($name);
        $this->email = strtolower(trim($email));
        $this->password = $password;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?string { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }

    public function rename(string $name): void
    {
        $this->name = trim($name);
        $this->touch();
    }

    public function changeEmail(string $email): void
    {
        $this->email = strtolower(trim($email));
        $this->touch();
    }

    public function getUserIdentifier(): string { return $this->id ?? $this->email; }
    public function getPassword(): ?string { return $this->password; }

    public function setPassword(string $password): void
    {
        $this->password = $password;
        $this->touch();
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    public function setRoles(array $roles): void
    {
        $this->roles = array_values(array_unique($roles));
        $this->touch();
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
