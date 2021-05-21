<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Fraction;
use App\Entity\FractionDetails;
use App\Entity\FractionInterest;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FractionDetailsEntity
 */
class FractionDetailsTest extends KernelTestCase
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
        return $this->entityManager->getRepository(FractionDetails::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(2, $before);

        $project = $this->entityManager->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $fraction = $this->entityManager->getRepository(Fraction::class)
            ->find(TestFixtures::FRACTION_RED['id']);

        $details = new FractionDetails();
        $details->setContactName('Testing');
        $details->setPossibleSponsor(true);
        $details->setProject($project);
        $details->setFraction($fraction);
        $this->entityManager->persist($details);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(3, $after);

        /* @var $found FractionDetails */
        $found = $this->getRepository()->find(3);

        self::assertSame('Testing', $found->getContactName());
        self::assertTrue($found->isPossibleSponsor());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $details FractionDetails */
        $details = $this->getRepository()->find(1);

        self::assertInstanceOf(Project::class, $details->getProject());
        self::assertInstanceOf(Fraction::class, $details->getFraction());
        self::assertInstanceOf(User::class, $details->getTeamContact());
        self::assertInstanceOf(User::class, $details->getUpdatedBy());

        self::assertCount(2, $details->getInterests());
        self::assertInstanceOf(FractionInterest::class, $details->getInterests()[0]);
    }
}
