<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\ActionLog;
use App\Entity\Category;
use App\Entity\Council;
use App\Entity\FederalState;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Message\ProjectReportedMessage;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Util\Test;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectApi
 */
class ProjectApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    /**
     * Test what anonymous users see.
     */
    public function testGetCollection(): void
    {
        $response = static::createClient()
            ->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'         => TestFixtures::PROJECT['id'],
                    'council' => [
                        'id' => TestFixtures::COUNCIL['id'],
                    ],
                    'categories' => [
                        0 => ['id' => 1],
                        1 => ['id' => 2],
                        2 => ['id' => 3],
                    ],
                    'createdBy'  => [
                        'id' => TestFixtures::PROJECT_COORDINATOR['id'],
                    ],
                    'state' => Project::STATE_PUBLIC,
                ],
            ],
        ]);

        $collection = $response->toArray();

        // the locked and the deleted project are NOT returned
        self::assertCount(1, $collection['hydra:member']);

        // those properties should not be visible to anonymous
        self::assertArrayNotHasKey('locked', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('memberships', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('partners', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('fractionDetails', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('problems', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('arguments', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('counterArguments', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('actionMandates', $collection['hydra:member'][0]);
        self::assertArrayNotHasKey('proposals', $collection['hydra:member'][0]);

        self::assertArrayNotHasKey('firstName', $collection['hydra:member'][0]['createdBy']);
        self::assertArrayNotHasKey('lastName', $collection['hydra:member'][0]['createdBy']);

        self::assertArrayNotHasKey('projects', $collection['hydra:member'][0]['categories'][0]);

        // fractions can be fetched via the council directly, we don't want to
        // filter here for active/inactive
        self::assertArrayNotHasKey('fractions', $collection['hydra:member'][0]['council']);
    }

    /**
     * Test what anonymous users see.
     */
    public function testGetCollectionReturnsOnlyPublicForAnonymous(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_PRIVATE);

        $locked = $em->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
        $locked->setLocked(false);

        $em->flush();
        $em->clear();

        $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'         => TestFixtures::LOCKED_PROJECT['id'],
                ],
            ],
        ]);
    }

    public function testGetCollectionDoesNotReturnDeletedCreator(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var User $creator */
        $creator = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        $creator->markDeleted();
        $em->flush();
        $em->clear();

        $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo kommt damit klar dass der creator hier leer ist obwohl pflichtfeld
        //self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'          => TestFixtures::PROJECT['id'],

                    // we test for this:
                    'createdBy'   => null,
                ],
            ],
        ]);
    }

    public function testGetCollectionByState(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_PUBLIC);
        $em->flush();
        $em->clear();

        $client->request('GET', '/projects?state='.Project::STATE_PUBLIC);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id' => TestFixtures::PROJECT['id'],
                ],
            ],
        ]);
    }

    public function testGetCollectionByPattern(): void
    {
        $client = static::createClient();

        $client->request('GET', '/projects?pattern=impact with');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id' => TestFixtures::PROJECT['id'],
                ],
            ],
        ]);
    }

    public function testFilterByLockedUnauthenticated(): void
    {
        $client = static::createClient();

        $client->request('GET', '/projects', ['query' => [
            'locked' => true,
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
            'hydra:member'     => [
            ],
        ]);
    }

    public function testFilterByLockedWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $client->request('GET', '/projects', ['query' => [
            'locked' => true,
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
            'hydra:member'     => [
            ],
        ]);
    }

    public function testFilterByLockedAsProcessManager(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $client->request('GET', '/projects', ['query' => [
            'locked' => true,
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id' => TestFixtures::LOCKED_PROJECT['id'],
                ],
            ],
        ]);
    }

    public function testFilterByStateAsProcessManager(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_PRIVATE);
        $em->flush();
        $em->clear();

        $client->request('GET', '/projects', ['query' => [
            'state' => Project::STATE_PRIVATE,
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'    => TestFixtures::PROJECT['id'],
                    'state' => Project::STATE_PRIVATE,
                ],
            ],
        ]);
    }

    public function testFilterByCouncil(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $fs = $em->getRepository(FederalState::class)->find(1);
        $council = new Council();
        $council->setFederalState($fs);
        $council->setTitle('Stadtrat #2');
        $em->persist($council);

        /** @var Project $locked */
        $locked = $em->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
        $locked->setLocked(false);
        $locked->setCouncil($council);

        $em->flush();
        $em->clear();

        $client->request('GET', '/projects', ['query' => [
            'council' => 2,
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'    => TestFixtures::LOCKED_PROJECT['id'],
                    'state' => Project::STATE_PUBLIC,
                ],
            ],
        ]);

        // test again for the other council, this time using the IRI instead of the ID
        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);
        $client->request('GET', '/projects', ['query' => [
            'council' => $iri,
        ]]);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'    => TestFixtures::PROJECT['id'],
                    'state' => Project::STATE_PUBLIC,
                ],
            ],
        ]);
    }

    public function testOrderCollection(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $locked */
        $locked = $em->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
        sleep(1);
        $locked->setCreatedAt(new DateTimeImmutable());
        $locked->setLocked(false);
        $em->flush();
        $em->clear();

        // /projects?order[createdAt]=desc
        $client->request('GET', '/projects', ['query' => [
            'order' => ['createdAt' => 'desc'],
        ]]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 2,
            'hydra:member'     => [
                0 => [
                    'id'    => TestFixtures::LOCKED_PROJECT['id'],
                ],
                1 => [
                    'id'    => TestFixtures::PROJECT['id'],
                ],
            ],
        ]);
    }

    /**
     * Test what process owners see (additional properties).
     */
    public function testGetCollectionAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $response = $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();
        // the locked and the deleted project are NOT returned
        self::assertCount(1, $collection['hydra:member']);

        self::assertSame(TestFixtures::PROJECT['id'], $collection['hydra:member'][0]['id']);

        // properties visible to PO
        self::assertArrayHasKey('createdBy', $collection['hydra:member'][0]);
        self::assertArrayHasKey('locked', $collection['hydra:member'][0]);
        self::assertArrayHasKey('memberships', $collection['hydra:member'][0]);
        self::assertArrayHasKey('proposals', $collection['hydra:member'][0]);
    }

    /**
     * Test what project observers see (additional properties but no user details).
     */
    public function testGetCollectionAsObserver(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $response = $client->request('GET', '/projects');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceCollectionJsonSchema(Project::class);

        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
            'hydra:member'     => [
                0 => [
                    'id'              => TestFixtures::PROJECT['id'],
                    'actionMandates'       => [
                        0 => [],
                    ],
                    'arguments'       => [
                        0 => [],
                    ],
                    'createdBy'       => [
                        'id' => TestFixtures::PROJECT_COORDINATOR['id'],
                    ],
                    'counterArguments'       => [
                        0 => [],
                    ],
                    'fractionDetails' => [
                        0 => [
                            'interests' => [
                                0 => [],
                                1 => [],
                            ],
                        ],
                        1 => [],
                    ],
                    'memberships'     => [
                        0 => [],
                        1 => [],
                        2 => [],
                    ],
                    'partners'        => [
                        0 => [],
                        1 => [],
                    ],
                    'problems'        => [
                        0 => [],
                    ],
                    'proposals' => [
                        0 => [
                            'id'            => TestFixtures::PROPOSAL_1['id'],
                            'actionMandate' => '<ul><li>proposal</li><li>action-mandate</li></ul>',
                            'comment'       => 'proposal comment',
                            'documentFile'  => [
                                'contentUrl'   => '/proposals/1/document-download',
                                'name'         => 'fake-abcd.odt',
                                'originalName' => 'fake.odt',
                                'mimeType'     => 'application/vnd.oasis.opendocument.text',
                            ],
                            'introduction'  => 'proposal <b>introduction</b>',
                            'reasoning'     => '<em>proposal reasoning</em>',
                            'sponsor'       => 'interfraktionell',
                            'title'         => 'proposal title',

                            'usedActionMandates' => [
                                0 => [
                                    'actionMandate'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                            'usedArguments' => [
                                0 => [
                                    'argument'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                            'usedCounterArguments' => [
                                0 => [
                                    'counterArgument'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                            'usedFractionInterests' => [
                                0 => [
                                    'fractionInterest'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                            'usedNegations' => [
                                0 => [
                                    'negation'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                            'usedProblems' => [
                                0 => [
                                    'problem'  => [
                                        'id' => 1,
                                    ],
                                ],
                            ],
                        ],
                        1 => [
                            'id'      => TestFixtures::PROPOSAL_2['id'],
                            'sponsor' => 'Green',
                        ],
                    ],
                ],
            ],
        ]);

        $collection = $response->toArray();

        // user details invisible
        self::assertArrayNotHasKey('user',
            $collection['hydra:member'][0]['memberships'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['fractionDetails'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['fractionDetails'][0]['interests'][0]);
        self::assertArrayNotHasKey('teamContact',
            $collection['hydra:member'][0]['partners'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['partners'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['problems'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['arguments'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['counterArguments'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['actionMandates'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $collection['hydra:member'][0]['proposals'][0]);

        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedActionMandates'][0]);
        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedArguments'][0]);
        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedCounterArguments'][0]);
        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedFractionInterests'][0]);
        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedNegations'][0]);
        self::assertArrayNotHasKey('createdBy',
            $collection['hydra:member'][0]['proposals'][0]['usedProblems'][0]);
    }

    /**
     * Filter the collection for undeleted projects only, same as default.
     */
    public function testGetUndeletedProjects(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 0,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        // the deleted and the locked project are NOT returned
        $collection = $response->toArray();
        self::assertCount(1, $collection['hydra:member']);

        self::assertSame(TestFixtures::PROJECT['id'], $collection['hydra:member'][0]['id']);
    }

    /**
     * Admins can explicitly request deleted projects via filter.
     */
    public function testGetDeletedProjectsAsAdmin(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 1,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        self::assertCount(1, $collection['hydra:member']);
        self::assertSame(TestFixtures::DELETED_PROJECT['id'],
            $collection['hydra:member'][0]['id']);
    }

    public function testGetDeletedProjectsFailsWithoutPrivilege(): void
    {
        $response = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ])->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 1,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);

        $collection = $response->toArray();
        self::assertCount(0, $collection['hydra:member']);
    }

    public function testGetDeletedProjectsFailsUnauthenticated(): void
    {
        $response = static::createClient()->request('GET', '/projects', ['query' => [
            'exists[deletedAt]' => 1,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 0,
        ]);

        $collection = $response->toArray();
        self::assertCount(0, $collection['hydra:member']);
    }

    public function testGetLockedProjectsAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $response = $client->request('GET', '/projects', [
            'query' => ['locked' => true],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'         => '/contexts/Project',
            '@id'              => '/projects',
            '@type'            => 'hydra:Collection',
            'hydra:totalItems' => 1,
        ]);

        $collection = $response->toArray();

        self::assertCount(1, $collection['hydra:member']);
        self::assertSame(TestFixtures::LOCKED_PROJECT['id'],
            $collection['hydra:member'][0]['id']);
    }

    /**
     * @todo replace by custom filter "mine"
     */
    public function testGetProjectsByIdAsProjectMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $client->request('GET', '/projects', [
            'query' => ['id' => [
                TestFixtures::PROJECT['id'],
                TestFixtures::LOCKED_PROJECT['id'],
                TestFixtures::DELETED_PROJECT['id'],

                // by IRI works too
                //$this->findIriBy(Project::class, ['id' => TestFixtures::PROJECT['id']]),
                //$this->findIriBy(Project::class, ['id' => TestFixtures::LOCKED_PROJECT['id']]),
                //$this->findIriBy(Project::class, ['id' => TestFixtures::DELETED_PROJECT['id']]),
            ]],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@context'          => '/contexts/Project',
            '@id'               => '/projects',
            '@type'             => 'hydra:Collection',

            // the deleted project is NOT returned
            'hydra:totalItems'  => 2,
            'hydra:member'      => [
                0 => ['id' => TestFixtures::PROJECT['id']],
                1 => ['id' => TestFixtures::LOCKED_PROJECT['id']],
            ],
        ]);
    }

    public function testGetProject(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $response = $client->request('GET', $iri);
        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'              => $iri,
            'categories' => [
                0 => ['id' => 1],
                1 => ['id' => 2],
                2 => ['id' => 3],
            ],
            'createdBy'        => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
            'council'        => [
                'id' => TestFixtures::COUNCIL['id'],
            ],
            'description'      => TestFixtures::PROJECT['description'],
            'id'               => TestFixtures::PROJECT['id'],
            'state'            => Project::STATE_PUBLIC,
            'topic'            => TestFixtures::PROJECT['topic'],
        ]);

        $projectData = $response->toArray();

        self::assertArrayNotHasKey('actionMandates', $projectData);
        self::assertArrayNotHasKey('arguments', $projectData);
        self::assertArrayNotHasKey('counterArguments', $projectData);
        self::assertArrayNotHasKey('deletedAt', $projectData);
        self::assertArrayNotHasKey('fractionDetails', $projectData);
        self::assertArrayNotHasKey('locked', $projectData);
        self::assertArrayNotHasKey('memberships', $projectData);
        self::assertArrayNotHasKey('partners', $projectData);
        self::assertArrayNotHasKey('problems', $projectData);
        self::assertArrayNotHasKey('proposals', $projectData);

        self::assertArrayNotHasKey('fractions', $projectData['council']);
        self::assertArrayNotHasKey('projects', $projectData['council']);

        // additional data for the council should be fetched via the council itself
        self::assertArrayNotHasKey('title', $projectData['council']);

        self::assertArrayNotHasKey('projects', $projectData['categories'][0]);

        self::assertArrayNotHasKey('firstName', $projectData['createdBy']);
        self::assertArrayNotHasKey('lastName', $projectData['createdBy']);
    }

    /* @todo with ApiSubresource
        public function testGetProjectCategories(): void
        {
            $client = static::createClient();

            $iri = $this->findIriBy(Project::class,
                ['id' => TestFixtures::PROJECT['id']]);

            $client->request('GET', $iri.'/categories');
            self::assertMatchesResourceCollectionJsonSchema(Category::class);

            self::assertJsonContains([
                'hydra:member' => [
                    0 => ['id' => 1],
                    1 => ['id' => 2],
                    2 => ['id' => 3],
                ],
            ]);
        }
    */
    public function testGetProjectDoesNotReturnDeletedCreator(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var User $creator */
        $creator = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
        $creator->markDeleted();
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('GET', $iri);

        // @todo kommt damit klar dass der creator hier leer ist obwohl pflichtfeld
        //self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'              => $iri,
            'createdBy'        => null,
            'description'      => TestFixtures::PROJECT['description'],
            'id'               => TestFixtures::PROJECT['id'],
        ]);
    }

    public function testGetProjectAsObserver(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('GET', $iri);
        $projectData = $response->toArray();

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'          => $iri,
            'id'           => TestFixtures::PROJECT['id'],
            'actionMandates'  => [
                0 => [],
            ],
            'arguments'  => [
                0 => [],
            ],
            'categories'   => [
                0 => ['id' => 1],
                1 => ['id' => 2],
                2 => ['id' => 3],
            ],
            'council'      => [
                'id'        => TestFixtures::COUNCIL['id'],
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
                    // inactive fractions are visible to project members
                    3 => [
                        'id' => TestFixtures::FRACTION_YELLOW['id'],
                    ],
                ],
            ],
            'counterArguments'  => [
                0 => [],
            ],
            'createdBy'    => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
            'fractionDetails' => [
                0 => [
                    'contactName' => 'Green',
                    'interests'   => [
                        0 => [
                            'description' => 'interest1',
                        ],
                        1 => [
                            'description' => 'interest2',
                        ],
                    ],
                ],
            ],
            'locked'       => false,
            'memberships'  => [
                0 => [],
                1 => [],
                2 => [],
            ],
            'partners'  => [
                0 => [],
                1 => [],
            ],
            'problems'  => [
                0 => [],
            ],
            'proposals' => [
                0 => [
                    'id'            => TestFixtures::PROPOSAL_1['id'],
                    'actionMandate' => '<ul><li>proposal</li><li>action-mandate</li></ul>',
                    'comment'       => 'proposal comment',
                    'introduction'  => 'proposal <b>introduction</b>',
                    'reasoning'     => '<em>proposal reasoning</em>',
                    'sponsor'       => 'interfraktionell',
                    'title'         => 'proposal title',

                    'usedActionMandates' => [
                        0 => [
                            'actionMandate'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                    'usedArguments' => [
                        0 => [
                            'argument'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                    'usedCounterArguments' => [
                        0 => [
                            'counterArgument'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                    'usedFractionInterests' => [
                        0 => [
                            'fractionInterest'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                    'usedNegations' => [
                        0 => [
                            'negation'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                    'usedProblems' => [
                        0 => [
                            'problem'  => [
                                'id' => 1,
                            ],
                        ],
                    ],
                ],
                1 => [
                    'id'      => TestFixtures::PROPOSAL_2['id'],
                    'sponsor' => 'Green',
                ],
            ],
            'title'        => TestFixtures::PROJECT['title'],
        ]);

        self::assertArrayNotHasKey('updatedBy', $projectData);
        self::assertArrayNotHasKey('user',
            $projectData['memberships'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['fractionDetails'][0]);
        self::assertArrayNotHasKey('teamContact',
            $projectData['fractionDetails'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['fractionDetails'][0]['interests'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['partners'][0]);
        self::assertArrayNotHasKey('teamContact',
            $projectData['partners'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['problems'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['arguments'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['counterArguments'][0]);
        self::assertArrayNotHasKey('updatedBy',
            $projectData['actionMandates'][0]);

        self::assertArrayNotHasKey('updatedBy',
            $projectData['proposals'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedActionMandates'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedArguments'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedCounterArguments'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedFractionInterests'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedNegations'][0]);
        self::assertArrayNotHasKey('createdBy',
            $projectData['proposals'][0]['usedProblems'][0]);

        self::assertArrayNotHasKey('firstName', $projectData['createdBy']);
        self::assertArrayNotHasKey('lastName', $projectData['createdBy']);
    }

    public function testGetProjectAsWriter(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'              => $iri,
            'id'               => TestFixtures::PROJECT['id'],
            'actionMandates'        => [
                0 => [
                    'description' => 'action-mandate 1',
                    'updatedBy'   => [],
                ],
            ],
            'arguments'        => [
                0 => [
                    'description' => 'argument 1',
                    'updatedBy'   => [],
                ],
            ],
            'categories'       => [
                0 => ['id' => 1],
                1 => ['id' => 2],
                2 => ['id' => 3],
            ],
            'council'          => [
                'id' => TestFixtures::COUNCIL['id'],
            ],
            'counterArguments' => [
                0 => [
                    'description' => 'counter-argument 1',
                    'updatedBy'   => [],
                ],
            ],
            'createdBy'      => [
                'id' => TestFixtures::PROJECT_COORDINATOR['id'],
            ],
            'fractionDetails' => [
                0 => [
                    'fraction'    => [
                        'id'   => TestFixtures::FRACTION_GREEN['id'],
                        'name' => TestFixtures::FRACTION_GREEN['name'],
                    ],
                    'contactName' => 'Green',
                    'interests'   => [
                        0 => [
                            'description' => 'interest1',
                            'updatedBy'   => [],
                        ],
                        1 => [
                            'description' => 'interest2',
                            'updatedBy'   => [],
                        ],
                    ],
                    'updatedBy'   => [],
                ],
            ],
            'locked'        => false,
            'title'         => TestFixtures::PROJECT['title'],
            'memberships' => [
                0 => [
                    'user' => [],
                ],
                1 => [
                    'user' => [],
                ],
                2 => [
                    'user' => [],
                ],
            ],
            'partners' => [
                0 => [
                    'updatedBy' => [],
                ],
                1 => [
                    'updatedBy' => [],
                ],
            ],
            'problems' => [
                0 => [
                    'description' => 'problem 1',
                    'updatedBy'   => [],
                ],
            ],
            'proposals' => [
                0 => [
                    'id'            => TestFixtures::PROPOSAL_1['id'],
                    'actionMandate' => '<ul><li>proposal</li><li>action-mandate</li></ul>',
                    'comment'       => 'proposal comment',
                    'documentFile'  => [
                        'contentUrl'   => '/proposals/1/document-download',
                        'name'         => 'fake-abcd.odt',
                        'originalName' => 'fake.odt',
                        'mimeType'     => 'application/vnd.oasis.opendocument.text',
                    ],
                    'introduction'  => 'proposal <b>introduction</b>',
                    'reasoning'     => '<em>proposal reasoning</em>',
                    'sponsor'       => 'interfraktionell',
                    'title'         => 'proposal title',

                    'updatedBy' => [
                        'id' => TestFixtures::PROCESS_MANAGER['id'],
                    ],

                    'usedActionMandates' => [
                        0 => [
                            'actionMandate'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                    'usedArguments' => [
                        0 => [
                            'argument'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                    'usedCounterArguments' => [
                        0 => [
                            'counterArgument'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                    'usedFractionInterests' => [
                        0 => [
                            'fractionInterest'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                    'usedNegations' => [
                        0 => [
                            'negation'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                    'usedProblems' => [
                        0 => [
                            'problem'  => [
                                'id' => 1,
                            ],
                            'createdBy' => [
                                'id' => TestFixtures::ADMIN['id'],
                            ],
                        ],
                    ],
                ],
                1 => [
                    'id'           => TestFixtures::PROPOSAL_2['id'],
                    'documentFile' => null,
                    'sponsor'      => 'Green',
                ],
            ],
        ]);
    }

    public function testGetProjectAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $response = $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'   => $iri,
            'id'    => TestFixtures::PROJECT['id'],
            'title' => TestFixtures::PROJECT['title'],
        ]);

        $projectData = $response->toArray();
        self::assertCount(3, $projectData['memberships']);
    }

    /**
     * Anonymous users cannot get a locked project, returns 404.
     */
    public function testGetLockedProjectFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
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

    /**
     * Normal users cannot get a locked project, returns 404.
     */
    public function testGetLockedProjectFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
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

    /**
     * A member can see a private project.
     */
    public function testGetPrivateProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_PRIVATE);

        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            '@id'   => $iri,
            'id'    => TestFixtures::PROJECT['id'],
            'title' => TestFixtures::PROJECT['title'],
        ]);
    }

    /**
     * Guests cannot get a private project, returns 404.
     */
    public function testGetPrivateProjectFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setState(Project::STATE_PRIVATE);

        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
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

    /**
     * Normal users cannot get a private project, returns 404.
     */
    public function testGetPrivateProjectFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $locked = $em->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
        $locked->setState(Project::STATE_PRIVATE);
        $locked->setLocked(false);

        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
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

    public function testProcessOwnerCanGetLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'id'    => TestFixtures::LOCKED_PROJECT['id'],
            'title' => TestFixtures::LOCKED_PROJECT['title'],
        ]);
    }

    public function testMemberCanGetLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'id'    => TestFixtures::LOCKED_PROJECT['id'],
            'title' => TestFixtures::LOCKED_PROJECT['title'],
        ]);
    }

    public function testCreateProject(): void
    {
        $before = new DateTimeImmutable();
        sleep(1);

        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $catIri = $this->findIriBy(Category::class,
            ['id' => 2]);

        $response = $client->request('POST', '/projects', ['json' => [
            'title'        => 'test project',
            'topic'        => 'new topic',
            'council'      => $iri,
            'motivation'   => 'my motivation',
            'skills'       => 'my project skills',
            'categories'   => [$catIri],
            'featureImage' => 'https://link.to/image',
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertMatchesResourceItemJsonSchema(Project::class);

        self::assertJsonContains([
            'description' => '',
            'id'          => 4, // ID 1-3 created by fixtures
            'title'       => 'test project',
            'memberships' => [
                [
                    '@type'      => 'ProjectMembership',
                    'role'       => ProjectMembership::ROLE_COORDINATOR,
                    'motivation' => 'my motivation',
                    'skills'     => 'my project skills',
                    'user'       => [
                        'id' => TestFixtures::ADMIN['id'],
                    ],
                ],
            ],
            'categories' => [
                0 => [
                    'id' => 2,
                ],
            ],
            'createdBy'             => [
                'id' => TestFixtures::ADMIN['id'],
            ],
            'council'               => [
                'id' => TestFixtures::COUNCIL['id'],
            ],
            'featureImage'          => 'https://link.to/image',
            'impact'                => '',
            'slug'                  => 'test-project',
            'state'                 => Project::STATE_PRIVATE,
            'topic'                 => 'new topic',
        ]);

        $projectData = $response->toArray();
        self::assertSame(TestFixtures::ADMIN['id'], $projectData['createdBy']['id']);

        $em = static::getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::CREATED_PROJECT]);
        self::assertCount(1, $logs);
        self::assertSame(TestFixtures::ADMIN['username'], $logs[0]->username);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $client->request('POST', '/projects', ['json' => [
            'title'            => 'just for fun',
            'motivation'       => 'my motivation',
            'skills'           => 'my skills',
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testCreateProjectWithoutTitleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'motivation' => 'my motivation',
            'council'    => $iri,
            'skills'     => 'my project skills',
            'topic'      => 'not required, only for this test',
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

    public function testCreateProjectWithoutCouncilFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $client->request('POST', '/projects', ['json' => [
            'title'      => 'test title',
            'topic'      => 'new topic',
            'skills'     => 'my project skills',
            'motivation' => 'my project motivation',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'council: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithInactiveCouncilFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $council = $em->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);
        $council->setActive(false);
        $em->flush();

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'council'    => $iri,
            'title'      => 'test title',
            'topic'      => 'new topic',
            'skills'     => 'my project skills',
            'motivation' => 'my project motivation',
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/councils/'
                .TestFixtures::COUNCIL['id'].'".',
        ]);
    }

    public function testCreateProjectWithoutMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'council' => $iri,
            'title'      => 'test title',
            'skills'     => 'my project skills',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithShortMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'title'      => 'test title',
            'motivation' => 'too short',
            'council' => $iri,
            'skills'     => 'my project skills',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'motivation: validate.general.tooShort',
        ]);
    }

    public function testCreateProjectWithoutSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'title'      => 'test title',
            'motivation' => 'my motivation',
            'council' => $iri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: validate.general.notBlank',
        ]);
    }

    public function testCreateProjectWithShortSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'motivation' => 'my motivation',
            'council' => $iri,
            'skills'     => 'my skills',
            'title'      => 'test title',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'skills: validate.general.tooShort',
        ]);
    }

    public function testStateIsIgnoredWhenCreatingProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(Council::class,
            ['id' => TestFixtures::COUNCIL['id']]);

        $client->request('POST', '/projects', ['json' => [
            'title'       => 'test title',
            'topic'       => 'new topic',
            'motivation'  => 'my motivation is good',
            'skills'      => 'my skills are better',
            'state'       => Project::STATE_PUBLIC,
            'council'  => $iri,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id'               => 4, // ID 1-3 created by fixtures
            'state'            => Project::STATE_PRIVATE,
        ]);
    }

    public function testUpdateProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        sleep(1);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'topic'  => 'new topic',
            'impact' => 'another impact',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'    => $iri,
            'topic'  => 'new topic',
            'impact' => 'another impact',
        ]);

        /** @var Project $found */
        $em = static::getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertTrue($now->getTimestamp() < $found->getUpdatedAt()->getTimestamp());
    }

    public function testUpdateCategories(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $categoryIri = $this->findIriBy(Category::class, ['id' => 4]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'categories' => [$categoryIri],
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'        => $iri,
            'categories' => [
                0 => ['id' => 4],
            ],
        ]);
    }

    public function testUpdateAutoTrim(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'topic'                 => ' new topic ',
            'title'                 => ' another title ',
            'impact'                => ' another impact ',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'                   => $iri,
            'topic'                 => 'new topic',
            'title'                 => 'another title',
            'impact'                => 'another impact',
        ]);
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'title'                 => 'another title',        ]]);

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

        $iri = $this->findIriBy(Project::class, ['id' => 1]);
        $client->request('PUT', $iri, ['json' => [
            'title'                 => 'another title',        ]]);

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

    public function testUpdateOfStateWithoutPrivilegeIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'state' => Project::STATE_PUBLIC,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'   => $iri,
            'state' => Project::STATE_PUBLIC,
        ]);
    }

    public function testUpdateWithEmptyStateIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'impact' => 'new challenges',
            'state'  => null,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'    => $iri,
            'impact' => 'new challenges',
            'state'  => Project::STATE_PUBLIC,
        ]);
    }

    public function testUpdateWithInvalidStateFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'state' => '13',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'state: validate.general.invalidChoice',
        ]);
    }

    public function testSettingEmptyTitleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'title' => ' ',
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

    public function testSettingShortTitleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'title' => '  no  ',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.general.tooShort',
        ]);
    }

    public function testSettingTitleWithoutLetterFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'title' => '66666',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'title: validate.general.letterRequired',
        ]);
    }

    public function testLockingWithoutPrivilegeIsIgnored(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('PUT', $iri, ['json' => [
            'locked' => true,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'      => $iri,
            'id'       => TestFixtures::PROJECT['id'],
        ]);

        $project = static::$container->get('doctrine')
            ->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertFalse($project->isLocked());
    }

    public function testLockingProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('PUT', $iri, ['json' => [
            'locked' => true,
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@id'    => $iri,
            'id'     => TestFixtures::PROJECT['id'],
            'locked' => true,
        ]);
    }

    public function testDeleteLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setLocked(true);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        /* @var $deleted Project */
        $deleted = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertInstanceOf(Project::class, $deleted);
        self::assertTrue($deleted->isDeleted());
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();

        /* @var $before Project */
        /*   $em = static::getContainer()->get('doctrine')->getManager();
           $before = $em
               ->getRepository(Project::class)
               ->find(TestFixtures::PROJECT['id']);
           $before->setLocked(true);
           $em->flush();
           $em->clear();
   */
        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('DELETE', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testDeleteActiveProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $iri = $this->findIriBy(Project::class, [
            'id' => TestFixtures::PROJECT['id'],
        ]);
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

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        /* @var $before Project */
        $em = static::getContainer()->get('doctrine')->getManager();
        $before = $em
            ->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $before->setLocked(true);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(Project::class, ['id' => 2]);
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

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('DELETE', '/projects');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE http://example.com/projects": Method Not Allowed (Allow: GET, POST)',
        ]);
    }

    public function testGetStatistics(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ])->request('GET', '/projects/statistics');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'total'   => 3,
            'new'     => 1, // non-locked, non-deleted
            'public'  => 1, // non-locked, non-deleted
            'deleted' => 1,
        ]);
    }

    public function testGetStatisticsFailsUnauthenticated(): void
    {
        static::createClient()->request('GET', '/projects/statistics');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testGetStatisticsFailsWithoutPrivilege(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ])->request('GET', '/projects/statistics');

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

    public function testReportProject(): void
    {
        $before = new DateTimeImmutable();
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', $iri.'/report', ['json' => [
            'reportMessage' => 'this project is very bad',
            'reporterName'  => 'ich bins',
            'reporterEmail' => 'fake@email.com',
        ]]);

        self::assertResponseStatusCodeSame(202);
        self::assertJsonContains([
            'success' => true,
            'message' => 'Request received',
        ]);

        $messenger = static::getContainer()->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ProjectReportedMessage::class,
            $messages[0]['message']);
        self::assertSame(TestFixtures::PROJECT['id'],
            $messages[0]['message']->projectId);
        self::assertSame('this project is very bad',
            $messages[0]['message']->message);
        self::assertSame('ich bins',
            $messages[0]['message']->reporterName);
        self::assertSame('fake@email.com',
            $messages[0]['message']->reporterEmail);

        $em = static::getContainer()->get('doctrine')->getManager();
        $logs = $em->getRepository(ActionLog::class)
            ->findBy(['action' => ActionLog::REPORTED_PROJECT]);
        self::assertCount(1, $logs);
        self::assertGreaterThan($before, $logs[0]->timestamp);
    }

    public function testReportProjectCannotModifyProperties(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $client->request('POST', $iri.'/report', ['json' => [
            'reportMessage' => 'this project is very bad',
            'reporterName'  => 'ich bins',
            'reporterEmail' => 'fake@email.com',
            'name'          => 'haxxed',
        ]]);

        self::assertResponseStatusCodeSame(202);

        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertSame(TestFixtures::PROJECT['title'], $project->getTitle());
    }

    public function testGetCollaboration(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('GET', $projectIri.'/collab');

        static::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'collabData' => [
                'description' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'description with 20 characters',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testGetCollaborationFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('GET', $projectIri.'/collab');

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

    public function testGetCollaborationFailsUnauthorized(): void
    {
        $client = static::createClient();

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('GET', $projectIri.'/collab');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testSetCollaboration(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', $projectIri.'/collab', ['json' => [
            'collabData' => [
                'description' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'heading',
                            'attrs'   => ['level' => '2'],
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Überschrift',
                                ],
                            ],
                        ],
                        [
                            'type'    => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Te',
                                ],
                                [
                                    'type'  => 'text',
                                    'text'  => 's',
                                    'marks' => [
                                        ['type' => 'bold'],
                                    ],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 't',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]]);

        static::assertResponseStatusCodeSame(200);

        $em = static::getContainer()->get('doctrine')->getManager();
        $em->clear();
        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        self::assertSame('<h2>Überschrift</h2><p>Te<strong>s</strong>t</p>', $project->getDescription());
    }

    public function testSetCollaborationFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', $projectIri.'/collab', ['json' => [
            'collabData' => [
                'description' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'heading',
                            'attrs'   => ['level' => '2'],
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Überschrift',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
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

    public function testSetCollaborationFailsUnauthorized(): void
    {
        $client = static::createClient();

        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', $projectIri.'/collab', ['json' => [
            'collabData' => [
                'description' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'heading',
                            'attrs'   => ['level' => '2'],
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Überschrift',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }
}
