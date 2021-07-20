<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Triggers the creation of ODT/PDF export of the given document.
 */
class ExportProposalMessage
{
    public int $proposalId;
    public int $userId;

    public function __construct(
        int $proposalId,
        int $userId
    ) {
        $this->proposalId = $proposalId;
        $this->userId = $userId;
    }
}
