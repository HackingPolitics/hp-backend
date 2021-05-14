<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\ActionLog;
use App\Event\Api\ApiPasswordResetEvent;
use App\Message\PurgeValidationsMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Vrok\SymfonyAddons\Event\CronDailyEvent;

class ValidationEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            CronDailyEvent::class => [
                ['onCronDaily', 100],
            ],

            // nothing to do here, @see onKernelException
            //ValidationExpiredEvent::class => [],

            KernelEvents::EXCEPTION=> [
                ['onKernelException', 100],
            ],
            ApiPasswordResetEvent::class=> [
                ['onPasswordReset', 100],
            ],
        ];
    }

    public function onCronDaily(): void
    {
        $this->messageBus()->dispatch(new PurgeValidationsMessage());
        $this->logger()->debug('Daily request to purge expired validation was sent to the message queue.');
    }

    /**
     * This handles three cases:
     * * NotFoundHttpException triggerd by ApiPlatform when the validation
     *   with the requested ID was not found
     * * NotFoundHttpException by the ValidationConfirmAction when the token
     *   does not match
     * * NotFoundHttpException by the ValidationConfirmAction when the validation
     *   is in the database but already expired.
     *
     * We don't use specific events because we have no way to differentiate
     * the NotFoundHttpExceptions to detect if the event was handled before
     * to prevent duplicate log entries.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $this->requestStack()->getCurrentRequest();
        if (!$request) {
            // should not happen, no request -> no HttpException
            return;
        }

        $routeName = $request->attributes->get('_route');
        if ('api_validations_confirm_item' !== $routeName) {
            return;
        }

        $user = $this->security()->getUser();

        $log = new ActionLog();
        $log->action = ActionLog::FAILED_VALIDATION;
        $log->ipAddress = $request->getClientIp();
        $log->username = $user instanceof UserInterface
            ? $user->getUsername()
            : null;
        $this->entityManager()->persist($log);
        $this->entityManager()->flush();
    }

    /**
     * A password reset request (/users/reset-password) is no a validation action
     * but leads to the creation of a validation.
     */
    public function onPasswordReset(ApiPasswordResetEvent $event): void
    {
        $request = $this->requestStack()->getCurrentRequest();
        if (!$request) {
            // should not happen, no request -> PW Request
            return;
        }

        $log = new ActionLog();
        $log->ipAddress = $request->getClientIp();

        $log->action = $event->success
            ? ActionLog::SUCCESSFUL_PW_RESET_REQUEST
            : ActionLog::FAILED_PW_RESET_REQUEST;

        $log->username = $event->user instanceof UserInterface
            ? $event->user->getUsername()
            : null;

        $this->entityManager()->persist($log);
        $this->entityManager()->flush();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function logger(): LoggerInterface
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

    private function requestStack(): RequestStack
    {
        return $this->container->get(__METHOD__);
    }
}
