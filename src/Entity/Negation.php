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
 * Negation.
 *
 * Collection cannot be queried, negations can only be retrieved via the
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
 *             "validation_groups"={"Default", "negation:create"},
 *         },
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "put"={
 *             "security"="is_granted('EDIT', object)",
 *             "validation_groups"={"Default", "negation:write"},
 *         },
 *         "delete"={
 *              "security"="is_granted('DELETE', object)",
 *         },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "negation:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "negation:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity(repositoryClass="App\Repository\NegationRepository")
 */
class Negation
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
     *     "negation:read",
     *     "negation:write",
     *     "counterArgument:read",
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
        $this->description = NormalizerHelper::toNullableHtml($value);

        return $this;
    }

    //endregion

    //region Priority
    /**
     * @Groups({
     *     "negation:read",
     *     "negation:write",
     *     "counterArgument:read",
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

    //region CounterArgument
    /**
     * @Assert\NotBlank
     * @Groups({
     *     "negation:read",
     *     "negation:create",
     * })
     * @MaxDepth(1)
     * @ORM\ManyToOne(targetEntity="CounterArgument", inversedBy="negations")
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

    //region UpdatedAt
    /**
     * @Assert\NotBlank(allowNull=true)
     * @Groups({
     *     "negation:read",
     *     "counterArgument:read",
     *     "project:read"
     * })
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    protected ?DateTimeImmutable $updatedAt = null;
    //endregion

    //region UpdatedBy
    /**
     * @Groups({
     *     "negation:writer-read",
     *     "negation:coordinator-read",
     *     "negation:pm-read",
     *     "negation:admin-read",
     *     "counterArgument:writer-read",
     *     "counterArgument:coordinator-read",
     *     "counterArgument:pm-read",
     *     "counterArgument:admin-read",
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
