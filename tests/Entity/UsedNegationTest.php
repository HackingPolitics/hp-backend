<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Negation;
use App\Entity\Proposal;
use App\Entity\UsedNegation;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UsedNegationEntity
 */
class UsedNegationTest extends KernelTestCase
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
        return $this->entityManager->getRepository(UsedNegation::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);
        $this->entityManager->remove($before[0]);
        $this->entityManager->flush();

        $negation = $this->entityManager->getRepository(Negation::class)
            ->find(1);
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find(1);
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $usedNegation = new UsedNegation();
        $usedNegation->setNegation($negation);
        $usedNegation->setProposal($proposal);
        $usedNegation->setCreatedBy($user);
        $this->entityManager->persist($usedNegation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(1, $after);

        /* @var $found UsedNegation */
        $found = $this->getRepository()->find(2);

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $usedNegation UsedNegation */
        $usedNegation = $this->getRepository()->find(1);

        self::assertInstanceOf(Negation::class, $usedNegation->getNegation());
        self::assertInstanceOf(Proposal::class, $usedNegation->getProposal());
        self::assertInstanceOf(User::class, $usedNegation->getCreatedBy());
    }
}
