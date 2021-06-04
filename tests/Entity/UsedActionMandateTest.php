<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ActionMandate;
use App\Entity\Proposal;
use App\Entity\UsedActionMandate;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UsedActionMandateEntity
 */
class UsedActionMandateTest extends KernelTestCase
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
        return $this->entityManager->getRepository(UsedActionMandate::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);
        $this->entityManager->remove($before[0]);
        $this->entityManager->flush();

        $actionMandate = $this->entityManager->getRepository(ActionMandate::class)
            ->find(1);
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find(1);
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $usedActionMandate = new UsedActionMandate();
        $usedActionMandate->setActionMandate($actionMandate);
        $usedActionMandate->setProposal($proposal);
        $usedActionMandate->setCreatedBy($user);
        $this->entityManager->persist($usedActionMandate);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(1, $after);

        /* @var $found UsedActionMandate */
        $found = $this->getRepository()->find(2);

        self::assertSame($user->getId(), $found->getCreatedBy()->getId());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $usedActionMandate UsedActionMandate */
        $usedActionMandate = $this->getRepository()->find(1);

        self::assertInstanceOf(ActionMandate::class, $usedActionMandate->getActionMandate());
        self::assertInstanceOf(Proposal::class, $usedActionMandate->getProposal());
        self::assertInstanceOf(User::class, $usedActionMandate->getCreatedBy());
    }
}
