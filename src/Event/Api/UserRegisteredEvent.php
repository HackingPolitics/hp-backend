<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\User;
use App\Event\Entity\UserEvent;

/**
 * Triggered when an user registers via the API.
 */
class UserRegisteredEvent extends UserEvent
{
    public string $validationUrl;

    public function __construct(User $user, string $validationUrl)
    {
        parent::__construct($user);
        $this->validationUrl = $validationUrl;
    }
}
