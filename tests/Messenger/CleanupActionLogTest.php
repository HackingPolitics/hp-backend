<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\Entity\ActionLog;
use App\Message\CleanupActionLogMessage;
use App\MessageHandler\CleanupActionLogMessageHandler;
use App\Util\DateHelper;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class CleanupActionLogTest extends KernelTestCase
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

    protected function getRepository()
    {
        return $this->entityManager->getRepository(ActionLog::class);
    }

    protected function removeAllLogs(): void
    {
        $logs = $this->getRepository()->findAll();
        foreach ($logs as $entry) {
            $this->entityManager->remove($entry);
        }

        $this->entityManager->flush();
    }

    public function testAnonymizeLogs(): void
    {
        $this->removeAllLogs();

        $l1 = new ActionLog();
        $l1->action = ActionLog::SUCCESSFUL_PW_RESET_REQUEST;
        $l1->ipAddress = 'l1';
        $l1->username = 'l1';
        $l1->timestamp = DateHelper::nowSubInterval('PT1H');
        $this->entityManager->persist($l1);

        $l2 = new ActionLog();
        $l2->action = ActionLog::FAILED_PW_RESET_REQUEST;
        $l2->ipAddress = 'l2';
        $l2->username = 'l2';
        $l2->timestamp = DateHelper::nowSubInterval('PT25H');
        $this->entityManager->persist($l2);

        $l3 = new ActionLog();
        $l3->action = ActionLog::REGISTERED_USER;
        $l3->ipAddress = 'l3';
        $l3->username = 'l3';
        $l3->timestamp = DateHelper::nowSubInterval('P8D');
        $this->entityManager->persist($l3);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $msg = new CleanupActionLogMessage();

        /* @var $handler CleanupActionLogMessageHandler */
        $handler = self::$container->get(CleanupActionLogMessageHandler::class);
        $handler($msg);

        $l1c = $this->getRepository()->find($l1->getId());
        self::assertSame('l1', $l1c->username);
        self::assertSame('l1', $l1c->ipAddress);

        $l2c = $this->getRepository()->find($l2->getId());
        self::assertSame('l2', $l2c->username);
        self::assertNull($l2c->ipAddress);

        $l3c = $this->getRepository()->find($l3->getId());
        self::assertNull($l3c->username);
        self::assertNull($l3c->ipAddress);
    }

    public function testPurgeLogs(): void
    {
        $this->removeAllLogs();

        $l1 = new ActionLog();
        $l1->action = ActionLog::FAILED_LOGIN;
        $l1->ipAddress = 'l1';
        $l1->username = 'l1';
        $l1->timestamp = DateHelper::nowSubInterval('P6D');
        $this->entityManager->persist($l1);

        $l2 = new ActionLog();
        $l2->action = ActionLog::FAILED_LOGIN;
        $l2->ipAddress = 'l2';
        $l2->username = 'l2';
        $l2->timestamp = DateHelper::nowSubInterval('P8D');
        $this->entityManager->persist($l2);

        $l3 = new ActionLog();
        $l3->action = ActionLog::REGISTERED_USER;
        $l3->ipAddress = 'l3';
        $l3->username = 'l3';
        $l3->timestamp = DateHelper::nowSubInterval('P8D');
        $this->entityManager->persist($l3);

        $l4 = new ActionLog();
        $l4->action = ActionLog::SUCCESSFUL_LOGIN;
        $l4->ipAddress = 'l4';
        $l4->username = 'l4';
        $l4->timestamp = DateHelper::nowSubInterval('P8D');
        $this->entityManager->persist($l4);

        $this->entityManager->flush();
        $this->entityManager->clear();

        $msg = new CleanupActionLogMessage();

        /* @var $handler CleanupActionLogMessageHandler */
        $handler = self::$container->get(CleanupActionLogMessageHandler::class);
        $handler($msg);

        $l1c = $this->getRepository()->find($l1->getId());
        self::assertNotNull($l1c);

        $l2c = $this->getRepository()->find($l2->getId());
        self::assertNull($l2c);

        $l3c = $this->getRepository()->find($l3->getId());
        self::assertNotNull($l3c);

        $l4c = $this->getRepository()->find($l4->getId());
        self::assertNull($l4c);
    }
}
