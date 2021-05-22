<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\CounterArgument;
use App\Entity\Negation;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group CounterArgumentEntity
 */
class CounterArgumentTest extends KernelTestCase
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
        return $this->entityManager->getRepository(CounterArgument::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $counterArgument = new CounterArgument();
        $counterArgument->setDescription('new counterArgument');
        $counterArgument->setPriority(99);
        $counterArgument->setProject($project);
        $this->entityManager->persist($counterArgument);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found CounterArgument */
        $found = $this->getRepository()->find(2);

        self::assertSame('new counterArgument', $found->getDescription());
        self::assertSame(99, $found->getPriority());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $counterArgument CounterArgument */
        $counterArgument = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $counterArgument->getProject());
        self::assertInstanceOf(User::class, $counterArgument->getUpdatedBy());

        self::assertCount(1, $counterArgument->getNegations());
        self::assertInstanceOf(Negation::class, $counterArgument->getNegations()[0]);
    }
}
