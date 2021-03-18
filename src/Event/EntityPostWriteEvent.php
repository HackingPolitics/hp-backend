<?php

declare(strict_types=1);

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class EntityPostWriteEvent extends Event
{
    // nullable for DELETE, use context['previous_data'] to access the entity
    public ?object $entity;

    public array $context;

    public function __construct(?object $entity, array $context)
    {
        $this->entity = $entity;
        $this->context = $context;
    }
}
