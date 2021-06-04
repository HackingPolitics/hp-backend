<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UsedCounterArgument.
 *
 * Collection cannot be queried, usedCounterArguments can only be retrieved via the
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
 *             "validation_groups"={"Default", "usedCounterArgument:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "usedCounterArgument:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "usedCounterArgument:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="counterArgument_proposal", columns={"counter_argument_id", "proposal_id"})
 * })
 * @UniqueEntity(fields={"counterArgument", "proposal"}, message="validate.proposal.duplicateCounterArgument")
 */
class UsedCounterArgument
{
    use AutoincrementId;

    //region CounterArgument
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedCounterArgument:read",
     *     "usedCounterArgument:create",
     *     "project:read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="CounterArgument", inversedBy="usages")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?CounterArgument $counterArgument = null;

    public function getCounterArgument(): ?CounterArgument
    {
        return $this->counterArgument;
    }

    public function setCounterArgument(?CounterArgument $counterArgument): self
    {
        $this->counterArgument = $counterArgument;

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Groups({
     *     "usedCounterArgument:read",
     *     "project:read"
     * })
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;
    //endregion

    //region CreatedBy
    /**
     * @Groups({
     *     "usedCounterArgument:create",
     *     "usedCounterArgument:read",
     *     "project:writer-read",
     *     "project:coordinator-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @MaxDepth(1)
     * @Gedmo\Blameable(on="create")
     * @ORM\ManyToOne(targetEntity="User")
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
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

    //region Proposal
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedCounterArgument:read",
     *     "usedCounterArgument:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Proposal", inversedBy="usedCounterArguments")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Proposal $proposal = null;

    public function getProposal(): ?Proposal
    {
        return $this->proposal;
    }

    public function setProposal(?Proposal $proposal): self
    {
        $this->proposal = $proposal;

        return $this;
    }

    //endregion
}
