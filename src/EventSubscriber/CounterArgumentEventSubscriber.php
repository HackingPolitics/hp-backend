<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiCounterArgumentPreCreateEvent;
use App\Event\Api\ApiCounterArgumentPreDeleteEvent;
use App\Event\Api\ApiCounterArgumentPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CounterArgumentEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiCounterArgumentPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiCounterArgumentPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiCounterArgumentPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiCounterArgumentPreCreateEvent $event): void
    {
        $project = $event->counterArgument->getProject();
        if (!$project) {
            throw new \RuntimeException('New counterArguments need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiCounterArgumentPreUpdateEvent $event): void
    {
        $project = $event->counterArgument->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiCounterArgumentPreDeleteEvent $event): void
    {
        $project = $event->counterArgument->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
