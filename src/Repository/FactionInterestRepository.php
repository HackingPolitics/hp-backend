<?php

namespace App\Repository;

use App\Entity\FactionInterest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FactionInterest|null find($id, $lockMode = null, $lockVersion = null)
 * @method FactionInterest|null findOneBy(array $criteria, array $orderBy = null)
 * @method FactionInterest[]    findAll()
 * @method FactionInterest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactionInterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactionInterest::class);
    }
}
