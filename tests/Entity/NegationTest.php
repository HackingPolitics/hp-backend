<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\CounterArgument;
use App\Entity\Negation;
use App\Entity\UsedNegation;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group NegationEntity
 */
class NegationTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Negation::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(1, $before);

        $counterArgument = $this->entityManager->getRepository(CounterArgument::class)
            ->find(1);

        $negation = new Negation();
        $negation->setCounterArgument($counterArgument);
        $negation->setDescription('Testing');
        $this->entityManager->persist($negation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(2, $after);

        /* @var $found Negation */
        $found = $this->getRepository()->find(2);

        self::assertSame('Testing', $found->getDescription());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $negation Negation */
        $negation = $this->getRepository()->find(1);

        self::assertInstanceOf(CounterArgument::class, $negation->getCounterArgument());
        self::assertInstanceOf(User::class, $negation->getUpdatedBy());

        self::assertCount(1, $negation->getUsages());
        self::assertInstanceOf(UsedNegation::class, $negation->getUsages()[0]);
    }
}
