<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedArgument;
use App\Event\Entity\UsedArgumentEvent;

abstract class ApiUsedArgumentEvent extends UsedArgumentEvent
{
    public array $context;

    public function __construct(UsedArgument $usedArgument, array $context)
    {
        parent::__construct($usedArgument);
        $this->context = $context;
    }
}
