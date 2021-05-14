<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched when all project members have left a project and it was
 * automatically deactivated.
 */
class AllProjectMembersLeftMessage
{
    public int $projectId;

    public function __construct(int $projectId)
    {
        $this->projectId = $projectId;
    }
}
