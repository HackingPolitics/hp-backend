<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Faction;
use App\Entity\FactionDetails;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionDetailsApi
 */
class FactionDetailsApiTest extends ApiTestCase
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
        ])->request('GET', '/faction_details');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /faction_details": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetFactionDetailsAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(FactionDetails::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'contactName' => 'Green',
            'project'     => [
                'id' => 1,
            ],
        ]);
    }

    public function testCreateFactionDetails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $factionIri = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_RED['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_OBSERVER['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/faction_details', ['json' => [
            'contactName' => 'Red',
            'faction'     => $factionIri,
            'project'     => $projectIri,
            'teamContact' => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt nicht mit den validatoren klar
        //self::assertMatchesResourceItemJsonSchema(FactionDetails::class);

        self::assertJsonContains([
            '@context'    => '/contexts/FactionDetails',
            '@type'       => 'FactionDetails',
            'contactName' => 'Red',
            'faction'     => ['@id' => $factionIri],
            'project'     => ['@id' => $projectIri],
            'teamContact' => ['@id' => $userIri],
            'updatedBy'   => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $factionIri = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_RED['id']]);

        static::createClient()->request('POST', '/faction_details', ['json' => [
            'faction' => $factionIri,
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
        $factionIri = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_RED['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ])->request('POST', '/faction_details', ['json' => [
            'faction'     => $factionIri,
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

    public function testCreateWithoutFactionFails(): void
    {
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/faction_details', ['json' => [
            'project' => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'faction: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutProjectFails(): void
    {
        $factionIri = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_RED['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/faction_details', ['json' => [
            'faction' => $factionIri,
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
        $factionIri = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_GREEN['id']]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/faction_details', ['json' => [
            'faction'     => $factionIri,
            'project'     => $projectIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'faction: validate.factionDetails.duplicateFactionDetails',
        ]);
    }

    public function testUpdateFactionDetails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'contactPhone' => '555-123',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'          => $iri,
            'contactPhone' => '555-123',
            'updatedBy'    => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'contactName' => 'Test #1',
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

        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'contactName' => 'Test #1',
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
        $iri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'contactName' => 'name name',
            'project'     => $newProjectIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // contactName got updated but project didn't
        self::assertJsonContains([
            'contactName'  => 'name name',
            'project'      => [
                '@id' => $projectIRI,
            ],
        ]);
    }

    public function testUpdateFactionFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $factionIRI = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_GREEN['id']]);
        $newFactionIRI = $this->findIriBy(Faction::class,
            ['id' => TestFixtures::FACTION_RED['id']]);
        $iri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'contactName' => 'name name',
            'faction'     => $newFactionIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // contactName got updated but faction didn't
        self::assertJsonContains([
            'contactName'  => 'name name',
            'faction'      => [
                '@id' => $factionIRI,
            ],
        ]);
    }

    public function testDelete(): void
    {
        /** @var Faction $before */
        $before = $this->entityManager->getRepository(Faction::class)
            ->find(TestFixtures::FACTION_GREEN['id']);
        self::assertCount(1, $before->getDetails());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var FactionDetails $deleted */
        $deleted = $this->entityManager->getRepository(FactionDetails::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Faction $after */
        $after = $this->entityManager->getRepository(Faction::class)
            ->find(TestFixtures::FACTION_GREEN['id']);
        self::assertCount(0, $after->getDetails());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
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
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(FactionDetails::class, ['id' => 1]);
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
