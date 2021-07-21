<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\ActionLog;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserValidatedMessage;
use App\Util\DateHelper;
use DateTimeImmutable;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ValidationApi
 */
class ValidationApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    public function testGetCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/validations');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET http://example.com/validations"',
        ]);
    }

    public function testGetFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testCreateNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/validations', ['json' => [
            'user'  => '/users/1',
            'type'  => Validation::TYPE_ACCOUNT,
            'token' => 'irrelevant',
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "POST http://example.com/validations"',
        ]);
    }

    public function testUpdateNotAvailable(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'token' => '123fail',
        ]]);

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "PUT http://example.com/validations/1": Method Not Allowed (Allow: GET)',
        ]);
    }

    public function testConfirmEmailChange(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 2 is the owners email change validation
        $token = $em->getRepository(Validation::class)
            ->find(2)
            ->getToken();

        $before = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['email'], $before->getEmail());
        $em->clear();

        $client->request('POST', '/validations/2/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        self::assertSame('new@zukunftsstadt.de', $after->getEmail());
        self::assertCount(0, $after->getValidations());
    }

    public function testConfirmAccountValidation(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var User $before */
        $before = $em->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
        $token = $before->getValidations()[0]->getToken();
        $em->clear();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token'    => $token,
            'password' => 'new-password',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        /** @var User $after */
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
        self::assertTrue($after->isValidated());
        self::assertCount(0, $after->getValidations());

        $messenger = static::getContainer()->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);

        // a message was pushed to the bus, to notify process owners
        self::assertInstanceOf(UserValidatedMessage::class,
            $messages[0]['message']);
    }

    public function testConfirmPasswordReset(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $oldPW = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id'])
            ->getPassword();
        $em->clear();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token'    => $token,
            'password' => '{{new-passw0rd}}',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'success' => true,
            'message' => 'Validation successful',
        ]);

        $after  = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id']);
        self::assertNotSame($oldPW, $after->getPassword());
        self::assertCount(0, $after->getValidations());
    }

    public function testConfirmPasswordResetFailsWithCompromisedPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token'    => $token,
            // https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/10-million-password-list-top-1000000.txt
            'password' => 'Soso123aljg',
        ]]);

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.compromised',
        ]);
    }

    public function testConfirmPasswordResetFailsWithShortPassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token'    => $token,
            'password' => '-*?*#',
        ]]);

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.general.tooShort',
        ]);
    }

    public function testConfirmPasswordResetFailsWithSamePassword(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the members PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token'    => $token,
            'password' => TestFixtures::PROJECT_OBSERVER['password'],
        ]]);

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.notChanged',
        ]);

        // check that the validation still exists so it can be retried with a new PW
        $em->clear();
        $oldToken = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();
        self::assertNotNull($oldToken);
    }

    public function testConfirmAccountValidationFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 1 is the jurors account validation
        $token = $em->getRepository(Validation::class)
            ->find(1)
            ->getToken();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'failure.unauthenticatedAccessOnly',
        ]);
    }

    public function testConfirmEmailChangeFailsAsOtherUser(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 2 is the users email change validation
        $token = $em->getRepository(Validation::class)
            ->find(2)
            ->getToken();

        $client->request('POST', '/validations/2/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testConfirmPasswordResetFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the users PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'failure.unauthenticatedAccessOnly',
        ]);
    }

    public function testConfirmPasswordResetFailsWhenBlocked(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        // ID 3 is the users PW reset validation
        $token = $em->getRepository(Validation::class)
            ->find(3)
            ->getToken();

        $l1 = new ActionLog();
        $l1->timestamp = DateHelper::nowSubInterval('PT10M');
        $l1->action = ActionLog::FAILED_VALIDATION;
        $l1->username = null;
        $l1->ipAddress = '127.0.0.1';
        $em->persist($l1);

        $l2 = clone $l1;
        $l2->timestamp = DateHelper::nowSubInterval('PT30M');
        $em->persist($l2);

        $l3 = clone $l1;
        $l3->timestamp = DateHelper::nowSubInterval('PT59M');
        $em->persist($l3);

        $em->flush();
        $em->clear();

        $client->request('POST', '/validations/3/confirm', ['json' => [
            'token' => $token,
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'failure.accessBlocked',
        ]);
    }

    public function testConfirmWithUnknownIdFails(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()->request('POST', '/validations/777/confirm', ['json' => [
            'token' => 'fails',
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_VALIDATION]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testConfirmWithWrongTokenFails(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()->request('POST', '/validations/1/confirm', ['json' => [
            'token' => 'fails',
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'         => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_VALIDATION]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testConfirmFailsWhenExpired(): void
    {
        $before = new DateTimeImmutable();
        $client = static::createClient();

        // ID 1 is the owners email change validation
        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Validation $validation */
        $validation = $em->getRepository(Validation::class)->find(1);
        $validation->setExpiresAt(new DateTimeImmutable('yesterday'));
        $em->flush();
        $em->clear();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token' => $validation->getToken(),
        ]]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_VALIDATION]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testConfirmFailsWhenBlocked(): void
    {
        $client = static::createClient();

        // ID 1 is the owners email change validation
        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Validation $validation */
        $validation = $em->getRepository(Validation::class)->find(1);

        $l1 = new ActionLog();
        $l1->timestamp = DateHelper::nowSubInterval('PT10M');
        $l1->action = ActionLog::FAILED_VALIDATION;
        $l1->username = null;
        $l1->ipAddress = '127.0.0.1';
        $em->persist($l1);

        $l2 = clone $l1;
        $l2->timestamp = DateHelper::nowSubInterval('PT30M');
        $em->persist($l2);

        $l3 = clone $l1;
        $l3->timestamp = DateHelper::nowSubInterval('PT59M');
        $em->persist($l3);

        $em->flush();
        $em->clear();

        $client->request('POST', '/validations/1/confirm', ['json' => [
            'token' => $validation->getToken(),
        ]]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'failure.accessBlocked',
        ]);
    }

    public function testDeleteNotAvailable(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);
        $iri = $this->findIriBy(Validation::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /validations/1": Method Not Allowed (Allow: GET)',
        ]);
    }

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('DELETE', '/validations');

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /validations"',
        ]);
    }

    // @todo
    // * fail email validation for deleted user
    // * fail pw reset for deleted user
    // * fail pw reset for inactive user
    // * fail account validation for deleted user
}
