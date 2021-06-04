<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedProblemPreCreateEvent;
use App\Event\Api\ApiUsedProblemPreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedProblemEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedProblemPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedProblemPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedProblemPreCreateEvent $event): void
    {
        $project = $event->usedProblem->getProblem()
            ? $event->usedProblem->getProblem()->getProject()
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedProblems need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedProblemPreDeleteEvent $event): void
    {
        $project = $event->usedProblem->getProblem()
            ? $event->usedProblem->getProblem()->getProject()
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
