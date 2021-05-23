<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Negation;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a negation.
 */
abstract class NegationEvent extends Event
{
    public Negation $negation;

    public function __construct(Negation $negation)
    {
        $this->negation = $negation;
    }
}
