<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\UserInput;
use App\Entity\User;
use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Listens to the (password-reset) validation events.
 */
class PasswordResetEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents()
    {
        return [
            ValidationConfirmedEvent::class => [
                ['onValidationConfirmed', 100],
            ],

            // nothing to do for expired pw reset, the validation object
            // is removed by the event trigger
            //ValidationExpiredEvent::class => [
            //    ['onValidationExpired', 100],
            //],
        ];
    }

    public function onValidationConfirmed(ValidationConfirmedEvent $event)
    {
        if (Validation::TYPE_RESET_PASSWORD !== $event->validation->getType()) {
            return;
        }

        if ($this->security()->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        $user = $event->validation->getUser();
        if ($user->isDeleted() || !$user->isActive()) {
            throw new NotFoundHttpException('Corresponding user not found.');
        }

        if (!isset($event->params['password'])) {
            throw new BadRequestHttpException('Parameter "password" is missing.');
        }

        // we need to validate the password manually
        $dto = new UserInput();
        $dto->password = $event->params['password'];
        $this->validator()->validate($dto);

        // do not allow the user to set the same password again
        $sameCheck = $this->passwordHasher()->isPasswordValid($user, $event->params['password']);
        if ($sameCheck) {
            throw new ValidationException(new ConstraintViolationList([new ConstraintViolation('validate.user.password.notChanged', null, [], null, 'password', $event->params['password'])]));
        }

        $user->setPassword(
            $this->passwordHasher()->hashPassword($user, $event->params['password'])
        );

        // no need to flush or remove the validation, this is done by the
        // event trigger.
    }

    private function passwordHasher(): UserPasswordHasherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function security(): Security
    {
        return $this->container->get(__METHOD__);
    }

    private function validator(): ValidatorInterface
    {
        return $this->container->get(__METHOD__);
    }
}
