<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeImmutable;

trait CreatedAtFunctions
{
    public function setCreatedAt(?DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }
}
