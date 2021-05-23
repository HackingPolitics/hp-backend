<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\UpdatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * Proposal.
 *
 * Collection cannot be queried, proposals can only be retrieved via the
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
 *             "validation_groups"={"Default", "proposal:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "proposal:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "proposal:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "proposal:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\ProposalRepository")
 */
class Proposal
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region ActionMandate
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=15000),
     *     @Assert\Length(max=5000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="text", length=15000, nullable=true)
     */
    private ?string $actionMandate = null;

    public function getActionMandate(): string
    {
        return $this->actionMandate ?? '';
    }

    public function setActionMandate(?string $value): self
    {
        $this->actionMandate = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Comment
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=15000),
     *     @Assert\Length(max=5000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="text", length=15000, nullable=true)
     */
    private ?string $comment = null;

    public function getComment(): string
    {
        return $this->comment ?? '';
    }

    public function setComment(?string $value): self
    {
        $this->comment = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Introduction
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=3000),
     *     @Assert\Length(max=1000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="string", length=3000, nullable=true)
     */
    private ?string $introduction = null;

    public function getIntroduction(): string
    {
        return $this->introduction ?? '';
    }

    public function setIntroduction(?string $value): self
    {
        $this->introduction = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "proposal:read",
     *     "proposal:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="proposals")
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

    //region Reasoning
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\Length(max=60000),
     *     @Assert\Length(max=20000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="text", length=60000, nullable=true)
     */
    private ?string $reasoning = null;

    public function getReasoning(): string
    {
        return $this->reasoning ?? '';
    }

    public function setReasoning(?string $value): self
    {
        $this->reasoning = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Sponsor
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=100),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="string", length=100, nullable=false)
     */
    private ?string $sponsor = null;

    public function getSponsor(): string
    {
        return $this->sponsor ?? '';
    }

    public function setSponsor(?string $value): self
    {
        $this->sponsor = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region Title
    /**
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=1000),
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="string", length=1000, nullable=false)
     */
    private ?string $title = null;

    public function getTitle(): string
    {
        return $this->title ?? '';
    }

    public function setTitle(?string $value): self
    {
        $this->title = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"proposal:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "proposal:writer-read",
     *     "proposal:coordinator-read",
     *     "proposal:pm-read",
     *     "proposal:admin-read",
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

    //region Url
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=200),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\Column(type="string", length=200, nullable=true)
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
}
