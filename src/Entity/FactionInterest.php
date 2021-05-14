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

/**
 * FactionInterest.
 *
 * Collection cannot be queried, factionInterest can only be retrieved via the
 * FactionDetails relations.
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
 *             "validation_groups"={"Default", "factionInterest:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "factionInterest:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "factionInterest:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "factionInterest:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\FactionInterestRepository")
 */
class FactionInterest
{
    use AutoincrementId;
    use UpdatedAtFunctions;

    //region Description
    /**
     * HTML allowed.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(max=6000),
     *     @Assert\Length(max=2000,
     *         normalizer={NormalizerHelper::class, "stripHtml"}
     *     ),
     * })
     * @Groups({
     *     "factionInterest:read",
     *     "factionInterest:write",
     *     "factionDetails:read",
     *     "project:read",
     * })
     * @ORM\Column(type="text", length=6000, nullable=true)
     */
    private ?string $description = null;

    public function getDescription(): string
    {
        return $this->description ?? '';
    }

    public function setDescription(?string $value): self
    {
        $this->description = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region FactionDetails
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "factionInterest:read",
     *     "factionInterest:create",
     * })
     * @ORM\ManyToOne(targetEntity="FactionDetails", inversedBy="interests")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private ?FactionDetails $factionDetails = null;

    public function getFactionDetails(): ?FactionDetails
    {
        return $this->factionDetails;
    }

    public function setFactionDetails(?FactionDetails $faction): self
    {
        $this->factionDetails = $faction;

        return $this;
    }

    //endregion

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({"factionInterest:read", "factionDetails:read", "project:read"})
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "factionInterest:writer-read",
     *     "factionInterest:coordinator-read",
     *     "factionInterest:pm-read",
     *     "factionInterest:admin-read",
     *     "factionDetails:writer-read",
     *     "factionDetails:coordinator-read",
     *     "factionDetails:pm-read",
     *     "factionDetails:admin-read",
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
}
