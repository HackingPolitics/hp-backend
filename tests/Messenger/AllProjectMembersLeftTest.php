<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\Project;
use App\Message\AllProjectMembersLeftMessage;
use App\MessageHandler\AllProjectMembersLeftMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class AllProjectMembersLeftTest extends KernelTestCase
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

    public function testHandlerSendsMessage()
    {
        $em = self::$container->get('doctrine')->getManager();

        /** @var Project $project */
        $project = $em->getRepository(Project::class)
            ->find(TestFixtures::PROJECT['id']);
        $project->setLocked(true);
        $project->setTitle('');
        $em->flush();

        $msg = new AllProjectMembersLeftMessage(TestFixtures::PROJECT['id']);

        /** @var AllProjectMembersLeftMessageHandler $handler */
        $handler = self::$container->get(AllProjectMembersLeftMessageHandler::class);
        $handler($msg);

        // check for sent emails, @see Symfony\Component\Mailer\Test\Constraint\EmailCount
        // & Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait, we don't
        // use the trait as it requires the usage of a WebTestCase
        $logger = self::$container->get('mailer.logger_message_listener');
        $sent = array_filter($logger->getEvents()->getEvents(), function ($e) {
            return !$e->isQueued();
        });
        self::assertCount(1, $sent);
    }
}
