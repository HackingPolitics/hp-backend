<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Council;
use App\Entity\Fraction;
use Doctrine\ORM\EntityManager;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FractionApi
 */
class FractionApiTest extends ApiTestCase
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
     * Test that no collection of fractions is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/fractions');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET http://example.com/fractions": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetFractionAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Fraction::class);

        self::assertJsonContains([
            '@id'          => $iri,
            'name'        => TestFixtures::FRACTION_GREEN['name'],
            'council'  => [
                'id' => 1,
            ],
            'updatedBy' => [
                'id' => TestFixtures::ADMIN['id'],
            ],
        ]);
    }

    public function testCreateFraction(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $councilIri = $this->findIriBy(Council::class, ['id' => 1]);

        $client->request('POST', '/fractions', ['json' => [
            'name'        => 'Grau',
            'council'     => $councilIri,
            'memberCount' => 14,
            'color'       => 'aaaaaa',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Fraction::class);

        self::assertJsonContains([
            '@context'    => '/contexts/Fraction',
            '@type'       => 'Fraction',
            'name'        => 'Grau',
            'memberCount' => 14,
            'color'       => 'aaaaaa',
            'council'     => [
                '@id' => $councilIri,
            ],
            'updatedBy'   => [
                'id' => TestFixtures::PROCESS_MANAGER['id'],
            ],
        ]);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $councilIri = $this->findIriBy(Council::class, ['id' => 1]);

        $client->request('POST', '/fractions', ['json' => [
            'name'     => 'Grau',
            'council'  => $councilIri,
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
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);
        $councilIri = $this->findIriBy(Council::class, ['id' => 1]);

        $client->request('POST', '/fractions', ['json' => [
            'name'     => 'Grau',
            'council'  => $councilIri,
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $councilIri = $this->findIriBy(Council::class, ['id' => 1]);

        $client->request('POST', '/fractions', ['json' => [
            'memberCount' => 11,
            'council'     => $councilIri,
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $councilIri = $this->findIriBy(Council::class, ['id' => 1]);

        $client->request('POST', '/fractions', ['json' => [
            'name'        => TestFixtures::FRACTION_GREEN['name'],
            'memberCount' => 1,
            'council'     => $councilIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.fraction.duplicateFraction',
        ]);
    }

    public function testUpdateFraction(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
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
        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Fraction::class, [
            'id' => TestFixtures::FRACTION_GREEN['id'], ]);
        $client->request('PUT', $iri, ['json' => [
            'name' => TestFixtures::FRACTION_RED['name'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'name: validate.fraction.duplicateFraction',
        ]);
    }

    public function testDelete(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Council $before */
        $before = $em->getRepository(Council::class)
            ->find(1);
        self::assertCount(4, $before->getFractions());

        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Fraction $deleted */
        $deleted = $em->getRepository(Fraction::class)
            ->find(1);
        self::assertNull($deleted);

        /** @var Council $after */
        $after = $em->getRepository(Council::class)
            ->find(1);
        self::assertCount(3, $after->getFractions());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
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
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Fraction::class, ['id' => 1]);
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
