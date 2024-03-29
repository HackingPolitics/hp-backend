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
 * @group CouncilApi
 */
class CouncilApiTest extends ApiTestCase
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
            ->request('GET', '/councils');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Council::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Council',
            '@id'              => '/councils',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'         => TestFixtures::PROJECT['id'],
                    'fractions' => [
                        0 => [
                            'id' => TestFixtures::FRACTION_GREEN['id'],
                        ],
                        1 => [
                            'id' => TestFixtures::FRACTION_RED['id'],
                        ],
                        2 => [
                            'id' => TestFixtures::FRACTION_BLACK['id'],
                        ],
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();
        self::assertCount(1, $collection['hydra:member']);

        // the inactive 4th fraction is not returned
        self::assertCount(3, $collection['hydra:member'][0]['fractions']);

        // those properties should not be visible to anonymous
        self::assertArrayNotHasKey('projects',
            $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('details',
            $collection['hydra:member'][0]['fractions'][0]);
    }

    public function testGetCollectionReturnsOnlyActive(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $council = $em->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);
        $council->setActive(false);
        $em->flush();
        $em->clear();

        $client->request('GET', '/councils');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Council::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Council',
            '@id'              => '/councils',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);
    }

    public function testGetCouncil(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Council::class, ['id' => 1]);

        $response = $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Council::class);

        self::assertJsonContains([
            '@id'          => $iri,
            'title'        => TestFixtures::COUNCIL['title'],
            'fractions'    => [
                0 => [
                    'id'    => TestFixtures::FRACTION_GREEN['id'],
                    'name'  => TestFixtures::FRACTION_GREEN['name'],
                    'color' => TestFixtures::FRACTION_GREEN['color'],
                ],
                1 => [
                    'id' => TestFixtures::FRACTION_RED['id'],
                ],
                2 => [
                    'id' => TestFixtures::FRACTION_BLACK['id'],
                ],
            ],
            'federalState' => [
                'id' => 1,
            ],
            'updatedBy' => [
                'id' => TestFixtures::ADMIN['id'],
            ],
        ]);

        $council = $response->toArray();

        // the inactive 4th fraction is not returned
        self::assertCount(3, $council['fractions']);

        // those properties should not be visible to anonymous
        self::assertArrayNotHasKey('projects', $council);
        self::assertArrayNotHasKey('details', $council['fractions'][0]);
    }

    public function testGetInactiveCouncilFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $council = $em->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);
        $council->setActive(false);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Council::class, ['id' => 1]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Not Found',
        ]);
    }

    public function testCreateCouncil(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $stateIri = $this->findIriBy(FederalState::class, ['id' => 3]);

        $client->request('POST', '/councils', ['json' => [
            'title'                     => 'Musterland',
            'federalState'              => $stateIri,
            'headOfAdministrationTitle' => 'OBM',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'                  => '/contexts/Council',
            '@type'                     => 'Council',
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
        static::createClient()->request('POST', '/councils', ['json' => [
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
        ])->request('POST', '/councils', ['json' => [
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
        ])->request('POST', '/councils', ['json' => [
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
        ])->request('POST', '/councils', ['json' => [
            'title'                     => TestFixtures::COUNCIL['title'],
            'headOfAdministrationTitle' => 'OBM',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.council.duplicateTitle',
        ]);
    }

    public function testUpdateCouncil(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Council::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'title'       => 'Test #1',
            'validatedAt' => null,
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
        $iri = $this->findIriBy(Council::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Council::class, ['id' => 1]);
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

        // add a second council to the db, we will try to name it like the first
        $council = new Council();
        $council->setTitle('just for fun');
        $council->setHeadOfAdministrationTitle('OBM');
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->persist($council);
        $em->flush();

        $iri = $this->findIriBy(Council::class, ['id' => 2]);
        $client->request('PUT', $iri, ['json' => [
            'title' => TestFixtures::COUNCIL['title'],
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.council.duplicateTitle',
        ]);
    }

    public function testDelete(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var FederalState $before */
        $before = $em->getRepository(FederalState::class)
            ->find(1);
        self::assertCount(1, $before->getCouncils());

        $iri = $this->findIriBy(Council::class, ['id' => 1]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /** @var Council $deleted */
        $deleted = $em->getRepository(Council::class)
            ->find(1);
        self::assertNotNull($deleted);
        self::assertNotNull($deleted->getDeletedAt());

        /** @var FederalState $after */
        $after = $em->getRepository(FederalState::class)
            ->find(1);
        self::assertCount(1, $after->getCouncils());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Council::class, ['id' => 1]);
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

        $iri = $this->findIriBy(Council::class, ['id' => 1]);
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
