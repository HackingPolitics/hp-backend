<?php

namespace App\Repository;

use App\Entity\CounterArgument;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method CounterArgument|null find($id, $lockMode = null, $lockVersion = null)
 * @method CounterArgument|null findOneBy(array $criteria, array $orderBy = null)
 * @method CounterArgument[]    findAll()
 * @method CounterArgument[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CounterArgumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CounterArgument::class);
    }
}
