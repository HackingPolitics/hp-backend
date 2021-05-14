<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Faction;
use App\Entity\FactionDetails;
use App\Entity\FactionInterest;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FactionDetailsEntity
 */
class FactionDetailsTest extends KernelTestCase
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
        return $this->entityManager->getRepository(FactionDetails::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(2, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $faction = $this->entityManager->getRepository(Faction::class)
            ->find(TestFixtures::FACTION_RED['id']);

        $details = new FactionDetails();
        $details->setContactName('Testing');
        $details->setPossibleProponent(true);
        $details->setProject($project);
        $details->setFaction($faction);
        $this->entityManager->persist($details);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(3, $after);

        /* @var $found FactionDetails */
        $found = $this->getRepository()->find(3);

        self::assertSame('Testing', $found->getContactName());
        self::assertTrue($found->isPossibleProponent());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $details FactionDetails */
        $details = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $details->getProject());
        self::assertInstanceOf(Faction::class, $details->getFaction());
        self::assertInstanceOf(User::class, $details->getTeamContact());
        self::assertInstanceOf(User::class, $details->getUpdatedBy());

        self::assertCount(2, $details->getInterests());
        self::assertInstanceOf(FactionInterest::class, $details->getInterests()[0]);
    }
}
