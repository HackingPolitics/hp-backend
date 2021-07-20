<?php

declare(strict_types=1);

namespace App\Entity\UploadedFileTypes;

use ApiPlatform\Core\Annotation\ApiResource;
use App\Entity\AbstractUploadedFile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

/**
 * The itemOperation "get" is only there to allow serialization:
 * ("No item route associated with the type App\Entity\AbstractUploadedFile.").
 *
 * @ApiResource(
 *     iri="http://schema.org/MediaObject",
 *     normalizationContext={
 *         "groups"={"default:read"}
 *     },
 *     collectionOperations={},
 *     itemOperations={
 *         "get"={"security"="is_granted('ROLE_ADMIN')"},
 *         "delete"={"security"="is_granted('DELETE', object)"}
 *     }
 * )
 * @ORM\Entity
 * @Vich\Uploadable
 */
class ProposalDocument extends AbstractUploadedFile
{
    /**
     * NOTE: This is not a mapped field of entity metadata, just a simple property.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank(),
     *     @Assert\File(
     *         maxSize="2500k",
     *         maxSizeMessage="validate.upload.tooBig",
     *         uploadIniSizeErrorMessage="validate.upload.tooBig",
     *         uploadFormSizeErrorMessage="validate.upload.tooBig",
     *     ),
     * })
     * @Vich\UploadableField(mapping="private_file", fileNameProperty="name", size="size", mimeType="mimeType", originalName="originalName", dimensions="dimensions")
     */
    public ?File $file = null;
}
