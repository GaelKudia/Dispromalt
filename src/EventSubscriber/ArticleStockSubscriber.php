<?php

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\HistoriqueReapprovisionnement;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;

class ArticleStockSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::preUpdate,
            Events::postPersist,
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Article) {
            return;
        }

        if (!$args->hasChangedField('quantite')) {
            return;
        }

        $ancienneQte = (int) $args->getOldValue('quantite');
        $nouvelleQte = (int) $args->getNewValue('quantite');
        $seuil = (int) ($entity->getSeuilAlerte() ?? 0);

        // 🚨 CAS 1 : le stock vient de PASSER SOUS le seuil
        if ($nouvelleQte <= $seuil && $ancienneQte > $seuil) {
            $args->setNewValue('alerteDeclenchee', true);
            $args->setNewValue('dateSignalementAlerte', new \DateTime());
            $this->logHistorique($args->getObjectManager(), $entity, 'ALERTE_DECLENCHEE',
                "Stock critique : {$nouvelleQte} unités (seuil : {$seuil})");
        }

        // ✅ CAS 2 : le stock vient de REMONTER au-dessus du seuil
        if ($nouvelleQte > $seuil && $ancienneQte <= $seuil) {
            $args->setNewValue('alerteDeclenchee', false);
            $args->setNewValue('reapprovisionnementEnCours', false);
            $args->setNewValue('dateSignalementAlerte', null);
            $this->logHistorique($args->getObjectManager(), $entity, 'ALERTE_LEVEE',
                "Stock rétabli : {$nouvelleQte} unités (seuil : {$seuil}) — alerte levée automatiquement");
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Article) {
            return;
        }
        if ($entity->getQuantite() <= ($entity->getSeuilAlerte() ?? 0)) {
            $entity->setAlerteDeclenchee(true);
            $entity->setDateSignalementAlerte(new \DateTime());
            $this->logHistorique($args->getObjectManager(), $entity, 'ALERTE_DECLENCHEE',
                "Article créé déjà sous le seuil");
        }
    }

    private function logHistorique($em, Article $article, string $type, string $commentaire): void
    {
        $h = new HistoriqueReapprovisionnement();
        $h->setArticle($article);
        $h->setTypeAction($type);
        $h->setCommentaire($commentaire);
        $h->setDateAction(new \DateTime());
        $h->setQuantiteAuMoment($article->getQuantite());
        $h->setSeuilAuMoment($article->getSeuilAlerte());
        $em->persist($h);
    }
}