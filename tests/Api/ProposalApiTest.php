<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Proposal;
use App\Entity\Project;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProposalApi
 */
class ProposalApiTest extends ApiTestCase
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
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/proposals');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /proposals": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetProposalAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@id'     => $iri,
            'title'   => 'proposal title',
            'project' => [
                'id' => 1,
            ],
        ]);
    }

    public function testCreate(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        sleep(1);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/proposals', ['json' => [
            'title'       => 'new proposal',
            'sponsor'     => 'Green',
            'project'     => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'    => '/contexts/Proposal',
            '@type'       => 'Proposal',
            'title'       => 'new proposal',
            'sponsor'     => 'Green',
            'project'     => ['@id' => $projectIri],
            'updatedBy'   => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);

        /** @var Project $found */
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // creation of a new sub-resource should update the timestamp of the parent
        self::assertTrue($now < $found->getUpdatedAt());
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createClient()->request('POST', '/proposals', ['json' => [
            'title'   => 'new proposal',
            'sponsor' => 'Green',
            'project' => $projectIri,
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
        ])->request('POST', '/proposals', ['json' => [
            'title'   => 'new proposal',
            'sponsor' => 'Green',
            'project' => $projectIri,
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

    public function testCreateWithoutTitleFails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/proposals', ['json' => [
            'project' => $projectIri,
            'sponsor' => 'Green',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutSponsorFails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/proposals', ['json' => [
            'project' => $projectIri,
            'title'   => 'new proposal',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'sponsor: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutProjectFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/proposals', ['json' => [
            'title'   => 'new proposal',
            'sponsor' => 'Green',
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

    public function testCreateDuplicateFails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/proposals', ['json' => [
            'title'   => TestFixtures::PROPOSAL_1['title'],
            'sponsor' => 'Red',
            'project' => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.proposal.duplicateTitle',
        ]);
    }

    public function testUpdate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        sleep(1);

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'comment' => 'new comment',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'comment'   => 'new comment',
            'updatedBy' => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
        ]);

        /** @var Project $found */
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // updating a sub-resource should update the timestamp of the parent
        self::assertTrue($now < $found->getUpdatedAt());
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'comment' => 'new comment',
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

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'comment' => 'new comment',
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
        $iri = $this->findIriBy(Proposal::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'comment' => 'new comment',
            'project' => $newProjectIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // comment got updated but project didn't
        self::assertJsonContains([
            'comment' => 'new comment',
            'project' => [
                '@id' => $projectIRI,
            ],
        ]);
    }

    public function testDeleteFromLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();

        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(1, $before->getProposals());
        $before->setLocked(true);
        $em->flush();
        $em->clear();

        sleep(1);
        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Proposal $deleted */
        $deleted = $em->getRepository(Proposal::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Project $after */
        $after = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(0, $after->getProposals());

        // deletion of a new sub-resource should update the timestamp of the parent
        self::assertTrue($before->getUpdatedAt() < $after->getUpdatedAt());
    }

    public function testDeleteFromActiveProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
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

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $before->setLocked(true);
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
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

        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $before->setLocked(true);
        $em = static::$kernel->getContainer()->get('doctrine')->getManager();
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Proposal::class, ['id' => 1]);
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
