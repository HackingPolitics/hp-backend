<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Problem;
use App\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProblemApi
 */
class ProblemApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    private ?EntityManager $entityManager;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
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
        ])->request('GET', '/problems');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET http://example.com/problems": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetProblemAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Problem::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'problem 1',
            'project'     => [
                'id' => 1,
            ],
        ]);
    }

    public function testCreate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $client->request('POST', '/problems', ['json' => [
            'description' => 'new problem',
            'project'     => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'    => '/contexts/Problem',
            '@type'       => 'Problem',
            'description' => 'new problem',
            'project'     => ['@id' => $projectIri],
            'updatedBy'   => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);

        /** @var Project $found */
        $em = static::getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // creation of a new sub-resource should update the timestamp of the parent
        self::assertTrue($now < $found->getUpdatedAt());
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/problems', ['json' => [
            'description' => 'new problem',
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/problems', ['json' => [
            'description' => 'new problem',
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/problems', ['json' => [
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
        ])->request('POST', '/problems', ['json' => [
            'description' => 'new problem',
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

    public function testUpdate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'priority' => 33,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'          => $iri,
            'description'  => 'problem 1',
            'priority'     => 33,
            'updatedBy'    => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
        ]);

        /** @var Project $found */
        $em = static::getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // updating a sub-resource should update the timestamp of the parent
        self::assertTrue($now < $found->getUpdatedAt());
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new problem',
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

        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new problem',
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
        $iri = $this->findIriBy(Problem::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'description' => 'new problem',
            'project'     => $newProjectIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // description got updated but project didn't
        self::assertJsonContains([
            'description' => 'new problem',
            'project'     => [
                '@id' => $projectIRI,
            ],
        ]);
    }

    public function testDelete(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(1, $before->getProblems());
        sleep(1);
        $em->clear();

        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Problem $deleted */
        $deleted = $em->getRepository(Problem::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Project $after */
        $after = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(0, $after->getProblems());

        // deletion of a new sub-resource should update the timestamp of the parent
        self::assertTrue($before->getUpdatedAt() < $after->getUpdatedAt());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Problem::class, ['id' => 1]);
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
