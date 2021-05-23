<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiActionMandatePreCreateEvent;
use App\Event\Api\ApiActionMandatePreDeleteEvent;
use App\Event\Api\ApiActionMandatePreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ActionMandateEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiActionMandatePreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiActionMandatePreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiActionMandatePreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiActionMandatePreCreateEvent $event): void
    {
        $project = $event->actionMandate->getProject();
        if (!$project) {
            throw new \RuntimeException('New actionMandates need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiActionMandatePreUpdateEvent $event): void
    {
        $project = $event->actionMandate->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiActionMandatePreDeleteEvent $event): void
    {
        $project = $event->actionMandate->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
