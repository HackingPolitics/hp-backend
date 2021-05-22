<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\CounterArgument;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a counter-argument.
 */
abstract class CounterArgumentEvent extends Event
{
    public CounterArgument $application;

    public function __construct(CounterArgument $counterArgument)
    {
        $this->application = $counterArgument;
    }
}
