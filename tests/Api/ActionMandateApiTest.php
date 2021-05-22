<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\ActionMandate;
use App\Entity\Project;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ActionMandateApi
 */
class ActionMandateApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    private ?EntityManager $entityManager;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager = null;
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    /**
     * Test that no collection of details is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/action_mandates');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /action_mandates": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetActionMandateAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'action-mandate 1',
            'project'     => [
                'id' => 1,
            ],
        ]);
    }

    public function testCreateActionMandate(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/action_mandates', ['json' => [
            'description' => 'new actionMandate',
            'project'     => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'    => '/contexts/ActionMandate',
            '@type'       => 'ActionMandate',
            'description' => 'new actionMandate',
            'project'     => ['@id' => $projectIri],
            'updatedBy'   => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createClient()->request('POST', '/action_mandates', ['json' => [
            'description' => 'new actionMandate',
            'project'     => $projectIri,
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
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ])->request('POST', '/action_mandates', ['json' => [
            'description' => 'new actionMandate',
            'project'     => $projectIri,
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

    public function testCreateWithoutDescriptionFails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/action_mandates', ['json' => [
            'project' => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'description: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutProjectFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/action_mandates', ['json' => [
            'description' => 'new actionMandate',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'project: validate.general.notBlank',
        ]);
    }

    public function testUpdateActionMandate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'priority' => 33,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'          => $iri,
            'description'  => 'action-mandate 1',
            'priority'     => 33,
            'updatedBy'    => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new actionMandate',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new actionMandate',
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

    public function testUpdateProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $newProjectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $iri = $this->findIriBy(ActionMandate::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'description' => 'new actionMandate',
            'project'     => $newProjectIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // description got updated but project didn't
        self::assertJsonContains([
            'description' => 'new actionMandate',
            'project'     => [
                '@id' => $projectIRI,
            ],
        ]);
    }

    public function testDelete(): void
    {
        /** @var Project $before */
        $before = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(1, $before->getActionMandates());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var ActionMandate $deleted */
        $deleted = $this->entityManager->getRepository(ActionMandate::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Project $after */
        $after = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(0, $after->getActionMandates());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $iri = $this->findIriBy(ActionMandate::class, ['id' => 1]);
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
}
