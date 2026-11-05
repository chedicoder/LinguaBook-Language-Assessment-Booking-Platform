<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AccountUpdateRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 120)]
        public readonly string $name,
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public readonly string $email
    ) {
    }
}
