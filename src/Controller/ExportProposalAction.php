<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Proposal;
use App\Message\ExportProposalMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ExportProposalAction
{
    public function __invoke(
        Request $request,
        Proposal $data,
        UserInterface $user,
        MessageBusInterface $bus
    ): JsonResponse {
        $bus->dispatch(
            new ExportProposalMessage($data->getId(), $user->getId())
        );

        // return 202: the action has not yet been enacted
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_ACCEPTED);
    }
}
