<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use HtmlToProseMirror\Renderer;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetProjectCollabAction
{
    public function __invoke(Project $project): JsonResponse {
        $renderer = new Renderer();
        // @todo restrict Nodes/Marks for output? Should not be necessary

        return new JsonResponse([
            'collabData' => [
                'description' => $renderer->render($project->getDescription())
            ]
        ]);
    }
}
