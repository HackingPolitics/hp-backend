<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Proposal;
use App\Entity\UploadedFileTypes\ProposalDocument;
use App\Entity\User;
use App\Message\ExportProposalMessage;
use CatoTH\HTML2OpenDocument\Text;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

class ExportProposalMessageHandler implements MessageHandlerInterface
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private TransportInterface $mailer;
    private ParameterBagInterface $parameterBag;
    private StorageInterface $storage;

    /**
     * ExportProposalMessageHandler constructor.
     *
     * @param StorageInterface      $storage      to get the uploaded images from VichUploader, they
     *                                            may be stored externally on a cloud service
     * @param TransportInterface    $mailer       to notify the user that triggered the pdf creation
     * @param ParameterBagInterface $parameterBag to get the fonts directory
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        StorageInterface $storage,
        TransportInterface $mailer,
        LoggerInterface $logger,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager = $entityManager;
        $this->mailer        = $mailer;
        $this->logger        = $logger;
        $this->parameterBag  = $parameterBag;
        $this->storage       = $storage;
    }

    public function __invoke(ExportProposalMessage $message)
    {
        $proposal = $this->entityManager->getRepository(Proposal::class)
            ->find($message->proposalId);

        if (!$proposal || $proposal->getProject()->isDeleted()) {
            $this->logger->info(
                "Proposal $message->proposalId does not exist or is deleted!"
            );

            return;
        }

        // create an ODT file with the proposal content
        $filename = $this->export($proposal);

        // copy the ODT to the final storage with VichUploader and create a DB entry for it
        $this->saveDocument($filename, $proposal);

        // notify the user that triggered the creation
        $this->sendNotificationMail($message->userId, $proposal, $filename);

        // remove the temp file
        unlink($filename);
    }

    private function export(Proposal $proposal): string
    {
        // @todo configurable?
        $odt = new Text(['templateFile' => __DIR__.'/../../templates/export/export-template.odt']);

        // replace fixed markers, content without HTML
        $odt->addReplace('/\{\{title\}\}/siu', strtoupper($proposal->getTitle()));
        $odt->addReplace('/\{\{sponsor\}\}/siu', $proposal->getSponsor());

        // add the proposal content from HTML, replaces a marker "{{ANTRAGSGRUEN:TEXT}}"
        // that must be present in the template

        // @todo translate + move to template?
        $odt->addHtmlTextBlock('<h2>Gegenstand</h2>');
        $odt->addHtmlTextBlock($proposal->getIntroduction());
        $odt->addHtmlTextBlock('<h2>Beschlussvorschlag</h2>');
        $odt->addHtmlTextBlock($proposal->getActionMandate());
        $odt->addHtmlTextBlock('<h2>Begründung</h2>');
        $odt->addHtmlTextBlock($proposal->getReasoning());

        // save the file on disk so VichUploader can use it
        $filename = tempnam(sys_get_temp_dir(), "proposal-{$proposal->getId()}");
        file_put_contents($filename, $odt->finishAndGetDocument());

        return $filename;
    }

    private function saveDocument(string $filename, Proposal $proposal): void
    {
        // remove previous file from the DB, Vich uploader lifecycle events will delete
        // the file from the storage
        if ($proposal->getDocumentFile()) {
            $this->entityManager->remove($proposal->getDocumentFile());
        }

        // let VichUploader move the file to the private storage and store/update the
        // record in the database
        $document = new ProposalDocument();
        $proposal->setDocumentFile($document);

        // prepare a filename that will be presented on download
        $title = substr($proposal->getProject()->getSlug(), 0, 20);
        $prefix = 'HP-';
        $now = new DateTimeImmutable();
        $ts = $now->format('Ymd-Hi');

        $document->file = new UploadedFile(
            $filename,
            "$prefix-$title-$ts.odt",
            'application/vnd.oasis.opendocument.text'
        );
        $this->entityManager->flush();
    }

    private function sendNotificationMail(int $userId, Proposal $proposal, string $filename): void
    {
        $user = $this->entityManager->getRepository(User::class)
            ->findOneBy([
                'id'        => $userId,
                'deletedAt' => null,
            ]);

        if (!$user) {
            $this->logger->warning(
                "User {$userId} does not exist, no notification after creating a document file."
            );

            return;
        }

        $email = (new TemplatedEmail())
            // FROM is added via listener, subject is added via template
            ->htmlTemplate('project/mail.proposal-exported.html.twig')
            ->context([
                'username'    => $user->getUsername(),
                'projectname' => $proposal->getProject()->getTitle(),
            ])
            ->addTo($user->getEmail())
            ->attachFromPath(
                $filename,
                $proposal->getDocumentFile()->getOriginalName(),
                $proposal->getDocumentFile()->getMimeType()
            );

        $this->mailer->send($email);
    }
}
