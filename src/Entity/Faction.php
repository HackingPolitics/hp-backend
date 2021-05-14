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
 * Faction.
 *
 * Collection cannot be queried, factions can only be retrieved via the
 * Parliament relations.
 * Item GET is required for API Platform to work, thus restricted to admins,
 * should not be used.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *         "force_eager"=false,
 *     },
 *     collectionOperations={
 *         "post"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "faction:create"}
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "faction:write"}
 *         },
 *         "delete"={
 *              "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *          },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "faction:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "faction:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FactionRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="faction_parliament_name", columns={"parliament_id", "name"})
 * })
 * @UniqueEntity(fields={"name", "parliament"}, message="validate.faction.duplicateFaction")
 */
class Faction
{
    use AutoincrementId;

    //region Active
    /**
     * @Groups({"faction:read", "faction:write"})
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

    //region Details
    /**
     * @var Collection|FactionDetails[]
     * @Groups({"none"})
     * @ORM\OneToMany(targetEntity="FactionDetails", mappedBy="faction", cascade={"persist"})
     */
    private $details;

    /**
     * @return Collection|FactionDetails[]
     */
    public function getDetails(): Collection
    {
        return $this->details;
    }

    public function addDetails(FactionDetails $details): self
    {
        if (!$this->details->contains($details)) {
            $this->details[] = $details;
            $details->setFaction($this);
        }

        return $this;
    }

    public function removeDetails(FactionDetails $factionDetails): self
    {
        if ($this->details->contains($factionDetails)) {
            $this->details->removeElement($factionDetails);
            // set the owning side to null (unless already changed)
            if ($factionDetails->getFaction() === $this) {
                $factionDetails->setFaction(null);
            }
        }

        return $this;
    }

    //endregion

    //region MemberCount
    /**
     * @Assert\NotBlank
     * @Assert\Range(min=1)
     * @Groups({"faction:read", "faction:write", "parliament:read"})
     * @ORM\Column(type="integer", options={"unsigned"=true})
     */
    private ?int $memberCount = null;

    public function getMemberCount(): ?int
    {
        return $this->memberCount;
    }

    public function setMemberCount(?int $value): void
    {
        $this->memberCount = $value;
    }
    //endregion

    //region Name
    /**
     * Require at least one letter in the name so that the slug
     * is never only numeric, to differentiate it from an ID.
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=60),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"faction:read", "faction:write"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $name = null;

    public function getName(): string
    {
        return $this->name ?? '';
    }

    public function setName(?string $value): self
    {
        $this->name = NormalizerHelper::toNullableString($value);

        return $this;
    }
    //endregion

    //region Parliament
    /**
     * @Groups({"faction:read", "faction:write"})
     * @ORM\ManyToOne(targetEntity="Parliament", inversedBy="factions")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Parliament $parliament = null;

    public function getParliament(): ?Parliament
    {
        return $this->parliament;
    }

    public function setParliament(?Parliament $parliament): self
    {
        $this->parliament = $parliament;

        return $this;
    }
    //endregion

    //region Url
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=200),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"faction:read", "faction:write"})
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

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"faction:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;

    use UpdatedAtFunctions;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "faction:create",
     *     "faction:read"
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
        $this->details = new ArrayCollection();
    }
}
