<?php

namespace App\Tests\Unit;

use App\Document\Reservation;
use App\Document\Session;
use App\Document\User;
use MongoDB\BSON\ObjectId;
use PHPUnit\Framework\TestCase;

final class DocumentInvariantTest extends TestCase
{
    public function testUserNormalizesEmailAndAlwaysHasUserRole(): void
    {
        $user = new User('Alex Chen', '  ALEX@Example.com ', 'hash');

        self::assertSame('alex@example.com', $user->getEmail());
        self::assertSame(['ROLE_USER'], $user->getRoles());

        $user->setRoles(['ROLE_ADMIN']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles());
    }

    public function testSessionRejectsCapacityBelowReservedPlaces(): void
    {
        $session = new Session('English', new \DateTimeImmutable('+2 days'), 'Paris', 4);
        $this->expectException(\DomainException::class);
        $session->update('English', new \DateTimeImmutable('+2 days'), 'Paris', 0);
    }

    public function testReservationCannotBeCancelledTwice(): void
    {
        $reservation = new Reservation(new ObjectId(), new ObjectId());
        $reservation->cancel();
        self::assertFalse($reservation->isActive());

        $this->expectException(\DomainException::class);
        $reservation->cancel();
    }
}
