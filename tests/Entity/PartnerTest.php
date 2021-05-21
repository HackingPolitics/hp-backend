<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group PartnerEntity
 */
class PartnerTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Partner::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(2, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $partner = new Partner();
        $partner->setName('new partner');
        $partner->setContactName('Testing');
        $partner->setProject($project);
        $this->entityManager->persist($partner);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(3, $after);

        /* @var $found Partner */
        $found = $this->getRepository()->find(3);

        self::assertSame('Testing', $found->getContactName());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $partner Partner */
        $partner = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $partner->getProject());
        self::assertInstanceOf(User::class, $partner->getTeamContact());
        self::assertInstanceOf(User::class, $partner->getUpdatedBy());
    }
}
