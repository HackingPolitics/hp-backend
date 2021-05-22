<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\ActionMandate;
use App\Event\Entity\ActionMandateEvent;

abstract class ApiActionMandateEvent extends ActionMandateEvent
{
    public array $context;

    public function __construct(ActionMandate $actionMandate, array $context)
    {
        parent::__construct($actionMandate);
        $this->context = $context;
    }
}
