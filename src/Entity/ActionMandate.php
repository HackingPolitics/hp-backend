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
 * ActionMandate.
 *
 * Collection cannot be queried, actionMandates can only be retrieved via the
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
 *             "validation_groups"={"Default", "actionMandate:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "actionMandate:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "actionMandate:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "actionMandate:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\ActionMandateRepository")
 */
class ActionMandate
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region Description
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=1000),
     * })
     * @Groups({
     *     "actionMandate:read",
     *     "actionMandate:write",
     *     "project:read",
     * })
     * @ORM\Column(type="text", length=1000, nullable=false)
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

    //region Priority
    /**
     * @Groups({
     *     "actionMandate:read",
     *     "actionMandate:write",
     *     "project:read",
     * })
     * @ORM\Column(type="smallint", nullable=true, options={"default":0})
     *
     * Can be less than 0.
     */
    private int $priority = 0;

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    //endregion

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "actionMandate:read",
     *     "actionMandate:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="actionMandates")
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

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"actionMandate:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "actionMandate:writer-read",
     *     "actionMandate:coordinator-read",
     *     "actionMandate:pm-read",
     *     "actionMandate:admin-read",
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
     * @var Collection|UsedActionMandate[]
     * @Groups({
     *     "argument:read",
     *     "project:read",
     * })
     * @ORM\OneToMany(targetEntity="UsedActionMandate", mappedBy="actionMandate", cascade={"persist"})
     */
    private Collection $usages;

    /**
     * @return Collection|UsedActionMandate[]
     */
    public function getUsages(): Collection
    {
        return $this->usages;
    }

    public function addUsage(UsedActionMandate $usedActionMandate): self
    {
        if (!$this->usages->contains($usedActionMandate)) {
            $this->usages[] = $usedActionMandate;
            $usedActionMandate->setActionMandate($this);
        }

        return $this;
    }

    public function removeUsage(UsedActionMandate $usedActionMandate): self
    {
        if ($this->usages->contains($usedActionMandate)) {
            $this->usages->removeElement($usedActionMandate);
            // set the owning side to null (unless already changed)
            if ($usedActionMandate->getActionMandate() === $this) {
                $usedActionMandate->setActionMandate(null);
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
