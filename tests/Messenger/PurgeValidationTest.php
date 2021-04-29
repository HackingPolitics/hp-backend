<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\PurgeValidationsMessage;
use App\MessageHandler\PurgeValidationsMessageHandler;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class PurgeValidationTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    private EntityManager $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    public function testExpiredAccountValidation(): void
    {
        /** @var User $before */
        $before = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);

        $validation = $before->getValidations()[0];
        $validation->setExpiresAt(new DateTimeImmutable('yesterday'));

        $this->entityManager->flush();
        $validationId = $validation->getId();
        $this->entityManager->clear();

        $msg = new PurgeValidationsMessage();

        /* @var $handler PurgeValidationsMessageHandler */
        $handler = self::$container->get(PurgeValidationsMessageHandler::class);
        $handler($msg);

        $after = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::GUEST['id']);
        self::assertNull($after);

        $noValidation = $this->entityManager->getRepository(Validation::class)
            ->find($validationId);
        self::assertNull($noValidation);
    }
}
