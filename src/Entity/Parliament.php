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
 * Parliament.
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
 *             "validation_groups"={"Default", "parliament:create"}
 *         },
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "parliament:write"}
 *         },
 *         "delete"={
 *              "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *          },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "parliament:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "parliament:write"},
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
 * @ORM\Entity(repositoryClass="App\Repository\ParliamentRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="parliament_deleted_idx", columns={"deleted_at"})
 * }, uniqueConstraints={
 *     @ORM\UniqueConstraint(name="parliament_title", columns={"title"})
 * })
 * @UniqueEntity(fields={"title"}, message="validate.parliament.duplicateTitle")
 */
class Parliament
{
    use AutoincrementId;
    use DeletedAtFunctions;
    use SlugFunctions;
    use UpdatedAtFunctions;

    //region DeletedAt
    /**
     * @Groups({"parliament:admin-read", "parliament:pm-read"})
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

    //region Factions
    /**
     * @var Collection|Faction[]
     * @Groups({
     *     "parliament:read",
     * })
     * @MaxDepth(1)
     * @ORM\OneToMany(targetEntity="Faction", mappedBy="parliament", cascade={"persist"})
     */
    private $factions;

    /**
     * @return Collection|Faction[]
     */
    public function getFactions(): Collection
    {
        return $this->factions;
    }

    public function addFaction(Faction $faction): self
    {
        if (!$this->factions->contains($faction)) {
            $this->factions[] = $faction;
            $faction->setParliament($this);
        }

        return $this;
    }

    public function removeFaction(Faction $faction): self
    {
        if ($this->factions->contains($faction)) {
            $this->factions->removeElement($faction);
            // set the owning side to null (unless already changed)
            if ($faction->getParliament() === $this) {
                $faction->setParliament(null);
            }
        }

        return $this;
    }

    //endregion

    //region FederalState
    /**
     * @Groups({"parliament:read", "parliament:write"})
     * @ORM\ManyToOne(targetEntity="FederalState", inversedBy="parliaments")
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
     * @Groups({"parliament:read", "parliament:write"})
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
     * @Groups({"parliament:read", "parliament:write"})
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
     *         message="validate.parliament.invalidLocation"
     *     ),
     * })
     * @Groups({"parliament:read", "parliament:write"})
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
     * @ORM\OneToMany(targetEntity="Project", mappedBy="parliament", cascade={"persist"})
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
            $project->setParliament($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            $this->projects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getParliament() === $this) {
                $project->setParliament(null);
            }
        }

        return $this;
    }

    //endregion

    //region Slug
    /**
     * @Groups({"parliament:read"})
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
     * @Groups({"parliament:read", "parliament:write"})
     * @ORM\Column(type="string", length=100, nullable=true)
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
     * @Groups({"parliament:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "parliament:create",
     *     "parliament:read"
     * })
     * @Gedmo\Blameable(on="update")
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
     * @Groups({"parliament:read", "parliament:write"})
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
     * @Groups({"parliament:read", "parliament:pm-write"})
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
     * @Groups({"parliament:read", "parliament:write"})
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
     *         message="validate.parliament.invalidZipArea"
     *     ),
     * })
     * @Groups({"parliament:read", "parliament:write"})
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
        $this->factions = new ArrayCollection();
        $this->projects = new ArrayCollection();
    }
}
