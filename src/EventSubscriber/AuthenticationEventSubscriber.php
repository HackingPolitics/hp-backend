<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActionLog;
use App\Security\AccessBlockService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class AuthenticationEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            // prio = 1000 to run before the user is loaded from the database (prio = 250)
            CheckPassportEvent::class => ['preCheckCredentials', 1000],

            LoginFailureEvent::class => 'onLoginFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }


    public function preCheckCredentials(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport instanceof UserPassportInterface || $passport->hasBadge(PreAuthenticatedUserBadge::class)) {
            return;
        }

        $badge = $passport->getBadge(UserBadge::class);
        if (!$badge) {
            throw new LogicException("No UserBadge for current login attempt!");
        }
        $identifier = $badge->getUserIdentifier();
        if (!$this->accessBlocker()->loginAllowed($identifier)) {
            // TooManyLoginAttemptsAuthenticationException would cause a 401, which
            // seems incorrect as the user had no chance to authenticate, his access was
            // "Forbidden", he is "not authorized to authenticate".
            throw new AccessDeniedHttpException('Access blocked, to many requests.');
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $badge = $event->getPassport()->getBadge(UserBadge::class);
        if (!$badge) {
            throw new LogicException("No UserBadge for current login attempt!");
        }

        $log = new ActionLog();
        $log->ipAddress = $event->getRequest()->getClientIp();
        $log->username = $badge->getUserIdentifier();
        $log->action = ActionLog::FAILED_LOGIN;

        $this->entityManager()->persist($log);
        $this->entityManager()->flush();
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $log = new ActionLog();
        $log->ipAddress = $event->getRequest()->getClientIp();
        $log->username = $event->getUser()->getUserIdentifier();
        $log->action = ActionLog::SUCCESSFUL_LOGIN;

        $this->entityManager()->persist($log);
        $this->entityManager()->flush();
    }

    private function accessBlocker(): AccessBlockService
    {
        return $this->container->get(__METHOD__);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }
}
