<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Validation;
use App\Event\ValidationConfirmedEvent;
use App\Event\ValidationExpiredEvent;
use App\Security\Voter\AccessBlockedVoter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ValidationConfirmAction
{
    public function __invoke(
        Validation $data,
        Request $request,
        EventDispatcherInterface $dispatcher,
        ManagerRegistry $registry,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        if (!$authorizationChecker->isGranted(AccessBlockedVoter::VALIDATION_CONFIRM, null)) {
            throw new AccessDeniedHttpException('Access blocked, to many requests.');
        }

        // the DTO in $data was validated by the DataTransformer
        // so the token has the correct format
        $params = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if ($params['token'] !== $data->getToken()) {
            // don't trigger a special event for blocking IPs/Users -  we cannot
            // trigger an event when a validation is not found because of
            // unknown ID, so this is done by tracking the NotFoundHttpException

            // no special message, same as with unknown/invalid ID
            throw new NotFoundHttpException('Not Found');
        }

        $em = $registry->getManagerForClass(Validation::class);

        if ($data->isExpired()) {
            // trigger an event to allow deleting the expired user etc.
            // blocking IPs/Users is done by tracking the NotFoundHttpException
            $dispatcher->dispatch(new ValidationExpiredEvent($data));

            // the listeners don't need to remove the validation themselves,
            // also they shouldn't flush the EM. When they are called via the
            // purge event the messageHandler will remove & flush.
            $em->remove($data);
            $em->flush();

            // no special message, same as with unknown/invalid ID
            throw new NotFoundHttpException('Not Found');
        }

        unset($params['token']);

        // trigger follow-up actions e.g. for setting the validated flag on the
        // confirmed user account or changing the email address
        $dispatcher->dispatch(new ValidationConfirmedEvent($data, $params));

        // the listeners don't need to remove the validation themselves,
        // also they shouldn't flush the EM.
        $em->remove($data);
        $em->flush();

        // return 205: the server has fulfilled the request and the user agent
        // SHOULD reset the document view
        return new JsonResponse([
            'success' => true,
            'message' => 'Validation successful',
        ], Response::HTTP_RESET_CONTENT);
    }
}
