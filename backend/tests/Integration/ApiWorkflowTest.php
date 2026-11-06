<?php

namespace App\Tests\Integration;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ApiWorkflowTest extends WebTestCase
{
    private KernelBrowser $browser;
    private DocumentManager $documents;

    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->documents = static::getContainer()->get(DocumentManager::class);
        $database = (string) $_SERVER['MONGODB_DB'];
        static::getContainer()->get(\MongoDB\Client::class)->selectDatabase($database)->drop();
        $this->documents->getSchemaManager()->ensureIndexes();
    }

    public function testRegistrationLoginAndProtectedAccountUpdate(): void
    {
        $this->browser->request('GET', '/api/auth/me');
        self::assertSame(401, $this->browser->getResponse()->getStatusCode());

        $csrf = $this->csrfToken();
        $this->jsonRequest('POST', '/api/auth/register', [
            'name' => 'Taylor Reed',
            'email' => 'taylor@example.test',
            'password' => 'short',
        ], $csrf);
        self::assertSame(422, $this->browser->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/auth/register', [
            'name' => 'Taylor Reed',
            'email' => 'taylor@example.test',
            'password' => 'correct-horse-battery-42',
        ], $csrf);
        self::assertSame(201, $this->browser->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/auth/register', [
            'name' => 'Taylor Reed',
            'email' => 'taylor@example.test',
            'password' => 'correct-horse-battery-42',
        ], $csrf);
        self::assertSame(409, $this->browser->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'taylor@example.test',
            'password' => 'incorrect-password',
        ], $csrf);
        self::assertSame(401, $this->browser->getResponse()->getStatusCode());

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'taylor@example.test',
            'password' => 'correct-horse-battery-42',
        ], $csrf);
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request('GET', '/api/auth/me');
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $identity = $this->responseData();
        self::assertSame('taylor@example.test', $identity['user']['email']);
        self::assertArrayNotHasKey('password', $identity['user']);

        $csrf = $this->csrfToken();
        $this->jsonRequest('PATCH', '/api/users/me', [
            'name' => 'Taylor Updated',
            'email' => 'taylor.updated@example.test',
        ], $csrf);
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        self::assertSame('taylor.updated@example.test', $this->responseData()['user']['email']);

        $this->jsonRequest('POST', '/api/sessions', [
            'language' => 'English',
            'startsAt' => gmdate('Y-m-d\TH:i:s\Z', time() + 86400),
            'location' => 'London',
            'capacity' => 3,
        ], $csrf);
        self::assertSame(403, $this->browser->getResponse()->getStatusCode());
    }

    public function testAdminCanCreateUpdateAndArchiveSessionsAndListingPaginates(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $admin = new User('Admin User', 'admin@example.test', '');
        $admin->setPassword($passwordHasher->hashPassword($admin, 'a-secure-admin-password-123'));
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $this->documents->persist($admin);
        $this->documents->flush();

        $csrf = $this->csrfToken();
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => 'admin@example.test',
            'password' => 'a-secure-admin-password-123',
        ], $csrf);
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $csrf = $this->csrfToken();
        $future = fn (int $days) => gmdate('Y-m-d\TH:i:s\Z', time() + 86400 * $days);
        $this->jsonRequest('POST', '/api/sessions', [
            'language' => 'English',
            'startsAt' => $future(1),
            'location' => 'London',
            'capacity' => 0,
        ], $csrf);
        self::assertSame(422, $this->browser->getResponse()->getStatusCode());

        foreach ([1, 2] as $days) {
            $this->jsonRequest('POST', '/api/sessions', [
                'language' => 'English',
                'startsAt' => $future($days),
                'location' => 'London',
                'capacity' => 4,
            ], $csrf);
            self::assertSame(201, $this->browser->getResponse()->getStatusCode());
        }

        $this->browser->request('GET', '/api/sessions?page=1&limit=1');
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $listing = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(2, $listing['meta']['total']);
        self::assertSame(2, $listing['meta']['pages']);
        self::assertCount(1, $listing['data']);

        $sessionId = $listing['data'][0]['id'];
        $csrf = $this->csrfToken();
        $this->jsonRequest('PATCH', '/api/sessions/'.$sessionId, ['capacity' => 5], $csrf);
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        self::assertSame(5, $this->responseData()['session']['capacity']);

        $csrf = $this->csrfToken();
        $this->browser->request('DELETE', '/api/sessions/'.$sessionId, [], [], [
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ]);
        self::assertSame(204, $this->browser->getResponse()->getStatusCode());
        self::assertNull(static::getContainer()->get(\App\Repository\SessionRepository::class)->find($sessionId));
    }

    private function csrfToken(): string
    {
        $this->browser->request('GET', '/api/auth/csrf');
        self::assertSame(200, $this->browser->getResponse()->getStatusCode());
        return $this->responseData()['csrfToken'];
    }

    private function jsonRequest(string $method, string $path, array $data, string $csrf): void
    {
        $this->browser->request($method, $path, [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_CSRF_TOKEN' => $csrf,
        ], json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function responseData(): array
    {
        $payload = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        return $payload['data'];
    }
}
