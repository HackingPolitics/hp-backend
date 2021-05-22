<?php

namespace App\Security\Voter;

use App\Entity\Negation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class NegationVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, ['CREATE', 'EDIT', 'DELETE'])
            && $subject instanceof Negation;
    }

    /**
     * {@inheritdoc}
     *
     * @param Negation $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        $counterArgument = $subject->getCounterArgument();
        if (!$counterArgument) {
            // on creation handled by validator, else it's an error
            return 'CREATE' === $attribute;
        }

        $project = $counterArgument->getProject();
        if (!$project) {
            return false;
        }

        switch ($attribute) {
            case 'CREATE':
                // fall through
            case 'EDIT':
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
