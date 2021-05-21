<?php

namespace App\Repository;

use App\Entity\Council;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Council|null find($id, $lockMode = null, $lockVersion = null)
 * @method Council|null findOneBy(array $criteria, array $orderBy = null)
 * @method Council[]    findAll()
 * @method Council[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CouncilRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Council::class);
    }

    public function findNonDeleted(int $id): ?Council
    {
        return $this->findOneBy([
            'deletedAt' => null,
            'id'        => $id,
        ]);
    }

    public function findOneNonDeletedBy(array $criteria): ?Council
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
