<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use App\Event\ValidationExpiredEvent;
use App\Message\UserValidatedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listen to the (account) validation events.
 */
class AccountValidationEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            ValidationConfirmedEvent::class => [
                ['onValidationConfirmed', 100],
            ],
            ValidationExpiredEvent::class => [
                ['onValidationExpired', 50],
            ],
        ];
    }

    /**
     * When an account validation was confirmed (via URL) set the validated flag.
     */
    public function onValidationConfirmed(ValidationConfirmedEvent $event)
    {
        if (Validation::TYPE_ACCOUNT !== $event->validation->getType()) {
            return;
        }

        if ($this->security()->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        $user = $event->validation->getUser();
        if ($user->isDeleted()) {
            throw new NotFoundHttpException('Corresponding user not found.');
        }

        // set validated flag
        $user->setValidated(true);

        // send a message to the queue to trigger additional async actions,
        $this->messageBus()->dispatch(
            new UserValidatedMessage($user->getId())
        );

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    /**
     * Called from the validation purge cron or when an expired validation
     * was requested via URL. Remove the corresponding user account if it
     * was not validated meanwhile.
     */
    public function onValidationExpired(ValidationExpiredEvent $event)
    {
        if (Validation::TYPE_ACCOUNT !== $event->validation->getType()) {
            return;
        }

        $user = $event->validation->getUser();

        // remove the non-validated user to allow re-registration.
        // keep the user if he is marked validated, e.g. by an admin
        if (!$user->isValidated()) {
            // we can safely remove the whole user
            $this->entityManager()->remove($user);
        }

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    private function entityManager(): EntityManagerInterface
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
