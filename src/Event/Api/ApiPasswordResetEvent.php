<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class ApiPasswordResetEvent extends Event
{
    public ?User $user;
    public bool $success;

    public function __construct(?User $user = null, bool $success = false)
    {
        $this->user = $user;
        $this->success = $success;
    }
}
