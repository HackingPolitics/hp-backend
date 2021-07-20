<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Proposal;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Vich\UploaderBundle\Handler\DownloadHandler;

/**
 * We cannot use an ApiPlatform resource annotation / custom operation as this
 * does not allow form-encoded POSTs, so we have to make our own route & checks.
 */
class DownloadProposalDocumentAction
{
    /**
     * @Route("/proposals/{id}/document-download", name="proposal_documentDownload", methods="GET|POST")
     */
    public function __invoke(
        Proposal $proposal,
        Security $security,
        Request $request,
        DownloadHandler $handler
    ): StreamedResponse {
        // Allow GET in the route to show a sensible error message
        if ('POST' !== $request->getMethod()) {
            throw new BadRequestException('You cannot access this resource directly!');
        }

        if (!$proposal || !$proposal->getProject() || !$security->getUser()) {
            throw new UnauthorizedHttpException('Access Denied.');
        }

        if (!$security->isGranted('READ', $proposal->getProject())) {
            throw new AccessDeniedHttpException('Access Denied.');
        }

        if (!$proposal->getDocumentFile()) {
            throw new NotFoundHttpException('Not Found');
        }

        return $handler->downloadObject(
            $proposal->getDocumentFile(),
            'file',
            null,
            $proposal->getDocumentFile()->getOriginalName(),
            false
        );
    }
}
