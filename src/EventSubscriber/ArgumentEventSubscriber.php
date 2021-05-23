<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiArgumentPreCreateEvent;
use App\Event\Api\ApiArgumentPreDeleteEvent;
use App\Event\Api\ApiArgumentPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ArgumentEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiArgumentPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiArgumentPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiArgumentPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiArgumentPreCreateEvent $event): void
    {
        $project = $event->argument->getProject();
        if (!$project) {
            throw new \RuntimeException('New arguments need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiArgumentPreUpdateEvent $event): void
    {
        $project = $event->argument->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiArgumentPreDeleteEvent $event): void
    {
        $project = $event->argument->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
