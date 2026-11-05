<?php

namespace App\Service;

use App\Exception\ApiException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DtoValidator
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);
        if (count($violations) === 0) {
            return;
        }

        $details = [];
        foreach ($violations as $violation) {
            $details[(string) $violation->getPropertyPath()][] = $violation->getMessage();
        }

        throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.', $details);
    }
}
