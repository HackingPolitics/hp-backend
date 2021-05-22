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
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * Partner.
 *
 * Collection cannot be queried, partners can only be retrieved via the
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
 *             "validation_groups"={"Default", "partner:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "partner:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "partner:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "partner:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\PartnerRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="name_project", columns={"name", "project_id"})
 * })
 * @UniqueEntity(fields={"name", "project"}, message="validate.partner.duplicateName")
 */
class Partner
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region ContactEmail
    /**
     * @Assert\Email
     * @Assert\Length(max=255)
     * @Groups({"partner:read", "partner:write", "project:read"})
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
     * @Groups({"partner:read", "partner:write", "project:read"})
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
     * @Groups({"partner:read", "partner:write", "project:read"})
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

    //region Name
    /**
     * Require at least one letter in the name so that the slug
     * is never only numeric, to differentiate it from an ID.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=255),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"partner:read", "partner:write", "project:read"})
     * @ORM\Column(type="string", length=255, nullable=false)
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

    //region Project
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "partner:read",
     *     "partner:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="partners")
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

    //region Role
    /**
     * @Assert\Sequentially({
     *     @Assert\Length(max=1000),
     *     @VrokAssert\NoLineBreaks,
     * })
     * @Groups({"partner:read", "partner:write", "project:read"})
     * @ORM\Column(type="text", length=1000, nullable=true)
     */
    private ?string $role = null;

    public function getRole(): string
    {
        return $this->role ?? '';
    }

    public function setRole(?string $value): self
    {
        $this->role = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region TeamContact
    /**
     * @Groups({
     *     "partner:create",
     *     "partner:writer-read",
     *     "partner:coordinator-read",
     *     "partner:pm-read",
     *     "partner:admin-read",
     *     "partner:member-write",
     *     "partner:coordinator-write",
     *     "partner:pm-write",
     *     "partner:admin-write",
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
     * @Groups({"partner:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "partner:writer-read",
     *     "partner:coordinator-read",
     *     "partner:pm-read",
     *     "partner:admin-read",
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
     * @Groups({"partner:read", "partner:write", "project:read"})
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
}
