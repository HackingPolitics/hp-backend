<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Argument;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an argument.
 */
abstract class ArgumentEvent extends Event
{
    public Argument $argument;

    public function __construct(Argument $argument)
    {
        $this->argument = $argument;
    }
}
