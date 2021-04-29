<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\ProjectMembership;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a project membership.
 */
abstract class ProjectMembershipEvent extends Event
{
    public ProjectMembership $membership;

    public function __construct(ProjectMembership $membership)
    {
        $this->membership = $membership;
    }
}
