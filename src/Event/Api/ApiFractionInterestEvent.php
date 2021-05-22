<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\FractionInterest;
use App\Event\Entity\FractionInterestEvent;

abstract class ApiFractionInterestEvent extends FractionInterestEvent
{
    public array $context;

    public function __construct(FractionInterest $fractionInterest, array $context)
    {
        parent::__construct($fractionInterest);
        $this->context = $context;
    }
}
