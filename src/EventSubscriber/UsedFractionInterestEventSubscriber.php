<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Event\Api\ApiUsedFractionInterestPreCreateEvent;
use App\Event\Api\ApiUsedFractionInterestPreDeleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UsedFractionInterestEventSubscriber implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiUsedFractionInterestPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiUsedFractionInterestPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiUsedFractionInterestPreCreateEvent $event): void
    {
        $project = $event->usedFractionInterest->getFractionInterest()
            ? ($event->usedFractionInterest->getFractionInterest()->getFractionDetails()
                ? $event->usedFractionInterest->getFractionInterest()->getFractionDetails()->getProject()
                : null)
            : null;
        if (!$project) {
            throw new \RuntimeException('New usedFractionInterests need a project!');
        }

        $project->setUpdatedAt(new \DateTimeImmutable());
    }

    public function onApiPreDelete(ApiUsedFractionInterestPreDeleteEvent $event): void
    {
        $project = $event->usedFractionInterest->getFractionInterest()
            ? ($event->usedFractionInterest->getFractionInterest()->getFractionDetails()
                ? $event->usedFractionInterest->getFractionInterest()->getFractionDetails()->getProject()
                : null)
            : null;
        if ($project) {
            $project->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
