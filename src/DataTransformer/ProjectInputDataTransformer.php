<?php

declare(strict_types=1);

namespace App\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Exception\DeserializationException;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use ApiPlatform\Core\Validator\ValidatorInterface;
use App\Dto\ProjectInput;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Handles setting the creator.
 */
class ProjectInputDataTransformer implements DataTransformerInterface
{
    /**
     * @var User
     */
    private $user;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * ProjectInputDataTransformer constructor.
     *
     * @throws DeserializationException when no authenticated user is found
     */
    public function __construct(
        TokenStorageInterface $tokenStorage, ValidatorInterface $validator)
    {
        $this->user = $tokenStorage->getToken()
            ? $tokenStorage->getToken()->getUser()
            : null;
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     *
     * @param ProjectInput $object
     *
     * @return Project
     */
    public function transform($object, string $to, array $context = [])
    {
        // this evaluates all constraint annotations on the DTO
        $context['groups'][] = 'Default';
        $this->validator->validate($object, $context);

        /* @var $project Project */
        $project = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE]
            ?? new Project();

        if ($object->council) {
            $project->setCouncil($object->council);
        }

        if (null !== $object->locked) {
            $project->setLocked($object->locked);
        }

        if (null !== $object->state) {
            $project->setState($object->state);
        }

        if (null !== $object->categories) {
            $project->setCategories($object->categories);
        }

        // creator is optional, we can create projects when a user registers
        // so the creator is set afterwards by the userInput Transformer
        if (!$project->getId() && $this->user instanceof UserInterface) {
            $project->setCreatedBy($this->user);
        }

        // When a user creates a new project set him as coordinator.
        if (!$project->getId()) {
            $membership = new ProjectMembership();
            $membership->setRole(ProjectMembership::ROLE_COORDINATOR);
            $project->addMembership($membership);

            // This can also happen when a user registers, then the user is
            // set afterwards by the userInput transformer.
            if ($this->user instanceof UserInterface) {
                $membership->setUser($this->user);
            }

            if (null !== $object->motivation) {
                $membership->setMotivation($object->motivation);
            }

            if (null !== $object->skills) {
                $membership->setSkills($object->skills);
            }

            $this->validator->validate($membership, $context);
        }

        $this->setProfileData($object, $project);

        return $project;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Project) {
            return false;
        }

        return Project::class === $to && null !== ($context['input']['class'] ?? null);
    }

    protected function setProfileData(ProjectInput $data, Project $project)
    {
        if (null !== $data->goal) {
            $project->setGoal($data->goal);
        }

        if (null !== $data->impact) {
            $project->setImpact($data->impact);
        }

        if (null !== $data->title) {
            $project->setTitle($data->title);
        }

        if (null !== $data->topic) {
            $project->setTopic($data->topic);
        }

        if (null !== $data->featureImage) {
            $project->setFeatureImage($data->featureImage);
        }
    }
}
