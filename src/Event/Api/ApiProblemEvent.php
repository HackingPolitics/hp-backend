<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Problem;
use App\Event\Entity\ProblemEvent;

abstract class ApiProblemEvent extends ProblemEvent
{
    public array $context;

    public function __construct(Problem $problem, array $context)
    {
        parent::__construct($problem);
        $this->context = $context;
    }
}
