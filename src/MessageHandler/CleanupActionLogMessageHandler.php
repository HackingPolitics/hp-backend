<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ActionLog;
use App\Message\CleanupActionLogMessage;
use App\Util\DateHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

class CleanupActionLogMessageHandler implements MessageHandlerInterface, ServiceSubscriberInterface
{
    use ServiceSubscriberTrait;

    public function __invoke(CleanupActionLogMessage $message)
    {
        $this->purgeLogs();
        $this->anonymizeLogs();
    }

    protected function anonymizeLogs()
    {
        $qbIp = $this->entityManager()->createQueryBuilder()
            ->update(ActionLog::class,'l')
            ->set('l.ipAddress', ':ip')
            ->where('l.ipAddress IS NOT NULL')
            ->andWhere('l.timestamp <= :time')
            ->setParameters([
                'ip'   => null,
                'time' => DateHelper::nowSubInterval('P1D'),
            ]);
        $haveIp = $qbIp->getQuery()->execute();
        $this->logger()->debug(
            'Removed IP address from '.$haveIp.' action log entries.'
        );

        // @todo keep the username on some actions, e.g. to have
        // a log of admin actions?
        $qbUser = $this->entityManager()->createQueryBuilder()
            ->update(ActionLog::class,'l')
            ->set('l.username', ':user')
            ->where('l.username IS NOT NULL')
            ->andWhere('l.timestamp <= :time')
            ->setParameters([
                'time' => DateHelper::nowSubInterval('P7D'),
                'user' => null,
            ]);
        $haveUsername = $qbUser->getQuery()->execute();
        $this->logger()->debug(
            'Removed username from '.$haveUsername.' action log entries.'
        );
    }

    protected function purgeLogs()
    {
        $qb = $this->entityManager()->createQueryBuilder()
            ->delete()
            ->from(ActionLog::class, 'l')
            ->where('l.action IN (:actions)')
            ->andWhere('l.timestamp <= :time')
            ->setParameters([
                'actions' => [
                    ActionLog::FAILED_LOGIN,
                    ActionLog::SUCCESSFUL_LOGIN,
                    ActionLog::FAILED_VALIDATION,
                    ActionLog::FAILED_PW_RESET_REQUEST,
                    ActionLog::SUCCESSFUL_PW_RESET_REQUEST,
                ],
                'time'   => DateHelper::nowSubInterval('P7D'),
            ]);
        $removed = $qb->getQuery()->execute();
        $this->logger()->debug(
            'Removed '.$removed.' old action log entries.'
        );
    }

    private function dispatcher(): EventDispatcherInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->container->get(__METHOD__);
    }

    private function logger(): LoggerInterface
    {
        return $this->container->get(__METHOD__);
    }
}
