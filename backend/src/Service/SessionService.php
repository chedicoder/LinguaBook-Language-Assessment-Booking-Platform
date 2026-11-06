<?php

namespace App\Service;

use App\Document\Session;
use App\DTO\SessionRequest;
use App\Exception\ApiException;
use App\Repository\SessionRepository;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;

final class SessionService
{
    public function __construct(
        private readonly Client $client,
        private readonly string $mongoDatabase,
        private readonly SessionRepository $sessions,
        private readonly DtoValidator $validator
    ) {
    }

    public function create(SessionRequest $input): Session
    {
        $this->validator->validate($input);
        $date = $this->parseDate($input->startsAt);
        if ($date <= new \DateTimeImmutable()) {
            throw new ApiException(422, 'invalid_session_date', 'Session date must be in the future.');
        }

        $session = new Session($input->language, $date, $input->location, $input->capacity);
        $this->sessions->save($session);
        return $session;
    }

    public function update(Session $session, array $data): Session
    {
        $allowed = ['language', 'startsAt', 'location', 'capacity'];
        if ($data === []) {
            throw new ApiException(422, 'validation_failed', 'At least one session field must be provided.');
        }
        if (array_diff(array_keys($data), $allowed) !== []) {
            throw new ApiException(422, 'unknown_fields', 'The request contains unsupported fields.');
        }
        $merged = [
            'language' => array_key_exists('language', $data) ? $data['language'] : $session->getLanguage(),
            'startsAt' => array_key_exists('startsAt', $data) ? $data['startsAt'] : $session->getStartsAt()->format(DATE_ATOM),
            'location' => array_key_exists('location', $data) ? $data['location'] : $session->getLocation(),
            'capacity' => array_key_exists('capacity', $data) ? $data['capacity'] : $session->getCapacity(),
        ];
        if (!is_string($merged['language']) || !is_string($merged['startsAt'])
            || !is_string($merged['location']) || !is_int($merged['capacity'])) {
            throw new ApiException(422, 'validation_failed', 'The submitted data is invalid.');
        }

        $input = new SessionRequest(...$merged);
        $this->validator->validate($input);
        $date = $this->parseDate($input->startsAt);
        if ($date <= new \DateTimeImmutable()) {
            throw new ApiException(422, 'invalid_session_date', 'Session date must be in the future.');
        }

        if ($session->getId() === null) {
            throw new \LogicException('Persisted session is missing its identifier.');
        }
        $result = $this->client->selectCollection($this->mongoDatabase, 'sessions')->updateOne([
            '_id' => new ObjectId($session->getId()),
            'archivedAt' => null,
            'reservedCount' => ['$lte' => $input->capacity],
        ], ['$set' => [
            'language' => trim($input->language),
            'startsAt' => new UTCDateTime($date),
            'location' => trim($input->location),
            'capacity' => $input->capacity,
            'updatedAt' => new UTCDateTime(),
        ]]);
        if ($result->getMatchedCount() !== 1) {
            throw new ApiException(409, 'capacity_below_reservations', 'Capacity cannot be lower than active reservations, or the session is no longer available.');
        }
        $this->sessions->clearIdentityMap();
        return $this->sessions->find($session->getId())
            ?? throw new ApiException(404, 'session_not_found', 'Session was not found.');
    }

    public function archive(Session $session): void
    {
        if ($session->getId() === null) {
            throw new \LogicException('Persisted session is missing its identifier.');
        }
        $result = $this->client->selectCollection($this->mongoDatabase, 'sessions')->updateOne([
            '_id' => new ObjectId($session->getId()),
            'archivedAt' => null,
            'reservedCount' => 0,
        ], ['$set' => ['archivedAt' => new UTCDateTime(), 'updatedAt' => new UTCDateTime()]]);
        if ($result->getMatchedCount() !== 1) {
            throw new ApiException(409, 'session_has_reservations', 'A session with active reservations cannot be deleted.');
        }
        $this->sessions->clearIdentityMap();
    }

    private function parseDate(string $value): \DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new ApiException(422, 'invalid_session_date', 'startsAt must be an ISO-8601 date with a timezone.');
        }
        try {
            $date = new \DateTimeImmutable($value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                throw new \UnexpectedValueException('Invalid calendar date.');
            }
            return $date;
        } catch (\Exception) {
            throw new ApiException(422, 'invalid_session_date', 'startsAt must be a valid ISO-8601 date.');
        }
    }
}
