<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Util\RequestAttributesExtractor;
use App\Entity\ActionMandate;
use App\Entity\Argument;
use App\Entity\CounterArgument;
use App\Entity\FractionDetails;
use App\Entity\FractionInterest;
use App\Entity\Negation;
use App\Entity\Partner;
use App\Entity\Problem;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Event\Api\ApiActionMandatePreCreateEvent;
use App\Event\Api\ApiActionMandatePreDeleteEvent;
use App\Event\Api\ApiActionMandatePreUpdateEvent;
use App\Event\Api\ApiArgumentPreCreateEvent;
use App\Event\Api\ApiArgumentPreDeleteEvent;
use App\Event\Api\ApiArgumentPreUpdateEvent;
use App\Event\Api\ApiCounterArgumentPreCreateEvent;
use App\Event\Api\ApiCounterArgumentPreDeleteEvent;
use App\Event\Api\ApiCounterArgumentPreUpdateEvent;
use App\Event\Api\ApiFractionDetailsPreCreateEvent;
use App\Event\Api\ApiFractionDetailsPreDeleteEvent;
use App\Event\Api\ApiFractionDetailsPreUpdateEvent;
use App\Event\Api\ApiFractionInterestPreCreateEvent;
use App\Event\Api\ApiFractionInterestPreDeleteEvent;
use App\Event\Api\ApiFractionInterestPreUpdateEvent;
use App\Event\Api\ApiNegationPreCreateEvent;
use App\Event\Api\ApiNegationPreDeleteEvent;
use App\Event\Api\ApiNegationPreUpdateEvent;
use App\Event\Api\ApiPartnerPreCreateEvent;
use App\Event\Api\ApiPartnerPreDeleteEvent;
use App\Event\Api\ApiPartnerPreUpdateEvent;
use App\Event\Api\ApiProblemPreCreateEvent;
use App\Event\Api\ApiProblemPreDeleteEvent;
use App\Event\Api\ApiProblemPreUpdateEvent;
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
 * events after the data was flushed. Only events we currently use are triggered.
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

        if ('put' === $itemOperation && is_object($controllerResult)) {
            switch (get_class($controllerResult)) {
                case ActionMandate::class:
                    $eventClass = ApiActionMandatePreUpdateEvent::class;
                    break;
                case Argument::class:
                    $eventClass = ApiArgumentPreUpdateEvent::class;
                    break;
                case CounterArgument::class:
                    $eventClass = ApiCounterArgumentPreUpdateEvent::class;
                    break;
                case FractionDetails::class:
                    $eventClass = ApiFractionDetailsPreUpdateEvent::class;
                    break;
                case FractionInterest::class:
                    $eventClass = ApiFractionInterestPreUpdateEvent::class;
                    break;
                case Negation::class:
                    $eventClass = ApiNegationPreUpdateEvent::class;
                    break;
                case Partner::class:
                    $eventClass = ApiPartnerPreUpdateEvent::class;
                    break;
                case Problem::class:
                    $eventClass = ApiProblemPreUpdateEvent::class;
                    break;
                case Project::class:
                    $eventClass = ApiProjectPreUpdateEvent::class;
                    break;
            }
        }

        if ('delete' === $itemOperation && is_object($controllerResult)) {
            switch (get_class($controllerResult)) {
                case ActionMandate::class:
                    $eventClass = ApiActionMandatePreDeleteEvent::class;
                    break;
                case Argument::class:
                    $eventClass = ApiArgumentPreDeleteEvent::class;
                    break;
                case CounterArgument::class:
                    $eventClass = ApiCounterArgumentPreDeleteEvent::class;
                    break;
                case FractionDetails::class:
                    $eventClass = ApiFractionDetailsPreDeleteEvent::class;
                    break;
                case FractionInterest::class:
                    $eventClass = ApiFractionInterestPreDeleteEvent::class;
                    break;
                case Negation::class:
                    $eventClass = ApiNegationPreDeleteEvent::class;
                    break;
                case Partner::class:
                    $eventClass = ApiPartnerPreDeleteEvent::class;
                    break;
                case Problem::class:
                    $eventClass = ApiProblemPreDeleteEvent::class;
                    break;
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

        if ('post' === $collectionOperation && is_object($controllerResult)) {
            switch (get_class($controllerResult)) {
                case ActionMandate::class:
                    $eventClass = ApiActionMandatePreCreateEvent::class;
                    break;
                case Argument::class:
                    $eventClass = ApiArgumentPreCreateEvent::class;
                    break;
                case CounterArgument::class:
                    $eventClass = ApiCounterArgumentPreCreateEvent::class;
                    break;
                case FractionDetails::class:
                    $eventClass = ApiFractionDetailsPreCreateEvent::class;
                    break;
                case FractionInterest::class:
                    $eventClass = ApiFractionInterestPreCreateEvent::class;
                    break;
                case Negation::class:
                    $eventClass = ApiNegationPreCreateEvent::class;
                    break;
                case Partner::class:
                    $eventClass = ApiPartnerPreCreateEvent::class;
                    break;
                case Problem::class:
                    $eventClass = ApiProblemPreCreateEvent::class;
                    break;
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

        if ('put' === $itemOperation && is_object($entity)) {
            switch (get_class($entity)) {
                case Project::class:
                    $eventClass = ApiProjectPostUpdateEvent::class;
                    break;
            }
        }

        if ('delete' === $itemOperation) {
            $entity = $attributes['previous_data'] ?? null;
            if (!is_object($entity)) {
                return;
            }

            switch (get_class($entity)) {
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

        if ('post' === $collectionOperation && is_object($controllerResult)) {
            switch (get_class($controllerResult)) {
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
