<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedFractionInterest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedFractionInterest.
 */
abstract class UsedFractionInterestEvent extends Event
{
    public UsedFractionInterest $usedFractionInterest;

    public function __construct(UsedFractionInterest $usedFractionInterest)
    {
        $this->usedFractionInterest = $usedFractionInterest;
    }
}
