<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use Doctrine\Persistence\ManagerRegistry;
use HtmlToProseMirror\Renderer as Converter;
use ProseMirrorToHtml\Renderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SetProjectCollabAction
{
    public function __invoke(
        Request $request,
        Project $project,
        ManagerRegistry $registry
    ): JsonResponse {
        // not associative! The PM->HTML Renderer needs the objects
        $input = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        if (!$input || empty($input->collabData)) {
            throw new BadRequestHttpException('Missing "collabData" in request!');
        }

        $renderer = new Renderer();
        // @todo restrict Nodes/Marks
        // @see https://github.com/ueberdosis/prosemirror-to-html/#custom-nodes

        if (!empty($input->collabData->goal)) {
            $project->setGoal($renderer->render($input->collabData->goal));
        }

        $entityManager = $registry->getManagerForClass(Project::class);
        $entityManager->flush();

        $converter = new Converter();
        // @todo restrict Nodes/Marks for output? Should not be necessary

        return new JsonResponse([
            'collabData' => [
                // the converter cannot handle empty values, give him an empty <p> to feed on...
                'goal' => $converter->render($project->getGoal() ?: '<p></p>'),
            ],
        ]);
    }
}
