<?php

namespace App\Repository;

use App\Entity\HistoriqueReapprovisionnement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HistoriqueReapprovisionnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HistoriqueReapprovisionnement::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('h')
            ->leftJoin('h.article', 'a')->addSelect('a')
            ->leftJoin('h.effectuePar', 'u')->addSelect('u')
            ->orderBy('h.dateAction', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByArticle(int $articleId): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.article = :id')
            ->setParameter('id', $articleId)
            ->orderBy('h.dateAction', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
 * Retourne le dernier lancement de réappro pour un article donné.
 */
public function findDernierLancement(int $articleId): ?HistoriqueReapprovisionnement
{
    return $this->createQueryBuilder('h')
        ->where('h.article = :articleId')
        ->andWhere('h.typeAction = :action')
        ->setParameter('articleId', $articleId)
        ->setParameter('action', 'REAPPRO_LANCE')
        ->orderBy('h.dateAction', 'DESC')
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
}