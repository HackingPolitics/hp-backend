<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActionLog;
use App\Event\Api\ApiProjectPostCreateEvent;
use App\Event\Api\ApiProjectPostDeleteEvent;
use App\Event\Api\ApiProjectPostUpdateEvent;
use App\Event\Api\ApiProjectPreCreateEvent;
use App\Event\Api\ApiProjectPreDeleteEvent;
use App\Event\Api\ApiProjectPreUpdateEvent;
use App\Event\Api\ApiProjectReportEvent;
use App\Event\Entity\ProjectMembershipPostDeleteEvent;
use App\Event\Entity\ProjectMembershipPreDeleteEvent;
use App\Event\Entity\ProjectPostCreateEvent;
use App\Event\Entity\ProjectPostUpdateEvent;
use App\Event\Entity\ProjectPreCreateEvent;
use App\Event\Entity\ProjectPreUpdateEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class ProjectEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    private array $deletedMemberships = [];

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ApiProjectPreCreateEvent::class => [
                ['onApiPreCreate', 100],
            ],
            ApiProjectPostCreateEvent::class => [
                ['onApiPostCreate', 100],
            ],
            ApiProjectPreUpdateEvent::class => [
                ['onApiPreUpdate', 100],
            ],
            ApiProjectPostUpdateEvent::class => [
                ['onApiPostUpdate', 100],
            ],
            ApiProjectPreDeleteEvent::class => [
                ['onApiPreDelete', 100],
            ],
            ApiProjectPostDeleteEvent::class => [
                ['onApiPostDelete', 100],
            ],
            ApiProjectReportEvent::class => [
                ['onApiReport', 100],
            ],

            ProjectPreCreateEvent::class => [
                ['onPreCreate', 100],
            ],
        ];
    }

    public function onApiPreCreate(ApiProjectPreCreateEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectPreCreateEvent($event->project));
    }

    public function onApiPostCreate(ApiProjectPostCreateEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectPostCreateEvent($event->project));
    }

    public function onApiPreUpdate(ApiProjectPreUpdateEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectPreUpdateEvent($event->project));
    }

    public function onApiPostUpdate(ApiProjectPostUpdateEvent $event): void
    {
        $this->dispatcher()->dispatch(new ProjectPostUpdateEvent($event->project));
    }

    /**
     * If a Project is deleted remove its memberships.
     *
     * @todo use Symfony WorkFlows
     */
    public function onApiPreDelete(ApiProjectPreDeleteEvent $event): void
    {
        $this->deletedMemberships = [];

        foreach ($event->project->getMemberships() as $membership) {
            $this->dispatcher()->dispatch(new ProjectMembershipPreDeleteEvent($membership));

            // orphan removal will delete those memberships
            $event->project->removeMembership($membership);

            // collect the removed memberships to later trigger the postDelete event
            $this->deletedMemberships[] = $membership;
        }
    }

    /**
     * We removed memberships/applications -> we are responsible to trigger the postDelete event.
     */
    public function onApiPostDelete(ApiProjectPostDeleteEvent $event): void
    {
        foreach ($this->deletedMemberships as $membership) {
            $this->dispatcher()->dispatch(new ProjectMembershipPostDeleteEvent($membership));
        }

        $this->deletedMemberships = [];
    }

    public function onApiReport(/*ApiProjectReportEvent $event*/): void
    {
        $user = $this->security()->getUser();

        $log = new ActionLog();
        $log->action = ActionLog::REPORTED_PROJECT;
        $log->ipAddress = $this->requestStack()->getCurrentRequest()->getClientIp();
        $log->username = $user instanceof UserInterface
            ? $user->getUserIdentifier()
            : null;

        $this->entityManager()->persist($log);
        $this->entityManager()->flush();
    }

    public function onPreCreate(ProjectPreCreateEvent $event): void
    {
        $log = new ActionLog();
        $log->action = ActionLog::CREATED_PROJECT;
        $log->ipAddress = $this->requestStack()->getCurrentRequest()->getClientIp();
        $log->username = $event->project->getCreatedBy()->getUsername();
        $this->entityManager()->persist($log);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function requestStack(): RequestStack
    {
        return $this->container->get(__METHOD__);
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }
}
