<?php

namespace App\Repository;

use App\Entity\Visite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Visite>
 */
class VisiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visite::class);
    }

    /**
     * Visites / RDV DG sur une journée donnée.
     *
     * @return Visite[]
     */
    public function findByJourDG(\DateTimeInterface $jour): array
    {
        $debut = (clone $jour)->setTime(0, 0, 0);
        $fin   = (clone $jour)->setTime(23, 59, 59);

        return $this->createQueryBuilder('v')
            ->where('v.avecDG = :oui')
            ->andWhere('v.dateArrivee BETWEEN :debut AND :fin')
            ->setParameter('oui', true)
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('v.dateArrivee', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les RDV DG par jour sur une plage (stats).
     */
    public function compterParJourDG(\DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT DATE(v.date_arrivee) AS jour, COUNT(v.id) AS nb
            FROM visite v
            WHERE v.avec_dg = 1
              AND v.date_arrivee BETWEEN :debut AND :fin
            GROUP BY DATE(v.date_arrivee)
            ORDER BY jour ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('debut', $debut->format('Y-m-d 00:00:00'));
        $stmt->bindValue('fin',   $fin->format('Y-m-d 23:59:59'));

        return $stmt->executeQuery()->fetchAllAssociative();
    }

    /**
     * RDV délégués à un utilisateur donné (via responsableDelegue).
     *
     * @return Visite[]
     */
    public function findPourDelegue(int $userId): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.responsableDelegue = :uid')
            ->andWhere('v.statut NOT IN (:exclus)')
            ->setParameter('uid', $userId)
            ->setParameter('exclus', ['ANNULE', 'TERMINE'])
            ->orderBy('v.dateArrivee', 'ASC')
            ->getQuery()
            ->getResult();
    }
}