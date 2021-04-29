<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\ProjectMembership;
use App\Event\Entity\ProjectMembershipEvent;

class ApiProjectMembershipPreDeleteEvent extends ProjectMembershipEvent
{
    public array $context;

    public function __construct(ProjectMembership $membership, array $context)
    {
        parent::__construct($membership);
        $this->context = $context;
    }
}
