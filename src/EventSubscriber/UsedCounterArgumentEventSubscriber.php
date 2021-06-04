<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedCounterArgumentPreCreateEvent;
use App\Event\Api\ApiUsedCounterArgumentPreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedCounterArgumentEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedCounterArgumentPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedCounterArgumentPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedCounterArgumentPreCreateEvent $event): void
    {
        $project = $event->usedCounterArgument->getCounterArgument()
            ? $event->usedCounterArgument->getCounterArgument()->getProject()
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedCounterArguments need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedCounterArgumentPreDeleteEvent $event): void
    {
        $project = $event->usedCounterArgument->getCounterArgument()
            ? $event->usedCounterArgument->getCounterArgument()->getProject()
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
