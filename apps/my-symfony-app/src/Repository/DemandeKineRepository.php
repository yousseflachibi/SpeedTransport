<?php

namespace App\Repository;

use App\Entity\DemandeKine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method DemandeKine|null find($id, $lockMode = null, $lockVersion = null)
 * @method DemandeKine|null findOneBy(array $criteria, array $orderBy = null)
 * @method DemandeKine[]    findAll()
 * @method DemandeKine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DemandeKineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeKine::class);
    }
}
