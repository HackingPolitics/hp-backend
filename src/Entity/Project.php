<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Controller\ProjectStatisticsAction;
use App\Controller\ReportProjectAction;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use App\Entity\Traits\DeletedAtFunctions;
use App\Entity\Traits\SlugFunctions;
use App\Entity\Traits\UpdatedAtFunctions;
use App\Filter\SimpleSearchFilter;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * Project.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *         "pagination_items_per_page"=15
 *     },
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_USER')",
 *             "validation_groups"={"Default", "project:create"}
 *         },
 *         "statistics"={
 *             "controller"=ProjectStatisticsAction::class,
 *             "method"="GET",
 *             "path"="/projects/statistics",
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *         },
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "project:write"}
 *         },
 *         "delete"={"security"="is_granted('DELETE', object)"},
 *         "report"={
 *             "controller"=ReportProjectAction::class,
 *             "method"="POST",
 *             "path"="/projects/{id}/report",
 *             "validation_groups"={"Default", "project:report"},
 *              "openapi_context"={
 *                 "requestBody"={
 *                     "content"={
 *                         "multipart/form-data"={
 *                             "schema"={
 *                                 "type"="object",
 *                                 "properties"={
 *                                     "reportMessage"={
 *                                         "type"="string",
 *                                     },
 *                                     "reporterEmail"={
 *                                         "type"="string",
 *                                     },
 *                                     "reporterName"={
 *                                         "type"="string",
 *                                     }
 *                                 }
 *                             }
 *                         }
 *                     }
 *                 }
 *             }
 *         },
 *     },
 *     input="App\Dto\ProjectInput",
 *     normalizationContext={
 *         "groups"={"default:read", "project:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "project:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 * @ApiFilter(BooleanFilter::class, properties={"locked"})
 * @ApiFilter(ExistsFilter::class, properties={"deletedAt"})
 * @ApiFilter(SearchFilter::class, properties={
 *     "id": "exact",
 *     "title": "partial",
 *     "slug": "exact",
 *     "state": "exact"
 * })
 * @ApiFilter(SimpleSearchFilter::class, properties={
 *     "description", "impact", "topic", "title",
 * }, arguments={"searchParameterName"="pattern"})
 *
 * @ORM\Entity
 * @ORM\Table(indexes={
 *     @ORM\Index(name="project_state_idx", columns={"state"})
 * })
 */
class Project
{
    use AutoincrementId;
    use CreatedAtFunctions;
    use DeletedAtFunctions;
    use SlugFunctions;
    use UpdatedAtFunctions;

    public const STATE_PUBLIC = 'public';
    public const STATE_PRIVATE = 'private';

    //region ActionMandates
    /**
     * @var Collection|ActionMandate[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="ActionMandate", mappedBy="project", cascade={"persist"})
     */
    private Collection $actionMandates;

    /**
     * @return Collection|ActionMandate[]
     */
    public function getActionMandates(): Collection
    {
        return $this->actionMandates;
    }

    public function addActionMandate(ActionMandate $actionMandate): self
    {
        if (!$this->actionMandates->contains($actionMandate)) {
            $this->actionMandates[] = $actionMandate;
            $actionMandate->setProject($this);
        }

        return $this;
    }

    public function removeActionMandate(ActionMandate $actionMandate): self
    {
        if ($this->actionMandates->contains($actionMandate)) {
            $this->actionMandates->removeElement($actionMandate);
            // set the owning side to null (unless already changed)
            if ($actionMandate->getProject() === $this) {
                $actionMandate->setProject(null);
            }
        }

        return $this;
    }

    //endregion
    
    //region Arguments
    /**
     * @var Collection|Argument[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="Argument", mappedBy="project", cascade={"persist"})
     */
    private Collection $arguments;

    /**
     * @return Collection|Argument[]
     */
    public function getArguments(): Collection
    {
        return $this->arguments;
    }

    public function addArgument(Argument $argument): self
    {
        if (!$this->arguments->contains($argument)) {
            $this->arguments[] = $argument;
            $argument->setProject($this);
        }

        return $this;
    }

    public function removeArgument(Argument $argument): self
    {
        if ($this->arguments->contains($argument)) {
            $this->arguments->removeElement($argument);
            // set the owning side to null (unless already changed)
            if ($argument->getProject() === $this) {
                $argument->setProject(null);
            }
        }

        return $this;
    }

    //endregion

    //region Categories
    /**
     * @var Collection|Category[]
     * @Groups({"project:read", "project:write"})
     * @ORM\ManyToMany(targetEntity="Category", inversedBy="projects")
     * @ORM\JoinTable(
     *     name="project_category",
     *     joinColumns={
     *         @ORM\JoinColumn(name="project_id", referencedColumnName="id", onDelete="CASCADE")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="category_id", referencedColumnName="id", onDelete="CASCADE")
     *     }
     * )
     */
    protected Collection $categories;

    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function setCategories(array $categories): self
    {
        $this->categories->clear();
        foreach ($categories as $category) {
            $this->addCategory($category);
        }

        return $this;
    }

    public function addCategory(Category $category): self
    {
        if ($this->categories->contains($category)) {
            return $this;
        }

        $this->categories->add($category);
        $category->addProject($this);

        return $this;
    }

    public function removeCategory(Category $category): self
    {
        if (!$this->categories->contains($category)) {
            return $this;
        }

        $this->categories->removeElement($category);
        $category->removeProject($this);

        return $this;
    }

    //endregion

    //region Council
    /**
     * @Assert\NotBlank
     * @Groups({"project:read", "project:create", "user:register"})
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Council", inversedBy="projects")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Council $council = null;

    public function getCouncil(): ?Council
    {
        return $this->council;
    }

    public function setCouncil(?Council $council): self
    {
        $this->council = $council;

        return $this;
    }

    //endregion

    //region CounterArguments
    /**
     * @var Collection|CounterArgument[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="CounterArgument", mappedBy="project", cascade={"persist"})
     */
    private Collection $counterArguments;

    /**
     * @return Collection|CounterArgument[]
     */
    public function getCounterArguments(): Collection
    {
        return $this->counterArguments;
    }

    public function addCounterArgument(CounterArgument $counterArgument): self
    {
        if (!$this->counterArguments->contains($counterArgument)) {
            $this->counterArguments[] = $counterArgument;
            $counterArgument->setProject($this);
        }

        return $this;
    }

    public function removeCounterArgument(CounterArgument $counterArgument): self
    {
        if ($this->counterArguments->contains($counterArgument)) {
            $this->counterArguments->removeElement($counterArgument);
            // set the owning side to null (unless already changed)
            if ($counterArgument->getProject() === $this) {
                $counterArgument->setProject(null);
            }
        }

        return $this;
    }

    //endregion
    
    //region CreatedAt
    /**
     * @Groups({"project:read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;
    //endregion

    //region CreatedBy
    /**
     * @Groups({
     *     "project:create",
     *     "project:read"
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="User", inversedBy="createdProjects")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     *
     * Nullable property for User::removeCreatedProject
     */
    private ?User $createdBy = null;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    //endregion

    //region DeletedAt
    /**
     * @Groups({"project:admin-read", "project:pm-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $deletedAt = null;

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
        $this->setDescription(null);
        $this->setTitle(null);

        return $this;
    }

    //endregion

    //region Description
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=6000),
     *     @Assert\Length(max=2000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"elastica", "project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $description = null;

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $value): self
    {
        $this->description = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region FractionDetails
    /**
     * @var Collection|FractionDetails[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="FractionDetails", mappedBy="project", cascade={"persist"})
     */
    private Collection $fractionDetails;

    /**
     * @return Collection|FractionDetails[]
     */
    public function getFractionDetails(): Collection
    {
        return $this->fractionDetails;
    }

    public function addFractionDetails(FractionDetails $fractionDetails): self
    {
        if (!$this->fractionDetails->contains($fractionDetails)) {
            $this->fractionDetails[] = $fractionDetails;
            $fractionDetails->setProject($this);
        }

        return $this;
    }

    public function removeFractionDetails(FractionDetails $fractionDetails): self
    {
        if ($this->fractionDetails->contains($fractionDetails)) {
            $this->fractionDetails->removeElement($fractionDetails);
            // set the owning side to null (unless already changed)
            if ($fractionDetails->getProject() === $this) {
                $fractionDetails->setProject(null);
            }
        }

        return $this;
    }

    //endregion

    //region Impact
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=6000),
     *     @Assert\Length(max=2000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"project:read", "project:write"})
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $impact = null;

    public function getImpact(): string
    {
        return $this->impact ?? '';
    }

    public function setImpact(?string $value): self
    {
        $this->impact = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Locked
    /**
     * @Groups({
     *     "project:pm-read",
     *     "project:admin-read",
     *     "project:coordinator-read",
     *     "project:member-read",
     *     "project:pm-write",
     *     "project:admin-write"
     * })
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $locked = false;

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $value = true): self
    {
        $this->locked = $value;

        return $this;
    }

    //endregion

    //region Memberships
    /**
     * @var Collection|ProjectMembership[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @MaxDepth(2)
     * @ORM\OneToMany(
     *     targetEntity="ProjectMembership",
     *     mappedBy="project",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     */
    private Collection $memberships;

    /**
     * @return Collection|ProjectMembership[]
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    /**
     * Returns the role the given user has in this project, null if the
     * user is no member.
     */
    public function getUserRole(User $user): ?string
    {
        foreach ($this->getMemberships() as $membership) {
            if ($membership->getUser() && $membership->getUser()->getId() === $user->getId()) {
                return $membership->getRole();
            }
        }

        return null;
    }

    public function userIsMember(User $user): bool
    {
        return null !== $this->getUserRole($user);
    }

    public function userCanRead(User $user): bool
    {
        $role = $this->getUserRole($user);

        return $role && ProjectMembership::ROLE_APPLICANT !== $role;
    }

    public function userCanWrite(User $user): bool
    {
        $role = $this->getUserRole($user);

        return ProjectMembership::ROLE_WRITER === $role
            || ProjectMembership::ROLE_COORDINATOR === $role;
    }

    /**
     * Returns all project members with the given role.
     *
     * @return User[]
     */
    public function getMembersByRole(string $role): array
    {
        $members = [];
        foreach ($this->getMemberships() as $membership) {
            if ($membership->getRole() === $role
                // make sure we don't return NULL from memberships marked for deletion
                && $membership->getUser()) {
                $members[] = $membership->getUser();
            }
        }

        return $members;
    }

    public function addMembership(ProjectMembership $membership): self
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships[] = $membership;
            $membership->setProject($this);
        }

        return $this;
    }

    public function removeMembership(ProjectMembership $membership): self
    {
        if ($this->memberships->contains($membership)) {
            $this->memberships->removeElement($membership);
            // set the owning side to null (unless already changed)
            if ($membership->getProject() === $this) {
                $membership->setProject(null);
            }
        }

        return $this;
    }

    //endregion

    //region Partners
    /**
     * @var Collection|Partner[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="Partner", mappedBy="project", cascade={"persist"})
     */
    private Collection $partners;

    /**
     * @return Collection|Partner[]
     */
    public function getPartners(): Collection
    {
        return $this->partners;
    }

    public function addPartner(Partner $partner): self
    {
        if (!$this->partners->contains($partner)) {
            $this->partners[] = $partner;
            $partner->setProject($this);
        }

        return $this;
    }

    public function removePartner(Partner $partner): self
    {
        if ($this->partners->contains($partner)) {
            $this->partners->removeElement($partner);
            // set the owning side to null (unless already changed)
            if ($partner->getProject() === $this) {
                $partner->setProject(null);
            }
        }

        return $this;
    }

    //endregion

    //region Problems
    /**
     * @var Collection|Problem[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="Problem", mappedBy="project", cascade={"persist"})
     */
    private Collection $problems;

    /**
     * @return Collection|Problem[]
     */
    public function getProblems(): Collection
    {
        return $this->problems;
    }

    public function addProblem(Problem $problem): self
    {
        if (!$this->problems->contains($problem)) {
            $this->problems[] = $problem;
            $problem->setProject($this);
        }

        return $this;
    }

    public function removeProblem(Problem $problem): self
    {
        if ($this->problems->contains($problem)) {
            $this->problems->removeElement($problem);
            // set the owning side to null (unless already changed)
            if ($problem->getProject() === $this) {
                $problem->setProject(null);
            }
        }

        return $this;
    }

    //endregion

    //region Slug
    /**
     * @Groups({"elastica", "project:read", "user:read"})
     * @ORM\Column(type="string", length=150, nullable=true)
     * @Gedmo\Slug(fields={"title"})
     */
    private ?string $slug = null;
    //endregion

    //region State
    /**
     * @Assert\Choice(
     *     choices={
     *         Project::STATE_PRIVATE,
     *         Project::STATE_PUBLIC,
     *     }
     * )
     * @Groups({
     *     "project:read",
     *     "project:coordinator-update",
     *     "project:pm-update",
     *     "project:admin-update",
     *     "user:read",
     * })
     * @ORM\Column(type="string", length=50, nullable=false, options={"default":"private"})
     */
    private string $state = self::STATE_PRIVATE;

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    //endregion

    //region Title
    /**
     * Require at least one letter in the title so that the slug
     * is never only numeric, to differentiate it from an ID.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=3, max=100),
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Regex(
     *         pattern="/[a-zA-Z]/",
     *         message="validate.general.letterRequired"
     *     ),
     * })
     * @Groups({"elastica", "project:read", "project:write", "user:read"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $title = null;

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(?string $value): self
    {
        $this->title = NormalizerHelper::toString($value);

        return $this;
    }

    //endregion

    //region Topic
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=2, max=1000),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"project:read", "project:write"})
     * @ORM\Column(type="text", length=1000, nullable=true)
     */
    private ?string $topic = null;

    public function getTopic(): string
    {
        return $this->topic ?? '';
    }

    public function setTopic(?string $value): self
    {
        $this->topic = NormalizerHelper::toString($value);

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    public function __construct()
    {
        $this->actionMandates = new ArrayCollection();
        $this->arguments = new ArrayCollection();
        $this->counterArguments = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->fractionDetails = new ArrayCollection();
        $this->memberships = new ArrayCollection();
        $this->partners = new ArrayCollection();
        $this->problems = new ArrayCollection();
    }
}
