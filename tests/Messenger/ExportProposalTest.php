<?php

declare(strict_types=1);

namespace App\Tests\Messenger;

use App\DataFixtures\TestFixtures;
use App\Entity\Proposal;
use App\Entity\UploadedFileTypes\ProposalDocument;
use App\Message\ExportProposalMessage;
use App\MessageHandler\ExportProposalMessageHandler;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Vrok\SymfonyAddons\PHPUnit\RefreshDatabaseTrait;

class ExportProposalTest extends KernelTestCase
{
    use RefreshDatabaseTrait;

    public static function setUpBeforeClass(): void
    {
        static::$fixtureGroups = ['initial', 'test'];
    }

    public static function tearDownAfterClass(): void
    {
        self::fixtureCleanup();
    }

    public function testHandlerCreatesFile(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $msg = new ExportProposalMessage(1, TestFixtures::PROJECT_COORDINATOR['id']);

        /** @var ExportProposalMessageHandler $handler */
        $handler = static::getContainer()->get(ExportProposalMessageHandler::class);
        $handler($msg);

        /** @var Proposal $proposal */
        $proposal = $em->getRepository(Proposal::class)
            ->find(1);

        self::assertInstanceOf(ProposalDocument::class, $proposal->getDocumentFile());

        /** @var FilesystemOperator $flySystem */
        $flySystem = static::getContainer()->get('private.storage');
        self::assertTrue($flySystem->fileExists($proposal->getDocumentFile()->file->getPathname()));

        // cleanup
        $flySystem->delete($proposal->getDocumentFile()->file->getPathname());

        self::assertEmailCount(1);
    }

    public function testHandlerReplacesExistingDocument(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $msg = new ExportProposalMessage(1, TestFixtures::PROJECT_COORDINATOR['id']);

        /** @var ExportProposalMessageHandler $handler */
        $handler = static::getContainer()->get(ExportProposalMessageHandler::class);
        $handler($msg);

        /** @var Proposal $proposal */
        $proposal = $em->getRepository(Proposal::class)->find(1);

        self::assertInstanceOf(ProposalDocument::class, $proposal->getDocumentFile());

        /** @var FilesystemOperator $flySystem */
        $flySystem = static::getContainer()->get('private.storage');
        self::assertTrue($flySystem->fileExists($proposal->getDocumentFile()->file->getPathname()));

        $proposal->setTitle('new test name');
        $em->flush();
        $em->clear();

        /** @var ExportProposalMessageHandler $handler */
        $handler = static::getContainer()->get(ExportProposalMessageHandler::class);
        $handler($msg);

        /** @var Proposal $after */
        $after = $em->getRepository(Proposal::class)->find(1);

        self::assertNotSame($proposal->getDocumentFile()->file->getPathname(), $after->getDocumentFile()->file->getPathname());
        self::assertTrue($flySystem->fileExists($after->getDocumentFile()->file->getPathname()));
        self::assertFalse($flySystem->fileExists($proposal->getDocumentFile()->file->getPathname()));

        // cleanup
        $flySystem->delete($proposal->getDocumentFile()->file->getPathname());
    }
}
