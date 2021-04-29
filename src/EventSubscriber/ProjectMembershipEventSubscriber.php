<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Event\Api\ApiProjectMembershipPostCreateEvent;
use App\Event\Api\ApiProjectMembershipPostDeleteEvent;
use App\Event\Api\ApiProjectMembershipPreDeleteEvent;
use App\Event\Entity\ProjectMembershipPostDeleteEvent;
use App\Event\Entity\ProjectMembershipPreDeleteEvent;
use App\Message\AllProjectMembersLeftMessage;
use App\Message\NewMemberApplicationMessage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ProjectMembershipEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiProjectMembershipPostCreateEvent::class => [
                ['onApiPostCreate', 100],
            ],
            ApiProjectMembershipPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
            ApiProjectMembershipPostDeleteEvent::class => [
                ['onApiPostDelete', 100],
            ],
            ProjectMembershipPreDeleteEvent::class => [
                ['onPreDelete', 100],
            ],
            ProjectMembershipPostDeleteEvent::class => [
                ['onPostDelete', 100],
            ],
        ];
    }

    /**
     * Notify project coordinators of new applications.
     */
    public function onApiPostCreate(ApiProjectMembershipPostCreateEvent $event): void
    {
        $user = $event->membership->getUser();

        if ($event->membership->getRole() === ProjectMembership::ROLE_APPLICANT
            && $user->isValidated() && $user->isActive()
        ) {
            $this->messageBus()->dispatch(
                new NewMemberApplicationMessage(
                    $user->getId(),
                    $event->membership->getProject()->getId()
                )
            );
        }
    }

    public function onApiPreDelete(ApiProjectMembershipPreDeleteEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectMembershipPreDeleteEvent($event->membership));
    }

    public function onApiPostDelete(ApiProjectMembershipPostDeleteEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectMembershipPostDeleteEvent($event->membership));
    }

    /**
     * Mark a project as locked when the last (active) member leaves it.
     */
    public function onPreDelete(ProjectMembershipPreDeleteEvent $event): void
    {
        // only deactivate if the leaving member has an "active" role
        if ($event->membership->getRole() !== ProjectMembership::ROLE_COORDINATOR
            && $event->membership->getRole() !== ProjectMembership::ROLE_WRITER
        ) {
            return;
        }

        /** @var Project $project */
        $project = $event->membership->getProject();
        $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);
        $writers = $project->getMembersByRole(ProjectMembership::ROLE_WRITER);

        // === 1 because the membership is not yet deleted
        if ((count($coordinators) + count($writers)) === 1) {
            $event->membership->getProject()->setLocked(true);
        }
    }

    /**
     * Notify process managers when all (active) members left a project.
     */
    public function onPostDelete(ProjectMembershipPostDeleteEvent $event): void
    {
        // we don't need to notify PMs of their own actions
        if ($this->security()->isGranted(User::ROLE_PROCESS_MANAGER)) {
            return;
        }

        /** @var Project $project */
        $project = $event->membership->getProject();
        $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);
        $writers = $project->getMembersByRole(ProjectMembership::ROLE_WRITER);

        if ((count($coordinators) + count($writers)) === 0) {
            $this->messageBus()->dispatch(
                new AllProjectMembersLeftMessage(
                    $project->getId()
                )
            );
        }
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function messageBus(): MessageBusInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }
}
