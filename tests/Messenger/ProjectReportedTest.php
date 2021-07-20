<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Message\ProjectReportedMessage;
use App\MessageHandler\ProjectReportedMessageHandler;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class ProjectReportedTest extends KernelTestCase
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

    public function testHandlerSendsMessageForProject()
    {
        $msg = new ProjectReportedMessage(
            TestFixtures::PROJECT['id'],
            'test-message',
            'ich bins',
            'fake@email.com'
        );

        /** @var ProjectReportedMessageHandler $handler */
        $handler = static::getContainer()->get(ProjectReportedMessageHandler::class);
        $handler($msg);

        self::assertEmailCount(1);
    }
}
