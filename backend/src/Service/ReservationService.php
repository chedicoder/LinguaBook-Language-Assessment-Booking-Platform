<?php

namespace App\Service;

use App\Document\Reservation;
use App\Document\Session;
use App\Document\User;
use App\Exception\ApiException;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Session as MongoSession;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class ReservationService
{
    public function __construct(
        private readonly Client $client,
        private readonly string $mongoDatabase,
        private readonly DocumentManager $documents,
        private readonly SessionRepository $sessions,
        private readonly ReservationRepository $reservations
    ) {
    }

    public function reserve(User $user, string $sessionId): Reservation
    {
        if ($user->getId() === null || !ObjectId::isValid($sessionId)) {
            throw new ApiException(404, 'session_not_found', 'Session was not found.');
        }

        $sessionObjectId = new ObjectId($sessionId);
        $userObjectId = new ObjectId($user->getId());
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            throw new ApiException(404, 'session_not_found', 'Session was not found.');
        }
        if ($session->getStartsAt() <= new \DateTimeImmutable()) {
            throw new ApiException(409, 'session_in_past', 'This session is no longer bookable.');
        }

        $mongoSession = $this->client->startSession();
        try {
            $reservationId = new ObjectId();
            $this->runTransaction($mongoSession, function (MongoSession $transaction) use (
                $sessionObjectId,
                $userObjectId,
                $reservationId
            ): void {
                $sessions = $this->client->selectCollection($this->mongoDatabase, 'sessions');
                $reservations = $this->client->selectCollection($this->mongoDatabase, 'reservations');
                $now = new UTCDateTime();
                if ($reservations->findOne([
                    'userId' => $userObjectId,
                    'sessionId' => $sessionObjectId,
                ], ['session' => $transaction]) !== null) {
                    throw new ApiException(409, 'duplicate_reservation', 'You have already reserved this session.');
                }

                $result = $sessions->updateOne([
                    '_id' => $sessionObjectId,
                    'startsAt' => ['$gt' => $now],
                    'archivedAt' => null,
                    '$expr' => ['$lt' => ['$reservedCount', '$capacity']],
                ], ['$inc' => ['reservedCount' => 1], '$set' => ['updatedAt' => $now]], [
                    'session' => $transaction,
                ]);

                if ($result->getMatchedCount() !== 1) {
                    throw new ApiException(409, 'session_unavailable', 'This session is full or no longer bookable.');
                }

                $reservations->insertOne([
                    '_id' => $reservationId,
                    'sessionId' => $sessionObjectId,
                    'userId' => $userObjectId,
                    'reservedAt' => $now,
                    'status' => Reservation::STATUS_ACTIVE,
                    'cancelledAt' => null,
                ], ['session' => $transaction]);
            });
        } catch (BulkWriteException $exception) {
            if ($exception->getCode() === 11000) {
                throw new ApiException(409, 'duplicate_reservation', 'You have already reserved this session.');
            }
            throw $exception;
        } finally {
            $mongoSession->endSession();
            $this->documents->clear();
        }

        $reservation = $this->reservations->find((string) $reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Reservation committed but could not be read back.');
        }

        return $reservation;
    }

    public function cancel(User $user, string $reservationId): void
    {
        if ($user->getId() === null || !ObjectId::isValid($reservationId)) {
            throw new ApiException(404, 'reservation_not_found', 'Reservation was not found.');
        }

        $reservationObjectId = new ObjectId($reservationId);
        $userObjectId = new ObjectId($user->getId());
        $session = $this->client->startSession();
        try {
            $this->runTransaction($session, function (MongoSession $transaction) use ($reservationObjectId, $userObjectId): void {
                $reservations = $this->client->selectCollection($this->mongoDatabase, 'reservations');
                $reservation = $reservations->findOne([
                    '_id' => $reservationObjectId,
                    'userId' => $userObjectId,
                ], ['session' => $transaction]);
                if ($reservation === null) {
                    throw new ApiException(404, 'reservation_not_found', 'Reservation was not found.');
                }
                if ($reservation['status'] !== Reservation::STATUS_ACTIVE) {
                    throw new ApiException(409, 'reservation_cancelled', 'Reservation is already cancelled.');
                }

                $now = new UTCDateTime();
                $changed = $reservations->updateOne([
                    '_id' => $reservationObjectId,
                    'userId' => $userObjectId,
                    'status' => Reservation::STATUS_ACTIVE,
                ], ['$set' => ['status' => Reservation::STATUS_CANCELLED, 'cancelledAt' => $now]], [
                    'session' => $transaction,
                ]);
                if ($changed->getModifiedCount() !== 1) {
                    throw new ApiException(409, 'reservation_cancelled', 'Reservation is already cancelled.');
                }

                $released = $this->client->selectCollection($this->mongoDatabase, 'sessions')->updateOne([
                    '_id' => $reservation['sessionId'],
                    'reservedCount' => ['$gt' => 0],
                ], ['$inc' => ['reservedCount' => -1], '$set' => ['updatedAt' => $now]], [
                    'session' => $transaction,
                ]);
                if ($released->getMatchedCount() !== 1) {
                    throw new \RuntimeException('Reservation counter is inconsistent.');
                }
            });
        } finally {
            $session->endSession();
            $this->documents->clear();
        }
    }

    private function runTransaction(MongoSession $session, callable $operation): void
    {
        $session->withTransaction($operation, [
            'readConcern' => new \MongoDB\Driver\ReadConcern(\MongoDB\Driver\ReadConcern::MAJORITY),
            'writeConcern' => new \MongoDB\Driver\WriteConcern(\MongoDB\Driver\WriteConcern::MAJORITY),
        ]);
    }
}
