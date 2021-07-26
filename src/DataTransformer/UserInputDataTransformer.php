<?php

declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\UserInput;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * Handles password encoding and setting a random password if none was given.
 */
class UserInputDataTransformer implements DataTransformerInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    /**
     * {@inheritdoc}
     *
     * @param UserInput $object
     *
     * @return User
     */
    public function transform($object, string $to, array $context = []): User
    {
        // this evaluates all constraint annotations on the DTO
        $context['groups'][] = 'Default';
        $this->validator()->validate($object, $context);

        $user = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? new User();

        if (null !== $object->username) {
            $user->setUsername($object->username);
        }

        if (null !== $object->email) {
            $user->setEmail($object->email);
        }

        if (null !== $object->roles) {
            $user->setRoles($object->roles);
        }

        if (null !== $object->validated) {
            $user->setValidated($object->validated);
        }

        if (null !== $object->active) {
            $user->setActive($object->active);
        }

        if (null !== $object->firstName) {
            $user->setFirstName($object->firstName);
        }

        if (null !== $object->lastName) {
            $user->setLastName($object->lastName);
        }

        if (!$user->getId() && !$object->password) {
            // no user is allowed to have an empty password
            // -> force-set a unknown random pw here, admin created user must
            // execute the password-reset mechanism
            $object->password = random_bytes(15);
        }

        // we have a (new) password given -> encode and replace the old one
        if (null !== $object->password) {
            $user->setPassword(
                $this->passwordHasher()->hashPassword($user, $object->password)
            );
        }

        foreach ($object->createdProjects as $projectData) {
            // the normalizer already created ProjectInputs from the JSON,
            // now convert to real projects
            $project = $this->projectTransformer()
                ->transform($projectData, Project::class, $context);

            // we don't have an @Assert\Valid on the users createdProjects
            // property as we don't want to validate all projects when only the
            // user data changes -> validate the project here, the
            // projectTransformer above only validated the ProjectInput
            $this->validator()->validate($project, $context);

            foreach ($project->getMemberships() as $membership) {
                $user->addProjectMembership($membership);
            }

            $user->addCreatedProject($project);
        }

        foreach ($object->projectMemberships as $membership) {
            $user->addProjectMembership($membership);

            // validate only after the user was set, to distinguish from
            // a project creation with a coordinator membership
            $this->validator()->validate($membership, $context);
        }

        return $user;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof User) {
            return false;
        }

        return User::class === $to && null !== ($context['input']['class'] ?? null);
    }

    private function projectTransformer(): ProjectInputDataTransformer
    {
        return $this->container->get(__METHOD__);
    }

    private function passwordHasher(): UserPasswordHasherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function validator(): ValidatorInterface
    {
        return $this->container->get(__METHOD__);
    }
}
