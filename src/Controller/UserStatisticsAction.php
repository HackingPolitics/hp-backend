<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserStatisticsAction
{
    public function __invoke(ManagerRegistry $registry): JsonResponse {
        $data = [];
        $entityManager = $registry->getManagerForClass(User::class);
        $ur = $entityManager->getRepository(User::class);

        $data['existing'] = (int)$ur->createQueryBuilder('u')
            ->select("count(u.id)")
            ->where("u.deletedAt IS NULL")
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['notActive'] = (int)$ur->createQueryBuilder('u')
            ->select("count(u.id)")
            ->where("u.active = :active")
            ->andWhere("u.deletedAt IS NULL")
            ->setParameter('active', false)
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['notValidated'] = (int)$ur->createQueryBuilder('u')
            ->select("count(u.id)")
            ->where("u.validated = :validated")
            ->andWhere("u.deletedAt IS NULL")
            ->setParameter('validated', false)
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $today = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $yesterday = $today->sub(new \DateInterval('P1D'));
        $data['newlyRegistered'] = (int)$ur->createQueryBuilder('u')
            ->select("count(u.id)")
            ->where("u.createdAt >= :yesterday")
            ->andWhere("u.deletedAt IS NULL")
            ->setParameter('yesterday', $yesterday)
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        $data['deleted'] = (int)$ur->createQueryBuilder('u')
            ->select("count(u.id)")
            ->where("u.deletedAt IS NOT NULL")
            ->getQuery()
            ->enableResultCache(60*30)
            ->getSingleScalarResult();

        return new JsonResponse($data);
    }
}
