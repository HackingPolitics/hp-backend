<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedActionMandatePreCreateEvent;
use App\Event\Api\ApiUsedActionMandatePreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedActionMandateEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedActionMandatePreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedActionMandatePreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedActionMandatePreCreateEvent $event): void
    {
        $project = $event->usedActionMandate->getActionMandate()
            ? $event->usedActionMandate->getActionMandate()->getProject()
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedActionMandates need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedActionMandatePreDeleteEvent $event): void
    {
        $project = $event->usedActionMandate->getActionMandate()
            ? $event->usedActionMandate->getActionMandate()->getProject()
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
