<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FractionInterest;
use App\Entity\Proposal;
use App\Entity\UsedFractionInterest;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UsedFractionInterestEntity
 */
class UsedFractionInterestTest extends KernelTestCase
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
        return $this->entityManager->getRepository(UsedFractionInterest::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);
        $this->entityManager->remove($before[0]);
        $this->entityManager->flush();

        $fractionInterest = $this->entityManager->getRepository(FractionInterest::class)
            ->find(1);
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find(1);
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $usedFractionInterest = new UsedFractionInterest();
        $usedFractionInterest->setFractionInterest($fractionInterest);
        $usedFractionInterest->setProposal($proposal);
        $usedFractionInterest->setCreatedBy($user);
        $this->entityManager->persist($usedFractionInterest);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(1, $after);

        /* @var $found UsedFractionInterest */
        $found = $this->getRepository()->find(2);

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $usedFractionInterest UsedFractionInterest */
        $usedFractionInterest = $this->getRepository()->find(1);

        self::assertInstanceOf(FractionInterest::class, $usedFractionInterest->getFractionInterest());
        self::assertInstanceOf(Proposal::class, $usedFractionInterest->getProposal());
        self::assertInstanceOf(User::class, $usedFractionInterest->getCreatedBy());
    }
}
