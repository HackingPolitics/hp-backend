<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiProposalPreCreateEvent;
use App\Event\Api\ApiProposalPreDeleteEvent;
use App\Event\Api\ApiProposalPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProposalEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiProposalPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiProposalPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiProposalPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiProposalPreCreateEvent $event): void
    {
        $project = $event->proposal->getProject();
        if (!$project) {
            throw new \RuntimeException('New proposals need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiProposalPreUpdateEvent $event): void
    {
        $project = $event->proposal->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiProposalPreDeleteEvent $event): void
    {
        $project = $event->proposal->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
