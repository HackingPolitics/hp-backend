<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ProjectMembership;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

class UserInput
{
    /**
     * @Assert\NotBlank(groups={"user:resetPassword"})
     * @Groups({
     *     "user:admin-write",
     *     "user:pm-write",
     *     "user:register",
     *     "user:resetPassword"
     * })
     */
    public ?string $username = null;

    /**
     * @Assert\NotBlank(groups={"user:changeEmail"})
     * @Groups({
     *     "user:admin-write",
     *     "user:pm-write",
     *     "user:changeEmail",
     *     "user:register"
     * })
     */
    public ?string $email = null;

    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank(groups={"user:register", "user:changePassword"}),
     *     @Assert\Length(min=6, max=200),
     *     @VrokAssert\PasswordStrength(minStrength=19),
     *     @Assert\NotCompromisedPassword,
     * })
     * @Groups({"user:register", "user:admin-write", "user:pm-write", "user:changePassword"})
     */
    public ?string $password = null;

    /**
     * @Assert\NotBlank(groups={"user:changeEmail", "user:changePassword"})
     * @Groups({"user:changeEmail", "user:changePassword"})
     */
    public ?string $confirmationPassword = null;

    /**
     * @Groups({"user:admin-write", "user:pm-write"})
     */
    public ?array $roles = null;

    /**
     * @var bool
     * @Groups({"user:admin-write", "user:pm-write"})
     */
    public ?bool $active = null;

    /**
     * @var bool
     * @Groups({"user:admin-write", "user:pm-write"})
     */
    public ?bool $validated = null;

    /**
     * @var ?string
     * @Groups({"user:write"})
     */
    public ?string $firstName = null;

    /**
     * @var ?string
     * @Groups({"user:write"})
     */
    public ?string $lastName = null;

    /**
     * no need for @Assert\Valid, the ProjectInputs are validated anyways by
     * the ProjectInputDataTransformer called by the UserInputDataTransformer.
     *
     * @var ProjectInput[]
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Type(type=ProjectInput::class)
     * })
     * @Groups({"user:register"})
     */
    public array $createdProjects = [];

    /**
     * @var ProjectMembership[]
     *
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Type(type=ProjectMembership::class)
     * })
     * @Groups({"user:register"})
     */
    public array $projectMemberships = [];

    /**
     * @var ?string
     * @Assert\NotBlank(allowNull=false, groups={
     *     "user:changeEmail",
     *     "user:newPassword",
     *     "user:register",
     *     "user:resetPassword"
     * })
     * @Assert\Regex(
     *     pattern="/{{token}}/",
     *     message="Token placeholder is missing."
     * )
     * @Assert\Regex(
     *     pattern="/{{id}}/",
     *     message="ID placeholder is missing."
     * )
     * @Groups({
     *     "user:changeEmail",
     *     "user:newPassword",
     *     "user:register",
     *     "user:resetPassword"
     * })
     */
    public ?string $validationUrl = null;
}
