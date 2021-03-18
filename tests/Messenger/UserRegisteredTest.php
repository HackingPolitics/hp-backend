<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserRegisteredMessage;
use App\MessageHandler\UserRegisteredMessageHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class UserRegisteredTest extends KernelTestCase
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
        $handler = self::$container->get(UserRegisteredMessageHandler::class);
        $handler($msg);

        // check for sent emails, @see Symfony\Component\Mailer\Test\Constraint\EmailCount
        // & Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait, we don't
        // use the trait as it requires the usage of a WebTestCase
        $logger = self::$container->get('mailer.logger_message_listener');
        $sent = array_filter($logger->getEvents()->getEvents(), function ($e) {
            return !$e->isQueued();
        });
        self::assertCount(1, $sent);

        $validation = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $admin]);
        self::assertInstanceOf(Validation::class, $validation);
        self::assertSame(Validation::TYPE_ACCOUNT, $validation->getType());
    }
}
