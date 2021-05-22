<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\FractionDetails;
use App\Event\Entity\FractionDetailsEvent;

abstract class ApiFractionDetailsEvent extends FractionDetailsEvent
{
    public array $context;

    public function __construct(FractionDetails $fractionDetails, array $context)
    {
        parent::__construct($fractionDetails);
        $this->context = $context;
    }
}
