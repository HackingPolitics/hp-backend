<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    private AccessBlockService $accessBlock;

    public function __construct(AccessBlockService $accessBlock)
    {
        $this->accessBlock = $accessBlock;
    }

    /**
     * Which messages are shown before the password is checked?
     */
    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof User) {
            return;
        }

        // isDeleted wird bereits im UserRepository geprüft
    }

    /**
     * Which messages are shown after the password was checked?
     * The user already authenticated here, we can show him internal
     * details to his aacount.
     */
    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isValidated()) {
            $e = new Exception\AccountNotValidatedException();
            $e->setUser($user);
            throw $e;
        }

        if (!$user->isActive()) {
            $e = new Exception\AccountNotActivatedException();
            $e->setUser($user);
            throw $e;
        }
    }
}
