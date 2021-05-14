<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\FactionDetails;
use App\Entity\FactionInterest;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionInterestApi
 */
class FactionInterestApiTest extends ApiTestCase
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
     * Test that no collection of interests is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/faction_interests');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /faction_interests": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetFactionInterestAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(FactionInterest::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'interest1',
        ]);
    }

    public function testCreateFactionInterest(): void
    {
        $factionDetailsIri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/faction_interests', ['json' => [
            'description'    => 'test interest',
            'factionDetails' => $factionDetailsIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt nicht mit den validatoren klar
        self::assertMatchesResourceItemJsonSchema(FactionInterest::class);

        self::assertJsonContains([
            '@context'       => '/contexts/FactionInterest',
            '@type'          => 'FactionInterest',
            'description'    => 'test interest',
            'factionDetails' => ['@id' => $factionDetailsIri],
            'updatedBy'      => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $factionDetailsIri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        static::createClient()->request('POST', '/faction_interests', ['json' => [
            'description'    => 'test interest',
            'factionDetails' => $factionDetailsIri,
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
        $factionDetailsIri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ])->request('POST', '/faction_interests', ['json' => [
            'description'    => 'test interest',
            'factionDetails' => $factionDetailsIri,
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
        $factionDetailsIri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/faction_interests', ['json' => [
            'factionDetails' => $factionDetailsIri,
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

    public function testCreateWithoutFactionDetailsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/faction_interests', ['json' => [
            'description'    => 'test interest',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'factionDetails: validate.general.notBlank',
        ]);
    }

    public function testUpdateFactionInterest(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new interest',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'new interest',
            'updatedBy'   => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new interest',
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

        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'description' => 'new interest',
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

    public function testUpdateFactionDetailsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $detailsIri = $this->findIriBy(FactionDetails::class,
            ['id' => 1]);
        $newDetailsIRI = $this->findIriBy(FactionDetails::class,
            ['id' => 2]);
        $iri = $this->findIriBy(FactionInterest::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'description'    => 'interest interest',
            'factionDetails' => $newDetailsIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // description got updated but factionDetails didn't
        self::assertJsonContains([
            'description'    => 'interest interest',
            'factionDetails'      => [
                '@id' => $detailsIri,
            ],
        ]);
    }

    public function testDelete(): void
    {
        /** @var FactionDetails $before */
        $before = $this->entityManager->getRepository(FactionDetails::class)
            ->find(1);
        self::assertCount(2, $before->getInterests());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var FactionInterest $deleted */
        $deleted = $this->entityManager->getRepository(FactionInterest::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var FactionDetails $after */
        $after = $this->entityManager->getRepository(FactionDetails::class)
            ->find(1);
        self::assertCount(1, $after->getInterests());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FactionInterest::class, ['id' => 1]);
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
