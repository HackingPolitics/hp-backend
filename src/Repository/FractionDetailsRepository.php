<?php

namespace App\Repository;

use App\Entity\FractionDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FractionDetails|null find($id, $lockMode = null, $lockVersion = null)
 * @method FractionDetails|null findOneBy(array $criteria, array $orderBy = null)
 * @method FractionDetails[]    findAll()
 * @method FractionDetails[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FractionDetailsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FractionDetails::class);
    }
}
