<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Proposal;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ProposalEntity
 */
class ProposalTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Proposal::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);

        $proposal = new Proposal();
        $proposal->setTitle('new title');
        $proposal->setSponsor('Testing');
        $proposal->setProject($project);
        $this->entityManager->persist($proposal);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found Proposal */
        $found = $this->getRepository()->find(2);

        self::assertSame('Testing', $found->getSponsor());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $proposal Proposal */
        $proposal = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $proposal->getProject());
        self::assertInstanceOf(User::class, $proposal->getUpdatedBy());
    }
}
