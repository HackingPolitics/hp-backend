<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Entity\Validation;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group UserEntity
 */
class UserTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private const invalidNames = [
        '123', // digits
        'TEst1', // digit
        'Test@de', // @ not allowed
        'Test,de', // , not allowed
        'Test?de', // ? not allowed
        'Test!de', // ! not allowed
        'Test"de', // " not allowed
        'Test§de', // § not allowed
        'Test$de', // $ not allowed
        'Test%de', // % not allowed
        'Test&de', // & not allowed
        "Test\de", // \ not allowed
        'Test/de', // / not allowed
        'Test(de', // ( not allowed
        'Test)de', // ) not allowed
        'Test<de', // < not allowed
        'Test>de', // > not allowed
        'Test[de', // [ not allowed
        'Test]de', // ] not allowed
        'Test{de', // { not allowed
        'Test}de', // } not allowed
        'Test#de', // # not allowed
        'Test:de', // : not allowed
        'Test;de', // ; not allowed
        'Test=de', // = not allowed
        'Test+de', // + not allowed
        'Test~de', // ~ not allowed
    ];

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

    protected function getUserRepository(): UserRepository
    {
        return $this->entityManager->getRepository(User::class);
    }

    /**
     * Tests the repository functions, checking for soft-deleted users.
     */
    public function testFindUsers(): void
    {
        /* @var $found User[] */
        $all = $this->getUserRepository()
            ->findAll();

        self::assertCount(7, $all);

        $adminIdentity = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::ADMIN['id']);
        self::assertInstanceOf(User::class, $adminIdentity);

        $deletedIdentity = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::DELETED_USER['id']);
        self::assertNull($deletedIdentity);

        $adminCriteria = $this->getUserRepository()
            ->findOneNonDeletedBy(['email' => TestFixtures::ADMIN['email']]);
        self::assertInstanceOf(User::class, $adminCriteria);

        // findOneBy returns the deleted record, findOneNonDeletedBy does not
        $deleted = $this->getUserRepository()
            ->findOneBy(['email' => TestFixtures::DELETED_USER['email']]);
        self::assertInstanceOf(User::class, $deleted);
        $deletedCriteria = $this->getUserRepository()
            ->findOneNonDeletedBy(['email' => TestFixtures::DELETED_USER['email']]);
        self::assertNull($deletedCriteria);

        // finds only the undeleted users
        $nonDeleted = $this->getUserRepository()
            ->findNonDeletedBy(['active' => true]);
        self::assertCount(6, $nonDeleted);
        self::assertSame(1, $nonDeleted[0]->getId());
    }

    /**
     * Tests the defaults for new users.
     */
    public function testCreateAndReadUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.net');
        $user->setUsername('tester');
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $found User */
        $found = $this->getUserRepository()
            ->findOneBy(['email' => 'test@example.net']);

        self::assertSame('tester', $found->getUsername());
        self::assertSame('no-secret', $found->getPassword());
        self::assertContains('ROLE_ADMIN', $found->getRoles());

        // added by default
        self::assertContains('ROLE_USER', $found->getRoles());

        self::assertTrue($user->isActive());
        self::assertFalse($user->isValidated());
        self::assertCount(0, $user->getObjectRoles());
        self::assertCount(0, $user->getValidations());
        self::assertNull($user->getDeletedAt());
        self::assertFalse($user->isDeleted());
        self::assertSame('', $user->getFirstName());
        self::assertSame('', $user->getLastName());

        // timestampable listener works
        self::assertInstanceOf(DateTimeImmutable::class,
            $user->getCreatedAt());

        // ID 1 - 7 are created by the fixtures
        self::assertSame(8, $user->getId());
    }

    public function testUpdateUser(): void
    {
        $user = $this->getUserRepository()->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertSame(TestFixtures::PROJECT_WRITER['username'], $user->getUsername());

        $user->setValidated(true);
        $user->setActive(false);
        $user->setFirstName('Peter');
        $user->setLastName('Pan');
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setEmail('new@zukunftsstadt.de');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $updated = $this->getUserRepository()->find(TestFixtures::PROJECT_WRITER['id']);
        self::assertSame('new@zukunftsstadt.de', $updated->getEmail());
        self::assertTrue($updated->isValidated());
        self::assertFalse($updated->isActive());
        self::assertSame(
            [User::ROLE_ADMIN, User::ROLE_USER],
            $updated->getRoles()
        );
        self::assertSame('Peter', $updated->getFirstName());
        self::assertSame('Pan', $updated->getLastName());
    }

    /**
     * Tests marking a User as deleted.
     */
    public function testSoftdeleteUser(): void
    {
        /** @var User $user */
        $user = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::PROJECT_WRITER['id']);

        self::assertNull($user->getDeletedAt());
        $user->markDeleted();

        $this->entityManager->flush();
        $this->entityManager->clear();

        /** @var User $deleted */
        $deleted = $this->getUserRepository()
            ->find(TestFixtures::PROJECT_WRITER['id']);

        self::assertInstanceOf(DateTimeImmutable::class,
            $deleted->getDeletedAt());
        self::assertTrue($deleted->isDeleted());
        self::assertSame('', $deleted->getFirstName());
        self::assertSame('', $deleted->getLastName());
        self::assertSame('', $deleted->getPassword());
        self::assertSame('deleted_'.TestFixtures::PROJECT_WRITER['id'],
            $deleted->getUsername());
        self::assertSame('deleted_'.TestFixtures::PROJECT_WRITER['id'].'@hpo.user',
            $deleted->getEmail());

        $notFound = $this->getUserRepository()
            ->findNonDeleted(TestFixtures::PROJECT_WRITER['id']);
        self::assertNull($notFound);
    }

    /**
     * Tests that no duplicate emails can be created.
     */
    public function testEmailIsUnique(): void
    {
        $user = new User();
        $user->setUsername('tester');
        $user->setEmail(TestFixtures::ADMIN['email']);
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * Tests that no duplicate usernames can be created.
     */
    public function testUsernameIsUnique(): void
    {
        $user = new User();
        $user->setUsername(TestFixtures::ADMIN['username']);
        $user->setEmail('test@zukunftsstadt.de');
        $user->setPassword('no-secret');
        $user->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($user);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testRelationsAccessible(): void
    {
        /* @var $user User */
        $user = $this->getUserRepository()
            ->find(TestFixtures::PROJECT_COORDINATOR['id']);

        self::assertCount(1, $user->getValidations());
        self::assertInstanceOf(Validation::class, $user->getValidations()[0]);

        self::assertCount(2, $user->getProjectMemberships());
        self::assertInstanceOf(ProjectMembership::class, $user->getProjectMemberships()[0]);

        self::assertCount(2, $user->getCreatedProjects());
        self::assertInstanceOf(Project::class, $user->getCreatedProjects()[0]);
    }

    public function testEmailRestrictions(): void
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = static::getContainer()->get(ValidatorInterface::class);

        $user->setEmail('deleted_12@hpo.user');
        $failing = $validator->validate($user);
        self::assertSame('Email is not valid.',
                    $failing->offsetGet(0)->getMessage());
    }

    public function testUsernameRestrictions(): void
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = static::getContainer()->get(ValidatorInterface::class);

        $invalidUsernames = [
            '123', // no letters
            'a123', // only one letter
            '0test', // not starting with a letter
            'Asd@de', // @ not allowed
            'Test,de', // , not allowed
            'Test-dé', // only a-ZA-Z letters
            'Te as', // no spaces
            'deleted_12', // reserved for deleted users
        ];

        foreach ($invalidUsernames as $invalidName) {
            $user->setUsername($invalidName);
            $failing = $validator->validate($user);
            self::assertSame('validate.user.username.notValid',
                $failing->offsetGet(0)->getMessage(), sprintf(
                    '"%s" should be invalid but is is not', $invalidName));
        }

        $user->setUsername('T-est.DE_de2');
        $valid = $validator->validate($user);
        self::assertCount(0, $valid);
    }

    public function testFirstNameRestrictions(): void
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = static::getContainer()->get(ValidatorInterface::class);

        foreach (self::invalidNames as $invalidName) {
            $user->setFirstName($invalidName);
            $failing = $validator->validate($user);
            self::assertSame(
                'The name contains invalid characters.',
                $failing->offsetGet(0)->getMessage(),
                sprintf('"%s" should be invalid but is is not', $invalidName)
            );
        }

        $user->setFirstName('Hans-Peter D´Artagòn');
        $valid = $validator->validate($user);
        self::assertCount(0, $valid);
    }

    public function testLastNameRestrictions(): void
    {
        // fetch a valid user and automatically initialize the service container
        $user = $this->getUserRepository()->find(TestFixtures::ADMIN['id']);

        $validator = static::getContainer()->get(ValidatorInterface::class);

        foreach (self::invalidNames as $invalidName) {
            $user->setLastName($invalidName);
            $failing = $validator->validate($user);
            self::assertCount(1, $failing);
            self::assertSame(
                'The name contains invalid characters.',
                $failing->offsetGet(0)->getMessage(),
                sprintf('"%s" should be invalid but is is not', $invalidName)
            );
        }

        $user->setLastName('Hans-Peter D´Artagòn');
        $valid = $validator->validate($user);
        self::assertCount(0, $valid);
    }
}
