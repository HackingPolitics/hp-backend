<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\UsedActionMandate;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an usedActionMandate.
 */
abstract class UsedActionMandateEvent extends Event
{
    public UsedActionMandate $usedActionMandate;

    public function __construct(UsedActionMandate $usedActionMandate)
    {
        $this->usedActionMandate = $usedActionMandate;
    }
}
