<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Faction;
use App\Entity\FactionDetails;
use App\Entity\Parliament;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionEntity
 */
class FactionTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Faction::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(4, $before);

        $parliament = $this->entityManager->getRepository(Parliament::class)
            ->find(TestFixtures::PARLIAMENT['id']);

        $faction = new Faction();
        $faction->setName('Testing');
        $faction->setMemberCount(13);
        $faction->setParliament($parliament);
        $this->entityManager->persist($faction);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(5, $after);

        /* @var $found Faction */
        $found = $this->getRepository()->find(5);

        self::assertSame('Testing', $found->getName());
        self::assertSame(13, $found->getMemberCount());
    }

    public function testRelationsAccessible()
    {
        /* @var $faction Faction */
        $faction = $this->getRepository()->find(1);

        self::assertInstanceOf(Parliament::class, $faction->getParliament());
        self::assertInstanceOf(User::class, $faction->getUpdatedBy());

        self::assertCount(1, $faction->getDetails());
        self::assertInstanceOf(FactionDetails::class, $faction->getDetails()[0]);
    }
}
