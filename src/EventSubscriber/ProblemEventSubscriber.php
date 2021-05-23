<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiProblemPreCreateEvent;
use App\Event\Api\ApiProblemPreDeleteEvent;
use App\Event\Api\ApiProblemPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProblemEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiProblemPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiProblemPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiProblemPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiProblemPreCreateEvent $event): void
    {
        $project = $event->problem->getProject();
        if (!$project) {
            throw new \RuntimeException('New problems need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiProblemPreUpdateEvent $event): void
    {
        $project = $event->problem->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiProblemPreDeleteEvent $event): void
    {
        $project = $event->problem->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
