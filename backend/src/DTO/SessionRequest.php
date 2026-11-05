<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class SessionRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public readonly string $language,
        #[Assert\NotBlank]
        #[Assert\Length(max: 80)]
        public readonly string $startsAt,
        #[Assert\NotBlank]
        #[Assert\Length(max: 240)]
        public readonly string $location,
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(10000)]
        public readonly int $capacity
    ) {
    }
}
