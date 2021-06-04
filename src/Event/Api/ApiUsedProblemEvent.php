<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\UsedProblem;
use App\Event\Entity\UsedProblemEvent;

abstract class ApiUsedProblemEvent extends UsedProblemEvent
{
    public array $context;

    public function __construct(UsedProblem $usedProblem, array $context)
    {
        parent::__construct($usedProblem);
        $this->context = $context;
    }
}
