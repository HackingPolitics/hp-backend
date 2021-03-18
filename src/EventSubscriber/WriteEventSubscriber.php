<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use App\Entity\User;
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
                // pre update events
            }
        }

        if ($itemOperation === "delete" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                case User::class:
                    $eventClass = ApiUserPreDeleteEvent::class;
                    break;
            }
        }

        if ($collectionOperation === "post" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                // pre create events
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
                // post update events
            }
        }

        if ($itemOperation === "delete") {
            $entity = $attributes['previous_data'] ?? null;
            if (!is_object($entity)) {
                return;
            }

            switch(get_class($entity)) {
                case User::class:
                    $eventClass = ApiUserPostDeleteEvent::class;
                    break;
            }
        }

        if ($collectionOperation === "post" && is_object($controllerResult)) {
            switch(get_class($controllerResult)) {
                // post create events
            }
        }

        if ($entity && $eventClass) {
            $this->dispatcher->dispatch(new $eventClass($entity, $attributes));
        }
    }
}
