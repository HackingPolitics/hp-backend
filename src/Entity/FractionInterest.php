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
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;

/**
 * FractionInterest.
 *
 * Collection cannot be queried, fractionInterest can only be retrieved via the
 * FractionDetails relations.
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
 *             "validation_groups"={"Default", "fractionInterest:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "fractionInterest:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fractionInterest:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fractionInterest:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FractionInterestRepository")
 */
class FractionInterest
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region Description
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=500),
     * })
     * @Groups({
     *     "fractionInterest:read",
     *     "fractionInterest:write",
     *     "fractionDetails:read",
     *     "project:read",
     * })
     * @ORM\Column(type="text", length=250, nullable=false)
     */
    private ?string $description = null;

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $value): self
    {
        $this->description = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region FractionDetails
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "fractionInterest:read",
     *     "fractionInterest:create",
     * })
     * @ORM\ManyToOne(targetEntity="FractionDetails", inversedBy="interests")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?FractionDetails $fractionDetails = null;

    public function getFractionDetails(): ?FractionDetails
    {
        return $this->fractionDetails;
    }

    public function setFractionDetails(?FractionDetails $fraction): self
    {
        $this->fractionDetails = $fraction;

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"fractionInterest:read", "fractionDetails:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "fractionInterest:writer-read",
     *     "fractionInterest:coordinator-read",
     *     "fractionInterest:pm-read",
     *     "fractionInterest:admin-read",
     *     "fractionDetails:writer-read",
     *     "fractionDetails:coordinator-read",
     *     "fractionDetails:pm-read",
     *     "fractionDetails:admin-read",
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

    //region Usages
    /**
     * @var Collection|UsedFractionInterest[]
     * @Groups({
     *     "fractionInterest:read",
     *     "project:read",
     * })
     * @ORM\OneToMany(targetEntity="UsedFractionInterest", mappedBy="fractionInterest", cascade={"persist"})
     */
    private Collection $usages;

    /**
     * @return Collection|UsedFractionInterest[]
     */
    public function getUsages(): Collection
    {
        return $this->usages;
    }

    public function addUsage(UsedFractionInterest $usedFractionInterest): self
    {
        if (!$this->usages->contains($usedFractionInterest)) {
            $this->usages[] = $usedFractionInterest;
            $usedFractionInterest->setFractionInterest($this);
        }

        return $this;
    }

    public function removeUsage(UsedFractionInterest $usedFractionInterest): self
    {
        if ($this->usages->contains($usedFractionInterest)) {
            $this->usages->removeElement($usedFractionInterest);
            // set the owning side to null (unless already changed)
            if ($usedFractionInterest->getFractionInterest() === $this) {
                $usedFractionInterest->setFractionInterest(null);
            }
        }

        return $this;
    }

    //endregion

    public function __construct()
    {
        $this->usages = new ArrayCollection();
    }
}
