<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * 🚨 Articles sous seuil SANS réappro en cours → vraies alertes
     */
    public function findArticlesEnAlerte(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.quantite <= a.seuilAlerte')
            ->andWhere('a.reapprovisionnementEnCours = false OR a.reapprovisionnementEnCours IS NULL')
            ->orderBy('a.quantite', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 📋 Tous les articles sous seuil (même en réappro) → pour la vue comptable
     */
    public function findAllSousSeuil(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.quantite <= a.seuilAlerte')
            ->orderBy('a.reapprovisionnementEnCours', 'ASC')
            ->addOrderBy('a.quantite', 'ASC')
            ->getQuery()
            ->getResult();
    }
}