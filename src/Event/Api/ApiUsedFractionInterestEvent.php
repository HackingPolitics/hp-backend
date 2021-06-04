<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedFractionInterest;
use App\Event\Entity\UsedFractionInterestEvent;

abstract class ApiUsedFractionInterestEvent extends UsedFractionInterestEvent
{
    public array $context;

    public function __construct(UsedFractionInterest $usedFractionInterest, array $context)
    {
        parent::__construct($usedFractionInterest);
        $this->context = $context;
    }
}
