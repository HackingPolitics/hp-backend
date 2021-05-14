<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
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
 * FactionDetails.
 *
 * Collection cannot be queried, factionDetails can only be retrieved via the
 * Project relations.
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
 *             "security_post_denormalize" = "is_granted('CREATE', object)",
 *             "validation_groups"={"Default", "factionDetails:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "factionDetails:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "factionDetails:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "factionDetails:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FactionDetailsRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="faction_project", columns={"faction_id", "project_id"})
 * })
 * @UniqueEntity(fields={"faction", "project"}, message="validate.factionDetails.duplicateFactionDetails")
 */
class FactionDetails
{
    use AutoincrementId;

    //region ContactEmail
    /**
     * @Assert\Email
     * @Assert\Length(max=255)
     * @Groups({"factionDetails:read", "factionDetails:write", "project:read"})
     * @ORM\Column(type="text", length=255, nullable=true)
     */
    private ?string $contactEmail = null;

    public function getContactEmail(): string
    {
        return $this->contactEmail ?? '';
    }

    public function setContactEmail(?string $value): self
    {
        $this->contactEmail = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region ContactName
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=100),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"factionDetails:read", "factionDetails:write", "project:read"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $contactName = null;

    public function getContactName(): string
    {
        return $this->contactName ?? '';
    }

    public function setContactName(?string $value): self
    {
        $this->contactName = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region ContactPhone
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=100),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"factionDetails:read", "factionDetails:write", "project:read"})
     * @ORM\Column(type="text", length=100, nullable=true)
     */
    private ?string $contactPhone = null;

    public function getContactPhone(): string
    {
        return $this->contactPhone ?? '';
    }

    public function setContactPhone(?string $value): self
    {
        $this->contactPhone = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region Faction
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "factionDetails:read",
     *     "factionDetails:create"
     * })
     * @ORM\ManyToOne(targetEntity="Faction", inversedBy="details")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Faction $faction = null;

    public function getFaction(): ?Faction
    {
        return $this->faction;
    }

    public function setFaction(?Faction $faction): self
    {
        $this->faction = $faction;

        return $this;
    }
    //endregion

    //region Interests
    /**
     * @var Collection|FactionInterest[]
     * @Groups({
     *     "factionDetails:read",
     *     "factionDetails:create",
     *     "project:member-read",
     * })
     * @ORM\OneToMany(targetEntity="FactionInterest", mappedBy="factionDetails", cascade={"persist"})
     */
    private $interests;

    /**
     * @return Collection|FactionInterest[]
     */
    public function getInterests(): Collection
    {
        return $this->interests;
    }

    public function addInterest(FactionInterest $interest): self
    {
        if (!$this->interests->contains($interest)) {
            $this->interests[] = $interest;
            $interest->setFactionDetails($this);
        }

        return $this;
    }

    public function removeInterest(FactionInterest $interest): self
    {
        if ($this->interests->contains($interest)) {
            $this->interests->removeElement($interest);
            // set the owning side to null (unless already changed)
            if ($interest->getFactionDetails() === $this) {
                $interest->setFactionDetails(null);
            }
        }

        return $this;
    }

    //endregion

    //region PossiblePartner
    /**
     * @Groups({"factionDetails:read", "factionDetails:write", "project:read"})
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $possiblePartner = false;

    public function isPossiblePartner(): bool
    {
        return $this->possiblePartner;
    }

    public function setPossiblePartner(bool $value = true): self
    {
        $this->possiblePartner = $value;

        return $this;
    }

    //endregion

    //region PossibleProponent
    /**
     * @Groups({
     *     "factionDetails:read",
     *     "factionDetails:write",
     *     "project:read",
     * })
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $possibleProponent = false;

    public function isPossibleProponent(): bool
    {
        return $this->possibleProponent;
    }

    public function setPossibleProponent(bool $value = true): self
    {
        $this->possibleProponent = $value;

        return $this;
    }

    //endregion

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "factionDetails:read",
     *     "factionDetails:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="factionDetails")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
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

    //region TeamContact
    /**
     * @Groups({
     *     "factionDetails:create",
     *     "factionDetails:writer-read",
     *     "factionDetails:coordinator-read",
     *     "factionDetails:pm-read",
     *     "factionDetails:admin-read",
     *     "factionDetails:member-write",
     *     "factionDetails:coordinator-write",
     *     "factionDetails:pm-write",
     *     "factionDetails:admin-write",
     *     "project:writer-read",
     *     "project:coordinator-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?User $teamContact = null;

    public function getTeamContact(): ?User
    {
        return $this->teamContact;
    }

    public function setTeamContact(?User $teamContact): self
    {
        $this->teamContact = $teamContact;

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"factionDetails:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;

    use UpdatedAtFunctions;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "factionDetails:writer-read",
     *     "factionDetails:coordinator-read",
     *     "factionDetails:pm-read",
     *     "factionDetails:admin-read",
     *     "project:writer-read",
     *     "project:coordinator-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @MaxDepth(1)
     * @Gedmo\Blameable(on="update")
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

    public function __construct()
    {
        $this->interests = new ArrayCollection();
    }
}
