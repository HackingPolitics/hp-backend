<?php

declare(strict_types=1);

namespace App\Event\Api;

use App\Entity\Partner;
use App\Event\Entity\PartnerEvent;

abstract class ApiPartnerEvent extends PartnerEvent
{
    public array $context;

    public function __construct(Partner $partner, array $context)
    {
        parent::__construct($partner);
        $this->context = $context;
    }
}
