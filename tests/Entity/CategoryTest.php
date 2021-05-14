<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\InitialFixtures;
use App\Entity\Category;
use App\Entity\Project;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group CategoryEntity
 */
class CategoryTest extends KernelTestCase
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
        return $this->entityManager->getRepository(Category::class);
    }

    public function testCreateAndRead(): void
    {
        $before = $this->getRepository()->findAll();
        self::assertCount(count(InitialFixtures::CATEGORIES), $before);

        $category = new Category();
        $category->setName('Testing');
        $this->entityManager->persist($category);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $after = $this->getRepository()->findAll();
        self::assertCount(count(InitialFixtures::CATEGORIES) + 1, $after);

        /* @var $found Category */
        $found = $this->getRepository()
            ->findOneBy(['name' => 'Testing']);

        self::assertSame('testing', $found->getSlug());
    }

    public function testSlugIsUpdatedAutomatically(): void
    {
        /* @var $category Category */
        $category = $this->getRepository()->find(1);

        self::assertSame('Bildung und Soziales', $category->getName());
        self::assertSame('bildung-und-soziales', $category->getSlug());

        $category->setName('A better_name, really!');
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $after Category */
        $after = $this->getRepository()->find(1);
        self::assertSame('a-better-name-really', $after->getSlug());
    }

    public function testRelationsAccessible(): void
    {
        /* @var $category Category */
        $category = $this->getRepository()
            ->find(1);

        self::assertCount(1, $category->getProjects());
        self::assertInstanceOf(Project::class, $category->getProjects()[0]);
    }
}
