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
 * FractionDetails.
 *
 * Collection cannot be queried, fractionDetails can only be retrieved via the
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
 *             "validation_groups"={"Default", "fractionDetails:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "fractionDetails:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fractionDetails:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fractionDetails:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FractionDetailsRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="fraction_project", columns={"fraction_id", "project_id"})
 * })
 * @UniqueEntity(fields={"fraction", "project"}, message="validate.fractionDetails.duplicateFractionDetails")
 */
class FractionDetails
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region ContactEmail
    /**
     * @Assert\Email
     * @Assert\Length(max=255)
     * @Groups({"fractionDetails:read", "fractionDetails:write", "project:read"})
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
     * @Groups({"fractionDetails:read", "fractionDetails:write", "project:read"})
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
     * @Groups({"fractionDetails:read", "fractionDetails:write", "project:read"})
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

    //region Fraction
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "fractionDetails:read",
     *     "fractionDetails:create",
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\ManyToOne(targetEntity="Fraction", inversedBy="details")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Fraction $fraction = null;

    public function getFraction(): ?Fraction
    {
        return $this->fraction;
    }

    public function setFraction(?Fraction $fraction): self
    {
        $this->fraction = $fraction;

        return $this;
    }

    //endregion

    //region Interests
    /**
     * @var Collection|FractionInterest[]
     * @Groups({
     *     "fractionDetails:read",
     *     "fractionDetails:create",
     *     "project:member-read",
     * })
     * @ORM\OneToMany(targetEntity="FractionInterest", mappedBy="fractionDetails", cascade={"persist"})
     */
    private $interests;

    /**
     * @return Collection|FractionInterest[]
     */
    public function getInterests(): Collection
    {
        return $this->interests;
    }

    public function addInterest(FractionInterest $interest): self
    {
        if (!$this->interests->contains($interest)) {
            $this->interests[] = $interest;
            $interest->setFractionDetails($this);
        }

        return $this;
    }

    public function removeInterest(FractionInterest $interest): self
    {
        if ($this->interests->contains($interest)) {
            $this->interests->removeElement($interest);
            // set the owning side to null (unless already changed)
            if ($interest->getFractionDetails() === $this) {
                $interest->setFractionDetails(null);
            }
        }

        return $this;
    }

    //endregion

    //region PossiblePartner
    /**
     * @Groups({"fractionDetails:read", "fractionDetails:write", "project:read"})
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

    //region PossibleSponsor
    /**
     * @Groups({
     *     "fractionDetails:read",
     *     "fractionDetails:write",
     *     "project:read",
     * })
     * @ORM\Column(type="boolean", options={"default":false})
     */
    private bool $possibleSponsor = false;

    public function isPossibleSponsor(): bool
    {
        return $this->possibleSponsor;
    }

    public function setPossibleSponsor(bool $value = true): self
    {
        $this->possibleSponsor = $value;

        return $this;
    }

    //endregion

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "fractionDetails:read",
     *     "fractionDetails:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="fractionDetails")
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
     *     "fractionDetails:create",
     *     "fractionDetails:writer-read",
     *     "fractionDetails:coordinator-read",
     *     "fractionDetails:pm-read",
     *     "fractionDetails:admin-read",
     *     "fractionDetails:member-write",
     *     "fractionDetails:coordinator-write",
     *     "fractionDetails:pm-write",
     *     "fractionDetails:admin-write",
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
     * @Groups({"fractionDetails:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
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

    public function __construct()
    {
        $this->interests = new ArrayCollection();
    }
}
