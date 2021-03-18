<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\User;
use App\Event\Entity\UserEvent;

abstract class ApiUserEvent extends UserEvent
{
    public array $context;

    public function __construct(User $user, array $context)
    {
        parent::__construct($user);
        $this->context = $context;
    }
}
