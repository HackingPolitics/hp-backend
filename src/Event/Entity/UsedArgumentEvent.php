<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedArgument;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedArgument.
 */
abstract class UsedArgumentEvent extends Event
{
    public UsedArgument $usedArgument;

    public function __construct(UsedArgument $usedArgument)
    {
        $this->usedArgument = $usedArgument;
    }
}
