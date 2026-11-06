<?php

namespace App\Tests\Integration;

use App\Document\Session;
use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Client;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationConcurrencyTest extends KernelTestCase
{
    private DocumentManager $documents;
    private Client $mongo;
    private string $database;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->documents = $container->get(DocumentManager::class);
        $this->mongo = $container->get(Client::class);
        $this->database = (string) $_SERVER['MONGODB_DB'];
        $this->mongo->selectDatabase($this->database)->drop();
        $this->documents->getSchemaManager()->ensureIndexes();
    }

    public function testOnlyOneOfTwoConcurrentRequestsCanBookTheLastPlace(): void
    {
        $user = new User('Concurrent User', 'concurrent@example.test', 'unused');
        $session = new Session('English', new \DateTimeImmutable('+2 days'), 'London', 1);
        $this->documents->persist($user);
        $this->documents->persist($session);
        $this->documents->flush();

        $userId = (string) $user->getId();
        $sessionId = (string) $session->getId();
        $worker = dirname(__DIR__).'/fixtures/reserve_worker.php';
        $environment = getenv();
        $environment['APP_ENV'] = 'test';
        $environment['MONGODB_DB'] = $this->database;

        $processes = [];
        for ($index = 0; $index < 2; $index++) {
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $worker, $userId, $sessionId],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__, 2),
                $environment
            );
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }

        $exitCodes = [];
        foreach ($processes as [$process, $pipes]) {
            stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCodes[] = proc_close($process);
            self::assertSame('', trim($stderr), 'Reservation worker failed: '.$stderr);
        }

        sort($exitCodes);
        self::assertSame([0, 1], $exitCodes);
        $storedSession = $this->documents->getDocumentCollection(Session::class)->findOne(['_id' => new \MongoDB\BSON\ObjectId($sessionId)]);
        self::assertSame(1, $storedSession['reservedCount']);
        self::assertSame(1, $this->documents->getDocumentCollection(\App\Document\Reservation::class)->countDocuments());
    }

    public function testReservationCancellationReleasesTheCapacity(): void
    {
        $user = new User('Booking User', 'booking@example.test', 'unused');
        $otherUser = new User('Other User', 'other@example.test', 'unused');
        $session = new Session('French', new \DateTimeImmutable('+3 days'), 'Paris', 1);
        $this->documents->persist($user);
        $this->documents->persist($otherUser);
        $this->documents->persist($session);
        $this->documents->flush();

        $service = static::getContainer()->get(\App\Service\ReservationService::class);
        $reservation = $service->reserve($user, (string) $session->getId());
        try {
            $service->cancel($otherUser, (string) $reservation->getId());
            self::fail('A user must not cancel another user reservation.');
        } catch (\App\Exception\ApiException $exception) {
            self::assertSame(404, $exception->status);
        }
        $service->cancel($user, (string) $reservation->getId());

        $storedSession = $this->documents->getDocumentCollection(Session::class)->findOne([
            '_id' => new \MongoDB\BSON\ObjectId((string) $session->getId()),
        ]);
        self::assertSame(0, $storedSession['reservedCount']);
        self::assertSame('cancelled', $this->documents->getDocumentCollection(\App\Document\Reservation::class)
            ->findOne(['_id' => new \MongoDB\BSON\ObjectId((string) $reservation->getId())])['status']);
    }

    public function testDuplicateBookingAndFullSessionsAreRejected(): void
    {
        $firstUser = new User('First User', 'first@example.test', 'unused');
        $secondUser = new User('Second User', 'second@example.test', 'unused');
        $session = new Session('German', new \DateTimeImmutable('+4 days'), 'Berlin', 1);
        $this->documents->persist($firstUser);
        $this->documents->persist($secondUser);
        $this->documents->persist($session);
        $this->documents->flush();
        $service = static::getContainer()->get(\App\Service\ReservationService::class);
        $service->reserve($firstUser, (string) $session->getId());

        try {
            $service->reserve($firstUser, (string) $session->getId());
            self::fail('A duplicate reservation should be rejected.');
        } catch (\App\Exception\ApiException $exception) {
            self::assertSame(409, $exception->status);
            self::assertSame('duplicate_reservation', $exception->errorCode);
        }

        try {
            $service->reserve($secondUser, (string) $session->getId());
            self::fail('A full session should reject another reservation.');
        } catch (\App\Exception\ApiException $exception) {
            self::assertSame(409, $exception->status);
        }
    }
}
