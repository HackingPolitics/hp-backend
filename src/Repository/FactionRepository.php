<?php

namespace App\Repository;

use App\Entity\Faction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Faction|null find($id, $lockMode = null, $lockVersion = null)
 * @method Faction|null findOneBy(array $criteria, array $orderBy = null)
 * @method Faction[]    findAll()
 * @method Faction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faction::class);
    }
}
