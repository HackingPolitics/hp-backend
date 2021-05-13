<?php

namespace App\Repository;

use App\Entity\Parliament;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Parliament|null find($id, $lockMode = null, $lockVersion = null)
 * @method Parliament|null findOneBy(array $criteria, array $orderBy = null)
 * @method Parliament[]    findAll()
 * @method Parliament[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParliamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parliament::class);
    }

    public function findNonDeleted(int $id): ?Parliament
    {
        return $this->findOneBy([
            'deletedAt' => null,
            'id'        => $id,
        ]);
    }

    public function findOneNonDeletedBy(array $criteria): ?Parliament
    {
        $criteria['deletedAt'] = null;

        return $this->findOneBy($criteria);
    }

    public function findNonDeletedBy(array $criteria): array
    {
        $criteria['deletedAt'] = null;

        return $this->findBy($criteria);
    }
}
