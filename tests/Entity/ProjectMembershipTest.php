<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectMembershipEntity
 */
class ProjectMembershipTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * Tests the defaults for new roles.
     */
    public function testCreateAndReadMember(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROCESS_MANAGER['id']);

        /** @var $project Project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $before = $this->getMembershipRepository()->findBy(['user' => $user]);
        self::assertCount(0, $before);

        $membership = new ProjectMembership();
        $membership->setRole(ProjectMembership::ROLE_WRITER);
        $membership->setMotivation('po motivation');
        $membership->setSkills('po skills');
        $project->addMembership($membership);
        $user->addProjectMembership($membership);

        $this->entityManager->persist($membership);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $byUser = $this->getMembershipRepository()->findBy(['user' => $user]);
        self::assertCount(1, $byUser);

        self::assertSame('po motivation', $byUser[0]->getMotivation());
        self::assertSame('po skills', $byUser[0]->getSkills());

        $byProject = $this->getMembershipRepository()->findBy([
            'project' => $project,
        ]);
        self::assertCount(4, $byProject);
    }

    protected function getMembershipRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(ProjectMembership::class);
    }

    /**
     * Tests that no duplicate memberships can be assigned.
     */
    public function testMembershipUnique(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);

        /** @var $project Project */
        $project = $this->entityManager->getRepository(Project::class)
            ->find(2);

        $membership = new ProjectMembership();
        $membership->setRole(ProjectMembership::ROLE_WRITER);
        $membership->setMotivation('member motivation');
        $membership->setSkills('member skills');
        $project->addMembership($membership);
        $user->addProjectMembership($membership);

        $this->entityManager->persist($membership);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * Tests that memberships are deleted when the user is deleted.
     */
    public function testDeletingUserDeletesMemberships(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);

        $before = $this->getMembershipRepository()->findBy([
            'user' => $user,
        ]);
        self::assertCount(2, $before);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $after = $this->getMembershipRepository()->findBy([
            'user' => $user,
        ]);

        self::assertCount(0, $after);
    }

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }
}
