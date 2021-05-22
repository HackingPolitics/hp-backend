<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\CounterArgument;
use App\Event\Entity\CounterArgumentEvent;

abstract class ApiCounterArgumentEvent extends CounterArgumentEvent
{
    public array $context;

    public function __construct(CounterArgument $counterArgument, array $context)
    {
        parent::__construct($counterArgument);
        $this->context = $context;
    }
}
