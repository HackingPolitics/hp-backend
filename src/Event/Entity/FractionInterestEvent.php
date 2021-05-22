<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\FractionInterest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for fraction interests.
 */
abstract class FractionInterestEvent extends Event
{
    public FractionInterest $application;

    public function __construct(FractionInterest $fractionInterest)
    {
        $this->application = $fractionInterest;
    }
}
