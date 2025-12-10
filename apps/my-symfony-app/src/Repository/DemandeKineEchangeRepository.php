<?php

namespace App\Repository;

use App\Entity\DemandeKineEchange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DemandeKineEchange|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandeKineEchange|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandeKineEchange[]    findAll()
 * @method DemandeKineEchange[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeKineEchangeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeKineEchange::class);
    }
}
