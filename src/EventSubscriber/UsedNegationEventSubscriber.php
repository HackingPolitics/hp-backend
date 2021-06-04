<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedNegationPreCreateEvent;
use App\Event\Api\ApiUsedNegationPreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedNegationEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedNegationPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedNegationPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedNegationPreCreateEvent $event): void
    {
        $project = $event->usedNegation->getNegation()
            ? ($event->usedNegation->getNegation()->getCounterArgument()
                ? $event->usedNegation->getNegation()->getCounterArgument()->getProject()
                : null)
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedNegations need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedNegationPreDeleteEvent $event): void
    {
        $project = $event->usedNegation->getNegation()
            ? ($event->usedNegation->getNegation()->getCounterArgument()
                ? $event->usedNegation->getNegation()->getCounterArgument()->getProject()
                : null)
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
