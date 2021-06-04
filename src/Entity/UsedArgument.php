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
 * UsedArgument.
 *
 * Collection cannot be queried, usedArguments can only be retrieved via the
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
 *             "validation_groups"={"Default", "usedArgument:create"},
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
 *         "groups"={"default:read", "usedArgument:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "usedArgument:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="argument_proposal", columns={"argument_id", "proposal_id"})
 * })
 * @UniqueEntity(fields={"argument", "proposal"}, message="validate.proposal.duplicateArgument")
 */
class UsedArgument
{
    use AutoincrementId;

    //region Argument
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedArgument:read",
     *     "usedArgument:create",
     *     "project:read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Argument", inversedBy="usages")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Argument $argument = null;

    public function getArgument(): ?Argument
    {
        return $this->argument;
    }

    public function setArgument(?Argument $argument): self
    {
        $this->argument = $argument;

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Groups({
     *     "usedArgument:read",
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
     *     "usedArgument:create",
     *     "usedArgument:read",
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
     *     "usedArgument:read",
     *     "usedArgument:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Proposal", inversedBy="usedArguments")
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
