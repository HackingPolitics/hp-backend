<?php

namespace App\Security\Voter;

use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['READ', 'EDIT', 'DELETE'])
                && $subject instanceof User;
    }

    /**
     * {@inheritdoc}
     *
     * @param User $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        switch ($attribute) {
            case 'READ':
                if ($user->hasRole(User::ROLE_ADMIN)) {
                    return true;
                }

                if ($subject->isDeleted()) {
                    return false;
                }

                if ($subject->getId() == $user->getId()) {
                    // check should not be necessary, only a valid & active
                    // user can login
                    return $subject->isActive() && $subject->isValidated();
                }

                return false;

            case 'EDIT':
                if ($subject->isDeleted()) {
                    return false;
                }

                if ($user->hasRole(User::ROLE_ADMIN)) {
                    return true;
                }

                if ($subject->getId() == $user->getId()) {
                    // check should not be necessary, only a valid & active
                    // user can login
                    return $subject->isActive() && $subject->isValidated();
                }

                return false;

            case 'DELETE':
                if ($subject->isDeleted()) {
                    return false;
                }

                if ($user->hasRole(User::ROLE_ADMIN)) {
                    return true;
                }

                if ($subject->getId() == $user->getId()) {
                    // check should not be necessary, only a valid & active
                    // user can login
                    return $subject->isActive() && $subject->isValidated();
                }

                return false;
        }

        return false;
    }
}
