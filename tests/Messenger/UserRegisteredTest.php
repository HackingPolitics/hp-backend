<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserRegisteredMessage;
use App\MessageHandler\UserRegisteredMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class UserRegisteredTest extends KernelTestCase
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

    public function testHandlerSendsMessage(): void
    {
        $admin = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::ADMIN['id']);
        $admin->setValidated(false);
        $this->entityManager->flush();

        $msg = new UserRegisteredMessage(
            TestFixtures::ADMIN['id'],
            'https://hpo.vrok.de/confirm-validation/?id={{id}}&token={{token}}&type={{type}}'
        );

        $notFound = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $admin]);
        self::assertNull($notFound);

        /** @var UserRegisteredMessageHandler $handler */
        $handler = static::getContainer()->get(UserRegisteredMessageHandler::class);
        $handler($msg);

        self::assertEmailCount(1);

        $validation = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $admin]);
        self::assertInstanceOf(Validation::class, $validation);
        self::assertSame(Validation::TYPE_ACCOUNT, $validation->getType());
    }
}
