<?php
// src/Repository/QrCodeRepository.php

namespace App\Repository;

use App\Entity\QrCode;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class QrCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QrCode::class);
    }

    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.design', 'd')->addSelect('d')
            ->where('q.user = :user')
            ->setParameter('user', $user)
            ->orderBy('q.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsForUser(User $user): array
    {
        $total = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $today = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.user = :user')
            ->andWhere('q.dateDebut >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()->getSingleScalarResult();

        $active = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.user = :user')
            ->andWhere('q.estActif = true')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        $scans = $this->createQueryBuilder('q')
            ->select('SUM(q.compteurScans)')
            ->where('q.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        return [
            'total'  => (int) $total,
            'today'  => (int) $today,
            'active' => (int) $active,
            'scans'  => (int) ($scans ?? 0),
        ];
    }
}