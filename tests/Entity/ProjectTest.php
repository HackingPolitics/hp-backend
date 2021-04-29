<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectEntity
 */
class ProjectTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function getProjectRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Project::class);
    }

    /**
     * Tests the defaults for new processes.
     */
    public function testCreateAndReadProject(): void
    {
        $before = $this->getProjectRepository()
            ->findAll();
        self::assertCount(3, $before);

        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_WRITER['id']);

        $project = new Project();
        $project->setTitle('Testing Project');
        $project->setDescription('long description');
        $project->setTopic('short topic');
        $project->setCreatedBy($user);
        $project->setImpact('short impact');

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getProjectRepository()->findAll();
        self::assertCount(4, $after);

        /* @var $found Project */
        $found = $this->getProjectRepository()
            ->findOneBy(['title' => 'Testing Project']);

        self::assertSame('testing-project', $found->getSlug());
        self::assertSame('long description', $found->getDescription());
        self::assertSame('short impact', $found->getImpact());
        self::assertSame('short topic', $found->getTopic());

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());

        // timestampable listener works
        self::assertInstanceOf(\DateTimeImmutable::class,
            $found->getCreatedAt());

        self::assertSame(Project::STATE_PRIVATE, $found->getState());

        self::assertFalse($found->isLocked());
        self::assertCount(0, $found->getMemberships());

        // ID 1-3 is created by the fixtures
        self::assertSame(4, $found->getId());
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $project Project */
        $project = $this->getProjectRepository()->find(TestFixtures::PROJECT['id']);

        self::assertSame('Car-free Dresden', $project->getTitle());
        self::assertSame('car-free-dresden', $project->getSlug());

        $project->setTitle('A better_name, really!');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $after Project */
        $after = $this->getProjectRepository()->find(TestFixtures::PROJECT['id']);
        self::assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible()
    {
        /* @var $project Project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        self::assertCount(3, $project->getMemberships());
        self::assertInstanceOf(ProjectMembership::class, $project->getMemberships()[0]);

        self::assertInstanceOf(User::class, $project->getCreatedBy());
    }
}