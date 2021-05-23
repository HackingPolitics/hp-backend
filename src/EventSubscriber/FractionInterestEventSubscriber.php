<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiFractionInterestPreCreateEvent;
use App\Event\Api\ApiFractionInterestPreDeleteEvent;
use App\Event\Api\ApiFractionInterestPreUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FractionInterestEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiFractionInterestPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiFractionInterestPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiFractionInterestPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiFractionInterestPreCreateEvent $event): void
    {
        $project = $event->fractionInterest->getProject();
        if (!$project) {
            throw new \RuntimeException('New fractionInterests need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreUpdate(ApiFractionInterestPreUpdateEvent $event): void
    {
        $project = $event->fractionInterest->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }

    public function onApiPreDelete(ApiFractionInterestPreDeleteEvent $event): void
    {
        $project = $event->fractionInterest->getProject();
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
