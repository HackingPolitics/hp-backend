<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiPartnerPreCreateEvent;
use App\Event\Api\ApiPartnerPreDeleteEvent;
use App\Event\Api\ApiPartnerPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PartnerEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiPartnerPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiPartnerPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiPartnerPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiPartnerPreCreateEvent $event): void
    {
        $project = $event->application->getProject();
        if (!$project) {
            throw new \RuntimeException("New partners need a project!");
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiPartnerPreUpdateEvent $event): void
    {
        $project = $event->application->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiPartnerPreDeleteEvent $event): void
    {
        $project = $event->application->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
