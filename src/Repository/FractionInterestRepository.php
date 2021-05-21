<?php

namespace App\Repository;

use App\Entity\FractionInterest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FractionInterest|null find($id, $lockMode = null, $lockVersion = null)
 * @method FractionInterest|null findOneBy(array $criteria, array $orderBy = null)
 * @method FractionInterest[]    findAll()
 * @method FractionInterest[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FractionInterestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FractionInterest::class);
    }
}
