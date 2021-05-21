<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\FractionDetails;
use App\Entity\FractionInterest;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FractionInterestEntity
 */
class FractionInterestTest extends KernelTestCase
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
        return $this->entityManager->getRepository(FractionInterest::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(2, $before);

        $details = $this->entityManager->getRepository(FractionDetails::class)
            ->find(1);

        $interest = new FractionInterest();
        $interest->setFractionDetails($details);
        $interest->setDescription('Testing');
        $this->entityManager->persist($interest);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(3, $after);

        /* @var $found FractionInterest */
        $found = $this->getRepository()->find(3);

        self::assertSame('Testing', $found->getDescription());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $interest FractionInterest */
        $interest = $this->getRepository()->find(1);

        self::assertInstanceOf(FractionDetails::class, $interest->getFractionDetails());
        self::assertInstanceOf(User::class, $interest->getUpdatedBy());

        // @todo motion usages
//        self::assertCount(1, $interest->getInterest());
//        self::assertInstanceOf(FractionInterestInterest::class, $interest->getInterest()[0]);
    }
}
