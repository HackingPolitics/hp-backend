<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProjectStatisticsAction
{
    public function __invoke(ManagerRegistry $registry): JsonResponse
    {
        $data = [];
        $entityManager = $registry->getManagerForClass(Project::class);
        $pr = $entityManager->getRepository(Project::class);

        $today = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $yesterday = $today->sub(new \DateInterval('P1D'));

        $data['total'] = (int) $pr->createQueryBuilder('p')
            ->select('count(p.id)')
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['new'] = (int) $pr->createQueryBuilder('p')
            ->select('count(p.id)')
            ->where('p.createdAt >= :yesterday')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.locked = :locked')
            ->setParameters([
                'locked'    => false,
                'yesterday' => $yesterday,
            ])
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['public'] = (int) $pr->createQueryBuilder('p')
            ->select('count(p.id)')
            ->where('p.state = :state')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.locked = :locked')
            ->setParameters([
                'locked' => false,
                'state'  => Project::STATE_PUBLIC,
            ])
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['deleted'] = (int) $pr->createQueryBuilder('p')
            ->select('count(p.id)')
            ->where('p.deletedAt IS NOT NULL')
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        return new JsonResponse($data);
    }
}
