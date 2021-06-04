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
 * UsedProblem.
 *
 * Collection cannot be queried, usedProblems can only be retrieved via the
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
 *             "validation_groups"={"Default", "usedProblem:create"},
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
 *         "groups"={"default:read", "usedProblem:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "usedProblem:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity()
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="problem_proposal", columns={"problem_id", "proposal_id"})
 * })
 * @UniqueEntity(fields={"problem", "proposal"}, message="validate.proposal.duplicateProblem")
 */
class UsedProblem
{
    use AutoincrementId;

    //region CreatedAt
    /**
     * @Groups({
     *     "usedProblem:read",
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
     *     "usedProblem:create",
     *     "usedProblem:read",
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

    //region Problem
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedProblem:read",
     *     "usedProblem:create",
     *     "project:read",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Problem", inversedBy="usages")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?Problem $problem = null;

    public function getProblem(): ?Problem
    {
        return $this->problem;
    }

    public function setProblem(?Problem $problem): self
    {
        $this->problem = $problem;

        return $this;
    }

    //endregion

    //region Proposal
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "usedProblem:read",
     *     "usedProblem:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Proposal", inversedBy="usedProblems")
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
