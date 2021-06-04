<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedProblem;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedProblem.
 */
abstract class UsedProblemEvent extends Event
{
    public UsedProblem $usedProblem;

    public function __construct(UsedProblem $usedProblem)
    {
        $this->usedProblem = $usedProblem;
    }
}
