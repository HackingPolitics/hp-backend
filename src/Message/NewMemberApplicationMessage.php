<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched to notify the project coordinators when a new member application
 * is available.
 * Dispatched when
 * - an user registers (with a membership) and is automatically validated & active
 * - an user (with a membership) validates his account
 * - an existing user applies for a project.
 */
class NewMemberApplicationMessage extends UserMessage
{
    public int $projectId;

    public function __construct(int $userId, int $projectId)
    {
        parent::__construct($userId);
        $this->projectId = $projectId;
    }
}
