<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProjectMembershipVoter extends Voter
{
    /**
     * {@inheritdoc}
     */
    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['EDIT', 'DELETE'])
            && $subject instanceof ProjectMembership;
    }

    /**
     * {@inheritdoc}
     *
     * @param ProjectMembership $subject
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof User) {
            return false;
        }

        /** @var Project $project */
        $project = $subject->getProject();

        switch ($attribute) {
            case 'EDIT':
                // no changes to a deleted project, membership can only
                // be deleted
                if ($project->isDeleted()) {
                    return false;
                }

                // admins can edit any membership
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
                    return true;
                }

                // no edits for members if the project is locked,
                // they can only remove their membership in this state
                if ($project->isLocked()) {
                    return false;
                }

                // users can edit their own membership/application
                if ($user->getId() == $subject->getUser()->getId()) {
                    return true;
                }

                // project coordinators can edit applications/memberships for other
                // members of their project
                return ProjectMembership::ROLE_COORDINATOR === $project->getUserRole($user);

            case 'DELETE':
                // the last/only coordinator cannot be deleted if there
                // are other members that could be upgraded beforehand
                if (ProjectMembership::ROLE_COORDINATOR === $subject->getRole()) {
                    $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);
                    $writers = $project->getMembersByRole(ProjectMembership::ROLE_WRITER);

                    if (1 === count($coordinators)
                        && count($writers) > 0
                    ) {
                        return false;
                    }
                }

                // admins can delete any membership
                if ($user->hasRole(User::ROLE_ADMIN)
                    || $user->hasRole(User::ROLE_PROCESS_MANAGER)
                ) {
                    return true;
                }

                // members can remove themselves from a project / retract
                // their application, even when it is locked/deactivated/deleted,
                // to cleanup their project list
                if ($subject->getUser()->getId() === $user->getId()) {
                    return true;
                }

                if ($project->isDeleted()
                    || $project->isLocked()
                ) {
                    return false;
                }

                // coordinators can remove other members that aren't coordinators
                if (ProjectMembership::ROLE_COORDINATOR === $project->getUserRole($user)) {
                    return ProjectMembership::ROLE_COORDINATOR !== $subject->getRole();
                }

                return false;
        }

        return false;
    }
}
