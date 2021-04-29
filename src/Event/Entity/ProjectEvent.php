<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Project;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a project.
 */
abstract class ProjectEvent extends Event
{
    public Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }
}
