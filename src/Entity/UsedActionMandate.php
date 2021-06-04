<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\UpdatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * UsedActionMandate.
 *
 * Collection cannot be queried, usedActionMandates can only be retrieved via the
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
 *             "validation_groups"={"Default", "usedActionMandate:create"},
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
 *         "groups"={"default:read", "usedActionMandate:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "usedActionMandate:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="actionMandate_proposal", columns={"action_mandate_id", "proposal_id"})
 * })
 * @UniqueEntity(fields={"actionMandate", "proposal"}, message="validate.proposal.duplicateActionMandate")
 */
class UsedActionMandate
{
    use AutoincrementId;

    //region ActionMandate
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedActionMandate:read",
     *     "usedActionMandate:create",
     *     "project:read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="ActionMandate", inversedBy="usages")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?ActionMandate $actionMandate = null;

    public function getActionMandate(): ?ActionMandate
    {
        return $this->actionMandate;
    }

    public function setActionMandate(?ActionMandate $actionMandate): self
    {
        $this->actionMandate = $actionMandate;

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Groups({
     *     "usedActionMandate:read",
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
     *     "usedActionMandate:create",
     *     "usedActionMandate:read",
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
     *     "usedActionMandate:read",
     *     "usedActionMandate:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Proposal", inversedBy="usedActionMandates")
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
