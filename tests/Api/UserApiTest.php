<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\ActionLog;
use App\Entity\Council;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Message\AllProjectMembersLeftMessage;
use App\Message\NewMemberApplicationMessage;
use App\Message\NewUserPasswordMessage;
use App\Message\UserEmailChangeMessage;
use App\Message\UserForgotPasswordMessage;
use App\Message\UserRegisteredMessage;
use App\Util\DateHelper;
use DateTimeImmutable;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UserApi
 */
class UserApiTest extends ApiTestCase
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

    public function testGetCollection(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('GET', '/users');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo the schema is broken, "The property deletedAt is not defined and the definition does not allow additional properties" etc
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 6,
        ]);

        $collection = $response->toArray();

        self::assertCount(6, $collection['hydra:member']);

        $ids = [];
        foreach ($collection['hydra:member'] as $user) {
            $ids[] = $user['id'];
        }
        self::assertContains(TestFixtures::ADMIN['id'], $ids);
        self::assertContains(TestFixtures::PROCESS_MANAGER['id'], $ids);
        self::assertContains(TestFixtures::PROJECT_WRITER['id'], $ids);
        self::assertContains(TestFixtures::PROJECT_COORDINATOR['id'], $ids);
        self::assertContains(TestFixtures::PROJECT_OBSERVER['id'], $ids);
        self::assertContains(TestFixtures::GUEST['id'], $ids);
        self::assertNotContains(TestFixtures::DELETED_USER['id'], $ids);
    }

    public function testGetCollectionFailsUnauthenticated(): void
    {
        static::createClient()->request('GET', '/users');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetCollectionFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('GET', '/users');

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

    /**
     * Filter the collection by exact username -> one result.
     */
    public function testGetUsersByUsername(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/users', ['query' => [
            'username' => TestFixtures::PROJECT_COORDINATOR['username'],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection by search pattern.
     */
    public function testGetUsersByEmailPattern(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $response = $client->request('GET', '/users', ['query' => [
            'pattern' => 'st@zu',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::GUEST['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection by search pattern.
     */
    public function testGetUsersByNamePattern(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $response = $client->request('GET', '/users', ['query' => [
            'pattern' => 'pete',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::PROJECT_WRITER['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection by user role.
     */
    public function testGetUsersByRole(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $response = $client->request('GET', '/users', ['query' => [
            'roles' => User::ROLE_ADMIN,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::ADMIN['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection by active flag.
     */
    public function testGetInactiveUsers(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $admin = $em->getRepository(User::class)->find(TestFixtures::PROCESS_MANAGER['id']);
        $admin->setActive(false);
        $em->flush();

        $response = $client->request('GET', '/users', ['query' => [
            'active' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::PROCESS_MANAGER['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection by validated flag.
     */
    public function testGetNotValidatedUsers(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $response = $client->request('GET', '/users', ['query' => [
            'validated' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $result = $response->toArray();
        self::assertCount(1, $result['hydra:member']);
        self::assertSame(TestFixtures::GUEST['email'],
            $result['hydra:member'][0]['email']);
    }

    /**
     * Filter the collection for undeleted users only, same as default.
     */
    public function testGetUndeletedUsers(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('GET', '/users', ['query' => [
            'exists[deletedAt]' => 0,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 6,
        ]);

        self::assertCount(6, $response->toArray()['hydra:member']);
    }

    /**
     * Admins can explicitly request deleted users via filter.
     */
    public function testGetDeletedUsersAsAdmin(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/users', ['query' => ['exists[deletedAt]' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo the schema is broken, "The property deletedAt is not defined and the definition does not allow additional properties" etc
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        self::assertCount(1, $collection['hydra:member']);
        self::assertSame(TestFixtures::DELETED_USER['id'],
            $collection['hydra:member'][0]['id']);
    }

    /**
     * Process owners cannot get deleted users, the collection must be empty.
     */
    public function testGetDeletedUsersAsProcessOwner(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('GET', '/users', ['query' => ['exists[deletedAt]' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(User::class);

        self::assertJsonContains([
            '@context'         => '/contexts/User',
            '@id'              => '/users',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);
    }

    public function testGet(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo the schema is broken, "The property deletedAt is not defined and the definition does not allow additional properties" etc
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'createdAt'          => '2019-02-02T00:00:00+00:00',
            'username'           => TestFixtures::PROJECT_WRITER['username'],
            'email'              => TestFixtures::PROJECT_WRITER['email'],
            'id'                 => TestFixtures::PROJECT_WRITER['id'],
            'active'             => true,
            'validated'          => true,
            'objectRoles'        => [],
            'roles'              => [User::ROLE_USER],
            'createdProjects'    => [
                0 => [
                    // PM can see deleted projects
                    'id' => TestFixtures::DELETED_PROJECT['id'],
                ],
            ],
            'projectMemberships' => [
                0 => [
                    '@id'        => '/project_memberships/project='
                        .TestFixtures::PROJECT['id']
                        .';user='.TestFixtures::PROJECT_WRITER['id'],
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'writer motivation',
                    'role'       => 'writer',
                    'skills'     => 'writer skills',
                    'project'    => [
                        '@id'   => '/projects/'.TestFixtures::PROJECT['id'],
                        '@type' => 'Project',
                        'id'    => TestFixtures::PROJECT['id'],
                        'title' => 'Car-free Dresden',
                    ],
                ],
                1 => [
                    '@id'        => '/project_memberships/project='
                        .TestFixtures::LOCKED_PROJECT['id']
                        .';user='.TestFixtures::PROJECT_WRITER['id'],
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'writer motivation',
                    'role'       => 'writer',
                    'skills'     => 'writer skills',
                    'project'    => [
                        '@id'   => '/projects/'.TestFixtures::LOCKED_PROJECT['id'],
                        '@type' => 'Project',
                        'id'    => TestFixtures::LOCKED_PROJECT['id'],
                        'title' => 'Locked Project',
                    ],
                ],
            ],
        ]);
    }

    public function testGetSelf(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'createdAt'          => '2019-02-02T00:00:00+00:00',
            'username'           => TestFixtures::PROJECT_WRITER['username'],
            'email'              => TestFixtures::PROJECT_WRITER['email'],
            'id'                 => TestFixtures::PROJECT_WRITER['id'],
            'active'             => true,
            'validated'          => true,
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [],
        ]);
    }

    public function testGetSelfWithMemberships(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'                => $iri,
            'username'           => TestFixtures::PROJECT_WRITER['username'],
            'email'              => TestFixtures::PROJECT_WRITER['email'],
            'id'                 => TestFixtures::PROJECT_WRITER['id'],
            'roles'              => [User::ROLE_USER],
            'projectMemberships' => [
                0 => [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'writer motivation',
                    'project'    => [
                        'id' => TestFixtures::PROJECT['id'],
                    ],
                    'role'       => ProjectMembership::ROLE_WRITER,
                    'skills'     => 'writer skills',
                ],
                1 => [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'writer motivation',
                    'project'    => [
                        'id' => TestFixtures::LOCKED_PROJECT['id'],
                    ],
                    'role'       => ProjectMembership::ROLE_WRITER,
                    'skills'     => 'writer skills',
                ],
            ],
        ]);
    }

    public function testGetReturnsNoLockedCreatedProjects(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);
        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'             => $iri,
            'createdProjects' => [
                0 => [
                    'id' => TestFixtures::PROJECT['id'],
                ],
            ],
        ]);

        $data = $response->toArray();
        self::assertCount(1, $data['createdProjects']);
    }

    public function testGetReturnsNoDeletedCreatedProjects(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@id'             => $iri,
            'createdProjects' => [
            ],
        ]);

        $data = $response->toArray();
        self::assertCount(0, $data['createdProjects']);
    }

    public function testGetFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

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
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);

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

    /**
     * Admins can request deleted users.
     */
    public function testGetDeletedUserAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);
    }

    /**
     * Process owners cannot get a deleted user, returns 404.
     */
    public function testGetDeletedUserAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testCreate(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'username' => 'Tester',
            'email'    => 'new@zukunftsstadt.de',
            'password' => '-*?*#+ with letters',
            'roles'    => [],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'    => '/contexts/User',
            '@type'       => 'User',
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'active'      => true,
            'validated'   => false,
            'firstName'   => '',
            'lastName'    => '',
            'roles'       => [User::ROLE_USER],
            'objectRoles' => [],
            'projectMemberships' => [],
        ]);

        $userData = $response->toArray();
        self::assertRegExp('~^/users/\d+$~', $userData['@id']);
        self::assertArrayHasKey('id', $userData);
        self::assertIsInt($userData['id']);
        self::assertArrayNotHasKey('password', $userData);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->find($userData['id']);

        // user has a password and it was encoded
        self::assertNotEmpty($user->getPassword());
        self::assertNotSame('irrelevant', $user->getPassword());
    }

    public function testCreateFailsUnauthenticated(): void
    {
        static::createClient()->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'Tester',
            'password' => 'irrelevant',
            'roles'    => [],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testCreateFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'Tester',
            'password' => 'irrelevant',
            'roles'    => [],
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

    public function testCreateOverwritingDefault(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'     => 'new@zukunftsstadt.de',
            'username'  => 'Tester',
            'password'  => '-*?*#+ with letters',
            'roles'     => [User::ROLE_ADMIN],
            'active'    => false,
            'validated' => true,
            'firstName' => 'Peter',
            'lastName'  => 'Lustig',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            'active'    => false,
            'validated' => true,
            'roles'     => [User::ROLE_ADMIN, User::ROLE_USER],
            'firstName' => 'Peter',
            'lastName'  => 'Lustig',
        ]);
    }

    public function testCreateWithoutEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'username' => 'Tester',
            'password' => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutUsernameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'test@zukunftsstadt.de',
            'password' => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testCreateWithDuplicateUsernameFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'test@zukunftsstadt.de',
            'username' => TestFixtures::ADMIN['username'],
            'password' => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: Username already exists.',
        ]);
    }

    public function testCreateWithDuplicateEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => TestFixtures::ADMIN['email'],
            'username' => 'Tester',
            'password' => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testCreateWithInvalidEmailFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'username' => 'invalid-mail-user',
            'email'    => 'no-email',
            'password' => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.invalidEmail',
        ]);
    }

    public function testCreateWithInvalidRolesFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'password' => 'invalid',
            'roles'    => User::ROLE_ADMIN, // should be an array to work
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'The input data is misformatted.',

            // old output:
            //'hydra:description' => 'The type of the "roles" attribute for class'
            // .' "App\\Dto\\UserInput" must be one of "array" ("string" given).',
        ]);
    }

    public function testCreateWithUnknownRoleFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'password' => '-*?*#+ with letters',
            'roles'    => ['SUPER_USER'],
            'username' => 'will-fail',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'roles[0]: validate.general.invalidChoice',
        ]);
    }

    public function testCreateWithoutPasswordSetsRandom(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('POST', '/users', ['json' => [
            'email'    => 'new@zukunftsstadt.de',
            'username' => 'tester',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        $userData = $response->toArray();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->find($userData['id']);
        self::assertNotEmpty($user->getPassword());
    }

    public function testRegistration(): void
    {
        $before = new DateTimeImmutable();
        sleep(1);

        $response = static::createClient()
            ->request('POST', '/users/register', ['json' => [
                'username'      => 'Tester',
                'email'         => 'new@zukunftsstadt.de',
                'firstName'     => 'Peter',
                'password'      => '-*?*#+ with letters',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'    => '/contexts/User',
            '@type'       => 'User',
            'email'       => 'new@zukunftsstadt.de',
            'username'    => 'Tester',
            'active'      => true,
            'validated'   => false,
            'firstName'   => 'Peter',
            'lastName'    => '',
            'roles'       => [User::ROLE_USER],
            'objectRoles' => [],
            'projectMemberships' => [],
        ]);

        $userData = $response->toArray();
        self::assertArrayNotHasKey('password', $userData);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(UserRegisteredMessage::class,
            $messages[0]['message']);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::REGISTERED_USER]);
        self::assertCount(1, $logs);
        self::assertSame('Tester', $logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testRegistrationWithProject(): void
    {
        $before = new DateTimeImmutable();
        sleep(1);

        $client = static::createClient();

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'        => 'Tester',
            'email'           => 'new@zukunftsstadt.de',
            'firstName'       => 'Peter',
            'password'        => '-*?*#+ with letters',
            'validationUrl'   => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'createdProjects' => [
                [
                    'motivation' => 'I wanna do something',
                    'council'    => $iri,
                    'title'      => 'new project title',
                    'topic'      => 'new topic',
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'           => '/contexts/User',
            '@type'              => 'User',
            'projectMemberships' => [
                [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'I wanna do something',
                    'role'       => ProjectMembership::ROLE_COORDINATOR,
                    'skills'     => 'I can do it',
                ],
            ],
            'createdProjects'    => [
                [
                    '@type' => 'Project',
                    'title' => 'new project title',
                ],
            ],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $userLogs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::REGISTERED_USER]);
        self::assertCount(1, $userLogs);
        self::assertSame('Tester', $userLogs[0]->username);
        self::assertGreaterThan($before, $userLogs[0]->timestamp);

        $projectLogs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::CREATED_PROJECT]);
        self::assertCount(1, $projectLogs);
        self::assertSame('Tester', $projectLogs[0]->username);
        self::assertGreaterThan($before, $projectLogs[0]->timestamp);
    }

    public function testRegistrationWithProjectWithInactiveCouncilFails(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $council = $em->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);
        $council->setActive(false);
        $em->flush();

        $client->request('POST', '/users/register', ['json' => [
            'username'        => 'Tester',
            'email'           => 'new@zukunftsstadt.de',
            'firstName'       => 'Peter',
            'password'        => '-*?*#+ with letters',
            'validationUrl'   => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'createdProjects' => [
                [
                    'motivation' => 'I wanna do something',
                    'council'    => $iri,
                    'title'      => 'new project title',
                    'topic'      => 'new topic',
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'council: validate.project.council.notActive',
        ]);
    }

    public function testRegistrationWithDuplicateEmailFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => TestFixtures::ADMIN['email'],
            'username'      => 'Tester',
            'password'      => '-*?*#+ with letters',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testRegistrationWithoutValidationUrlFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => '-*?*#+ with letters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testRegistrationWithoutIdPlaceholderFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => '-*?*#+ with letters',
            'validationUrl' => 'http://fcp.de/?token={{token}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: ID placeholder is missing.',
        ]);
    }

    public function testRegistrationWithoutTokenPlaceholderFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'username'      => 'Tester',
            'password'      => '-*?*#+ with letters',
            'validationUrl' => 'https://fcp.de/?token=token&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: Token placeholder is missing.',
        ]);
    }

    public function testRegistrationWithInvalidUsernameFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'test@zukunftsstadt.de',
            'password'      => '-*?*#+ with letters',
            'username'      => '1@2',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.user.username.notValid',
        ]);
    }

    public function testRegistrationWithShortPasswordFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'test@zukunftsstadt.de',
            'password'      => '-*?*#',
            'username'      => '1@2',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.general.tooShort',
        ]);
    }

    public function testRegistrationWithWeakPasswordFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'test@zukunftsstadt.de',
            'password'      => '123456789',
            'username'      => 'myusername',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.tooWeak',
        ]);
    }

    public function testRegistrationWithCompromisedPasswordFails(): void
    {
        static::createClient()->request('POST', '/users/register', ['json' => [
            'email'         => 'test@zukunftsstadt.de',
            // https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/10-million-password-list-top-1000000.txt
            'password'      => 'Soso123aljg',
            'username'      => '1@2',
            'validationUrl' => 'https://fcp.de/?token={{token}}&id={{id}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.compromised',
        ]);
    }

    /**
     * requires zalas/phpunit-globals.
     *
     * @env USER_VALIDATION_REQUIRED=false
     */
    public function testRegistrationWithApplication(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'           => 'Tester',
            'email'              => 'new@zukunftsstadt.de',
            'firstName'          => 'Peter',
            'password'           => '-*?*#+ with letters',
            'validationUrl'      => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(User::class);

        self::assertJsonContains([
            '@context'           => '/contexts/User',
            '@type'              => 'User',
            'username'           => 'Tester',
            'email'              => 'new@zukunftsstadt.de',
            'firstName'          => 'Peter',
            'active'             => true,
            'validated'          => true,
            'projectMemberships' => [
                [
                    '@type'      => 'ProjectMembership',
                    'motivation' => 'I wanna do something',
                    'project'    => [
                        '@id' => $projectIri,
                    ],
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]);

        // the user registered with a membership application and was
        // marked validated -> notification for the project coordinators
        // should be triggered
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(NewMemberApplicationMessage::class,
            $messages[0]['message']);
    }

    public function testRegistrationWithApplicationFailsWithForbiddenRole(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => '-*?*#+ with letters',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_COORDINATOR,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testRegistrationWithApplicationFailsForLockedProject(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => TestFixtures::PROJECT_OBSERVER['password'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/'.
                TestFixtures::LOCKED_PROJECT['id'].'".',
        ]);
    }

    public function testRegistrationWithApplicationFailsForDeletedProject(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::DELETED_PROJECT['id']]);

        $client->request('POST', '/users/register', ['json' => [
            'username'      => 'Tester',
            'email'         => 'new@zukunftsstadt.de',
            'firstName'     => 'Peter',
            'password'      => TestFixtures::PROJECT_COORDINATOR['password'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            'projectMemberships' => [
                [
                    'motivation' => 'I wanna do something',
                    'project'    => $projectIri,
                    'role'       => ProjectMembership::ROLE_APPLICANT,
                    'skills'     => 'I can do it',
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/'
                .TestFixtures::DELETED_PROJECT['id'].'".',
        ]);
    }

    public function testUpdate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $before = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('PUT', $iri, ['json' => [
            'email'     => TestFixtures::PROJECT_WRITER['email'],
            'active'    => false,
            'validated' => false,
            'roles'     => [User::ROLE_ADMIN],
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'active'    => false,
            'validated' => false,
            'roles'     => [User::ROLE_ADMIN, User::ROLE_USER],
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]);

        $em->clear();
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        // password stays unchanged
        self::assertSame($before->getPassword(), $after->getPassword());
    }

    public function testUpdateAutoTrim(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('PUT', $iri, ['json' => [
            'firstName'   => ' Erich ',
            'lastName'    => ' Müller ',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'firstName'   => 'Erich',
            'lastName'    => 'Müller',
        ]);
    }

    public function testUpdateSelf(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);
        $client->request('PUT', $iri, ['json' => [
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'firstName' => 'Erich',
            'lastName'  => 'Müller',
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'    => TestFixtures::PROJECT_WRITER['email'],
            'username' => TestFixtures::PROJECT_WRITER['username'],
            'active'   => false,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'    => TestFixtures::PROJECT_WRITER['email'],
            'username' => TestFixtures::PROJECT_WRITER['username'],
            'active'   => false,
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

    public function testUpdatePasswordAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $oldPW = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id'])
            ->getPassword();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_WRITER['email'],
            'password'    => 'new-passw0rd',
        ]]);

        self::assertResponseIsSuccessful();

        $em->clear();
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        // password changed and is encoded
        self::assertNotSame($oldPW, $after->getPassword());
        self::assertNotSame('new-passw0rd', $after->getPassword());
    }

    public function testUpdateWithCompromisedPasswordFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_WRITER['email'],
            // https://raw.githubusercontent.com/danielmiessler/SecLists/master/Passwords/Common-Credentials/10-million-password-list-top-1000000.txt
            'password'    => 'Soso123aljg',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.compromised',
        ]);
    }

    public function testUpdateFailsWithShortPassword(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_WRITER['email'],
            'password'    => '-*?*$',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.general.tooShort',
        ]);
    }

    public function testUpdateFailsWithWeakPassword(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::PROJECT_WRITER['email'],
            'password'    => 'aaaaaaaa',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.tooWeak',
        ]);
    }

    public function testUpdateEmail(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'email'       => 'new@zukunftsstadt.de',
        ]);
    }

    public function testUpdateWithDuplicateEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => TestFixtures::ADMIN['email'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testUpdateWithInvalidEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email'       => 'no-email',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.invalidEmail',
        ]);
    }

    public function testUpdateWithDuplicateUsernameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username'       => TestFixtures::ADMIN['username'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: Username already exists.',
        ]);
    }

    public function testUpdateWithEmptyUsernameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username' => '',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testUpdateWithUnknownRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'test@example.com',
            'roles' => ['SUPER_USER'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'roles[0]: validate.general.invalidChoice',
        ]);
    }

    public function testUpdateOwnEmailFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'email' => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);
    }

    public function testUpdateOfOwnPasswordIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $oldPW = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id'])
            ->getPassword();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'password' => 'myNewPassw0rd',
        ]]);

        self::assertResponseIsSuccessful();
        $em->clear();
        $after = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        // password unchanged
        self::assertSame($oldPW, $after->getPassword());
    }

    public function testUpdateOfOwnUsernameIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'username' => 'new-name',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'username'    => TestFixtures::PROJECT_WRITER['username'],
        ]);
    }

    public function testUpdateOfOwnRolesIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'roles' => [User::ROLE_ADMIN],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'roles'       => [User::ROLE_USER],
        ]);
    }

    public function testUpdateOfOwnActiveIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'active' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'    => $iri,
            'active' => true,
        ]);
    }

    public function testUpdateOfOwnValidatedIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('PUT', $iri, ['json' => [
            'validated' => false,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'validated' => true,
        ]);
    }

    public function testDelete(): void
    {
        $before = new DateTimeImmutable();
        sleep(1);

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $allMemberships = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(5, $allMemberships);
        $old = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertCount(2, $old->getProjectMemberships());
        $em->clear();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());
        self::assertGreaterThan($before, $user->getDeletedAt());
        self::assertCount(0, $user->getProjectMemberships());

        $remaining = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(3, $remaining);

        // removal of other private data is tested in Enity\UserTest
    }

    public function testDeleteSelf(): void
    {
        $before = new DateTimeImmutable();
        sleep(1);

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $allMemberships = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(5, $allMemberships);
        $old = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertCount(2, $old->getProjectMemberships());
        $em->clear();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());
        self::assertGreaterThan($before, $user->getDeletedAt());
        self::assertCount(0, $user->getProjectMemberships());
        // removal of other private data is tested in Enity\UserTest

        $remaining = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(3, $remaining);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::GUEST['email']]);

        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Access Denied.',
        ]);
    }

    public function testDeleteDeletedUserFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::DELETED_USER['email']]);

        $client->request('DELETE', $iri);

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

    public function testDeleteOnlyCoordinatorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);

        $client->request('DELETE', $iri);

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

    public function testDeleteCoordinatorWithOtherCoordinators(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $allMemberships = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(5, $allMemberships);
        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $planner->getProjectMemberships()[0]->setRole(ProjectMembership::ROLE_COORDINATOR);
        $planner->getProjectMemberships()[1]->setRole(ProjectMembership::ROLE_COORDINATOR);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());
    }

    public function testDeleteCoordinatorWithoutWriters(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $allMemberships = $em->getRepository(ProjectMembership::class)
            ->findAll();
        self::assertCount(5, $allMemberships);
        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $planner->getProjectMemberships()[0]->setRole(ProjectMembership::ROLE_OBSERVER);
        $planner->getProjectMemberships()[1]->setRole(ProjectMembership::ROLE_OBSERVER);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_COORDINATOR['email']]);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var User $user */
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        self::assertNotNull($user);
        self::assertTrue($user->isDeleted());

        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // no more writers or coordinators -> project is locked
        self::assertTrue($project->isLocked());

        // notification for the process managers should be triggered
        // (for the "normal" and for the locked project)
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(2, $messages);
        self::assertInstanceOf(AllProjectMembersLeftMessage::class,
            $messages[0]['message']);
        self::assertInstanceOf(AllProjectMembersLeftMessage::class,
            $messages[1]['message']);
    }

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('DELETE', '/users');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /users": Method Not Allowed (Allow: GET, POST)',
        ]);
    }

    public function testPasswordResetWithUsername(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::PROJECT_COORDINATOR['username'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(UserForgotPasswordMessage::class,
            $messages[0]['message']);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['id'],
            $messages[0]['message']->userId);
    }

    public function testPasswordResetWithEmail(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::PROJECT_COORDINATOR['email'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(UserForgotPasswordMessage::class,
            $messages[0]['message']);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['id'],
            $messages[0]['message']->userId);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::SUCCESSFUL_PW_RESET_REQUEST]);
        self::assertCount(1, $logs);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['username'],
            $logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testPasswordResetWithUnknownUsernameFails(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'does-not-exist',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        // request failed but response always returns success
        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // when the request fails no message is dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(0, $messages);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_PW_RESET_REQUEST]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testPasswordResetWithUnknownEmailFails(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'does@not-exist.de',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        // request failed but response always returns success
        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // when the request fails no message is dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(0, $messages);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_PW_RESET_REQUEST]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testPasswordResetWithEmptyUsernameFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => '',
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'username: validate.general.notBlank',
        ]);
    }

    public function testPasswordResetWithoutValidationUrlFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username' => 'irrelevant',
            ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testPasswordResetWithoutIdPlaceholderFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => 'irrelevant',
                'validationUrl' => 'http://fcp.de/?token={{token}}',
            ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: ID placeholder is missing.',
        ]);
    }

    public function testPasswordResetWithoutTokenPlaceholderFails(): void
    {
        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username' => 'irrelevant',
                'validationUrl' => 'http://fcp.de/?id={{id}}',
            ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: Token placeholder is missing.',
        ]);
    }

    public function testPasswordResetFailsAuthenticated(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);
        $client->request('POST', '/users/reset-password', ['json' => [
            'username'     => 'irrelevant',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
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

    public function testPasswordResetFailsWhenBlocked(): void
    {
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $l1 = new ActionLog();
        $l1->timestamp = DateHelper::nowSubInterval('PT10M');
        $l1->action = ActionLog::SUCCESSFUL_PW_RESET_REQUEST;
        $l1->username = TestFixtures::PROJECT_COORDINATOR['username'];
        $l1->ipAddress = '127.0.0.1';
        $em->persist($l1);

        $l2 = clone $l1;
        $l2->timestamp = DateHelper::nowSubInterval('PT30M');
        $em->persist($l2);

        $l3 = clone $l1;
        $l3->timestamp = DateHelper::nowSubInterval('PT59M');
        $l3->action = ActionLog::FAILED_PW_RESET_REQUEST;
        $em->persist($l3);

        $em->flush();

        $client->request('POST', '/users/reset-password', ['json' => [
            'username'      => TestFixtures::PROJECT_COORDINATOR['email'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
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

    public function testPasswordResetWithDeletedUserFails(): void
    {
        $before = new DateTimeImmutable();

        static::createClient()
            ->request('POST', '/users/reset-password', ['json' => [
                'username'      => TestFixtures::DELETED_USER['email'],
                'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
            ]]);

        // request failed but response always returns success
        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // when the request fails no message is dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(0, $messages);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_PW_RESET_REQUEST]);
        self::assertCount(1, $logs);
        self::assertNull($logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testPasswordResetWithInactiveUserFails(): void
    {
        $before = new DateTimeImmutable();

        $client = static::createClient();
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        $user->setActive(false);
        $em->flush();
        $em->clear();

        $client->request('POST', '/users/reset-password', ['json' => [
            'username'      => TestFixtures::PROJECT_COORDINATOR['email'],
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        // request failed but response always returns success
        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // when the request fails no message is dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(0, $messages);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::FAILED_PW_RESET_REQUEST]);
        self::assertCount(1, $logs);
        self::assertSame(TestFixtures::PROJECT_COORDINATOR['username'],
            $logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testEmailChange(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => 'new@zukunftsstadt.de',
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // check that the email wasn't changed already
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROCESS_MANAGER['id']);
        self::assertSame(TestFixtures::PROCESS_MANAGER['email'], $user->getEmail());

        // ... instead a queue message was dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(UserEmailChangeMessage::class,
            $messages[0]['message']);
        self::assertSame(TestFixtures::PROCESS_MANAGER['id'],
            $messages[0]['message']->userId);
        self::assertSame('new@zukunftsstadt.de',
            $messages[0]['message']->newEmail);
    }

    public function testEmailChangeFailsWithInvalidEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => 'invalid',
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.invalidEmail',
        ]);
    }

    public function testEmailChangeFailsWithDuplicateEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => TestFixtures::ADMIN['email'],
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: Email already exists.',
        ]);
    }

    public function testEmailChangeFailsWithoutEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'email: validate.general.notBlank',
        ]);
    }

    public function testEmailChangeFailsWithoutValidationUrl(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'email'                => 'new@zukunftsstadt.de',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testEmailChangeFailsWithoutConfirmationPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'         => 'new@zukunftsstadt.de',
            'validationUrl' => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'confirmationPassword: validate.general.notBlank',
        ]);
    }

    public function testEmailChangeFailsWithWrongConfirmationPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => 'new@zukunftsstadt.de',
            'confirmationPassword' => 'this is wrong',
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.user.passwordMismatch',
        ]);
    }

    public function testEmailChangeFailsUnauthenticated(): void
    {
        $client = self::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => 'new@zukunftsstadt.de',
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testEmailChangeFailsWithoutPrivilege(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/change-email', ['json' => [
            'email'                => 'new@zukunftsstadt.de',
            'confirmationPassword' => TestFixtures::PROCESS_MANAGER['password'],
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
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

    public function testNewPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $oldUser = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $oldPW = $oldUser->getPassword();

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/new-password', ['json' => [
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        // check that the password was changed already
        $em->clear();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertNotSame($oldPW, $user->getPassword());

        // ... instead a queue message was dispatched
        $messenger = self::$container->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(NewUserPasswordMessage::class,
            $messages[0]['message']);
        self::assertSame(TestFixtures::PROJECT_WRITER['id'],
            $messages[0]['message']->userId);
    }

    public function testNewPasswordFailsWithoutValidationUrl(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROCESS_MANAGER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/new-password', ['json' => [
            // empty
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validationUrl: validate.general.notBlank',
        ]);
    }

    public function testNewPasswordFailsUnauthenticated(): void
    {
        $client = self::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROCESS_MANAGER['email']]);

        $client->request('POST', $iri.'/new-password', ['json' => [
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testNewPasswordFailsWithoutPrivilege(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_COORDINATOR['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/new-password', ['json' => [
            'validationUrl'        => 'https://vrok.de/?token={{token}}&id={{id}}&type={{type}}',
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

    public function testPasswordChange(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => 'myNewPassword',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Password changed',
        ]);

        /** @var UserPasswordEncoderInterface $pwe */
        $pwe = self::$container->get(UserPasswordEncoderInterface::class);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        self::assertTrue($pwe->isPasswordValid($user, 'myNewPassword'));
    }

    public function testPasswordChangeFailsWithWeakPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => 'aaaaaaaa',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.tooWeak',
        ]);
    }

    public function testPasswordChangeFailsWithCompromisedPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => 'my new password',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.user.password.compromised',
        ]);
    }

    public function testPasswordChangeIgnoresEmail(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => 'myNewPassword',
            'email'                => 'new-email@test.com',
        ]]);

        self::assertResponseStatusCodeSame(200);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        self::assertSame(TestFixtures::PROJECT_WRITER['email'], $user->getEmail());
    }

    public function testPasswordChangeFailsWithoutPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.general.notBlank',
        ]);
    }

    public function testPasswordChangeFailsWithShortPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => '-*?*#',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'password: validate.general.tooShort',
        ]);
    }

    public function testPasswordChangeFailsWithoutConfirmationPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'password' => 'myNewPassword',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'confirmationPassword: validate.general.notBlank',
        ]);
    }

    public function testPasswordChangeFailsWithWrongConfirmationPassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => 'this is bad',
            'password'             => 'myNewPassword',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.user.passwordMismatch',
        ]);
    }

    public function testPasswordChangeFailsWithSamePassword(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => TestFixtures::PROJECT_WRITER['password'],
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.user.password.notChanged',
        ]);
    }

    public function testPasswordChangeFailsUnauthenticated(): void
    {
        $client = self::createClient();
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::PROJECT_WRITER['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'confirmationPassword' => TestFixtures::PROJECT_WRITER['password'],
            'password'             => 'myNewPassword',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testPasswordChangeFailsWithoutPrivilege(): void
    {
        $client = self::createAuthenticatedClient(
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $iri = $this->findIriBy(User::class,
            ['email' => TestFixtures::GUEST['email']]);

        $client->request('POST', $iri.'/change-password', ['json' => [
            'oldPassword' => TestFixtures::GUEST['password'],
            'password'    => 'myNewPassword',
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

    public function testGetStatistics(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('GET', '/users/statistics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'existing'        => 6,
            'newlyRegistered' => 0,
            'notValidated'    => 1,
            'notActive'       => 0,
            'deleted'         => 1,
        ]);
    }

    public function testGetStatisticsFailsUnauthenticated(): void
    {
        static::createClient()->request('GET', '/users/statistics');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetStatisticsFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('GET', '/users/statistics');

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

    // @todo
    // * registerWithProject:
    // ** w/o motivation fails
    // ** w/o skills fails
    // ** skills too short
    // ** motivation too short
}
