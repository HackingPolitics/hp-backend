<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Core\Bridge\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Message\AllProjectMembersLeftMessage;
use App\Message\NewMemberApplicationMessage;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Vrok\SymfonyAddons\PHPUnit\AuthenticatedClientTrait;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectMembershipApi
 */
class ProjectMembershipApiTest extends ApiTestCase
{
    use AuthenticatedClientTrait;
    use RefreshDatabaseTrait;

    private EntityManager $entityManager;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    protected function getOwner(): Project
    {
        return $this->getEntityManager()->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);
    }

    protected function getMember(): User
    {
        return $this->getEntityManager()->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
    }

    protected function getGuest(): User
    {
        return $this->getEntityManager()->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
    }

    protected function getProject(): Project
    {
        return $this->getEntityManager()->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
    }

    protected function getLockedProject(): Project
    {
        return $this->getEntityManager()->getRepository(Project::class)
            ->find(TestFixtures::LOCKED_PROJECT['id']);
    }

    protected function getDeletedProject()
    {
        return $this->getEntityManager()->getRepository(Project::class)
            ->find(TestFixtures::DELETED_PROJECT['id']);
    }

    /**
     * Test that no collection of memberships is available, not even for admins.
     */
    public function testCollectionNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('GET', '/project_memberships');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "GET /project_memberships": Method Not Allowed (Allow: POST)',
        ]);
    }

    public function testGetMembershipAsAdmin(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ]);

        $iri = $this->findIriBy(ProjectMembership::class,
            ['user' => $this->getMember(), 'project' => $this->getProject()]);

        $client->request('GET', $iri);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            '@id'        => $iri,
            'motivation' => 'writer motivation',
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'writer skills',
            'project'    => [
                '@id'   => '/projects/'.TestFixtures::PROJECT['id'],
                '@type' => 'Project',
                'id'    => TestFixtures::PROJECT['id'],
            ],
            'user'       => [
                '@id'   => '/users/'.TestFixtures::PROJECT_WRITER['id'],
                '@type' => 'User',
                'id'    => TestFixtures::PROJECT_WRITER['id'],
            ],
        ]);
    }

    /**
     * Anonymous users cannot get memberships.
     */
    public function testGetMembershipFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'user'    => TestFixtures::PROJECT_WRITER['id'],
            'project' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('GET', $iri);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code'    => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    /**
     * Normal users cannot get memberships, not even their own.
     */
    public function testGetMembershipFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $iri = $this->findIriBy(ProjectMembership::class, [
            'user'    => TestFixtures::PROJECT_WRITER['id'],
            'project' => TestFixtures::PROJECT['id'],
        ]);
        $client->request('GET', $iri);

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

    public function testCreate(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        // @todo the schema is broken, "The property applications is not defined and the definition does not allow additional properties" etc
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI,
            ],
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'skills with 20 characters',
            'user'       => [
                '@id' => $userIri,
            ],
        ]);

        // $data = $response->toArray();
        // @todo should not return the projects full details including other
        // memberships etc
    }

    public function testCreateWithoutRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: validate.general.notBlank',
        ]);
    }

    public function testCreateWithUnknownRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => 'SUPER_USER',
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: validate.general.invalidChoice',
        ]);
    }

    public function testCreateAsApplicationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testCreateWithoutUserFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'user: validate.general.notBlank',
        ]);
    }

    public function testCreateWithoutMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
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

    public function testCreateWithoutSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'user'       => $userIri,
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

    public function testCreateWithoutProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'motivation with 20 characters',
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
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
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_COORDINATOR,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'project: validate.projectMembership.duplicateMembership',
        ]);
    }

    public function testApplication(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::GUEST['email'],
        ]);

        // after createAuthenticatedClient, it boots the kernel which resets the DB state
        $guest = $this->getGuest();
        $guest->setValidated(true);
        $this->getEntityManager()->flush();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $response = $client->request('POST', '/project_memberships', ['json' => [
            'motivation' => 'motivation with 20 characters',
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI,
            ],
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'skills'     => 'skills with 20 characters',
            'user'       => [
                '@id' => $userIri,
            ],
        ]);

        $data = $response->toArray();
        self::assertArrayNotHasKey('createdBy', $data['project']);
        self::assertArrayNotHasKey('memberships', $data['project']);
        self::assertArrayNotHasKey('applications', $data['project']);

        // notification for the project coordinators should be triggered
        $messenger = static::getContainer()->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(NewMemberApplicationMessage::class,
            $messages[0]['message']);
    }

    public function testCreateForOtherUserFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testApplicationWithForbiddenRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::GUEST['email'],
        ]);

        // after createAuthenticatedClient, it boots the kernel which resets the DB state
        $guest = $this->getGuest();
        $guest->setValidated(true);
        $this->getEntityManager()->flush();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'other motivation with 20 characters',
            'skills'     => 'other skills with 20 characters',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testApplicationForLockedProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::GUEST['email'],
        ]);

        $guest = $this->getGuest();
        $guest->setValidated(true);
        $this->getEntityManager()->flush();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::LOCKED_PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation',
            'skills'     => 'other skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',

            // locked projects are hidden for normal users
            'hydra:description' => 'Item not found for "/projects/'.
                TestFixtures::LOCKED_PROJECT['id'].'".',
        ]);
    }

    public function testApplicationForDeletedProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::DELETED_PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_WRITER['id']]);

        $client->request('POST', '/project_memberships', ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'other motivation',
            'skills'     => 'other skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'Item not found for "/projects/'.
                TestFixtures::DELETED_PROJECT['id'].'".',
        ]);
    }

    public function testUpdateAsProcessManager(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_COORDINATOR['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_COORDINATOR['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_COORDINATOR,
            'motivation' => 'new motivation with 20 characters',
            'skills'     => 'new skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');
        self::assertMatchesResourceItemJsonSchema(ProjectMembership::class);

        self::assertJsonContains([
            'motivation' => 'new motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI,
            ],
            'role'       => ProjectMembership::ROLE_COORDINATOR,
            'skills'     => 'new skills with 20 characters',
            'user'       => [
                '@id' => $userIri,
            ],
        ]);
    }

    public function testUpdateAutoTrim(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user' => TestFixtures::PROJECT_COORDINATOR['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'motivation' => ' new motivation with 20 characters ',
            'skills'     => ' new skills with 20 characters ',
        ]]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'motivation' => 'new motivation with 20 characters',
            'skills'     => 'new skills with 20 characters',
        ]);
    }

    public function testAcceptApplication(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $ms = new ProjectMembership();
        $ms->setUser($user);
        $ms->setProject($project);
        $ms->setMotivation('my motivation');
        $ms->setRole(ProjectMembership::ROLE_APPLICANT);
        $ms->setSkills('my skills with enough characters');
        $em->persist($ms);
        $em->flush();
        $em->clear();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::GUEST['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::GUEST['id'],
        ]);
        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'project'    => [
                '@id' => $projectIRI,
            ],
            'role'       => ProjectMembership::ROLE_WRITER,
            'user'       => [
                '@id' => $userIri,
            ],
        ]);
    }

    public function testUpdateAsProjectMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'new motivation with 20 characters',
            'skills'     => 'new skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonContains([
            'motivation' => 'new motivation with 20 characters',
            'project'    => [
                '@id' => $projectIRI,
            ],
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'new skills with 20 characters',
            'user'       => [
                '@id' => $userIri,
            ],
        ]);

        // @todo no creator returned
    }

    public function testUpdateFailsUnauthenticated(): void
    {
        $client = static::createClient();

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'new skills',
            'user'       => $userIri,
        ]]);

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type',
            'application/json');

        self::assertJsonContains([
            'code' => 401,
            'message' => 'JWT Token not found',
        ]);
    }

    public function testUpdateFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $projectIRI = $this->findIriBy(Project::class,
            ['id' => TestFixtures::PROJECT['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_OBSERVER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_OBSERVER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'project'    => $projectIRI,
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'new skills',
            'user'       => $userIri,
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

    public function testUpdateOwnRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_COORDINATOR,
            'motivation' => 'old motivation with 20 characters',
            'skills'     => 'old skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testDowngradeToApplicantFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_APPLICANT,
            'motivation' => 'old motivation with 20 characters',
            'skills'     => 'old skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testDowngradeOfOtherCoordinatorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var User $planner */
        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $planner->getProjectMemberships()[0]->setRole(ProjectMembership::ROLE_COORDINATOR);
        $em->flush();
        $em->clear();

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'old motivation with 20 characters',
            'skills'     => 'old skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testDowngradeSelfAsOnlyCoordinatorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'old motivation with 20 characters',
            'skills'     => 'old skills with 20 characters',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.cannotDowngradeLastCoordinator',
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
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'new skills and project',
            'project'    => $newProjectIRI,
        ]]);

        self::assertResponseIsSuccessful();

        // skills got updated but project didn't
        self::assertJsonContains([
            'skills'  => 'new skills and project',
            'project' => [
                '@id' => $projectIRI,
            ],
        ]);
    }

    public function testUpdateUserFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $memberIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_WRITER['id']]);
        $userIri = $this->findIriBy(User::class,
            ['id' => TestFixtures::PROJECT_OBSERVER['id']]);
        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'   => ProjectMembership::ROLE_WRITER,
            'skills' => 'new skills and user',
            'user'   => $userIri,
        ]]);

        self::assertResponseIsSuccessful();

        // skills got updated but user didn't
        self::assertJsonContains([
            'skills' => 'new skills and user',
            'user'   => [
                '@id' => $memberIri,
            ],
        ]);
    }

    public function testUpdateForLockedProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::LOCKED_PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'   => ProjectMembership::ROLE_WRITER,
            'skills' => 'new skills',
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

    public function testUpdateForDeletedProjectFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        // a deleted project should not have memberships, just to make sure...
        $em = static::getContainer()->get('doctrine')->getManager();
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->markDeleted();
        $em->flush();
        $em->clear();

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::LOCKED_PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'new skills',
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

    public function testUpdateSelfWithEmptyMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => '',
            'skills'     => 'writing 20 characters long texts',
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

    public function testUpdateSelfWithEmptySkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => '',
            'motivation' => 'writing 20 characters motivation is cool',
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

    public function testUpdateOtherMemberSkillsFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'skills'     => 'this will not work',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testUpdateOtherMemberMotivationFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => ProjectMembership::ROLE_WRITER,
            'motivation' => 'this will not work',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'validate.projectMembership.invalidRequest',
        ]);
    }

    public function testUpdateWithUnknownRoleFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);

        $membershipIRI = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $client->request('PUT', $membershipIRI, ['json' => [
            'role'       => 'SUPER_USER',
            'skills'     => 'my super good super-hero skills',
            'motivation' => 'writing 20 characters motivation is cool',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json; charset=utf-8');

        self::assertJsonContains([
            '@context'          => '/contexts/ConstraintViolationList',
            '@type'             => 'ConstraintViolationList',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'role: validate.general.invalidChoice',
        ]);
    }

    public function testDeleteAsProcessOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);
        $em = static::getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertInstanceOf(ProjectMembership::class, $membershipExists);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertNull($notExisting);
    }

    public function testDeleteAsProjectOwner(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);
        $em = static::getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertInstanceOf(ProjectMembership::class, $membershipExists);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertNull($notExisting);
    }

    public function testDeleteAsProjectMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();
        $member = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $membershipExists = $em
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertInstanceOf(ProjectMembership::class, $membershipExists);

        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $member, 'project' => $project]);
        self::assertNull($notExisting);
    }

    public function testDeleteAsApplicant(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_WRITER['email'],
        ]);

        $user = $this->getMember();
        foreach ($user->getProjectMemberships() as $membership) {
            $membership->setRole(ProjectMembership::ROLE_APPLICANT);
        }
        $this->getEntityManager()->flush();

        $project = $this->getEntityManager()->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
        ]);
        $client->request('DELETE', $iri);

        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy(['user' => $user, 'project' => $project]);
        self::assertNull($notExisting);
    }

    public function testDeleteFailsUnauthenticated(): void
    {
        $client = static::createClient();
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
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

    public function testDeleteFailsWithoutPrivilege(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_OBSERVER['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
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

    public function testDeleteFailsWithLockedProject(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setLocked(true);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
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

    public function testDeleteSelfAsOnlyCoordinatorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
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

    public function testDeleteOnlyCoordinatorAsProcessManagerFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROCESS_MANAGER['email'],
        ]);
        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
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

    public function testDeleteOtherCoordinatorFails(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var User $planner */
        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $planner->getProjectMemberships()[0]->setRole(ProjectMembership::ROLE_COORDINATOR);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_WRITER['id'],
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

    public function testDeleteSelfWithOtherCoordinator(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        /** @var User $planner */
        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $planner->getProjectMemberships()[0]->setRole(ProjectMembership::ROLE_COORDINATOR);
        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
        ]);

        $client->request('DELETE', $iri);
        static::assertResponseStatusCodeSame(204);

        $notExisting = static::$container->get('doctrine')
            ->getRepository(ProjectMembership::class)
            ->findOneBy([
                'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
                'project' => TestFixtures::PROJECT['id'],
            ]);
        self::assertNull($notExisting);
    }

    public function testDeleteSelfAsLastMember(): void
    {
        $client = static::createAuthenticatedClient([
            'email' => TestFixtures::PROJECT_COORDINATOR['email'],
        ]);

        $em = static::getContainer()->get('doctrine')->getManager();

        $planner = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);
        $em->remove($planner->getProjectMemberships()[0]);
        $observer = $em->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id']);
        $em->remove($observer->getProjectMemberships()[0]);

        $em->flush();
        $em->clear();

        $iri = $this->findIriBy(ProjectMembership::class, [
            'project' => TestFixtures::PROJECT['id'],
            'user'    => TestFixtures::PROJECT_COORDINATOR['id'],
        ]);

        $client->request('DELETE', $iri);
        static::assertResponseStatusCodeSame(204);

        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        self::assertTrue($project->isLocked());
        self::assertCount(0, $project->getMemberships());

        // notification for the process managers should be triggered
        $messenger = static::getContainer()->get('messenger.default_bus');
        $messages = $messenger->getDispatchedMessages();
        self::assertCount(1, $messages);
        self::assertInstanceOf(AllProjectMembersLeftMessage::class,
            $messages[0]['message']);
    }

    /**
     * Test that the DELETE operation for the whole collection is not available.
     */
    public function testCollectionDeleteNotAvailable(): void
    {
        static::createAuthenticatedClient([
            'email' => TestFixtures::ADMIN['email'],
        ])->request('DELETE', '/project_memberships');

        self::assertResponseStatusCodeSame(405);
        self::assertResponseHeaderSame('content-type',
            'application/ld+json');

        self::assertJsonContains([
            '@context'          => '/contexts/Error',
            '@type'             => 'hydra:Error',
            'hydra:title'       => 'An error occurred',
            'hydra:description' => 'No route found for "DELETE /project_memberships": Method Not Allowed (Allow: POST)',
        ]);
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return static::$container->get('doctrine')
            ->getManagerForClass(ProjectMembership::class);
    }
}
