<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Council;
use App\Entity\FederalState;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FederalStateApi
 */
class FederalStateApiTest extends ApiTestCase
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

    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/federal_states');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(FederalState::class);

        self::assertJsonContains([
            '@context'         => '/contexts/FederalState',
            '@id'              => '/federal_states',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 16,
        ]);

        $collection = $response->toArray();

        // pagesize = 15
        self::assertCount(15, $collection['hydra:member']);
        self::assertNotNull($collection['hydra:view']['hydra:next']);
    }

    public function testGetFederalState(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(FederalState::class);

        self::assertJsonContains([
            '@id'         => $iri,
            'name'        => 'Baden-Württemberg',
            'slug'        => 'baden-wurttemberg',
            'councils' => [
                0 => [],
            ],
        ]);
    }

    public function testCreateFederalState(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/federal_states', ['json' => [
            'name' => 'Musterland',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(FederalState::class);

        self::assertJsonContains([
            '@context'    => '/contexts/FederalState',
            '@type'       => 'FederalState',
            'name'        => 'Musterland',
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        static::createClient()->request('POST', '/federal_states', ['json' => [
            'name' => 'Musterland',
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
        ])->request('POST', '/federal_states', ['json' => [
            'name' => 'Musterland',
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
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/federal_states', ['json' => [
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
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('POST', '/federal_states', ['json' => [
            'name' => 'Sachsen',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.federalState.duplicateName',
        ]);
    }

    public function testUpdateFederalState(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name' => 'Test #1',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'  => $iri,
            'name' => 'Test #1',
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'name' => 'Sachsen',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.federalState.duplicateName',
        ]);
    }

    public function testDelete(): void
    {
        /** @var Council $before */
        $before = $this->entityManager->getRepository(Council::class)
            ->find(1);
        self::assertInstanceOf(FederalState::class, $before->getFederalState());

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $deleted = static::$container->get('doctrine')
            ->getRepository(FederalState::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Council $after */
        $after = $this->entityManager->getRepository(Council::class)
            ->find(1);
        self::assertNull($after->getFederalState());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
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

        $iri = $this->findIriBy(FederalState::class, ['id' => 1]);
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
