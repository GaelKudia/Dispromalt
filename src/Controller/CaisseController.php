<?php

namespace App\Controller;

use App\Entity\MouvementCaisse;
use App\Entity\User;
use App\Entity\Visite;
use App\Form\MouvementCaisseType;
use App\Form\VisiteType;
use App\Repository\MouvementCaisseRepository;
use App\Repository\VisiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/caisse')]
class CaisseController extends AbstractController
{
    private const ROLES_CAISSE = "is_granted('ROLE_CAISSIER') or is_granted('ROLE_ADMIN')";

    // ═══════════════════════════════════════════════════════════
    // ✅ ENTRÉE DE FONDS EN CAISSE
    // ═══════════════════════════════════════════════════════════
    #[Route('/entree', name: 'app_caisse_entree', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function entree(
        Request $request,
        EntityManagerInterface $em,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $mouvement = new MouvementCaisse();
        $mouvement->setType(MouvementCaisse::TYPE_ENTREE);
        $mouvement->setEffectuePar($user);

        $form = $this->createForm(MouvementCaisseType::class, $mouvement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($mouvement);
            $em->flush();

            $this->addFlash('success', sprintf(
                '✅ Entrée enregistrée : %s %s — %s',
                number_format((float) $mouvement->getMontant(), 2, ',', ' '),
                $mouvement->getDevise(),
                $mouvement->getProvenance()
            ));

            return $this->redirectToRoute('app_caisse_entree_imprimer', ['id' => $mouvement->getId()]);
        }

        return $this->render('dashboard/caisse/entree.html.twig', [
            'form'   => $form->createView(),
            'soldes' => [
                'USD' => $mouvementRepo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_USD),
                'CDF' => $mouvementRepo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_CDF),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ IMPRESSION DU REÇU D'ENTRÉE (PDF)
    // ═══════════════════════════════════════════════════════════
    #[Route('/entree/imprimer/{id}', name: 'app_caisse_entree_imprimer', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function imprimerEntree(MouvementCaisse $mouvement): Response
    {
        if ($mouvement->getType() !== MouvementCaisse::TYPE_ENTREE) {
            throw $this->createNotFoundException('Ce mouvement n\'est pas une entrée.');
        }

        $html = $this->renderView('dashboard/caisse/recu_pdf.html.twig', [
            'mouvement' => $mouvement,
            'date'      => new \DateTime(),
        ]);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont'     => 'Helvetica',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A5', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="recu_entree_' . $mouvement->getId() . '.pdf"',
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ MES MOUVEMENTS (filtrés sur l'utilisateur connecté)
    // ═══════════════════════════════════════════════════════════
    #[Route('/mouvements', name: 'app_caisse_mouvements', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function historique(
        Request $request,
        MouvementCaisseRepository $repo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $dateDebut = $request->query->get('date_debut');
        $dateFin   = $request->query->get('date_fin');
        $devise    = $request->query->get('devise');
        $type      = $request->query->get('type');

        // Uniquement les mouvements de l'utilisateur connecté
        $qb = $repo->createQueryBuilder('m')
            ->where('m.effectuePar = :user')
            ->setParameter('user', $user)
            ->orderBy('m.dateMouvement', 'DESC');

        if ($dateDebut && $dateFin) {
            $qb->andWhere('m.dateMouvement BETWEEN :start AND :end')
               ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
               ->setParameter('end',   new \DateTime($dateFin   . ' 23:59:59'));
        }
        if ($devise) {
            $qb->andWhere('m.devise = :devise')->setParameter('devise', $devise);
        }
        if ($type) {
            $qb->andWhere('m.type = :type')->setParameter('type', $type);
        }

        // Solde personnel du caissier connecté
        $soldeUSD = $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_USD);
        $soldeCDF = $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_CDF);

        return $this->render('dashboard/caisse/mouvements.html.twig', [
            'mouvements' => $qb->getQuery()->getResult(),
            'soldeUSD'   => $soldeUSD,
            'soldeCDF'   => $soldeCDF,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'devise'     => $devise,
            'type'       => $type,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ IMPRESSION DU RAPPORT DE CAISSE (PDF)
    // ═══════════════════════════════════════════════════════════
    #[Route('/rapport-imprimer', name: 'app_caisse_rapport_imprimer', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function imprimerRapport(
        Request $request,
        MouvementCaisseRepository $repo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $dateDebut = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFin   = $request->query->get('date_fin',   (new \DateTime('today'))->format('Y-m-d'));

        // Uniquement les mouvements de l'utilisateur connecté
        $mouvements = $repo->createQueryBuilder('m')
            ->where('m.dateMouvement BETWEEN :start AND :end')
            ->andWhere('m.effectuePar = :user')
            ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
            ->setParameter('end',   new \DateTime($dateFin   . ' 23:59:59'))
            ->setParameter('user',  $user)
            ->orderBy('m.dateMouvement', 'ASC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('dashboard/caisse/rapport_pdf.html.twig', [
            'mouvements' => $mouvements,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'soldeUSD'   => $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_USD),
            'soldeCDF'   => $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_CDF),
        ]);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont'     => 'Helvetica',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rapport_caisse_' . $dateDebut . '_' . $dateFin . '.pdf"',
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ PLANNING DG
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg', name: 'app_caisse_rdv_dg', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function planningDG(Request $request, VisiteRepository $visiteRepo): Response
    {
        $jour = $request->query->get('jour', (new \DateTime('today'))->format('Y-m-d'));
        $dateJour = new \DateTime($jour);

        $visitesDuJour = $visiteRepo->findByJourDG($dateJour);

        $debut = (clone $dateJour)->modify('monday this week');
        $fin   = (clone $dateJour)->modify('sunday this week');
        $statsSemaine = $visiteRepo->compterParJourDG($debut, $fin);

        return $this->render('dashboard/caisse/rdv_dg.html.twig', [
            'jour'          => $jour,
            'visitesDuJour' => $visitesDuJour,
            'nbJour'        => count($visitesDuJour),
            'statsSemaine'  => $statsSemaine,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ PROGRAMMER UNE RÉUNION DG ↔ CHEF DE SERVICE
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg/nouveau', name: 'app_caisse_rdv_nouveau', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function nouveauRDV(
        Request $request,
        EntityManagerInterface $em,
        VisiteRepository $visiteRepo
    ): Response {
        $visite = new Visite();
        $visite->setAvecDG(true);
        $visite->setType('RDV_DG');

        $form = $this->createForm(VisiteType::class, $visite, [
            'with_extra_fields' => true,
            'with_delegation'   => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$visite->getDateArrivee()) {
                $visite->setDateArrivee(new \DateTime());
            }
            $visite->setStatut('RESERVE');
            $em->persist($visite);
            $em->flush();

            $this->addFlash('success', '🗓️ RDV DG enregistré avec succès.');
            return $this->redirectToRoute('app_caisse_rdv_imprimer', ['id' => $visite->getId()]);
        }

        $chefsService = $em->getRepository(User::class)->findBy(
            ['isChefService' => true],
            ['email' => 'ASC']
        );

        return $this->render('dashboard/caisse/rdv_nouveau.html.twig', [
            'form'         => $form->createView(),
            'chefsService' => $chefsService,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ DÉLÉGUER UN RDV
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg/deleguer/{id}', name: 'app_caisse_rdv_deleguer', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_DG') or is_granted('ROLE_ADMIN')"))]
    public function deleguerRDV(
        Request $request,
        Visite $visite,
        EntityManagerInterface $em
    ): Response {
        $delegueId = (int) $request->request->get('delegue_id');
        $motif     = trim((string) $request->request->get('motif_delegation', ''));

        $delegue = $em->getRepository(User::class)->find($delegueId);
        if (!$delegue) {
            $this->addFlash('danger', '❌ Délégué introuvable.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        $visite->setAvecDG(false);
        $visite->setResponsableDelegue($delegue);
        $visite->setMotifDelegation($motif);
        $visite->setStatut('DELEGUE');

        $em->flush();

        $this->addFlash('success', sprintf(
            '📤 RDV #%d délégué à %s.',
            $visite->getId(),
            $delegue->getUserIdentifier()
        ));
        return $this->redirectToRoute('app_caisse_rdv_dg');
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ IMPRESSION D'UN RDV (PDF)
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg/imprimer/{id}', name: 'app_caisse_rdv_imprimer', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function imprimerRDV(Visite $visite): Response
    {
        $html = $this->renderView('dashboard/caisse/rdv_pdf.html.twig', [
            'visite' => $visite,
            'date'   => new \DateTime(),
        ]);

        $dompdf = new \Dompdf\Dompdf([
            'isRemoteEnabled' => true,
            'defaultFont'     => 'Helvetica',
        ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'inline; filename="rdv_' . $visite->getId() . '.pdf"',
            ]
        );
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ ENREGISTRER UNE VISITE
    // ═══════════════════════════════════════════════════════════
    #[Route('/nouvelle-visite', name: 'app_caisse_nouvelle_visite', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function nouvelleVisite(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $visite = new Visite();
        $form = $this->createForm(VisiteType::class, $visite, [
            'with_extra_fields' => true,
            'with_delegation'   => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $visite->setStatut('RESERVE');
            $visite->setDateArrivee(new \DateTime());
            $em->persist($visite);
            $em->flush();
            $this->addFlash('success', '🗓️ Visite enregistrée.');
            return $this->redirectToRoute('app_caisse_mouvements');
        }

        return $this->render('dashboard/caisse/nouvelle_visite.html.twig', [
            'formVisite' => $form->createView(),
        ]);
    }

        // ═══════════════════════════════════════════════════════════
    // ✅ REJETER UN DEVIS ARRIVÉ EN CAISSE
    // ═══════════════════════════════════════════════════════════
    #[Route('/devis/rejeter/{id}', name: 'app_caisse_devis_rejeter', methods: ['POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function rejeterDevisCaisse(
        Request $request,
        \App\Entity\Devis $devis,
        EntityManagerInterface $em
    ): Response {
        // Le devis doit être en attente caisse
        if ($devis->getStatut() !== \App\Entity\Devis::STATUT_ATTENTE_CAISSE) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté (statut : ' . $devis->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $motif = trim((string) $request->request->get('motif_rejet', ''));

        // On passe le devis en rejeté
        $devis->setStatut(\App\Entity\Devis::STATUT_REJETE);

        // On journalise le motif si un champ existe
        if (method_exists($devis, 'setCommentaireRejet')) {
            $devis->setCommentaireRejet($motif);
        }

        $em->flush();

        $this->addFlash('warning', sprintf(
            '❌ Devis #%d rejeté par la caisse%s',
            $devis->getId(),
            $motif !== '' ? ' — Motif : ' . $motif : '.'
        ));

        return $this->redirectToRoute('app_dashboard');
    }
}