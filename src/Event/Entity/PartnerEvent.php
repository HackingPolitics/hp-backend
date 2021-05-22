<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\Partner;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for a partner.
 */
abstract class PartnerEvent extends Event
{
    public Partner $application;

    public function __construct(Partner $partner)
    {
        $this->application = $partner;
    }
}
