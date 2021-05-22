<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Argument;
use App\Event\Entity\ArgumentEvent;

abstract class ApiArgumentEvent extends ArgumentEvent
{
    public array $context;

    public function __construct(Argument $argument, array $context)
    {
        parent::__construct($argument);
        $this->context = $context;
    }
}
