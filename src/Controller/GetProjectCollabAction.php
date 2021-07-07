<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use HtmlToProseMirror\Renderer as Converter;
use Symfony\Component\HttpFoundation\JsonResponse;

class GetProjectCollabAction
{
    public function __invoke(Project $project): JsonResponse {
        $converter = new Converter();
        // @todo restrict Nodes/Marks for output? Should not be necessary

        return new JsonResponse([
            'collabData' => [
                // the converter cannot handle empty values, give him an empty <p> to feed on...
                'description' => $converter->render($project->getDescription() ?: '<p></p>')
            ]
        ]);
    }
}
