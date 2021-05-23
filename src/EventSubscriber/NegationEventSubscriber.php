<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiNegationPreCreateEvent;
use App\Event\Api\ApiNegationPreDeleteEvent;
use App\Event\Api\ApiNegationPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NegationEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiNegationPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiNegationPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiNegationPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiNegationPreCreateEvent $event): void
    {
        $project = $event->negation->getProject();
        if (!$project) {
            throw new \RuntimeException('New negations need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiNegationPreUpdateEvent $event): void
    {
        $project = $event->negation->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiNegationPreDeleteEvent $event): void
    {
        $project = $event->negation->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
