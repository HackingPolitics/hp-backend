<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Proposal;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a proposal.
 */
abstract class ProposalEvent extends Event
{
    public Proposal $proposal;

    public function __construct(Proposal $proposal)
    {
        $this->proposal = $proposal;
    }
}
