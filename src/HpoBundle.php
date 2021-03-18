<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\SettingsExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class HpoBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new SettingsExtension();
    }
}
