<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched to send the user a validation email with a confirmation URL at
 * which he can reset his password.
 * The validation URL depends on the client and needs to be injected.
 */
class UserForgotPasswordMessage extends UserMessage
{
    public string $validationUrl;

    public function __construct(int $userId, string $validationUrl)
    {
        parent::__construct($userId);
        $this->validationUrl = $validationUrl;
    }
}
