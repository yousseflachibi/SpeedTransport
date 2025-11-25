<?php

namespace App\Repository;

use App\Entity\ZoneKine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ZoneKine>
 *
 * @method ZoneKine|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZoneKine|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZoneKine[]    findAll()
 * @method ZoneKine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZoneKineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZoneKine::class);
    }
}
