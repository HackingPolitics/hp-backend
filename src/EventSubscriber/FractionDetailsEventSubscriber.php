<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiFractionDetailsPreCreateEvent;
use App\Event\Api\ApiFractionDetailsPreDeleteEvent;
use App\Event\Api\ApiFractionDetailsPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FractionDetailsEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiFractionDetailsPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiFractionDetailsPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiFractionDetailsPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiFractionDetailsPreCreateEvent $event): void
    {
        $project = $event->application->getProject();
        if (!$project) {
            throw new \RuntimeException("New fractionDetailss need a project!");
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiFractionDetailsPreUpdateEvent $event): void
    {
        $project = $event->application->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiFractionDetailsPreDeleteEvent $event): void
    {
        $project = $event->application->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
