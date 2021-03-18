<?php

declare(strict_types=1);

namespace App\Controller;

use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Entity\User;
use App\Event\Api\ApiPasswordResetEvent;
use App\Message\UserForgotPasswordMessage;
use App\Security\Voter\AccessBlockedVoter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PasswordResetAction
{
    public function __invoke(
        Request $request,
        ManagerRegistry $registry,
        MessageBusInterface $bus,
        Security $security,
        ValidatorInterface $validator,
        EventDispatcherInterface $dispatcher,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        if ($security->isGranted(User::ROLE_USER)) {
            throw new AccessDeniedException('Forbidden for authenticated users.');
        }

        // DTO was validated by the DataTransformer, username & validationUrl
        // should be there
        $params = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $entityManager = $registry->getManagerForClass(User::class);
        $user = $entityManager->getRepository(User::class)
            ->loadUserByUsername($params['username']);

        if (!$authorizationChecker->isGranted(AccessBlockedVoter::PW_RESET,
                $user ? $user->getUsername() : null)
        ) {
            throw new AccessDeniedHttpException("Access blocked, to many requests.");
        }

        $event = new ApiPasswordResetEvent($user, false);
        if ($user && $user->isActive()) {
            $bus->dispatch(
                new UserForgotPasswordMessage($user->getId(), $params['validationUrl'])
            );
            $event->success = true;
        }

        $dispatcher->dispatch($event);

        // always return success to not leak information about which accounts
        // exist and which not.
        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
