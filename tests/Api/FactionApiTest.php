<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Faction;
use App\Entity\FederalState;
use App\Entity\Parliament;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionApi
 */
class FactionApiTest extends ApiTestCase
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
     * Test that no collection of factions is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/factions');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /factions": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetFactionAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Faction::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Faction::class);

        self::assertJsonContains([
            '@id'          => $iri,
            'name'        => TestFixtures::FACTION_GREEN['name'],
            'parliament'  => [
                'id' => 1,
            ],
            'updatedBy' => [
                'id' => TestFixtures::ADMIN['id'],
            ],
        ]);
    }

    public function testCreateFaction(): void
    {
        $parliamentIri = $this->findIriBy(Parliament::class, ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/factions', ['json' => [
            'name'        => 'Grau',
            'parliament'  => $parliamentIri,
            'memberCount' => 14,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt nicht mit den validatoren klar, bspw: location: Must be at least 2 characters long
        self::assertMatchesResourceItemJsonSchema(Faction::class);

        self::assertJsonContains([
            '@context'    => '/contexts/Faction',
            '@type'       => 'Faction',
            'name'        => 'Grau',
            'memberCount' => 14,
            'parliament'  => [
                '@id' => $parliamentIri,
            ],
            'updatedBy' => [
                'id' => TestFixtures::PROCESS_MANAGER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $parliamentIri = $this->findIriBy(Parliament::class, ['id' => 1]);

        static::createClient()->request('POST', '/factions', ['json' => [
            'name'        => 'Grau',
            'parliament'  => $parliamentIri,
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
        $parliamentIri = $this->findIriBy(Parliament::class, ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ])->request('POST', '/factions', ['json' => [
            'name'        => 'Grau',
            'parliament'  => $parliamentIri,
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

    public function testCreateWithoutNameFails(): void
    {
        $parliamentIri = $this->findIriBy(Parliament::class, ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/factions', ['json' => [
            'memberCount' => 11,
            'parliament'  => $parliamentIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.general.notBlank',
        ]);
    }

    public function testCreateWithDuplicateNameFails(): void
    {
        $parliamentIri = $this->findIriBy(Parliament::class, ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/factions', ['json' => [
            'name'        => TestFixtures::FACTION_GREEN['name'],
            'memberCount' => 1,
            'parliament'  => $parliamentIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.faction.duplicateFaction',
        ]);
    }

    public function testUpdateFaction(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Faction::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name' => 'Test #1',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'name'      => 'Test #1',
            'updatedBy' => [
                'id' => TestFixtures::PROCESS_MANAGER['id'],
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name' => 'Test #1',
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
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name' => 'Test #1',
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

    public function testUpdateWithDuplicateNameFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Faction::class, [
            'id' => TestFixtures::FACTION_GREEN['id']]);
        $client->request('PUT', $iri, ['json' => [
            'name' => TestFixtures::FACTION_RED['name'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.faction.duplicateFaction',
        ]);
    }

    public function testDelete(): void
    {
        /** @var Parliament $before */
        $before = $this->entityManager->getRepository(Parliament::class)
            ->find(1);
        self::assertCount(4, $before->getFactions());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Faction::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Faction $deleted */
        $deleted = $this->entityManager->getRepository(Faction::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Parliament $after */
        $after = $this->entityManager->getRepository(Parliament::class)
            ->find(1);
        self::assertCount(3, $after->getFactions());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Faction::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Faction::class, ['id' => 1]);
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
