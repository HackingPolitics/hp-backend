<?php

declare(strict_types=1);

namespace App\Util;

abstract class DateHelper
{
    public static function nowAddInterval(string $interval): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->add(new \DateInterval($interval))
            // Doctrine does not automatically convert the timezone in parameters
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function nowSubInterval(string $interval): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())
            ->sub(new \DateInterval($interval))
            // Doctrine does not automatically convert the timezone in parameters
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
