<?php

declare(strict_types=1);

namespace App\Event\Entity;

use App\Entity\FractionDetails;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base class for events triggered for fraction details.
 */
abstract class FractionDetailsEvent extends Event
{
    public FractionDetails $fractionDetails;

    public function __construct(FractionDetails $fractionDetails)
    {
        $this->fractionDetails = $fractionDetails;
    }
}
