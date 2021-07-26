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
     * @param UserInput $data
     *
     * @return User
     */
    public function transform($data, string $to, array $context = [])
    {
        // this evaluates all constraint annotations on the DTO
        $context['groups'][] = 'Default';
        $this->validator()->validate($data, $context);

        $user = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? new User();

        if (null !== $data->username) {
            $user->setUsername($data->username);
        }

        if (null !== $data->email) {
            $user->setEmail($data->email);
        }

        if (null !== $data->roles) {
            $user->setRoles($data->roles);
        }

        if (null !== $data->validated) {
            $user->setValidated($data->validated);
        }

        if (null !== $data->active) {
            $user->setActive($data->active);
        }

        if (null !== $data->firstName) {
            $user->setFirstName($data->firstName);
        }

        if (null !== $data->lastName) {
            $user->setLastName($data->lastName);
        }

        if (!$user->getId() && !$data->password) {
            // no user is allowed to have an empty password
            // -> force-set a unknown random pw here, admin created user must
            // execute the password-reset mechanism
            $data->password = random_bytes(15);
        }

        // we have a (new) password given -> encode and replace the old one
        if (null !== $data->password) {
            $user->setPassword(
                $this->passwordHasher()->hashPassword($user, $data->password)
            );
        }

        foreach ($data->createdProjects as $projectData) {
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

        foreach ($data->projectMemberships as $membership) {
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
