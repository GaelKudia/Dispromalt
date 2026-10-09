<?php

namespace App\Repository;

use App\Entity\MouvementCaisse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MouvementCaisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementCaisse::class);
    }

    /**
     * 🎯 SOLDE GLOBAL par devise (toute l'entreprise)
     * Solde = Σ entrées - Σ sorties
     */
    public function getSolde(string $devise): float
    {
        $res = $this->createQueryBuilder('m')
            ->select('
                COALESCE(SUM(CASE WHEN m.type = :e THEN m.montant ELSE 0 END), 0) AS entrees,
                COALESCE(SUM(CASE WHEN m.type = :s THEN m.montant ELSE 0 END), 0) AS sorties
            ')
            ->where('m.devise = :devise')
            ->setParameter('e', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('s', MouvementCaisse::TYPE_SORTIE)
            ->setParameter('devise', $devise)
            ->getQuery()
            ->getSingleResult();

        return (float) $res['entrees'] - (float) $res['sorties'];
    }

    /**
     * 🎯 SOLDE PERSONNEL d'un utilisateur donné pour une devise.
     * = somme de ses propres entrées − somme de ses propres sorties.
     */
        /**
     * Solde PERSONNEL d'un utilisateur donné pour une devise.
     * = somme de ses propres entrées − somme de ses propres sorties.
     */
    public function getSoldeParUtilisateur(User $user, string $devise): float
    {
        $res = $this->createQueryBuilder('m')
            ->select('
                COALESCE(SUM(CASE WHEN m.type = :entree THEN m.montant ELSE 0 END), 0) AS entrees,
                COALESCE(SUM(CASE WHEN m.type = :sortie THEN m.montant ELSE 0 END), 0) AS sorties
            ')
            ->where('m.devise = :devise')
            ->andWhere('m.effectuePar = :user')
            ->setParameter('entree', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('sortie', MouvementCaisse::TYPE_SORTIE)
            ->setParameter('devise', $devise)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleResult();

        return (float) $res['entrees'] - (float) $res['sorties'];
    }
    /** Vérifie si la caisse peut couvrir un montant donné */
    public function peutDecaisser(string $devise, float $montant): bool
    {
        return $this->getSolde($devise) >= $montant;
    }

    /** Tous les mouvements (ordre décroissant) */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.devis', 'd')->addSelect('d')
            ->leftJoin('m.effectuePar', 'u')->addSelect('u')
            ->orderBy('m.dateMouvement', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Mouvements d'une période (pour rapports) */
    public function findByPeriode(\DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.effectuePar', 'u')->addSelect('u')
            ->where('m.dateMouvement BETWEEN :d AND :f')
            ->setParameter('d', $debut)
            ->setParameter('f', $fin)
            ->orderBy('m.dateMouvement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Totaux entrées/sorties d'une période, par devise */
    public function getTotauxPeriode(\DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('
                m.devise,
                COALESCE(SUM(CASE WHEN m.type = :e THEN m.montant ELSE 0 END), 0) AS entrees,
                COALESCE(SUM(CASE WHEN m.type = :s THEN m.montant ELSE 0 END), 0) AS sorties
            ')
            ->where('m.dateMouvement BETWEEN :d AND :f')
            ->setParameter('e', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('s', MouvementCaisse::TYPE_SORTIE)
            ->setParameter('d', $debut)
            ->setParameter('f', $fin)
            ->groupBy('m.devise')
            ->getQuery()
            ->getResult();

        $totaux = [
            'USD' => ['entrees' => 0, 'sorties' => 0],
            'CDF' => ['entrees' => 0, 'sorties' => 0],
        ];
        foreach ($rows as $r) {
            $totaux[$r['devise']] = [
                'entrees' => (float) $r['entrees'],
                'sorties' => (float) $r['sorties'],
            ];
        }
        return $totaux;
    }
}