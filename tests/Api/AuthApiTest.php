<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\ActionLog;
use App\Entity\User;
use App\Util\DateHelper;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group AuthApi
 */
class AuthApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    public function testAuthRequiresPassword(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'username' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "password" must be provided.',
        ]);
    }

    public function testAuthRequiresUsername(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "username" must be provided.',
        ]);
    }

    public function testAuthRequiresUsernameToBeSet(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => 'null',
                'username' => null,
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "username" must be a string.',
        ]);
    }

    public function testAuthRequiresPasswordToBeSet(): void
    {
        static::createClient()->request('POST', '/authentication_token', [
            'json' => [
                'password' => null,
                'username' => 'null',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'type'   => 'https://tools.ietf.org/html/rfc2616#section-10',
            'title'  => 'An error occurred',
            'detail' => 'The key "password" must be a string.',
        ]);
    }

    public function testAuthWorks(): void
    {
        $before = new \DateTimeImmutable();

        $response = static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $auth = $response->toArray();
        self::assertArrayHasKey('token', $auth);
        self::assertArrayHasKey('refresh_token', $auth);
        self::assertArrayHasKey('refresh_token_expires', $auth);

        /** @var $decoder JWTEncoderInterface */
        $decoder = static::$container->get(JWTEncoderInterface::class);
        $decoded = $decoder->decode($auth['token']);

        self::assertArrayHasKey('exp', $decoded);
        self::assertSame(TestFixtures::ADMIN['username'], $decoded['username']);
        self::assertSame([User::ROLE_ADMIN, User::ROLE_USER], $decoded['roles']);

        // these are non-standard, added by our JWTEventSubscriber
        self::assertSame(TestFixtures::ADMIN['id'], $decoded['id']);
        self::assertArrayHasKey('editableProjects', $decoded);
        self::assertIsArray($decoded['editableProjects']);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $rtoken = $em->getRepository(RefreshToken::class)->findOneBy([
            'refreshToken' => $auth['refresh_token'],
        ]);

        self::assertInstanceOf(RefreshToken::class, $rtoken);

        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => TestFixtures::ADMIN['username']]);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::SUCCESSFUL_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testAuthWorksWithEmail(): void
    {
        $before = new \DateTimeImmutable();

        $r = static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['email'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $auth = $r->toArray();
        self::assertArrayHasKey('token', $auth);
        self::assertArrayHasKey('refresh_token', $auth);
        self::assertArrayHasKey('refresh_token_expires', $auth);

        /** @var $decoder JWTEncoderInterface */
        $decoder = static::$container->get(JWTEncoderInterface::class);
        $decoded = $decoder->decode($auth['token']);

        self::assertArrayHasKey('exp', $decoded);
        self::assertSame(TestFixtures::ADMIN['username'], $decoded['username']);
        self::assertSame([User::ROLE_ADMIN, User::ROLE_USER], $decoded['roles']);
        self::assertSame(TestFixtures::ADMIN['id'], $decoded['id']);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $rtoken = $em->getRepository(RefreshToken::class)->findOneBy([
            'refreshToken' => $auth['refresh_token'],
        ]);

        self::assertInstanceOf(RefreshToken::class, $rtoken);

        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => TestFixtures::ADMIN['username']]);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::SUCCESSFUL_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testAuthFailsWithUnknownUsername(): void
    {
        $before = new \DateTimeImmutable();

        static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => 'not-found',
            'password' => 'irrelevant',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.invalidCredentials',
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => 'not-found']);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::FAILED_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testAuthFailsWhenBlocked(): void
    {
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $l1 = new ActionLog();
        $l1->timestamp = DateHelper::nowSubInterval('PT10M');
        $l1->action = ActionLog::FAILED_LOGIN;
        $l1->username = TestFixtures::ADMIN['email'];
        $l1->ipAddress = '127.0.0.1';
        $em->persist($l1);

        $l2 = clone $l1;
        $l2->timestamp = DateHelper::nowSubInterval('PT30M');
        $em->persist($l2);

        $l3 = clone $l1;
        $l3->timestamp = DateHelper::nowSubInterval('PT59M');
        $em->persist($l3);

        $em->flush();

        $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['email'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'failure.accessBlocked',
        ]);
    }

    public function testAuthFailsWithWrongPassword(): void
    {
        $before = new \DateTimeImmutable();

        static::createClient()->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => 'this-is-wrong',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.invalidCredentials',
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => TestFixtures::ADMIN['username']]);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::FAILED_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testAuthFailsWithInactiveUser(): void
    {
        $before = new \DateTimeImmutable();
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->find(TestFixtures::ADMIN['id']);
        $admin->setActive(false);
        $em->flush();

        $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.notActivated',
        ]);

        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => TestFixtures::ADMIN['username']]);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::FAILED_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testAuthFailsWithNotValidatedUser(): void
    {
        $before = new \DateTimeImmutable();
        $client = static::createClient();

        $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::GUEST['username'],
            'password' => TestFixtures::GUEST['password'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.notValidated',
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['username' => TestFixtures::GUEST['username']]);
        self::assertCount(1, $logs);
        self::assertSame(ActionLog::FAILED_LOGIN, $logs[0]->action);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testRefreshTokenWorks(): void
    {
        $client = static::createClient();

        $r = $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);

        $auth = $r->toArray();
        self::assertArrayHasKey('refresh_token', $auth);
        self::assertArrayHasKey('refresh_token_expires', $auth);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $oldLogs = $em->getRepository(ActionLog::class)->findAll();
        foreach ($oldLogs as $oldLog) {
            $em->remove($oldLog);
        }
        $em->flush();

        $response = $client->request('POST', '/refresh_token', ['json' => [
            'refresh_token' => $auth['refresh_token'],
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');

        $result = $response->toArray();
        self::assertArrayHasKey('token', $result);
        self::assertArrayHasKey('refresh_token', $result);
        self::assertArrayHasKey('refresh_token_expires', $result);

        /* @var $decoder JWTEncoderInterface */
        $decoder = static::$container->get(JWTEncoderInterface::class);
        $decoded = $decoder->decode($auth['token']);

        self::assertArrayHasKey('exp', $decoded);
        self::assertSame(TestFixtures::ADMIN['username'], $decoded['username']);
        self::assertSame([User::ROLE_ADMIN, User::ROLE_USER], $decoded['roles']);

        $oldToken = $em->getRepository(RefreshToken::class)->findOneBy([
            'refreshToken' => $auth['refresh_token'],
        ]);
        self::assertNull($oldToken);

        $newToken = $em->getRepository(RefreshToken::class)->findOneBy([
            'refreshToken' => $result['refresh_token'],
        ]);
        self::assertInstanceOf(RefreshToken::class, $newToken);

        // refreshing creates *no* login logs, this is only for manual logins
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::SUCCESSFUL_LOGIN]);
        self::assertCount(0, $logs);
    }

    /**
     * requires zalas/phpunit-globals.
     *
     * @env REFRESH_TOKEN_TTL=3
     */
    public function testRefreshFailsWithExpiredToken(): void
    {
        $client = static::createClient();

        $r = $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);
        $auth = $r->toArray();

        sleep(5);

        $client->request('POST', '/refresh_token', ['json' => [
            'refresh_token' => $auth['refresh_token'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,

            // @todo Gesdinet\JWTRefreshTokenBundle\Service\Refreshtoken wirft basic AuthenticationExceptions
            // ohne individuellen messageKey, dieser wird aber von Lexik im AuthenticationFailureHandler
            // für die Rückgabe verwendet, daher haben alle Fehler diese Meldung, egal welche Ursache...
            'message' => 'Es ist ein Fehler bei der Authentifikation aufgetreten.',
        ]);
    }

    public function testRefreshFailsWithInvalidToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/refresh_token', ['json' => [
            'refresh_token' => 'deadbeef',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,

            // @todo Gesdinet\JWTRefreshTokenBundle\Service\Refreshtoken wirft basic AuthenticationExceptions
            // ohne individuellen messageKey, dieser wird aber von Lexik im AuthenticationFailureHandler
            // für die Rückgabe verwendet, daher haben alle Fehler diese Meldung, egal welche Ursache...
            'message' => 'Es ist ein Fehler bei der Authentifikation aufgetreten.',
        ]);
    }

    public function testRefreshFailsWithInactiveUser(): void
    {
        $client = static::createClient();
        $r = $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::ADMIN['username'],
            'password' => TestFixtures::ADMIN['password'],
        ]]);
        $auth = $r->toArray();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->find(TestFixtures::ADMIN['id']);
        $admin->setActive(false);
        $em->flush();

        $client->request('POST', '/refresh_token', ['json' => [
            'refresh_token' => $auth['refresh_token'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'user.notActivated',
        ]);
    }

    public function testRefreshFailsWithDeletedUser(): void
    {
        $client = static::createClient();
        $r = $client->request('POST', '/authentication_token', ['json' => [
            'username' => TestFixtures::PROJECT_COORDINATOR['username'],
            'password' => TestFixtures::PROJECT_COORDINATOR['password'],
        ]]);
        static::assertResponseStatusCodeSame(200);
        $auth = $r->toArray();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->find(TestFixtures::PROJECT_COORDINATOR['id']);
        $user->markDeleted();
        $em->flush();

        $client = static::createClient();
        $client->request('POST', '/refresh_token', ['json' => [
            'refresh_token' => $auth['refresh_token'],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,

            // @todo Gesdinet\JWTRefreshTokenBundle\Service\Refreshtoken wirft basic AuthenticationExceptions
            // ohne individuellen messageKey, dieser wird aber von Lexik im AuthenticationFailureHandler
            // für die Rückgabe verwendet, daher haben alle Fehler diese Meldung, egal welche Ursache...
            'message' => 'Es ist ein Fehler bei der Authentifikation aufgetreten.',
        ]);
    }
}
