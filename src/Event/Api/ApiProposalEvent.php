<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Proposal;
use App\Event\Entity\ProposalEvent;

abstract class ApiProposalEvent extends ProposalEvent
{
    public array $context;

    public function __construct(Proposal $proposal, array $context)
    {
        parent::__construct($proposal);
        $this->context = $context;
    }
}
