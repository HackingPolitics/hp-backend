<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Negation;
use App\Event\Entity\NegationEvent;

abstract class ApiNegationEvent extends NegationEvent
{
    public array $context;

    public function __construct(Negation $negation, array $context)
    {
        parent::__construct($negation);
        $this->context = $context;
    }
}
