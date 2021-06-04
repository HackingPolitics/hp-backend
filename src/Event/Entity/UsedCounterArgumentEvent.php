<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedCounterArgument;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedCounterArgument.
 */
abstract class UsedCounterArgumentEvent extends Event
{
    public UsedCounterArgument $usedCounterArgument;

    public function __construct(UsedCounterArgument $usedCounterArgument)
    {
        $this->usedCounterArgument = $usedCounterArgument;
    }
}
