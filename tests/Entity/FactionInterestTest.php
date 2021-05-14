<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FactionDetails;
use App\Entity\FactionInterest;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionInterestEntity
 */
class FactionInterestTest extends KernelTestCase
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
        return $this->entityManager->getRepository(FactionInterest::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(2, $before);

        $details = $this->entityManager->getRepository(FactionDetails::class)
            ->find(1);

        $interest = new FactionInterest();
        $interest->setFactionDetails($details);
        $interest->setDescription('Testing');
        $this->entityManager->persist($interest);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(3, $after);

        /* @var $found FactionInterest */
        $found = $this->getRepository()->find(3);

        self::assertSame('Testing', $found->getDescription());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $interest FactionInterest */
        $interest = $this->getRepository()->find(1);

        self::assertInstanceOf(FactionDetails::class, $interest->getFactionDetails());
        self::assertInstanceOf(User::class, $interest->getUpdatedBy());

        // @todo motion usages
//        self::assertCount(1, $interest->getInterest());
//        self::assertInstanceOf(FactionInterestInterest::class, $interest->getInterest()[0]);
    }
}
