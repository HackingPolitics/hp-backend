<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Event\Api\ApiProjectMembershipPostCreateEvent;
use App\Event\Api\ApiProjectMembershipPostDeleteEvent;
use App\Event\Api\ApiProjectMembershipPreCreateEvent;
use App\Event\Api\ApiProjectMembershipPreDeleteEvent;
use App\Event\Api\ApiProjectPostCreateEvent;
use App\Event\Api\ApiProjectPostDeleteEvent;
use App\Event\Api\ApiProjectPostUpdateEvent;
use App\Event\Api\ApiProjectPreCreateEvent;
use App\Event\Api\ApiProjectPreDeleteEvent;
use App\Event\Api\ApiProjectPreUpdateEvent;
use App\Event\Api\ApiUserPostDeleteEvent;
use App\Event\Api\ApiUserPreDeleteEvent;
use App\Event\EntityPostWriteEvent;
use App\Event\EntityPreWriteEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Translates the Kernel View Event into more precise entity related events to
 * allow specific actions when entities are created/updated/deleted.
 * Triggers pre-write events before the persisters are called and post-write
 * events after the data was flushed.
 */
class WriteEventSubscriber implements EventSubscriberInterface
{
    private EventDispatcherInterface $dispatcher;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::VIEW => [
                ['onPreWrite', EventPriorities::PRE_WRITE],
                ['onPostWrite', EventPriorities::POST_WRITE],
            ],
        ];
    }

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function onPreWrite(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();
        $request = $event->getRequest();

        if ($controllerResult instanceof Response
            || $request->isMethodSafe()
            || !($attributes = RequestAttributesExtractor::extractAttributes($request))
            || !$attributes['persist']
        ) {
            return;
        }

        $this->dispatcher->dispatch(new EntityPreWriteEvent($controllerResult, $attributes));

        $eventClass = null;
        $itemOperation = $attributes['item_operation_name'] ?? null;
        $collectionOperation = $attributes['collection_operation_name'] ?? null;

        if ($itemOperation === "put" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                case Project::class:
                    $eventClass = ApiProjectPreUpdateEvent::class;
                    break;
            }
        }

        if ($itemOperation === "delete" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                case Project::class:
                    $eventClass = ApiProjectPreDeleteEvent::class;
                    break;

                case ProjectMembership::class:
                    $eventClass = ApiProjectMembershipPreDeleteEvent::class;
                    break;

                case User::class:
                    $eventClass = ApiUserPreDeleteEvent::class;
                    break;
            }
        }

        if ($collectionOperation === "post" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                case Project::class:
                    $eventClass = ApiProjectPreCreateEvent::class;
                    break;

                case ProjectMembership::class:
                    $eventClass = ApiProjectMembershipPreCreateEvent::class;
                    break;
            }
        }

        if ($eventClass) {
            $this->dispatcher->dispatch(new $eventClass($controllerResult, $attributes));
        }
    }

    public function onPostWrite(ViewEvent $event): void
    {
        $controllerResult = $event->getControllerResult();
        $request = $event->getRequest();

        if ($controllerResult instanceof Response
            || $request->isMethodSafe()
            || !($attributes = RequestAttributesExtractor::extractAttributes($request))
            || !$attributes['persist']
        ) {
            return;
        }

        $this->dispatcher->dispatch(new EntityPostWriteEvent($controllerResult, $attributes));

        $entity = $controllerResult;
        $eventClass = null;
        $itemOperation = $attributes['item_operation_name'] ?? null;
        $collectionOperation = $attributes['collection_operation_name'] ?? null;

        if ($itemOperation === "put" && is_object($entity)) {
            switch(get_class($entity)) {
                case Project::class:
                    $eventClass = ApiProjectPostUpdateEvent::class;
                    break;
            }
        }

        if ($itemOperation === "delete") {
            $entity = $attributes['previous_data'] ?? null;
            if (!is_object($entity)) {
                return;
            }

            switch(get_class($entity)) {
                case Project::class:
                    $eventClass = ApiProjectPostDeleteEvent::class;
                    break;

                case ProjectMembership::class:
                    $eventClass = ApiProjectMembershipPostDeleteEvent::class;
                    break;

                case User::class:
                    $eventClass = ApiUserPostDeleteEvent::class;
                    break;
            }
        }

        if ($collectionOperation === "post" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                case Project::class:
                    $eventClass = ApiProjectPostCreateEvent::class;
                    break;

                case ProjectMembership::class:
                    $eventClass = ApiProjectMembershipPostCreateEvent::class;
                    break;
            }
        }

        if ($entity && $eventClass) {
            $this->dispatcher->dispatch(new $eventClass($entity, $attributes));
        }
    }
}
