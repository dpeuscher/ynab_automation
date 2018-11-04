<?php

namespace App\Repository;

use App\Entity\BoonTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method BoonTransaction|null find($id, $lockMode = null, $lockVersion = null)
 * @method BoonTransaction|null findOneBy(array $criteria, array $orderBy = null)
 * @method BoonTransaction[]    findAll()
 * @method BoonTransaction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BoonTransactionRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, BoonTransaction::class);
    }

    /**
     * @param string $checksum
     * @return BoonTransaction[] Returns an array of BoonTransaction objects
     */
    public function findByChecksum(string $checksum): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.checksum = :checksum')
            ->setParameter('checksum', $checksum)
            ->orderBy('h.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }
}
