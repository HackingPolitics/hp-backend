<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Problem;
use App\Entity\Proposal;
use App\Entity\UsedProblem;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UsedProblemEntity
 */
class UsedProblemTest extends KernelTestCase
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
        return $this->entityManager->getRepository(UsedProblem::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);
        $this->entityManager->remove($before[0]);
        $this->entityManager->flush();

        $problem = $this->entityManager->getRepository(Problem::class)
            ->find(1);
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find(1);
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $usedProblem = new UsedProblem();
        $usedProblem->setProblem($problem);
        $usedProblem->setProposal($proposal);
        $usedProblem->setCreatedBy($user);
        $this->entityManager->persist($usedProblem);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(1, $after);

        /* @var $found UsedProblem */
        $found = $this->getRepository()->find(2);

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $usedProblem UsedProblem */
        $usedProblem = $this->getRepository()->find(1);

        self::assertInstanceOf(Problem::class, $usedProblem->getProblem());
        self::assertInstanceOf(Proposal::class, $usedProblem->getProposal());
        self::assertInstanceOf(User::class, $usedProblem->getCreatedBy());
    }
}
