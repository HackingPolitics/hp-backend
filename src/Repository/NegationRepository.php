<?php

namespace App\Repository;

use App\Entity\Negation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Negation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Negation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Negation[]    findAll()
 * @method Negation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NegationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Negation::class);
    }
}
