<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Argument;
use App\Entity\Project;
use App\Entity\UsedArgument;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ArgumentEntity
 */
class ArgumentTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Argument::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $argument = new Argument();
        $argument->setDescription('new argument');
        $argument->setPriority(99);
        $argument->setProject($project);
        $this->entityManager->persist($argument);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found Argument */
        $found = $this->getRepository()->find(2);

        self::assertSame('new argument', $found->getDescription());
        self::assertSame(99, $found->getPriority());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $argument Argument */
        $argument = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $argument->getProject());
        self::assertInstanceOf(User::class, $argument->getUpdatedBy());

        self::assertCount(1, $argument->getUsages());
        self::assertInstanceOf(UsedArgument::class, $argument->getUsages()[0]);

    }
}
