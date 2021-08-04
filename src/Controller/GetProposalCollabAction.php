<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Proposal;
use HtmlToProseMirror\Renderer as Converter;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetProposalCollabAction
{
    public function __invoke(Proposal $proposal): JsonResponse
    {
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
