<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Proposal;
use Doctrine\Persistence\ManagerRegistry;
use HtmlToProseMirror\Renderer as Converter;
use ProseMirrorToHtml\Renderer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SetProposalCollabAction
{
    public function __invoke(
        Request         $request,
        Proposal        $proposal,
        ManagerRegistry $registry,
        LoggerInterface $appLogger,
    ): JsonResponse {
        // not associative! The PM->HTML Renderer needs the objects
        $input = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        if (!$input || empty($input->collabData)) {
            throw new BadRequestHttpException('Missing "collabData" in request!');
        }

        // @todo remove debug
       $appLogger->alert('collab', [$request->getContent()]);

        $renderer = new Renderer();
        // @todo restrict Nodes/Marks
        // @see https://github.com/ueberdosis/prosemirror-to-html/#custom-nodes

        if (!empty($input->collabData->actionMandate)) {
            $proposal->setActionMandate($renderer->render($input->collabData->actionMandate));
        }
        if (!empty($input->collabData->comment)) {
            $proposal->setComment($renderer->render($input->collabData->comment));
        }
        if (!empty($input->collabData->introduction)) {
            $proposal->setIntroduction($renderer->render($input->collabData->introduction));
        }
        if (!empty($input->collabData->reasoning)) {
            $proposal->setReasoning($renderer->render($input->collabData->reasoning));
        }

        $entityManager = $registry->getManagerForClass(Proposal::class);
        $entityManager->flush();

        $converter = new Converter();
        // @todo restrict Nodes/Marks for output? Should not be necessary

        return new JsonResponse([
            'collabData' => [
                // the converter cannot handle empty values, give him an empty <p> to feed on...
                'actionMandate' => $converter->render($proposal->getActionMandate() ?: '<p></p>'),
                'comment'       => $converter->render($proposal->getComment() ?: '<p></p>'),
                'introduction'  => $converter->render($proposal->getIntroduction() ?: '<p></p>'),
                'reasoning'     => $converter->render($proposal->getReasoning() ?: '<p></p>'),
            ],
        ]);
    }
}
