<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use App\Entity\Project;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Vich\UploaderBundle\Storage\StorageInterface;

class ResolveContentUrlSubscriber implements EventSubscriberInterface
{
/* @todo we currently have no public files
    private $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }
*/
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                'onPreSerialize', EventPriorities::PRE_SERIALIZE,
            ],
        ];
    }

    public function onPreSerialize(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();
        $request = $event->getRequest();
        if ($controllerResult instanceof Response || !$request->attributes->getBoolean('_api_respond', true)) {
            return;
        }

        $className = $request->attributes->get('_api_resource_class');

        if (Project::class === $className) {
            $projects = $controllerResult instanceof Project
                ? [$controllerResult]
                : $controllerResult;

            if (!$projects) {
                // controllerResult is null on deletion
                return;
            }

            /** @var Project $project */
            foreach ($projects as $project) {
                foreach ($project->getProposals() as $proposal) {
                    if ($document = $proposal->getDocumentFile()) {
                        // the document is private and requires a POST with the
                        // JWT in the body -> use a specific URL instead determining
                        // it via Vich Uploader
                        //$document->contentUrl = $this->storage->resolveUri($document, 'file');
                        $document->contentUrl = "/proposals/{$proposal->getId()}/document-download";
                    }
                }
            }

            return;
        }
    }
}
