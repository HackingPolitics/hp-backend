<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Controller\ValidationConfirmAction;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;
use Tuupola\Base62;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;

/**
 * Validation.
 *
 * No other operations than "confirm", item:GET is required by API Platform.
 *
 * @ApiResource(
 *     collectionOperations={
 *     },
 *     itemOperations={
 *         "get"={
 *             "security"="is_granted('ROLE_ADMIN')",
 *         },
 *         "confirm"={
 *             "controller"=ValidationConfirmAction::class,
 *             "method"="POST",
 *             "path"="/validations/{id}/confirm",
 *             "requirements"={"id"="\d+"}
 *         }
 *     },
 *     input="App\Dto\ValidationInput",
 *     denormalizationContext={
 *         "allow_extra_attributes"=true,
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ORM\Entity
 */
class Validation
{
    public const TYPE_ACCOUNT        = 'account';
    public const TYPE_RESET_PASSWORD = 'reset-password';
    public const TYPE_CHANGE_EMAIL   = 'change-email';

    use AutoincrementId;

    //region Content
    /**
     * @ORM\Column(type="small_json", length=512, nullable=true)
     */
    private ?array $content = null;

    public function getContent(): array
    {
        return $this->content ?? [];
    }

    public function setContent(?array $content): self
    {
        $this->content = NormalizerHelper::toNullableArray($content);

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;

    use CreatedAtFunctions;
    //endregion

    //region ExpiresAt
    /**
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    private DateTimeImmutable $expiresAt;

    public function isExpired(): bool
    {
        return $this->getExpiresAt() < new DateTimeImmutable();
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    //endregion

    //region Token
    /**
     * @Assert\NotBlank
     * @Assert\Length(max=255)
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    private string $token = '';

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @throws \Exception when token generation fails
     */
    public function generateToken(): void
    {
        // we use BASE62 to shorten URLs instead of using hex chars
        $base62 = new Base62();
        $this->setToken($base62->encode(random_bytes(30)));
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    //endregion

    //region Type
    /**
     * @Assert\NotBlank
     * @Assert\Choice(
     *     choices={
     *         Validation::TYPE_ACCOUNT,
     *         Validation::TYPE_RESET_PASSWORD,
     *         Validation::TYPE_CHANGE_EMAIL,
     *     }
     * )
     * @ORM\Column(type="string", length=50, nullable=false)
     */
    private string $type = '';

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    //endregion

    //region User
    /**
     * @Assert\NotBlank
     * @ORM\ManyToOne(targetEntity="User", inversedBy="validations")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     *
     * Nullable property for User::removeValidation
     */
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    //endregion
}
