<?php

namespace App\Repository;

use App\Entity\ArticleAttribut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Category;
use App\Entity\SubCategory;

/**
 * @extends ServiceEntityRepository<ArticleAttribut>
 */
class ArticleAttributRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleAttribut::class);
    }

    //    /**
    //     * @return ArticleAttribut[] Returns an array of ArticleAttribut objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ArticleAttribut
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    // Dans ton Repository ou Contrôleur (Ex: ArticleAttributRepository)
public function findAllWithDetails(?Category $category = null, ?SubCategory $subCategory = null)
{
    $qb = $this->createQueryBuilder('aa')
        ->join('aa.article', 'a')
        ->leftJoin('a.category', 'c')
        ->leftJoin('a.subCategory', 'sc');

    if ($category) {
        $qb->andWhere('c = :category')
           ->setParameter('category', $category);
    }

    if ($subCategory) {
        $qb->andWhere('sc = :subCategory')
           ->setParameter('subCategory', $subCategory);
    }

    return $qb->getQuery()->getResult();
}
}
