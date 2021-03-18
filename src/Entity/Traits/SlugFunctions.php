<?php

declare(strict_types=1);

namespace App\Entity\Traits;

trait SlugFunctions
{
    public function getSlug(): string
    {
        return $this->slug ?? '';
    }

    public function setSlug(?string $slug): self
    {
        // empty string will be set to null.
        $this->slug = trim($slug ?? '') ?: null;

        return $this;
    }
}
