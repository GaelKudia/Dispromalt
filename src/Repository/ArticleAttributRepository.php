<?php

namespace App\Repository;

use App\Entity\ArticleAttribut;
use App\Entity\Category;
use App\Entity\SubCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleAttribut>
 */
class ArticleAttributRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleAttribut::class);
    }

    /**
     * Récupère tous les attributs d'articles avec leurs relations
     * (article, catégorie, sous-catégorie, machine) préchargées.
     *
     * ⚠️ Utilise LEFT JOIN partout pour ne JAMAIS exclure d'article
     *    (ex : un article sans catégorie doit quand même apparaître).
     *
     * @return ArticleAttribut[]
     */
    public function findAllWithDetails(?Category $category = null, ?SubCategory $subCategory = null): array
    {
        $qb = $this->createQueryBuilder('aa')
            ->addSelect('a', 'c', 'sc', 'm')
            ->leftJoin('aa.article', 'a')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.subCategory', 'sc')
            ->leftJoin('a.machine', 'm')
            ->orderBy('a.nom', 'ASC')
            ->addOrderBy('aa.id', 'ASC');

        if ($category !== null) {
            $qb->andWhere('c = :category')
               ->setParameter('category', $category);
        }

        if ($subCategory !== null) {
            $qb->andWhere('sc = :subCategory')
               ->setParameter('subCategory', $subCategory);
        }

        return $qb->getQuery()->getResult();
    }
}