<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\ExistsFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\DeletedAtFunctions;
use App\Entity\Traits\SlugFunctions;
use App\Entity\Traits\UpdatedAtFunctions;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * Council.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *         "pagination_items_per_page"=15
 *     },
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "council:create"}
 *         },
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "council:write"}
 *         },
 *         "delete"={
 *              "security"="is_granted('ROLE_ADMIN')",
 *          },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "council:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "council:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ApiFilter(ExistsFilter::class, properties={"deletedAt"})
 * @ApiFilter(SearchFilter::class, properties={
 *     "id": "exact",
 *     "title": "partial",
 *     "slug": "exact"
 * })
 *
 * @ORM\Entity(repositoryClass="App\Repository\CouncilRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="council_deleted_idx", columns={"deleted_at"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="council_title", columns={"title"})
 * })
 * @UniqueEntity(fields={"title"}, message="validate.council.duplicateTitle")
 */
class Council
{
    use AutoincrementId;
    use DeletedAtFunctions;
    use SlugFunctions;
    use UpdatedAtFunctions;

    //region Active
    /**
     * @Groups({"council:read", "council:write", "project:member-read"})
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

    //region DeletedAt
    /**
     * @Groups({"council:admin-read", "council:pm-read"})
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
        $this->setTitle(null);

        return $this;
    }

    //endregion

    //region Fractions
    /**
     * @var Collection|Fraction[]
     * @Groups({
     *     "council:read",
     *     "project:member-read",
     * })
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity="Fraction", mappedBy="council", cascade={"persist"})
     */
    private $fractions;

    /**
     * @return Collection|Fraction[]
     */
    public function getFractions(): Collection
    {
        return $this->fractions;
    }

    public function addFraction(Fraction $fraction): self
    {
        if (!$this->fractions->contains($fraction)) {
            $this->fractions[] = $fraction;
            $fraction->setCouncil($this);
        }

        return $this;
    }

    public function removeFraction(Fraction $fraction): self
    {
        if ($this->fractions->contains($fraction)) {
            $this->fractions->removeElement($fraction);
            // set the owning side to null (unless already changed)
            if ($fraction->getCouncil() === $this) {
                $fraction->setCouncil(null);
            }
        }

        return $this;
    }

    //endregion

    //region FederalState
    /**
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\ManyToOne(targetEntity="FederalState", inversedBy="councils")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?FederalState $federalState = null;

    public function getFederalState(): ?FederalState
    {
        return $this->federalState;
    }

    public function setFederalState(?FederalState $federalState): self
    {
        $this->federalState = $federalState;

        return $this;
    }

    //endregion

    //region HeadOfAdministration
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=50),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=50, nullable=true)
     */
    private ?string $headOfAdministration = null;

    public function getHeadOfAdministration(): string
    {
        return $this->headOfAdministration ?? '';
    }

    public function setHeadOfAdministration(?string $value): self
    {
        $this->headOfAdministration = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region HeadOfAdministrationTitle
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=50),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=50, nullable=true)
     */
    private ?string $headOfAdministrationTitle = null;

    public function getHeadOfAdministrationTitle(): string
    {
        return $this->headOfAdministrationTitle ?? '';
    }

    public function setHeadOfAdministrationTitle(?string $value): self
    {
        $this->headOfAdministrationTitle = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Location
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(min=2, max=200),
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Regex(
     *         pattern="/[a-zA-Z\-\.\(\)]/",
     *         message="validate.council.invalidLocation"
     *     ),
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=200, nullable=true)
     */
    private ?string $location = null;

    public function getLocation(): string
    {
        return $this->location ?? '';
    }

    public function setLocation(?string $value): self
    {
        $this->location = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Projects
    /**
     * @var Collection|Project[]
     * @Groups({"none"})
     * @ORM\OneToMany(targetEntity="Project", mappedBy="council", cascade={"persist"})
     */
    private $projects;

    /**
     * @return Collection|Project[]
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setCouncil($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            $this->projects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getCouncil() === $this) {
                $project->setCouncil(null);
            }
        }

        return $this;
    }

    //endregion

    //region Slug
    /**
     * @Groups({"council:read", "project:member-read"})
     * @ORM\Column(type="string", length=150, nullable=true)
     * @Gedmo\Slug(fields={"title"})
     */
    private ?string $slug = null;
    //endregion

    //region Title
    /**
     * Require at least one letter in the title so that the slug
     * is never only numeric, to differentiate it from an ID.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=80),
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Regex(
     *         pattern="/[a-zA-Z]/",
     *         message="validate.general.letterRequired"
     *     ),
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $title = null;

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(?string $value): self
    {
        $this->title = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"council:read", "project:member-read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "council:create",
     *     "council:read",
     *     "project:member-read"
     * })
     * @Gedmo\Blameable(on="update", "project:read")
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?User $updatedBy = null;

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?User $updatedBy): self
    {
        $this->updatedBy = $updatedBy;

        return $this;
    }

    //endregion

    //region Url
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=200),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=200, nullable=true)
     */
    private ?string $url = null;

    public function getUrl(): string
    {
        return $this->url ?? '';
    }

    public function setUrl(?string $value): self
    {
        $this->url = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region ValidatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"council:read", "council:pm-write", "project:member-read"})
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $validatedAt = null;

    /**
     * Sets validatedAt.
     *
     * @return $this
     */
    public function setValidatedAt(DateTimeImmutable $validatedAt): self
    {
        $this->validatedAt = $validatedAt;

        return $this;
    }

    /**
     * Returns validatedAt.
     *
     * @return DateTimeImmutable
     */
    public function getValidatedAt(): ?DateTimeImmutable
    {
        return $this->validatedAt;
    }

    //endregion

    //region WikipediaUrl
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=200),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=200, nullable=true)
     */
    private ?string $wikipediaUrl = null;

    public function getWikipediaUrl(): string
    {
        return $this->wikipediaUrl ?? '';
    }

    public function setWikipediaUrl(?string $value): self
    {
        $this->wikipediaUrl = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region ZipArea
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=5),
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Regex(
     *         pattern="/[a-zA-Z0-9]/",
     *         message="validate.council.invalidZipArea"
     *     ),
     * })
     * @Groups({"council:read", "council:write", "project:member-read"})
     * @ORM\Column(type="text", length=20, nullable=true)
     */
    private ?string $zipArea = null;

    public function getZipArea(): string
    {
        return $this->zipArea ?? '';
    }

    public function setZipArea(?string $value): self
    {
        $this->zipArea = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    public function __construct()
    {
        $this->fractions = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }
}
