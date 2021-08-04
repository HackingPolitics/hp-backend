<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use DateTimeImmutable;

trait UpdatedAtFunctions
{
    public function setUpdatedAt(?DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        if (!$this->updatedAt && !empty($this->createdAt)) {
            return $this->createdAt;
        }

        return $this->updatedAt;
    }
}
