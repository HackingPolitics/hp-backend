<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatched on cron:daily to anonymize and purge old action logs.
 */
class CleanupActionLogMessage
{
}
