<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Validator\Constraints as AppAssert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * ProjectMembership.
 *
 * Collection cannot be queried, memberships can only be retrieved via the
 * user or project relations.
 * Item GET is required for API Platform to work, thus restricted to admins,
 * should not be used.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('ROLE_USER')",
 *         "force_eager"=false,
 *     },
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_USER')",
 *             "validation_groups"={"Default", "projectMembership:create"}
 *         }
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "projectMembership:write"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         }
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "projectMembership:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "projectMembership:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @AppAssert\ValidMembershipRequest(groups={
 *     "projectMembership:create",
 *     "projectMembership:write",
 *     "user:register",
 * })
 * @ORM\Entity
 * @UniqueEntity(fields={"project", "user"}, message="validate.projectMembership.duplicateMembership")
 */
class ProjectMembership
{
    public const ROLE_APPLICANT   = 'applicant';
    public const ROLE_WRITER      = 'writer';
    public const ROLE_COORDINATOR = 'coordinator';
    public const ROLE_OBSERVER    = 'observer';

    //region Motivation
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=10, max=1000),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private string $motivation = '';

    public function getMotivation(): string
    {
        return $this->motivation;
    }

    public function setMotivation(?string $value): self
    {
        $this->motivation = NormalizerHelper::toString($value);

        return $this;
    }

    //endregion

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "projectMembership:read",
     *     "projectMembership:create",
     *     "user:read",
     *     "user:register",
     * })
     * @MaxDepth(1)
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="memberships")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     *
     * Nullable property for Project::removeMembership
     */
    private ?Project $project = null;

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    //endregion

    //region Role
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Choice(
     *         choices={
     *             ProjectMembership::ROLE_APPLICANT,
     *             ProjectMembership::ROLE_WRITER,
     *             ProjectMembership::ROLE_COORDINATOR,
     *             ProjectMembership::ROLE_OBSERVER
     *         }
     *     ),
     * })
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private string $role = '';

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    //endregion

    //region Skills
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=10, max=1000),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({
     *     "project:read",
     *     "projectMembership:read",
     *     "projectMembership:write",
     *     "user:read",
     *     "user:register",
     * })
     * @ORM\Column(type="text", length=1000, nullable=false)
     */
    private string $skills = '';

    public function getSkills(): string
    {
        return $this->skills;
    }

    public function setSkills(string $value): self
    {
        $this->skills = NormalizerHelper::toString($value);

        return $this;
    }

    //endregion

    //region User
    /**
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Assert\NotBlank(groups={"projectMembership:create"})
     * @Groups({
     *     "project:pm-read",
     *     "project:writer-read",
     *     "project:coordinator-read",
     *     "projectMembership:create",
     *     "projectMembership:read",
     * })
     * @MaxDepth(2)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     * @ORM\ManyToOne(targetEntity="User", inversedBy="projectMemberships")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     *
     * Nullable property for User::removeProjectMembership
     */
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    //endregion
}
