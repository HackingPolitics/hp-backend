<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Controller\ChangeEmailAction;
use App\Controller\ChangePasswordAction;
use App\Controller\NewPasswordAction;
use App\Controller\PasswordResetAction;
use App\Controller\UserRegistrationAction;
use App\Controller\UserStatisticsAction;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use App\Filter\SimpleSearchFilter;
use App\Validator\Constraints as AppAssert;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * User.
 *
 * @ApiResource(
 *     attributes={
 *      "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_PROCESS_MANAGER')",
 *      "pagination_items_per_page"=15
 *     },
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *             "validation_groups"={"Default", "user:create"}
 *         },
 *         "register"={
 *             "controller"=UserRegistrationAction::class,
 *             "method"="POST",
 *             "path"="/users/register",
 *             "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *             "validation_groups"={"Default", "user:register"}
 *         },
 *         "resetPassword"={
 *             "controller"=PasswordResetAction::class,
 *             "method"="POST",
 *             "path"="/users/reset-password",
 *             "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *             "validation_groups"={"Default", "user:resetPassword"}
 *         },
 *         "statistics"={
 *             "controller"=UserStatisticsAction::class,
 *             "method"="GET",
 *             "path"="/users/statistics",
 *             "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_PROCESS_MANAGER')",
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('READ', object)"
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "user:update"}
 *         },
 *         "delete"={
 *             "security"="is_granted('DELETE', object)"
 *         },
 *         "changeEmail"={
 *             "controller"=ChangeEmailAction::class,
 *             "method"="POST",
 *             "path"="/users/{id}/change-email",
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "user:changeEmail"}
 *         },
 *         "changePassword"={
 *             "controller"=ChangePasswordAction::class,
 *             "method"="POST",
 *             "path"="/users/{id}/change-password",
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "user:changePassword"}
 *         },
 *         "newPassword"={
 *             "controller"=NewPasswordAction::class,
 *             "method"="POST",
 *             "path"="/users/{id}/new-password",
 *             "security"="is_granted('ROLE_ADMIN') or is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "user:newPassword"}
 *         }
 *     },
 *     input="App\Dto\UserInput",
 *     normalizationContext={
 *         "groups"={"default:read", "user:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "user:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ApiFilter(SearchFilter::class, properties={
 *     "roles": "partial",
 *     "username": "exact"
 * })
 * @ApiFilter(BooleanFilter::class, properties={"active", "validated"})
 * @ApiFilter(ExistsFilter::class, properties={"deletedAt"})
 * @ApiFilter(SimpleSearchFilter::class, properties={"lastName", "firstName", "username", "email"}, arguments={"searchParameterName"="pattern"})
 *
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="user_deleted_idx", columns={"deleted_at"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="user_email", columns={"email"})
 * })
 * @UniqueEntity(fields={"email"}, message="Email already exists.")
 * @UniqueEntity(fields={"username"}, message="Username already exists.")
 */
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use AutoincrementId;
    use CreatedAtFunctions;

    public const ROLE_ADMIN           = 'ROLE_ADMIN';
    public const ROLE_PROCESS_MANAGER = 'ROLE_PROCESS_MANAGER';
    public const ROLE_USER            = 'ROLE_USER';

    //region Username
    /**
     * User names must start with a letter; may contain only letters, digits,
     * dots, hyphens and underscores; they must contain at least two letters
     * (first regex).
     * User names may not be in the format "deleted_{0-9}" as this is reserved
     * for deleted users (second regex).
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Length(min=2, max=20),
     *     @Assert\Regex(
     *         pattern="/^[a-zA-Z]+[a-zA-Z0-9._-]*[a-zA-Z][a-zA-Z0-9._-]*$/",
     *         message="validate.user.username.notValid"
     *     ),
     *     @Assert\Regex(
     *         pattern="/^deleted_[0-9]+$/",
     *         match=false,
     *         message="validate.user.username.notValid"
     *     )
     * })
     *
     * Username must be readable when a project is read to display the
     * project's creator's username:
     * @Groups({
     *     "user:read",
     *     "user:create",
     *     "user:admin-write",
     *     "project:read",
     * })
     * @ORM\Column(type="string", length=20, nullable=false, unique=true)
     */
    private string $username = '';

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = NormalizerHelper::toString($username);

        return $this;
    }

    //endregion

    //region Password
    /**
     * @Assert\NotBlank
     * @Assert\Length(max=255)
     * @Groups({"user:admin-write", "user:pm-write", "user:changePassword"})
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private string $password = '';

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    //endregion

    //region Email
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Email,
     *     @Assert\Length(max=255),
     *     @Assert\Regex(
     *         pattern="/^deleted_[0-9]+@hpo.user$/",
     *         match=false,
     *         message="Email is not valid."
     *     )
     * })
     * @Groups({"user:changeEmail", "user:read", "user:write"})
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private string $email = '';

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    //endregion

    //region Roles
    /**
     * @Groups({"user:read", "user:write"})
     * @Assert\All({
     *     @Assert\NotBlank,
     *     @Assert\Choice(
     *         choices={
     *             User::ROLE_ADMIN,
     *             User::ROLE_PROCESS_MANAGER,
     *             User::ROLE_USER,
     *         },
     *     )
     * })
     *
     * @ORM\Column(type="small_json", length=255, nullable=true)
     */
    private array $roles = [];

    /**
     * Returns true if the user has the given role, else false.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        // guarantee every user at least has ROLE_USER
        $roles[] = self::ROLE_USER;

        return array_unique($roles);
    }

    public function setRoles(array $roles = []): self
    {
        // make sure every role is stored only once, remove ROLE_USER
        $this->roles = array_diff(array_unique($roles), [self::ROLE_USER]);

        return $this;
    }

    //endregion

    //region FirstName
    /**
     * @Assert\Sequentially({
     *     @VrokAssert\NoLineBreaks,
     *     @AppAssert\ValidPersonName,
     *     @Assert\Length(max=255),
     * })
     * @Groups({
     *     "user:read",
     *     "user:write",
     *     "project:coordinator-read",
     *     "project:writer-read",
     *     "project:pm-read",
     * })
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $firstName = null;

    public function getFirstName(): string
    {
        return $this->firstName ?? '';
    }

    public function setFirstName(string $value): self
    {
        $this->firstName = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region LastName
    /**
     * @Assert\Sequentially({
     *     @VrokAssert\NoLineBreaks,
     *     @AppAssert\ValidPersonName,
     *     @Assert\Length(max=255),
     * })
     * @Groups({
     *     "user:read",
     *     "user:write",
     *     "project:coordinator-read",
     *     "project:writer-read",
     *     "project:pm-read",
     * })
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $lastName = null;

    public function getLastName(): string
    {
        return $this->lastName ?? '';
    }

    public function setLastName(?string $value): self
    {
        $this->lastName = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region Active
    /**
     * @Groups({"user:read", "user:write"})
     * @ORM\Column(type="boolean", options={"default":true})
     */
    private bool $active = true;

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active = true): self
    {
        $this->active = $active;

        return $this;
    }

    //endregion

    //region Validated
    /**
     * @Groups({
     *     "user:read",
     *     "user:write",
     *     "project:member-read",
     *     "project:pm-read",
     * })
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $validated = false;

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated = true): self
    {
        $this->validated = $validated;

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"user:read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;
    //endregion

    //region DeletedAt
    /**
     * @Groups({"user:admin-read", "user:pm-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $deletedAt = null;

    /**
     * Sets deletedAt.
     *
     * @return $this
     */
    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * Returns deletedAt.
     *
     * @return DateTimeImmutable
     */
    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    /**
     * Is deleted?
     */
    public function isDeleted(): bool
    {
        return null !== $this->deletedAt;
    }

    /**
     * Sets the deletedAt timestamp to mark the object as deleted.
     * Removes private and/or identifying data to comply with privacy laws.
     *
     * @return $this
     */
    public function markDeleted(): self
    {
        $this->deletedAt = new DateTimeImmutable();

        // remove private / identifying data
        $this->setUsername('deleted_'.$this->getId());
        $this->setEmail('deleted_'.$this->getId().'@hpo.user');
        $this->setPassword('');
        $this->setFirstName('');
        $this->setLastName('');

        // remove privileges
        foreach ($this->getObjectRoles() as $objectRole) {
            $this->removeObjectRole($objectRole);
        }

        return $this;
    }

    //endregion

    //region ObjectRoles
    /**
     * @var Collection|UserObjectRole[]
     * @Groups({"user:read"})
     * @MaxDepth(2)
     * @ORM\OneToMany(
     *     targetEntity="UserObjectRole",
     *     mappedBy="user",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $objectRoles;

    /**
     * @return Collection|UserObjectRole[]
     */
    public function getObjectRoles(): Collection
    {
        return $this->objectRoles;
    }

    public function addObjectRole(UserObjectRole $objectRole): self
    {
        if (!$this->objectRoles->contains($objectRole)) {
            $this->objectRoles[] = $objectRole;
            $objectRole->setUser($this);
        }

        return $this;
    }

    public function removeObjectRole(UserObjectRole $objectRole): self
    {
        if ($this->objectRoles->contains($objectRole)) {
            $this->objectRoles->removeElement($objectRole);
            // set the owning side to null (unless already changed)
            if ($objectRole->getUser() === $this) {
                $objectRole->setUser(null);
            }
        }

        return $this;
    }

    //endregion

    //region CreatedProjects
    /**
     * @var Collection|Project[]
     * @Groups({
     *     "user:admin-read",
     *     "user:pm-read",
     *     "user:self",
     *     "user:register",
     * })
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity="Project", mappedBy="createdBy", cascade={"persist"})
     */
    private $createdProjects;

    /**
     * @return Collection|Project[]
     */
    public function getCreatedProjects(): Collection
    {
        return $this->createdProjects;
    }

    public function addCreatedProject(Project $project): self
    {
        if (!$this->createdProjects->contains($project)) {
            $this->createdProjects[] = $project;
            $project->setCreatedBy($this);
        }

        return $this;
    }

    public function removeCreatedProject(Project $project): self
    {
        if ($this->createdProjects->contains($project)) {
            $this->createdProjects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getCreatedBy() === $this) {
                $project->setCreatedBy(null);
            }
        }

        return $this;
    }

    //endregion

    //region ProjectMemberships
    /**
     * @var Collection|ProjectMembership[]
     * @Groups({
     *     "user:admin-read",
     *     "user:pm-read",
     *     "user:self",
     *     "user:register"
     * })
     * @MaxDepth(2)
     * @ORM\OneToMany(
     *     targetEntity="ProjectMembership",
     *     mappedBy="user",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private $projectMemberships;

    /**
     * @return Collection|ProjectMembership[]
     */
    public function getProjectMemberships(): Collection
    {
        return $this->projectMemberships;
    }

    public function addProjectMembership(ProjectMembership $member): self
    {
        if (!$this->projectMemberships->contains($member)) {
            $this->projectMemberships[] = $member;
            $member->setUser($this);
        }

        return $this;
    }

    public function removeProjectMembership(ProjectMembership $member): self
    {
        if ($this->projectMemberships->contains($member)) {
            $this->projectMemberships->removeElement($member);
            // set the owning side to null (unless already changed)
            if ($member->getUser() === $this) {
                $member->setUser(null);
            }
        }

        return $this;
    }

    //endregion

    //region Validations
    /**
     * @var Collection|Validation[]
     * @ORM\OneToMany(targetEntity="Validation", mappedBy="user", orphanRemoval=true)
     */
    private $validations;

    /**
     * @return Collection|Validation[]
     */
    public function getValidations(): Collection
    {
        return $this->validations;
    }

    public function addValidation(Validation $validation): self
    {
        if (!$this->validations->contains($validation)) {
            $this->validations[] = $validation;
            $validation->setUser($this);
        }

        return $this;
    }

    public function removeValidation(Validation $validation): self
    {
        if ($this->validations->contains($validation)) {
            $this->validations->removeElement($validation);
            // set the owning side to null (unless already changed)
            if ($validation->getUser() === $this) {
                $validation->setUser(null);
            }
        }

        return $this;
    }

    //endregion

    public function __construct()
    {
        $this->createdProjects = new ArrayCollection();
        $this->objectRoles = new ArrayCollection();
        $this->projectMemberships = new ArrayCollection();
        $this->validations = new ArrayCollection();
    }

    /**
     * @see UserInterface
     */
    public function getSalt(): ?string
    {
        // relict in UserInterface from times when the salt was stored
        // separately from the password...
        return null;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }
}
