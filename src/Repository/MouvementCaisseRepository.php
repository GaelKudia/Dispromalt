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

    public function getSoldeComptabilite(string $devise): float
    {
        $res = $this->createQueryBuilder('m')
            ->select('
                COALESCE(SUM(CASE WHEN m.type = :e THEN m.montant ELSE 0 END), 0) AS entrees,
                COALESCE(SUM(CASE WHEN m.type = :s THEN m.montant ELSE 0 END), 0) AS sorties
            ')
            ->join('m.effectuePar', 'u')
            ->where('m.devise = :devise')
            ->andWhere('u.roles LIKE :compta OR u.roles LIKE :chef')
            ->setParameter('e', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('s', MouvementCaisse::TYPE_SORTIE)
            ->setParameter('devise', $devise)
            ->setParameter('compta', '%ROLE_COMPTA%')
            ->setParameter('chef',   '%ROLE_CHEF_COMPTA%')
            ->getQuery()
            ->getSingleResult();

        return (float) $res['entrees'] - (float) $res['sorties'];
    }

    public function getSoldeCaisse(string $devise): float
    {
        $res = $this->createQueryBuilder('m')
            ->select('
                COALESCE(SUM(CASE WHEN m.type = :e THEN m.montant ELSE 0 END), 0) AS entrees,
                COALESCE(SUM(CASE WHEN m.type = :s THEN m.montant ELSE 0 END), 0) AS sorties
            ')
            ->join('m.effectuePar', 'u')
            ->where('m.devise = :devise')
            ->andWhere('u.roles LIKE :caissier')
            ->setParameter('e', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('s', MouvementCaisse::TYPE_SORTIE)
            ->setParameter('devise', $devise)
            ->setParameter('caissier', '%ROLE_CAISSIER%')
            ->getQuery()
            ->getSingleResult();

        return (float) $res['entrees'] - (float) $res['sorties'];
    }

    public function peutDecaisser(string $devise, float $montant): bool
    {
        return $this->getSolde($devise) >= $montant;
    }

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