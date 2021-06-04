<?php

namespace App\Security\Voter;

use App\Entity\UsedNegation;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UsedNegationVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject): bool
    {
        return in_array($attribute, ['CREATE', 'DELETE'])
            && $subject instanceof UsedNegation;
    }

    /**
     * {@inheritdoc}
     *
     * @param UsedNegation $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        $negation = $subject->getNegation();
        if (!$negation) {
            // on creation handled by validator, else it's an error
            return 'CREATE' === $attribute;
        }

        $project = $negation->getCounterArgument()
            ? $negation->getCounterArgument()->getProject()
            : null;
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
