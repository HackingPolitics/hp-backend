<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use App\Entity\Validation;
use App\Message\UserForgotPasswordMessage;
use App\MessageHandler\UserForgotPasswordMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class PasswordResetTest extends KernelTestCase
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
        $msg = new UserForgotPasswordMessage(
            TestFixtures::PROJECT_OBSERVER['id'],
            'https://hpo.vrok.de/confirm-validation/?id={{id}}&token={{token}}&type={{type}}'
        );

        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)
            ->find(TestFixtures::PROJECT_OBSERVER['id']);
        foreach ($user->getValidations() as $validation) {
            $this->entityManager->remove($validation);
        }
        $this->entityManager->flush();

        /** @var UserForgotPasswordMessageHandler $handler */
        $handler = static::getContainer()->get(UserForgotPasswordMessageHandler::class);
        $handler($msg);

        self::assertEmailCount(1);

        $validation = $this->entityManager->getRepository(Validation::class)
            ->findOneBy(['user' => $user]);
        self::assertInstanceOf(Validation::class, $validation);
        self::assertSame(Validation::TYPE_RESET_PASSWORD, $validation->getType());
    }
}
