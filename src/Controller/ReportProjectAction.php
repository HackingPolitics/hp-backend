<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Event\Api\ApiProjectReportEvent;
use App\Message\ProjectReportedMessage;
use App\Security\Voter\AccessBlockedVoter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ReportProjectAction
{
    public function __invoke(
        Request $request,
        Project $data,
        EventDispatcherInterface $dispatcher,
        MessageBusInterface $bus,
        AuthorizationCheckerInterface $authorizationChecker
    ): JsonResponse {
        if (!$authorizationChecker->isGranted(AccessBlockedVoter::REPORT_PROJECT)) {
            throw new AccessDeniedHttpException('Access blocked, to many requests.');
        }

        // DTO was validated by the DataTransformer
        $params = json_decode($request->getContent(), true);

        $bus->dispatch(
            new ProjectReportedMessage(
                $data->getId(),
                $params['reportMessage'],
                $params['reporterName'],
                $params['reporterEmail']
            )
        );

        // to allow logging etc.
        // the kernel.view event is not triggered if we return a Response
        $event = new ApiProjectReportEvent($data, []);
        $dispatcher->dispatch($event);

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
