<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedCounterArgument;
use App\Event\Entity\UsedCounterArgumentEvent;

abstract class ApiUsedCounterArgumentEvent extends UsedCounterArgumentEvent
{
    public array $context;

    public function __construct(UsedCounterArgument $usedCounterArgument, array $context)
    {
        parent::__construct($usedCounterArgument);
        $this->context = $context;
    }
}
