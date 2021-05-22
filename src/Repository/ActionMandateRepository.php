<?php

namespace App\Repository;

use App\Entity\ActionMandate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ActionMandate|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActionMandate|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActionMandate[]    findAll()
 * @method ActionMandate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActionMandateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActionMandate::class);
    }
}
