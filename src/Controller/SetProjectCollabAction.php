<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use ProseMirrorToHtml\Renderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SetProjectCollabAction
{
    public function __invoke(
        Request $request,
        Project $project
    ): JsonResponse {
        // not associative! The PM->HTML Renderer needs the objects
        $input = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        if (!$input || empty($input->collabData)) {
            throw new BadRequestHttpException('Missing "collabData" in request!');
        }

        $renderer = new Renderer();
        // @todo restrict Nodes/Marks
        // @see https://github.com/ueberdosis/prosemirror-to-html/#custom-nodes

        if (!empty($input->collabData->description)) {
            $project->setDescription($renderer->render($input->collabData->description));
        }

        // return 204: successful, but no content
        return new JsonResponse([
            'success' => true,
            'message' => 'Request received',
        ], Response::HTTP_NO_CONTENT);
    }
}
