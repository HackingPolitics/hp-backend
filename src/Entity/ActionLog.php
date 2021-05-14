<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\AutoincrementId;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ActionLogRepository")
 * @ORM\Table(indexes={
 *     @ORM\Index(name="ipAddress_idx", columns={"ip_address"}),
 *     @ORM\Index(name="action_idx", columns={"action"}),
 *     @ORM\Index(name="username_idx", columns={"username"}),
 *     @ORM\Index(name="timestamp_idx", columns={"timestamp"})
 * })
 */
class ActionLog
{
    /*
     * Because DateTime columns can't be used in composite identifiers we have
     * to use the autoincrement ID:
     * - DateTime[Immutable] does not implement __toString() needed by the UOW to create the combined identifier string
     * - UOW does not use the getTimestamp() getter where we could return a subclass that implements __toString
     * - Gedmo\Timestampable uses Reflection instead of the setTimestamp() setter where we could convert to a subclass
     */
    use AutoincrementId;
    public const FAILED_LOGIN = 'failed_login';
    public const SUCCESSFUL_LOGIN = 'successful_login';
    public const REGISTERED_USER = 'registered_user';
    public const FAILED_VALIDATION = 'failed_validation';
    public const FAILED_PW_RESET_REQUEST = 'failed_pw_reset_request';
    public const SUCCESSFUL_PW_RESET_REQUEST = 'successful_pw_reset_request';
    public const CREATED_PROJECT = 'created_project';
    public const REPORTED_PROJECT = 'reported_project';

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public ?string $ipAddress;

    /**
     * Length=255 to equal email address.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    public ?string $username;

    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     */
    public string $action;

    /**
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(type="datetime_immutable", nullable=false)
     */
    public DateTimeImmutable $timestamp;
}
