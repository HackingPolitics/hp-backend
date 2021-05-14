<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Parliament;
use App\Entity\Project;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ProjectInput
{
    /**
     * @var Parliament
     * @Groups({"project:create", "user:register"})
     */
    public ?Parliament $parliament = null;

    /**
     * @Assert\Choice({Project::STATE_PRIVATE, Project::STATE_PUBLIC})
     * @Groups({"project:coordinator-update", "project:pm-update", "project:admin-update"})
     */
    public ?string $state = null;

    /**
     * @Groups({"project:pm-write", "project:admin-write"})
     */
    public ?bool $locked = null;

    //region Project profile
    /**
     * @Groups({"project:write"})
     */
    public ?string $description = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $impact = null;

    /**
     * @Groups({"project:write", "project:create", "user:register"})
     */
    public ?string $title = null;

    /**
     * @Groups({"project:write"})
     */
    public ?string $topic = null;

    //endregion

    //region Project creation
    /**
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Groups({"project:create", "user:register"})
     */
    public ?string $motivation = null;

    /**
     * @Assert\NotBlank(allowNull=true, groups={"user:register"})
     * @Groups({"project:create", "user:register"})
     */
    public ?string $skills = null;
    //endregion

    //region Report
    /**
     * @Assert\Length(min=5, max=1000)
     * @Groups({"project:report"})
     */
    public ?string $reportMessage;

    /**
     * @Assert\Length(min=5, max=200)
     * @Groups({"project:report"})
     */
    public ?string $reporterName;

    /**
     * @Assert\Length(min=5, max=255)
     * @Assert\Email
     * @Groups({"project:report"})
     */
    public ?string $reporterEmail;
    //endregion
}
