<?php

namespace App\Security\Voter;

use App\Entity\UsedArgument;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UsedArgumentVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, ['CREATE', 'DELETE'])
            && $subject instanceof UsedArgument;
    }

    /**
     * {@inheritdoc}
     *
     * @param UsedArgument $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        $argument = $subject->getArgument();
        if (!$argument) {
            // on creation handled by validator, else it's an error
            return 'CREATE' === $attribute;
        }

        $project = $argument->getProject();
        if (!$project) {
            return false;
        }

        switch ($attribute) {
            case 'CREATE':
                // fall through
            case 'DELETE':
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
                    return true;
                }

                if ($project->isLocked()) {
                    return false;
                }

                return $project->userCanWrite($user);
        }

        return false;
    }
}
