<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActionLog;
use App\Entity\ProjectMembership;
use App\Event\Api\ApiUserPostDeleteEvent;
use App\Event\Api\ApiUserPreDeleteEvent;
use App\Event\Api\UserRegisteredEvent;
use App\Event\Entity\ProjectMembershipPostDeleteEvent;
use App\Event\Entity\ProjectMembershipPreDeleteEvent;
use App\Event\Entity\ProjectPreCreateEvent;
use App\Message\NewMemberApplicationMessage;
use App\Message\UserRegisteredMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listens to different events regarding users, to push necessary tasks to the
 * message queue for asynchronous execution.
 */
class UserEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => [
                ['onApiUserCreated', 100],
            ],
        ];
    }

    /**
     * Send the validation email asynchronously to reduce load time.
     */
    public function onApiUserCreated(UserRegisteredEvent $event): void
    {
        $log = new ActionLog();
        $log->action = ActionLog::REGISTERED_USER;
        $log->ipAddress = $this->requestStack()->getCurrentRequest()->getClientIp();
        $log->username = $event->user->getUsername();
        $this->entityManager()->persist($log);

        if ($event->user->isValidated()) {
            // if validation is not required -> nothing more to do
            return;
        }

        $this->messageBus()->dispatch(
            new UserRegisteredMessage($event->user->getId(), $event->validationUrl)
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function messageBus(): MessageBusInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function requestStack(): RequestStack
    {
        return $this->container->get(__METHOD__);
    }
}
