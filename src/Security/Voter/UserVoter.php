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
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
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

                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
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

                foreach ($subject->getProjectMemberships() as $membership) {
                    if ($membership->getRole() !== ProjectMembership::ROLE_COORDINATOR) {
                        continue;
                    }

                    $project = $membership->getProject();
                    $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);
                    if (count($coordinators) > 1) {
                        continue;
                    }

                    $writers = $project->getMembersByRole(ProjectMembership::ROLE_WRITER);
                    if (count($writers) > 0) {
                        // the user is a project coordinator, the projects has only
                        // 1 coordinator but has writers -> cannot delete, transfer
                        // coordinator role first
                        return false;
                    }
                }

                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
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
