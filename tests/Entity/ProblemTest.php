<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Fraction;
use App\Entity\Problem;
use App\Entity\FractionInterest;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProblemEntity
 */
class ProblemTest extends KernelTestCase
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

    protected function getRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Problem::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $problem = new Problem();
        $problem->setDescription("new problem");
        $problem->setPriority(99);
        $problem->setProject($project);
        $this->entityManager->persist($problem);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found Problem */
        $found = $this->getRepository()->find(2);

        self::assertSame('new problem', $found->getDescription());
        self::assertSame(99, $found->getPriority());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $problem Problem */
        $problem = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $problem->getProject());
        self::assertInstanceOf(User::class, $problem->getUpdatedBy());
    }
}
