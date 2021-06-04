<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedNegation;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedNegation.
 */
abstract class UsedNegationEvent extends Event
{
    public UsedNegation $usedNegation;

    public function __construct(UsedNegation $usedNegation)
    {
        $this->usedNegation = $usedNegation;
    }
}
