<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched to notify a user that his password was reset and send
 * him a confirmation URL at which he can set his new password.
 * The validation URL depends on the client and needs to be injected.
 */
class NewUserPasswordMessage extends UserMessage
{
    public string $validationUrl;

    public function __construct(int $userId, string $validationUrl)
    {
        parent::__construct($userId);
        $this->validationUrl = $validationUrl;
    }
}
