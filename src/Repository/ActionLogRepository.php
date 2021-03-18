<?php

namespace App\Repository;

use App\Entity\ActionLog;
use App\Util\DateHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ActionLog[] findAll()
 * @method ActionLog[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionLog::class);
    }

    /**
     * Returns the number of log entries with the given action(s) in the
     * last X minutes/hours as specified in the interval.
     */
    public function getActionCount(array $actions, string $interval): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.action in (:actionList)')
            ->andWhere('a.timestamp >= :after')
            ->setParameters([
                'actionList' => $actions,
                'after'      => DateHelper::nowSubInterval($interval),
            ]);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns the number of log entries for the given IP address with the given
     * action(s) in the last X minutes/hours as specified in the interval.
     */
    public function getActionCountByIp(string $ip, array $actions, string $interval): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.action in (:actionList)')
            ->andWhere('a.ipAddress = :ip')
            ->andWhere('a.timestamp >= :after')
            ->setParameters([
                'actionList' => $actions,
                'after'      => DateHelper::nowSubInterval($interval),
                'ip'         => $ip,
            ]);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Returns the number of log entries for the given user with the given
     * action(s) in the last X minutes/hours as specified in the interval.
     */
    public function getActionCountByUsername(string $username, array $actions, string $interval): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.action in (:actionList)')
            ->andWhere('a.username = :username')
            ->andWhere('a.timestamp >= :after')
            ->setParameters([
                'actionList' => $actions,
                'after'      => DateHelper::nowSubInterval($interval),
                'username'   => $username,
            ]);

        return $qb->getQuery()->getSingleScalarResult();
    }
}
