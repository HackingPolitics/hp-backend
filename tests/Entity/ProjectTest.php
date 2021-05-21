<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Category;
use App\Entity\Council;
use App\Entity\FractionDetails;
use App\Entity\Partner;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProjectEntity
 */
class ProjectTest extends KernelTestCase
{
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

        /** @var $council Council */
        $council = $this->entityManager->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);

        $project = new Project();
        $project->setTitle('Testing Project');
        $project->setDescription('long description');
        $project->setTopic('short topic');
        $project->setImpact('short impact');
        $project->setCreatedBy($user);
        $project->setCouncil($council);

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

    public function testRelationsAccessible(): void
    {
        /* @var $project Project */
        $project = $this->getProjectRepository()
            ->find(TestFixtures::PROJECT['id']);

        self::assertCount(3, $project->getMemberships());
        self::assertInstanceOf(ProjectMembership::class, $project->getMemberships()[0]);

        self::assertInstanceOf(Council::class, $project->getCouncil());
        self::assertInstanceOf(User::class, $project->getCreatedBy());

        self::assertCount(2, $project->getFractionDetails());
        self::assertInstanceOf(FractionDetails::class, $project->getFractionDetails()[0]);

        self::assertCount(3, $project->getCategories());
        self::assertInstanceOf(Category::class, $project->getCategories()[0]);

        self::assertCount(2, $project->getPartners());
        self::assertInstanceOf(Partner::class, $project->getPartners()[0]);

        self::assertCount(1, $project->getProblems());
        self::assertInstanceOf(Problem::class, $project->getProblems()[0]);
    }
}
