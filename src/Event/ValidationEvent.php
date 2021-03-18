<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Validation;
use Symfony\Contracts\EventDispatcher\Event;

abstract class ValidationEvent extends Event
{
    public Validation $validation;

    public function __construct(Validation $validation)
    {
        $this->validation = $validation;
    }
}
