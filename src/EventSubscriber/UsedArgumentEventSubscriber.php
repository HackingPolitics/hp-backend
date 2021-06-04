<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedArgumentPreCreateEvent;
use App\Event\Api\ApiUsedArgumentPreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedArgumentEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedArgumentPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedArgumentPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedArgumentPreCreateEvent $event): void
    {
        $project = $event->usedArgument->getArgument()
            ? $event->usedArgument->getArgument()->getProject()
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedArguments need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedArgumentPreDeleteEvent $event): void
    {
        $project = $event->usedArgument->getArgument()
            ? $event->usedArgument->getArgument()->getProject()
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
