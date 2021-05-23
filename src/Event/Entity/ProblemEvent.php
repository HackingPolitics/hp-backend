<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Problem;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a problem.
 */
abstract class ProblemEvent extends Event
{
    public Problem $problem;

    public function __construct(Problem $problem)
    {
        $this->problem = $problem;
    }
}
