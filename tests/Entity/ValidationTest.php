<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Validation;
use DateTimeImmutable;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ValidationEntity
 */
class ValidationTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function getValidationRepository(): EntityRepository
    {
        return $this->entityManager->getRepository(Validation::class);
    }

    /**
     * Tests the defaults for new validations.
     */
    public function testCreateAndReadValidation(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $validation = new Validation();
        $validation->setType(Validation::TYPE_CHANGE_EMAIL);
        $validation->setContent(['new_email' => 'new@zukunftsstadt.de']);
        $validation->setExpiresAt(new DateTimeImmutable('2099-01-01'));

        $validation->generateToken();
        self::assertNotEmpty($validation->getToken());

        $user->addValidation($validation);
        self::assertEquals($user, $validation->getUser());

        $this->entityManager->persist($validation);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(4, $validation->getId());

        /** @var $found Validation * */
        $found = $this->getValidationRepository()
            ->find(4);

        self::assertInstanceOf(Validation::class, $found);
        self::assertFalse($found->isExpired());
        self::assertInstanceOf(User::class, $found->getUser());

        // timestampable listener works
        self::assertInstanceOf(\DateTimeImmutable::class,
            $found->getCreatedAt());

        $found->setExpiresAt(new DateTimeImmutable('2000-01-01'));
        self::assertTrue($found->isExpired());
    }

    /**
     * Tests that pending validations are deleted when the user is deleted.
     */
    public function testDeletingUserDeletesValidation(): void
    {
        /** @var $user User */
        $user = $this->entityManager->getRepository(User::class)
            ->find(1);

        $validation = new Validation();
        $validation->setType(Validation::TYPE_CHANGE_EMAIL);
        $validation->setExpiresAt(new DateTimeImmutable());
        $validation->generateToken();

        $user->addValidation($validation);

        $this->entityManager->persist($validation);
        $this->entityManager->flush();

        $all = $this->getValidationRepository()->findAll();
        self::assertCount(4, $all);

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $none = $this->getValidationRepository()->findAll();
        self::assertCount(3, $none);
    }
}
