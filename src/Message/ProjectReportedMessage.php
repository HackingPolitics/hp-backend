<?php

declare(strict_types=1);

namespace App\Message;

class ProjectReportedMessage
{
    public int $projectId;
    public string $message;
    public string $reporterName;
    public string $reporterEmail;

    public function __construct(
        int $projectId,
        string $message,
        string $reporterName,
        string $reporterEmail
    ) {
        $this->projectId = $projectId;
        $this->message = $message;
        $this->reporterName = $reporterName;
        $this->reporterEmail = $reporterEmail;
    }
}
