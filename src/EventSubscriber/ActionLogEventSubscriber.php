<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Message\CleanupActionLogMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;
use Vrok\SymfonyAddons\Event\CronDailyEvent;

class ActionLogEventSubscriber implements EventSubscriberInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            CronDailyEvent::class => [
                ['onCronDaily', 100],
            ],
        ];
    }

    public function onCronDaily(): void
    {
        $this->messageBus()->dispatch(new CleanupActionLogMessage());
        $this->logger()->debug('Daily request to anonymize & purge old action logs was sent to the message queue.');
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function messageBus(): MessageBusInterface
    {
        return $this->container->get(__METHOD__);
    }
}
