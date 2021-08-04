<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ExportProposalAction;
use App\Controller\GetProposalCollabAction;
use App\Controller\SetProposalCollabAction;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\UpdatedAtFunctions;
use App\Entity\UploadedFileTypes\ProposalDocument;
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
 *         "export"={
 *             "controller"=ExportProposalAction::class,
 *             "method"="POST",
 *             "path"="/proposals/{id}/export",
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "proposal:export"},
 *         },
 *         "getCollab"={
 *             "controller"=GetProposalCollabAction::class,
 *             "method"="GET",
 *             "path"="/proposals/{id}/collab",
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "proposal:collab"},
 *         },
 *         "setCollab"={
 *             "controller"=SetProposalCollabAction::class,
 *             "method"="POST",
 *             "path"="/proposals/{id}/collab",
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "proposal:collab"},
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
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="title_project", columns={"title", "project_id"})
 * })
 * @UniqueEntity(fields={"title", "project"}, message="validate.proposal.duplicateTitle")
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

    //region DocumentFile
    /**
     * @Groups({"proposal:read", "proposal:write", "project:read"})
     * @ORM\OneToOne(
     *     targetEntity="App\Entity\UploadedFileTypes\ProposalDocument",
     *     cascade={"persist", "remove"},
     *     orphanRemoval=true
     * )
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private ?ProposalDocument $documentFile = null;

    public function getDocumentFile(): ?ProposalDocument
    {
        return $this->documentFile;
    }

    public function setDocumentFile(?ProposalDocument $documentFile): self
    {
        $this->documentFile = $documentFile;

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
     *     @VrokAssert\NoLineBreaks,
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
        $this->url = NormalizerHelper::toNullableString($value);

        return $this;
    }

    //endregion

    //region UsedActionMandates
    /**
     * @var Collection|UsedActionMandate[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedActionMandate", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedActionMandates;

    /**
     * @return Collection|UsedActionMandate[]
     */
    public function getUsedActionMandates(): Collection
    {
        return $this->usedActionMandates;
    }

    public function addUsedActionMandate(UsedActionMandate $usedActionMandate): self
    {
        if (!$this->usedActionMandates->contains($usedActionMandate)) {
            $this->usedActionMandates[] = $usedActionMandate;
            $usedActionMandate->setProposal($this);
        }

        return $this;
    }

    public function removeUsedActionMandate(UsedActionMandate $usedActionMandate): self
    {
        if ($this->usedActionMandates->contains($usedActionMandate)) {
            $this->usedActionMandates->removeElement($usedActionMandate);
            // set the owning side to null (unless already changed)
            if ($usedActionMandate->getProposal() === $this) {
                $usedActionMandate->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    //region UsedArguments
    /**
     * @var Collection|UsedArgument[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedArgument", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedArguments;

    /**
     * @return Collection|UsedArgument[]
     */
    public function getUsedArguments(): Collection
    {
        return $this->usedArguments;
    }

    public function addUsedArgument(UsedArgument $usedArgument): self
    {
        if (!$this->usedArguments->contains($usedArgument)) {
            $this->usedArguments[] = $usedArgument;
            $usedArgument->setProposal($this);
        }

        return $this;
    }

    public function removeUsedArgument(UsedArgument $usedArgument): self
    {
        if ($this->usedArguments->contains($usedArgument)) {
            $this->usedArguments->removeElement($usedArgument);
            // set the owning side to null (unless already changed)
            if ($usedArgument->getProposal() === $this) {
                $usedArgument->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    //region UsedCounterArguments
    /**
     * @var Collection|UsedCounterArgument[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedCounterArgument", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedCounterArguments;

    /**
     * @return Collection|UsedCounterArgument[]
     */
    public function getUsedCounterArguments(): Collection
    {
        return $this->usedCounterArguments;
    }

    public function addUsedCounterArgument(UsedCounterArgument $usedCounterArgument): self
    {
        if (!$this->usedCounterArguments->contains($usedCounterArgument)) {
            $this->usedCounterArguments[] = $usedCounterArgument;
            $usedCounterArgument->setProposal($this);
        }

        return $this;
    }

    public function removeUsedCounterArgument(UsedCounterArgument $usedCounterArgument): self
    {
        if ($this->usedCounterArguments->contains($usedCounterArgument)) {
            $this->usedCounterArguments->removeElement($usedCounterArgument);
            // set the owning side to null (unless already changed)
            if ($usedCounterArgument->getProposal() === $this) {
                $usedCounterArgument->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    //region UsedFractionInterests
    /**
     * @var Collection|UsedFractionInterest[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedFractionInterest", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedFractionInterests;

    /**
     * @return Collection|UsedFractionInterest[]
     */
    public function getUsedFractionInterests(): Collection
    {
        return $this->usedFractionInterests;
    }

    public function addUsedFractionInterest(UsedFractionInterest $usedFractionInterest): self
    {
        if (!$this->usedFractionInterests->contains($usedFractionInterest)) {
            $this->usedFractionInterests[] = $usedFractionInterest;
            $usedFractionInterest->setProposal($this);
        }

        return $this;
    }

    public function removeUsedFractionInterest(UsedFractionInterest $usedFractionInterest): self
    {
        if ($this->usedFractionInterests->contains($usedFractionInterest)) {
            $this->usedFractionInterests->removeElement($usedFractionInterest);
            // set the owning side to null (unless already changed)
            if ($usedFractionInterest->getProposal() === $this) {
                $usedFractionInterest->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    //region UsedNegations
    /**
     * @var Collection|UsedNegation[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedNegation", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedNegations;

    /**
     * @return Collection|UsedNegation[]
     */
    public function getUsedNegations(): Collection
    {
        return $this->usedNegations;
    }

    public function addUsedNegation(UsedNegation $usedNegation): self
    {
        if (!$this->usedNegations->contains($usedNegation)) {
            $this->usedNegations[] = $usedNegation;
            $usedNegation->setProposal($this);
        }

        return $this;
    }

    public function removeUsedNegation(UsedNegation $usedNegation): self
    {
        if ($this->usedNegations->contains($usedNegation)) {
            $this->usedNegations->removeElement($usedNegation);
            // set the owning side to null (unless already changed)
            if ($usedNegation->getProposal() === $this) {
                $usedNegation->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    //region UsedProblems
    /**
     * @var Collection|UsedProblem[]
     * @Groups({
     *     "project:member-read",
     *     "project:pm-read",
     *     "project:admin-read",
     * })
     * @ORM\OneToMany(targetEntity="UsedProblem", mappedBy="proposal", cascade={"persist"})
     */
    private Collection $usedProblems;

    /**
     * @return Collection|UsedProblem[]
     */
    public function getUsedProblems(): Collection
    {
        return $this->usedProblems;
    }

    public function addUsedProblem(UsedProblem $usedProblem): self
    {
        if (!$this->usedProblems->contains($usedProblem)) {
            $this->usedProblems[] = $usedProblem;
            $usedProblem->setProposal($this);
        }

        return $this;
    }

    public function removeUsedProblem(UsedProblem $usedProblem): self
    {
        if ($this->usedProblems->contains($usedProblem)) {
            $this->usedProblems->removeElement($usedProblem);
            // set the owning side to null (unless already changed)
            if ($usedProblem->getProposal() === $this) {
                $usedProblem->setProposal(null);
            }
        }

        return $this;
    }

    //endregion

    public function __construct()
    {
        $this->usedActionMandates = new ArrayCollection();
        $this->usedArguments = new ArrayCollection();
        $this->usedCounterArguments = new ArrayCollection();
        $this->usedFractionInterests = new ArrayCollection();
        $this->usedNegations = new ArrayCollection();
        $this->usedProblems = new ArrayCollection();
    }
}
