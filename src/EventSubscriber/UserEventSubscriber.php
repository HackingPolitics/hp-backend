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

    private array $deletedMemberships = [];

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => [
                ['onApiUserCreated', 100],
            ],
            ApiUserPreDeleteEvent::class => [
                ['onPreDelete', 100],
            ],
            ApiUserPostDeleteEvent::class => [
                ['onPostDelete', 100],
            ],
        ];
    }

    /**
     * Send the validation email asynchronously to reduce load time.
     * If the user is already validated notify project coordinators of his
     * membership application if any exists.
     */
    public function onApiUserCreated(UserRegisteredEvent $event): void
    {
        // a new idea or project may be created (by the UserInputDataTransformer)
        // together with a registration -> trigger an event for new projects/ideas
        // @todo no ProjectPostCreateEvent is currently triggered on registration
        if ($event->user->getCreatedProjects()->count()) {
            foreach ($event->user->getCreatedProjects() as $project) {
                $this->dispatcher()->dispatch(new ProjectPreCreateEvent($project));
            }
        }

        $log = new ActionLog();
        $log->action = ActionLog::REGISTERED_USER;
        $log->ipAddress = $this->requestStack()->getCurrentRequest()->getClientIp();
        $log->username = $event->user->getUsername();
        $this->entityManager()->persist($log);

        if ($event->user->isValidated()) {
            // notify project coordinators (they are not notified on registration if
            // the user still needs to validate)
            foreach ($event->user->getProjectMemberships() as $membership) {
                if (ProjectMembership::ROLE_APPLICANT === $membership->getRole()) {
                    $this->messageBus()->dispatch(
                        new NewMemberApplicationMessage(
                            $event->user->getId(),
                            $membership->getProject()->getId()
                        )
                    );
                }
            }

            // if validation is not required -> nothing more to do
            return;
        }

        $this->messageBus()->dispatch(
            new UserRegisteredMessage($event->user->getId(), $event->validationUrl)
        );
    }

    public function onPreDelete(ApiUserPreDeleteEvent $event): void
    {
        $this->deletedMemberships = [];

        foreach ($event->user->getProjectMemberships() as $membership) {
            $this->dispatcher()->dispatch(new ProjectMembershipPreDeleteEvent($membership));

            // orphan removal will delete those memberships
            $event->user->removeProjectMembership($membership);

            // collect the removed memberships to later trigger the postDelete event
            $this->deletedMemberships[] = $membership;
        }
    }

    /**
     * We removed the memberships -> we are responsible to trigger the postDelete event.
     */
    public function onPostDelete(ApiUserPostDeleteEvent $event): void
    {
        foreach ($this->deletedMemberships as $membership) {
            $this->dispatcher()->dispatch(new ProjectMembershipPostDeleteEvent($membership));
        }

        $this->deletedMemberships = [];
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->container->get(__METHOD__);
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
