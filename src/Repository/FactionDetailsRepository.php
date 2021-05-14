<?php

namespace App\Repository;

use App\Entity\FactionDetails;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FactionDetails|null find($id, $lockMode = null, $lockVersion = null)
 * @method FactionDetails|null findOneBy(array $criteria, array $orderBy = null)
 * @method FactionDetails[]    findAll()
 * @method FactionDetails[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactionDetailsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactionDetails::class);
    }
}
