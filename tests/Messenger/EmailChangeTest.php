<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserEmailChangeMessage;
use App\MessageHandler\UserEmailChangeMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class EmailChangeTest extends KernelTestCase
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
        $msg = new UserEmailChangeMessage(
            TestFixtures::PROJECT_OBSERVER['id'],
            'new@zukunftsstadt.de',
            'https://hpo.vrok.de/confirm-validation/?id={{id}}&token={{token}}&type={{type}}'
        );

        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id']);
        foreach ($user->getValidations() as $validation) {
            $this->entityManager->remove($validation);
        }
        $this->entityManager->flush();

        /** @var UserEmailChangeMessageHandler $handler */
        $handler = self::$container->get(UserEmailChangeMessageHandler::class);
        $handler($msg);

        // check for sent emails, @see Symfony\Component\Mailer\Test\Constraint\EmailCount
        // & Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait, we don't
        // use the trait as it requires the usage of a WebTestCase
        $logger = self::$container->get('mailer.logger_message_listener');
        $sent = array_filter($logger->getEvents()->getEvents(), static function ($e) {
            return !$e->isQueued();
        });
        self::assertCount(1, $sent);

        $validation = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $user]);
        self::assertInstanceOf(Validation::class, $validation);
        self::assertSame(Validation::TYPE_CHANGE_EMAIL, $validation->getType());
        self::assertSame(['email' => 'new@zukunftsstadt.de'], $validation->getContent());
    }
}
