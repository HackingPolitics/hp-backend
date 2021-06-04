<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedNegation;
use App\Event\Entity\UsedNegationEvent;

abstract class ApiUsedNegationEvent extends UsedNegationEvent
{
    public array $context;

    public function __construct(UsedNegation $usedNegation, array $context)
    {
        parent::__construct($usedNegation);
        $this->context = $context;
    }
}
