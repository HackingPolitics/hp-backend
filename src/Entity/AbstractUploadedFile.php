<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiProperty;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\CreatedAtFunctions;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Serializer\Annotation\Groups;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * @ORM\Entity()
 * @ORM\Table(name="uploaded_file", indexes={
 *     @ORM\Index(name="type_idx", columns={"type"})
 * })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string", length=50)
 * @ORM\DiscriminatorMap({
 *     "proposal_document" = "App\Entity\UploadedFileTypes\ProposalDocument",
 * })
 * @Vich\Uploadable
 */
abstract class AbstractUploadedFile
{
    use AutoincrementId;
    use CreatedAtFunctions;

    //region Name
    /**
     * @Groups({"default:read"})
     * @ORM\Column(type="string", length=255, nullable=false)
     *
     * Nullable property to allow deletion
     */
    protected ?string $name;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    //endregion

    //region OriginalName
    /**
     * @Groups({"default:read"})
     * @ORM\Column(nullable=false)
     *
     * Nullable property to allow deletion
     */
    protected ?string $originalName;

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(?string $originalName): self
    {
        $this->originalName = $originalName;

        return $this;
    }

    //endregion

    //region MimeType
    /**
     * @Groups({"default:read"})
     * @ORM\Column(type="string", length=50, nullable=false)
     *
     * Nullable property to allow deletion
     */
    protected ?string $mimeType;

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    //endregion

    //region Size
    /**
     * @Groups({"default:read"})
     * @ORM\Column(type="integer", nullable=false, options={"unsigned": true})
     *
     * Nullable property to allow deletion
     */
    protected int $size;

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size ?? 0;

        return $this;
    }

    //endregion

    //region Dimensions
    /**
     * @Groups({"default:read"})
     * @ORM\Column(type="small_json", length=50, nullable=true)
     */
    protected ?array $dimensions = null;

    public function getDimensions(): ?array
    {
        return $this->dimensions;
    }

    public function setDimensions(?array $dimensions): self
    {
        $this->dimensions = $dimensions;

        return $this;
    }

    //endregion

    //region CreatedAt
    /**
     * @Groups({"default:read"})
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable")
     */
    protected ?DateTimeImmutable $createdAt = null;
    //endregion

    /**
     * Will be injected by the ResolveContentUrlSubscriber.
     *
     * @ApiProperty(iri="http://schema.org/contentUrl")
     * @Groups({"default:read", "default:write"})
     */
    public ?string $contentUrl = null;
}
