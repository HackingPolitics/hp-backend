<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\InitialFixtures;
use App\Entity\FederalState;
use App\Entity\Parliament;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group FederalStateEntity
 */
class FederalStateTest extends KernelTestCase
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
        return $this->entityManager->getRepository(FederalState::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(count(InitialFixtures::FEDERAL_STATES), $before);

        $federalState = new FederalState();
        $federalState->setName('Testing');
        $this->entityManager->persist($federalState);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(count(InitialFixtures::FEDERAL_STATES) + 1, $after);

        /* @var $found FederalState */
        $found = $this->getRepository()
            ->findOneBy(['name' => 'Testing']);

        self::assertSame('testing', $found->getSlug());
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $federalState FederalState */
        $federalState = $this->getRepository()->find(1);

        self::assertSame('Baden-Württemberg', $federalState->getName());
        self::assertSame('baden-wurttemberg', $federalState->getSlug());

        $federalState->setName('A better_name, really!');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $after FederalState */
        $after = $this->getRepository()->find(1);
        self::assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $federalState FederalState */
        $federalState = $this->getRepository()
            ->findOneBy([
                'name' => 'Baden-Württemberg',
            ]);

        self::assertCount(1, $federalState->getParliaments());
        self::assertInstanceOf(Parliament::class, $federalState->getParliaments()[0]);
    }
}
