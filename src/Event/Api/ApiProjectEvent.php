<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Project;
use App\Event\Entity\ProjectEvent;

abstract class ApiProjectEvent extends ProjectEvent
{
    public array $context;

    public function __construct(Project $project, array $context)
    {
        parent::__construct($project);
        $this->context = $context;
    }
}
