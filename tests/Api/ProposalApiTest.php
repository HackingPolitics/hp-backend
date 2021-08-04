<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\Proposal;
use App\Entity\UploadedFileTypes\ProposalDocument;
use App\Entity\User;
use App\Message\ExportProposalMessage;
use App\MessageHandler\ExportProposalMessageHandler;
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
            'hydra:description' => 'No route found for "GET http://example.com/proposals": Method Not Allowed (Allow: POST)',
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
            'title'   => TestFixtures::PROPOSAL_1['title'],
            'project' => [
                'id' => 1,
            ],
        ]);
    }

    public function testCreate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        sleep(1);

        $client->request('POST', '/proposals', ['json' => [
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
        $em = static::getContainer()->get('doctrine')->getManager();
        $found = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        // creation of a new sub-resource should update the timestamp of the parent
        self::assertTrue($now < $found->getUpdatedAt());
    }

    public function testCreateFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/proposals', ['json' => [
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/proposals', ['json' => [
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/proposals', ['json' => [
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/proposals', ['json' => [
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
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $projectIri = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/proposals', ['json' => [
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
        $em = static::getContainer()->get('doctrine')->getManager();
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

        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        self::assertCount(2, $before->getProposals());
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
        self::assertCount(1, $after->getProposals());

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

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $before->setLocked(true);
        $em = static::getContainer()->get('doctrine')->getManager();
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

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Project $before */
        $before = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $before->setLocked(true);
        $em = static::getContainer()->get('doctrine')->getManager();
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

    public function testExport(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('POST', $proposalIri.'/export',
            ['json' => []]);

        static::assertResponseStatusCodeSame(202);

        $messenger = self::getContainer()->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(ExportProposalMessage::class,
            $messages[0]['message']);
    }

    public function testExportFailsUnauthorized(): void
    {
        $client = static::createClient();
        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('POST', $proposalIri.'/export',
            ['json' => []]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testExportFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $client->request('POST', $proposalIri.'/export',
            ['json' => []]);

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

    public function testDownloadDocument(): void
    {
        $client = static::createClient();

        // >>> create an ODT
        $msg = new ExportProposalMessage(
            1,
            TestFixtures::PROJECT_COORDINATOR['id']
        );

        /** @var ExportProposalMessageHandler $handler */
        $handler = self::getContainer()->get(ExportProposalMessageHandler::class);
        $handler($msg);
        // <<< create an ODT

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $token = static::getJWT(static::getContainer(), [
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        // use output buffering to prevent sending the ODT file to the stdout as
        // the client does not support streamed responses (@see https://github.com/symfony/symfony/issues/25005)
        ob_start();

        // we need to use the getKernelBrowser() to send an form-encoded request
        $client->getKernelBrowser()->request('POST', $proposalIri.'/document-download', [
            'bearer' => $token,
        ]);

        ob_end_clean();
        static::assertResponseStatusCodeSame(200);

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var Proposal $proposal */
        $proposal = $em->getRepository(Proposal::class)->find(1);

        self::assertInstanceOf(ProposalDocument::class, $proposal->getDocumentFile());

        $headers = $client->getKernelBrowser()->getInternalResponse()->getHeaders();
        self::assertSame('application/vnd.oasis.opendocument.text', $headers['content-type'][0]);
        self::assertSame(
            'inline; filename='.$proposal->getDocumentFile()->getOriginalName(),
            $headers['content-disposition'][0]
        );

        // cleanup, removes the file from the storage
        $em->remove($proposal->getDocumentFile());
        $em->flush();
    }

    public function testDownloadFailsWithoutPrivilege(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine')->getManager();
        /** @var User $guest */
        $guest = $em->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
        $guest->setValidated(true);
        $em->flush();

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $token = static::getJWT(static::getContainer(), [
            'email' => TestFixtures::GUEST['email'],
        ]);

        // we need to use the getKernelBrowser() to send an form-encoded request
        $client->getKernelBrowser()->request('POST', $proposalIri.'/document-download', [
            'bearer' => $token,
        ]);

        // returns text/html
        self::assertResponseStatusCodeSame(403);
    }

    public function testDownloadFailsUnauthorized(): void
    {
        $client = static::createClient();

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);

        // we need to use the getKernelBrowser() to send an form-encoded request
        $client->getKernelBrowser()->request('POST', $proposalIri.'/document-download', [
            // no token
        ]);

        // returns text/html
        self::assertResponseStatusCodeSame(401);
    }

    public function testDownloadReturns404WithoutFile(): void
    {
        $client = static::createClient();

        // remove the demo record created by the fixtures
        $em = static::getContainer()->get('doctrine')->getManager();
        $proposal = $em->getRepository(Proposal::class)->find(1);
        $proposal->setDocumentFile(null);
        $em->flush();

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $token = static::getJWT(static::getContainer(), [
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        // we need to use the getKernelBrowser() to send a form-encoded request
        $client->getKernelBrowser()->request('POST', $proposalIri.'/document-download', [
            'bearer' => $token,
        ]);

        static::assertResponseStatusCodeSame(404);
    }

    public function testDownloadFailsWithGetRequest(): void
    {
        $client = static::createClient();

        $proposalIri = $this->findIriBy(Proposal::class, ['id' => 1]);
        $token = static::getJWT(static::getContainer(), [
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        // we need to use the getKernelBrowser() to send a form-encoded request
        $client->getKernelBrowser()->request('GET', $proposalIri.'/document-download', [
            'bearer' => $token,
        ]);

        static::assertResponseStatusCodeSame(400);
    }

    public function testGetCollaboration(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('GET', $proposalIri.'/collab');

        static::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'collabData' => [
                'actionMandate' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'bullet_list',
                            'content' => [],
                        ],
                    ],
                ],
                'introduction' => [
                ],
                'reasoning' => [
                ],
                'comment' => [
                    'type'    => 'doc',
                    'content' => [
                        [
                            'type'    => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'proposal comment',
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

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('GET', $proposalIri.'/collab');

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

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('GET', $proposalIri.'/collab');

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

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('POST', $proposalIri.'/collab', ['json' => [
            'collabData' => [
                'comment' => [
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
        /** @var Proposal $proposal */
        $proposal = $em->getRepository(Proposal::class)
            ->find(TestFixtures::PROPOSAL_1['id']);

        self::assertSame('<h2>Überschrift</h2><p>Te<strong>s</strong>t</p>', $proposal->getComment());
    }

    public function testSetCollaborationFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('POST', $proposalIri.'/collab', ['json' => [
            'collabData' => [
                'comment' => [
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

        $proposalIri = $this->findIriBy(Proposal::class,
            ['id' => TestFixtures::PROPOSAL_1['id']]);

        $client->request('POST', $proposalIri.'/collab', ['json' => [
            'collabData' => [
                'comment' => [
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
