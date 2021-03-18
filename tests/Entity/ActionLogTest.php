<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\ActionLog;
use App\Repository\ActionLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

/**
 * @group ActionLogEntity
 */
class ActionLogTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    /**
     * @var EntityManager
     */
    private $entityManager;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();
    }

    protected function getRepository(): ActionLogRepository
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->entityManager->getRepository(ActionLog::class);
    }

    public function testCreateAndRead(): void
    {
        $log = new ActionLog();
        $log->ipAddress = '127.0.0.99';
        $log->username = 'max-mustermann';
        $log->action = 'failed-login';

        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->entityManager->clear();

        /* @var $found ActionLog */
        $found = $this->getRepository()
            ->findOneBy(['ipAddress' => '127.0.0.99']);

        self::assertSame('max-mustermann', $found->username);
        self::assertSame('failed-login', $found->action);
        self::assertInstanceOf(DateTimeImmutable::class, $found->timestamp);
    }

    public function testGetActionCount(): void
    {
        $repo = $this->getRepository();

        self::assertSame(0, $repo->getActionCount(
            ['login-successful'], 'P2D'));
        self::assertSame(1, $repo->getActionCount(
            ['create-idea'], 'P2D'));
        self::assertSame(1, $repo->getActionCount(
            ['create-comment'], 'PT1H'));
        self::assertSame(2, $repo->getActionCount(
            ['create-comment'], 'PT12H'));
        self::assertSame(3, $repo->getActionCount(
            ['create-comment'], 'P2D'));
        self::assertSame(3, $repo->getActionCount(
            ['create-comment', 'create-idea'], 'PT12H'));
    }

    public function testGetActionCountByIp(): void
    {
        $repo = $this->getRepository();

        self::assertSame(0, $repo->getActionCountByIp(
            '10.0.0.1', ['create-idea'], 'P2D'));
        self::assertSame(0, $repo->getActionCountByIp(
            '127.0.0.1', ['login-successful'], 'P2D'));
        self::assertSame(1, $repo->getActionCountByIp(
            '127.0.0.1', ['create-comment'], 'PT1H'));
        self::assertSame(2, $repo->getActionCountByIp(
            '127.0.0.1', ['create-comment'], 'PT12H'));
        self::assertSame(3, $repo->getActionCountByIp(
            '127.0.0.1', ['create-comment', 'create-idea'], 'P2D'));
    }

    public function testGetActionCountByUsername(): void
    {
        $repo = $this->getRepository();

        self::assertSame(0, $repo->getActionCountByUsername(
            'peter-mueller', ['create-idea'], 'P2D'));
        self::assertSame(0, $repo->getActionCountByUsername(
            'tester', ['login-successful'], 'P2D'));
        self::assertSame(1, $repo->getActionCountByUsername(
            'tester', ['create-comment'], 'PT1H'));
        self::assertSame(2, $repo->getActionCountByUsername(
            'tester', ['create-comment'], 'PT12H'));
        self::assertSame(3, $repo->getActionCountByUsername(
            'tester', ['create-comment', 'create-project'], 'P2D'));
    }
}
