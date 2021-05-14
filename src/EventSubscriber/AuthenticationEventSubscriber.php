<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActionLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

class AuthenticationEventSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;

    private EntityManagerInterface $entityManager;

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationEvents::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
            AuthenticationEvents::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
        ];
    }

    public function __construct(RequestStack $requestStack, EntityManagerInterface $entityManager)
    {
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $log = new ActionLog();
        $log->ipAddress = $this->getRequest()->getClientIp();
        $log->username = $event->getAuthenticationToken()->getUsername();
        $log->action = ActionLog::FAILED_LOGIN;

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        // token refresh triggers this with an AnonymousToken -> ignore
        if (!$event->getAuthenticationToken() instanceof UsernamePasswordToken) {
            return;
        }

        $request = $this->getRequest();

        $log = new ActionLog();
        $log->ipAddress = $request->getClientIp();
        $log->username = $event->getAuthenticationToken()->getUsername();
        $log->action = ActionLog::SUCCESSFUL_LOGIN;

        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    private function getRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new \RuntimeException('No request.');
        }

        return $request;
    }
}
