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
 * Fraction.
 *
 * Collection cannot be queried, fractions can only be retrieved via the
 * Council relations.
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
 *             "validation_groups"={"Default", "fraction:create"}
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')"
 *         },
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "fraction:write"}
 *         },
 *         "delete"={
 *              "security"="is_granted('ROLE_ADMIN')",
 *          },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "fraction:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "fraction:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FractionRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="fraction_council_name", columns={"council_id", "name"})
 * })
 * @UniqueEntity(fields={"name", "council"}, message="validate.fraction.duplicateFraction")
 */
class Fraction
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region Active
    /**
     * @Groups({
     *     "fraction:read",
     *     "fraction:write",
     *     "council:read",
     *     "project:read",
     * })
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

    //region Color
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=6),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({
     *     "fraction:read",
     *     "fraction:write",
     *     "council:read",
     *     "project:read",
     * })
     * @ORM\Column(type="string", length=6, nullable=false, options={"default": "000000"})
     */
    private string $color = '000000';

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $value): self
    {
        $this->color = trim($value);

        return $this;
    }

    //endregion

    //region Details
    /**
     * @var Collection|FractionDetails[]
     * @Groups({"none"})
     * @ORM\OneToMany(targetEntity="FractionDetails", mappedBy="fraction", cascade={"persist"})
     */
    private $details;

    /**
     * @return Collection|FractionDetails[]
     */
    public function getDetails(): Collection
    {
        return $this->details;
    }

    public function addDetails(FractionDetails $details): self
    {
        if (!$this->details->contains($details)) {
            $this->details[] = $details;
            $details->setFraction($this);
        }

        return $this;
    }

    public function removeDetails(FractionDetails $fractionDetails): self
    {
        if ($this->details->contains($fractionDetails)) {
            $this->details->removeElement($fractionDetails);
            // set the owning side to null (unless already changed)
            if ($fractionDetails->getFraction() === $this) {
                $fractionDetails->setFraction(null);
            }
        }

        return $this;
    }

    //endregion

    //region MemberCount
    /**
     * @Assert\NotBlank
     * @Assert\Range(min=1)
     * @Groups({
     *     "fraction:read",
     *     "fraction:write",
     *     "council:read",
     *     "project:read",
     * })
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
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=60),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({
     *     "fraction:read",
     *     "fraction:write",
     *     "council:read",
     *     "project:read",
     * })
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

    //region Council
    /**
     * @Groups({"fraction:read", "fraction:write"})
     * @ORM\ManyToOne(targetEntity="Council", inversedBy="fractions")
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

    //region Url
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=200),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({
     *     "fraction:read",
     *     "fraction:write",
     *     "council:read",
     *     "project:read",
     * })
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
     * @Groups({"fraction:read", "council:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({"fraction:create", "fraction:read", "council:read"})
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
