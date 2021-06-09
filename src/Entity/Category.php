<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiFilter;
use ApiPlatform\Core\Annotation\ApiResource;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use App\Entity\Traits\AutoincrementId;
use App\Entity\Traits\SlugFunctions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vrok\DoctrineAddons\Entity\NormalizerHelper;
use Vrok\SymfonyAddons\Validator\Constraints as VrokAssert;

/**
 * Category.
 *
 * @ApiResource(
 *     attributes={
 *         "security"="is_granted('IS_AUTHENTICATED_ANONYMOUSLY')",
 *         "pagination_items_per_page"=15
 *     },
 *     collectionOperations={
 *         "get",
 *         "post"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "category:create"}
 *         },
 *     },
 *     itemOperations={
 *         "get",
 *         "put"={
 *             "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *             "validation_groups"={"Default", "category:write"}
 *         },
 *         "delete"={
 *              "security"="is_granted('ROLE_PROCESS_MANAGER')",
 *          },
 *     },
 *     normalizationContext={
 *         "groups"={"default:read", "category:read"},
 *         "enable_max_depth"=true,
 *         "swagger_definition_name"="Read"
 *     },
 *     denormalizationContext={
 *         "groups"={"default:write", "category:write"},
 *         "swagger_definition_name"="Write"
 *     }
 * )
 *
 * @ApiFilter(SearchFilter::class, properties={
 *     "id": "exact",
 *     "name": "partial",
 *     "slug": "exact"
 * })
 *
 * @ORM\Entity(repositoryClass="App\Repository\CategoryRepository")
 * @ORM\Table(uniqueConstraints={
 *     @ORM\UniqueConstraint(name="category_name", columns={"name"})
 * })
 * @UniqueEntity(fields={"name"}, message="validate.category.duplicateName")
 */
class Category
{
    use AutoincrementId;
    use SlugFunctions;

    //region Name
    /**
     * Require at least one letter in the name so that the slug
     * is never only numeric, to differentiate it from an ID.
     *
     * @Assert\Sequentially({
     *     @Assert\NotBlank,
     *     @Assert\Length(min=5, max=100),
     *     @VrokAssert\NoLineBreaks,
     *     @Assert\Regex(
     *         pattern="/[a-zA-Z]/",
     *         message="validate.general.letterRequired"
     *     ),
     * })
     * @Groups({"category:read", "category:write", "project:read"})
     * @ORM\Column(type="string", length=100, nullable=false)
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

    //region Projects
    /**
     * @var Collection|Project[]
     *
     * @ORM\ManyToMany(targetEntity="Project", mappedBy="categories")
     * @ORM\JoinTable(name="project_category")
     */
    protected Collection $projects;

    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            return $this;
        }

        $this->projects->add($project);
        $project->addCategory($this);

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            return $this;
        }

        $this->projects->removeElement($project);
        $project->removeCategory($this);

        return $this;
    }

    //endregion

    //region Slug
    /**
     * @Groups({"category:read", "project:read"})
     * @ORM\Column(type="string", length=150, nullable=true)
     * @Gedmo\Slug(fields={"name"})
     */
    private ?string $slug = null;
    //endregion

    public function __construct()
    {
        $this->projects = new ArrayCollection();
    }
}
