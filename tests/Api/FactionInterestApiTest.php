<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\FractionDetails;
use App\Entity\FractionInterest;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FractionInterestApi
 */
class FractionInterestApiTest extends ApiTestCase
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
        ])->request('GET', '/fraction_interests');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /fraction_interests": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetFractionInterestAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(FractionInterest::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'description' => 'interest1',
        ]);
    }

    public function testCreateFractionInterest(): void
    {
        $fractionDetailsIri = $this->findIriBy(FractionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('POST', '/fraction_interests', ['json' => [
            'description'    => 'test interest',
            'fractionDetails' => $fractionDetailsIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt nicht mit den validatoren klar
        self::assertMatchesResourceItemJsonSchema(FractionInterest::class);

        self::assertJsonContains([
            '@context'       => '/contexts/FractionInterest',
            '@type'          => 'FractionInterest',
            'description'    => 'test interest',
            'fractionDetails' => ['@id' => $fractionDetailsIri],
            'updatedBy'      => [
                'id' => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $fractionDetailsIri = $this->findIriBy(FractionDetails::class,
            ['id' => 1]);

        static::createClient()->request('POST', '/fraction_interests', ['json' => [
            'description'    => 'test interest',
            'fractionDetails' => $fractionDetailsIri,
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
        $fractionDetailsIri = $this->findIriBy(FractionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ])->request('POST', '/fraction_interests', ['json' => [
            'description'    => 'test interest',
            'fractionDetails' => $fractionDetailsIri,
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
        $fractionDetailsIri = $this->findIriBy(FractionDetails::class,
            ['id' => 1]);

        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/fraction_interests', ['json' => [
            'fractionDetails' => $fractionDetailsIri,
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

    public function testCreateWithoutFractionDetailsFails(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/fraction_interests', ['json' => [
            'description'    => 'test interest',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'fractionDetails: validate.general.notBlank',
        ]);
    }

    public function testUpdateFractionInterest(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
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
        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
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

    public function testUpdateFractionDetailsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $detailsIri = $this->findIriBy(FractionDetails::class,
            ['id' => 1]);
        $newDetailsIRI = $this->findIriBy(FractionDetails::class,
            ['id' => 2]);
        $iri = $this->findIriBy(FractionInterest::class,
            ['id' => 1]);

        $client->request('PUT', $iri, ['json' => [
            'description'    => 'interest interest',
            'fractionDetails' => $newDetailsIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // description got updated but fractionDetails didn't
        self::assertJsonContains([
            'description'    => 'interest interest',
            'fractionDetails'      => [
                '@id' => $detailsIri,
            ],
        ]);
    }

    public function testDelete(): void
    {
        /** @var FractionDetails $before */
        $before = $this->entityManager->getRepository(FractionDetails::class)
            ->find(1);
        self::assertCount(2, $before->getInterests());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var FractionInterest $deleted */
        $deleted = $this->entityManager->getRepository(FractionInterest::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var FractionDetails $after */
        $after = $this->entityManager->getRepository(FractionDetails::class)
            ->find(1);
        self::assertCount(1, $after->getInterests());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FractionInterest::class, ['id' => 1]);
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
