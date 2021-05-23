<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\ActionMandate;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for an action mandate.
 */
abstract class ActionMandateEvent extends Event
{
    public ActionMandate $actionMandate;

    public function __construct(ActionMandate $actionMandate)
    {
        $this->actionMandate = $actionMandate;
    }
}
