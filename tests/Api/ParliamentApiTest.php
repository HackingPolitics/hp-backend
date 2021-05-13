<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\FederalState;
use App\Entity\Parliament;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ParliamentApi
 */
class ParliamentApiTest extends ApiTestCase
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

    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/parliaments');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Parliament::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Parliament',
            '@id'              => '/parliaments',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();
        self::assertCount(1, $collection['hydra:member']);
    }

    public function testGetParliament(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Parliament::class);

        self::assertJsonContains([
            '@id'          => $iri,
            'title'        => TestFixtures::PARLIAMENT['title'],
            'factions'     => [
                0 => [],
                1 => [],
                2 => [],
                3 => [],
            ],
            'federalState' => [
                'id' => 1,
            ],
            'updatedBy' => [
                'id' => TestFixtures::ADMIN['id'],
            ],
        ]);
    }

    public function testCreateParliament(): void
    {
        $stateIri = $this->findIriBy(FederalState::class, ['id' => 3]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/parliaments', ['json' => [
            'title'                     => 'Musterland',
            'federalState'              => $stateIri,
            'headOfAdministrationTitle' => 'OBM',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt nicht mit den validatoren klar, bspw: location: Must be at least 2 characters long
        //self::assertMatchesResourceItemJsonSchema(Parliament::class);

        self::assertJsonContains([
            '@context'                  => '/contexts/Parliament',
            '@type'                     => 'Parliament',
            'title'                     => 'Musterland',
            'headOfAdministrationTitle' => 'OBM',
            'federalState'              => [
                '@id' => $stateIri,
            ],
            'updatedBy' => [
                'id' => TestFixtures::PROCESS_MANAGER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        static::createClient()->request('POST', '/parliaments', ['json' => [
            'title' => 'Musterland',
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
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ])->request('POST', '/parliaments', ['json' => [
            'title' => 'Musterland',
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
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/parliaments', ['json' => [
            'headOfAdministrationTitle' => 'OBM',
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

    public function testCreateWithDuplicateTitleFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/parliaments', ['json' => [
            'title'                     => TestFixtures::PARLIAMENT['title'],
            'headOfAdministrationTitle' => 'OBM',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.parliament.duplicateTitle',
        ]);
    }

    public function testUpdateParliament(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'title' => 'Test #1',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'       => $iri,
            'title'     => 'Test #1',
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
            'title' => 'Test #1',
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
            'title' => 'Test #1',
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

    public function testUpdateWithDuplicateTitleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        // add a second parliament to the db, we will try to name it like the first
        $parliament = new Parliament();
        $parliament->setTitle('just for fun');
        $parliament->setHeadOfAdministrationTitle('OBM');
        $this->entityManager->persist($parliament);
        $this->entityManager->flush();

        $iri = $this->findIriBy(Parliament::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'title' => TestFixtures::PARLIAMENT['title'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.parliament.duplicateTitle',
        ]);
    }

    public function testDelete(): void
    {
        /** @var FederalState $before */
        $before = $this->entityManager->getRepository(FederalState::class)
            ->find(1);
        self::assertCount(1, $before->getParliaments());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Parliament $deleted */
        $deleted = $this->entityManager->getRepository(Parliament::class)
            ->find(1);
        self::assertNotNull($deleted);
        self::assertNotNull($deleted->getDeletedAt());

        /** @var FederalState $after */
        $after = $this->entityManager->getRepository(FederalState::class)
            ->find(1);
        self::assertCount(1, $after->getParliaments());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Parliament::class, ['id' => 1]);
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
