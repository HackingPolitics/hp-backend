<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Council;
use App\Entity\FederalState;
use App\Entity\Fraction;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ParliamentEntity
 */
class CouncilTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Council::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $parliament = new Council();
        $parliament->setTitle('Testing');
        $this->entityManager->persist($parliament);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found Council */
        $found = $this->getRepository()->find(2);

        self::assertSame('Testing', $found->getTitle());
        self::assertSame('testing', $found->getSlug());
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $parliament Council */
        $parliament = $this->getRepository()->find(1);

        self::assertSame('Stadtrat Stuttgart', $parliament->getTitle());
        self::assertSame('stadtrat-stuttgart', $parliament->getSlug());

        $parliament->setTitle('A better_name, really!');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $after Council */
        $after = $this->getRepository()->find(1);
        self::assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $parliament Council */
        $parliament = $this->getRepository()->find(1);

        self::assertInstanceOf(FederalState::class, $parliament->getFederalState());
        self::assertInstanceOf(User::class, $parliament->getUpdatedBy());

        self::assertCount(4, $parliament->getFractions());
        self::assertInstanceOf(Fraction::class, $parliament->getFractions()[0]);
        self::assertCount(3, $parliament->getProjects());
        self::assertInstanceOf(Project::class, $parliament->getProjects()[0]);
    }
}
