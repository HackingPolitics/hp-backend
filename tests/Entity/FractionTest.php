<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Fraction;
use App\Entity\FractionDetails;
use App\Entity\Council;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FractionEntity
 */
class FractionTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Fraction::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(4, $before);

        $council = $this->entityManager->getRepository(Council::class)
            ->find(TestFixtures::COUNCIL['id']);

        $fraction = new Fraction();
        $fraction->setName('Testing');
        $fraction->setMemberCount(13);
        $fraction->setCouncil($council);
        $this->entityManager->persist($fraction);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(5, $after);

        /* @var $found Fraction */
        $found = $this->getRepository()->find(5);

        self::assertSame('Testing', $found->getName());
        self::assertSame(13, $found->getMemberCount());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $fraction Fraction */
        $fraction = $this->getRepository()->find(1);

        self::assertInstanceOf(Council::class, $fraction->getCouncil());
        self::assertInstanceOf(User::class, $fraction->getUpdatedBy());

        self::assertCount(1, $fraction->getDetails());
        self::assertInstanceOf(FractionDetails::class, $fraction->getDetails()[0]);
    }
}
