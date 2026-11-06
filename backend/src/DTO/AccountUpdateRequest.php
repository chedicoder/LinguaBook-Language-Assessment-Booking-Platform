<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AccountUpdateRequest
{
    public function __construct(
        #[Assert\When(
            expression: 'this.name !== null',
            constraints: [
                new Assert\NotBlank(),
                new Assert\Length(max: 120),
            ]
        )]
        public ?string $name = null,

        #[Assert\When(
            expression: 'this.email !== null',
            constraints: [
                new Assert\NotBlank(),
                new Assert\Email(),
                new Assert\Length(max: 180),
            ]
        )]
        public ?string $email = null
    ) {
    }
}