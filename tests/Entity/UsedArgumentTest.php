<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Argument;
use App\Entity\CounterArgument;
use App\Entity\Proposal;
use App\Entity\UsedArgument;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UsedArgumentEntity
 */
class UsedArgumentTest extends KernelTestCase
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
        return $this->entityManager->getRepository(UsedArgument::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);
        $this->entityManager->remove($before[0]);
        $this->entityManager->flush();

        $argument = $this->entityManager->getRepository(Argument::class)
            ->find(1);
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find(1);
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $usedArgument = new UsedArgument();
        $usedArgument->setArgument($argument);
        $usedArgument->setProposal($proposal);
        $usedArgument->setCreatedBy($user);
        $this->entityManager->persist($usedArgument);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(1, $after);

        /* @var $found UsedArgument */
        $found = $this->getRepository()->find(2);

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $usedArgument UsedArgument */
        $usedArgument = $this->getRepository()->find(1);

        self::assertInstanceOf(Argument::class, $usedArgument->getArgument());
        self::assertInstanceOf(Proposal::class, $usedArgument->getProposal());
        self::assertInstanceOf(User::class, $usedArgument->getCreatedBy());
    }
}
