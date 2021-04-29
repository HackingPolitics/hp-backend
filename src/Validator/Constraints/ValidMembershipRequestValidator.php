<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidMembershipRequestValidator extends ConstraintValidator
{
    protected EntityManagerInterface $manager;

    protected Security $security;

    public function __construct(EntityManagerInterface $manager, Security $security)
    {
        $this->manager = $manager;
        $this->security = $security;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $value
     *
     * @throws UnexpectedTypeException
     */
    public function validate($object, Constraint $constraint)
    {
        if (!$constraint instanceof ValidMembershipRequest) {
            throw new UnexpectedTypeException($constraint, ValidMembershipRequest::class);
        }

        if (!$object instanceof ProjectMembership) {
            throw new UnexpectedTypeException($object, Project::class);
        }

        if (!$object->getRole() || !$object->getProject() || !$object->getUser()) {
            // should be handled by a NotBlank constraint
            return;
        }

        switch($this->context->getGroup()) {
            case 'user:register':
                $this->onRegistration($object, $constraint);
                return;

            case 'projectMembership:create':
                $this->onCreate($object, $constraint);
                return;

            case 'projectMembership:write':
                $this->onUpdate($object, $constraint);
                return;

            default:
                throw new \RuntimeException("Unexpected validation group '{$this->context->getGroup()}'' found!'");
        }
    }

    private function onRegistration(ProjectMembership $membership, Constraint $constraint)
    {
        // if the user registers with a new project where he will be
        // added as coordinator this method is _not_ called (as no user
        // is set on the membership object), so this is only for registrations
        // that contain an application

        if (ProjectMembership::ROLE_APPLICANT !== $membership->getRole()) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        /** @var Project $project */
        $project = $membership->getProject();

        if ($project->isLocked()
            || $project->isDeleted()
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        // should not happen, just to be save
        if ($membership->getProject()->userIsMember($membership->getUser())) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }
    }

    private function onCreate(ProjectMembership $membership, Constraint $constraint)
    {
        /** @var Project $project */
        $project = $membership->getProject();

        if ($project->isLocked()
            || $project->isDeleted()
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
            return;
        }

        if ($project->userIsMember($membership->getUser())) {
            // User can only have one membership type -> handled by the
            // unique entity validator
            return;
        }

        $currentUser = $this->security->getUser();

        // we require a logged in user to continue
        // @todo refactor to work in the messenger queue?
        if (!$currentUser instanceof User) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }

        if ($project->getUserRole($currentUser) === ProjectMembership::ROLE_COORDINATOR) {
            // @todo implement invitation via Validations so users not
            // already existing on the platform can be invited to participate

            // coordinators can only create "normal" members
            if (ProjectMembership::ROLE_APPLICANT === $membership->getRole()) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();
            }

            return;
        }

        // @todo allow the ProcessManager to create memberships?

        // a user can only:
        // * apply for a membership, not add himself as writer/coordinator/observer
        // * apply himself, not another user
        if (ProjectMembership::ROLE_APPLICANT !== $membership->getRole()
            || $currentUser->getId() !== $membership->getUser()->getId()
        ) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();
        }
    }

    private function onUpdate(ProjectMembership $membership, Constraint $constraint)
    {
        /** @var Project $project */
        $project = $membership->getProject();
        /** @var User $member */
        $member = $membership->getUser();

        $oldObject = $this->manager->getUnitOfWork()
            ->getOriginalEntityData($membership);
        $oldRole = $oldObject['role'];
        $newRole = $membership->getRole();

        // an application cannot be updated (only upgraded to a normal membership
        // or deleted) and an existing membership cannot be downgraded to an application
        if ($newRole === ProjectMembership::ROLE_APPLICANT) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();

            return;
        }

        // user and project cannot be changed
        if ($member->getId() !== $oldObject['user_id']
            || $project->getId() !== $oldObject['project_id']
        ) {
            // should be already handled by serialization group annotation
            // and return "extra attributes user|project not allowed"
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();

            return;
        }

        if (ProjectMembership::ROLE_COORDINATOR === $oldRole
            && ProjectMembership::ROLE_COORDINATOR !== $newRole
        ) {
            $coordinators = $project->getMembersByRole(ProjectMembership::ROLE_COORDINATOR);

            // a coordinator cannot be downgraded if he is the only coordinator,
            // neither by himself nor by ProcessManagers/Admins
            if (count($coordinators) === 0) {
                $this->context
                    ->buildViolation($constraint->coordinatorDowngradeMessage)
                    ->addViolation();

                return;
            }
        }

        $currentUser = $this->security->getUser();

        // we require a logged in user to continue
        // @todo refactor to work in the messenger queue?
        if (!$currentUser instanceof User) {
            $this->context
                ->buildViolation($constraint->message)
                ->addViolation();

            return;
        }

        if ($currentUser->getId() === $member->getId()) {
            // only a coordinator can change is own role
            if ($oldRole !== ProjectMembership::ROLE_COORDINATOR
                && $newRole !== $oldRole
            ) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();

                return;
            }

            // but he can do everything else with his own membership -> return here
            return;
        }

        if ($project->getUserRole($currentUser) === ProjectMembership::ROLE_COORDINATOR) {
            // a coordinator cannot downgrade other coordinators
            if (ProjectMembership::ROLE_COORDINATOR === $oldRole
                && ProjectMembership::ROLE_COORDINATOR !== $newRole
            ) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();

                return;
            }

            // motivation and skills cannot be changed by coordinators
            if ($oldObject['motivation'] !== $membership->getMotivation()
                || $oldObject['skills'] !== $membership->getSkills()
            ) {
                $this->context
                    ->buildViolation($constraint->message)
                    ->addViolation();

                return;
            }

            // he can upgrade/downgrade normal members, upgrade applications,
            // edit the tasks
            return;
        }

        // the user is not the project coordinator and it's not his own membership
        // -> he can only be admin or process manager, everything else is forbidden
        // by the Voter -> nothing more to check
    }
}
