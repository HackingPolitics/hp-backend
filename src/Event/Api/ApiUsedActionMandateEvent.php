<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedActionMandate;
use App\Event\Entity\UsedActionMandateEvent;

abstract class ApiUsedActionMandateEvent extends UsedActionMandateEvent
{
    public array $context;

    public function __construct(UsedActionMandate $usedActionMandate, array $context)
    {
        parent::__construct($usedActionMandate);
        $this->context = $context;
    }
}
