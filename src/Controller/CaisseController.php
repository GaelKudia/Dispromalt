<?php

namespace App\Controller;

use App\Entity\Devis;
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
    // ✅ ENTRÉE DE FONDS — Formulaire
    // URL : /caisse/entree
    //
    // 🔄 UX AMÉLIORÉE :
    //   → On ne redirige PLUS vers le PDF après validation
    //   → On reste sur la page avec un flash structuré
    //   → Le flash contient deux actions : imprimer / voir mouvements
    //   → Le solde est automatiquement mis à jour
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
        $mouvement->setDateMouvement(new \DateTime());

        $form = $this->createForm(MouvementCaisseType::class, $mouvement, ['is_entree' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // ✅ Sécurité : date par défaut si vide
            if (!$mouvement->getDateMouvement()) {
                $mouvement->setDateMouvement(new \DateTime());
            }

            $categorie  = $mouvement->getCategorie();
            $isVente    = ($categorie === 'VENTE');
            $provenance = trim((string) $mouvement->getProvenance());
            $reference  = trim((string) $mouvement->getReference());

            // ✅ Validation métier — VENTE
            if ($isVente) {

                if ($provenance === '') {
                    $this->addFlash('danger', '❌ Le nom du client est obligatoire pour une vente.');
                    return $this->redirectToRoute('app_caisse_entree');
                }

                if ($reference === '') {
                    $this->addFlash('danger', '❌ Le numéro de facture est obligatoire pour une vente.');
                    return $this->redirectToRoute('app_caisse_entree');
                }

                if (!preg_match('/^[A-Za-z0-9\-_\/]{3,50}$/', $reference)) {
                    $this->addFlash('danger', '❌ Le numéro de facture est invalide (3 à 50 caractères alphanumériques attendus).');
                    return $this->redirectToRoute('app_caisse_entree');
                }

                $existant = $em->getRepository(MouvementCaisse::class)->findOneBy([
                    'type'      => MouvementCaisse::TYPE_ENTREE,
                    'reference' => $reference,
                ]);
                if ($existant) {
                    $this->addFlash('danger', sprintf(
                        '❌ Le n° de facture « %s » a déjà été enregistré (mouvement #%d).',
                        $reference,
                        $existant->getId()
                    ));
                    return $this->redirectToRoute('app_caisse_entree');
                }
            }

            // ✅ Si non-vente : provenance libre obligatoire
            if (!$isVente && $provenance === '') {
                $this->addFlash('danger', '❌ La provenance est obligatoire.');
                return $this->redirectToRoute('app_caisse_entree');
            }

            // ✅ Enregistrement
            $em->persist($mouvement);
            $em->flush();

            // ═══════════════════════════════════════════════════════
            // ✅ EXPÉRIENCE UTILISATEUR : flash structuré + retour formulaire
            //    Le template parse ce message pour afficher un bandeau
            //    de succès avec les boutons d'action.
            // ═══════════════════════════════════════════════════════
            $this->addFlash(
                'success',
                sprintf(
                    '__ENTRY_SUCCESS__|%d|%s|%s|%s',
                    $mouvement->getId(),
                    number_format((float) $mouvement->getMontant(), 2, ',', ' '),
                    $mouvement->getDevise(),
                    $mouvement->getProvenance()
                )
            );

            return $this->redirectToRoute('app_caisse_entree');
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
    // URL : /caisse/entree/imprimer/{id}
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
    // URL : /caisse/mouvements
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

        return $this->render('dashboard/caisse/mouvements.html.twig', [
            'mouvements' => $qb->getQuery()->getResult(),
            'soldeUSD'   => $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_USD),
            'soldeCDF'   => $repo->getSoldeParUtilisateur($user, MouvementCaisse::DEVISE_CDF),
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'devise'     => $devise,
            'type'       => $type,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ HISTORIQUE DES ENTRÉES — recherche + filtre date
    // URL : /caisse/historique
    // ═══════════════════════════════════════════════════════════
    #[Route('/historique', name: 'app_caisse_historique', methods: ['GET'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function historiqueEntrees(
        Request $request,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        $dateDebut = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFin   = $request->query->get('date_fin',   (new \DateTime('today'))->format('Y-m-d'));
        $recherche = trim((string) $request->query->get('q', ''));

        $qb = $mouvementRepo->createQueryBuilder('m')
            ->leftJoin('m.effectuePar', 'u')->addSelect('u')
            ->where('m.type = :type')
            ->andWhere('m.dateMouvement BETWEEN :debut AND :fin')
            ->setParameter('type', MouvementCaisse::TYPE_ENTREE)
            ->setParameter('debut', new \DateTime($dateDebut . ' 00:00:00'))
            ->setParameter('fin',   new \DateTime($dateFin   . ' 23:59:59'))
            ->orderBy('m.dateMouvement', 'DESC');

        if ($recherche !== '') {
            $qb->andWhere(
                'm.provenance LIKE :q OR m.reference LIKE :q OR m.categorie LIKE :q'
            )->setParameter('q', '%' . $recherche . '%');
        }

        $entrees = $qb->getQuery()->getResult();

        $totalUSD = 0;
        $totalCDF = 0;
        foreach ($entrees as $m) {
            if ($m->getDevise() === 'USD') {
                $totalUSD += (float) $m->getMontant();
            } else {
                $totalCDF += (float) $m->getMontant();
            }
        }

        return $this->render('dashboard/caisse/historique_entrees.html.twig', [
            'entrees'    => $entrees,
            'date_debut' => $dateDebut,
            'date_fin'   => $dateFin,
            'recherche'  => $recherche,
            'totalUSD'   => $totalUSD,
            'totalCDF'   => $totalCDF,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ IMPRESSION DU RAPPORT DE CAISSE (PDF)
    // URL : /caisse/rapport-imprimer
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
    // URL : /caisse/rdv-dg
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
    // ✅ PROGRAMMER UNE RÉUNION DG
    // URL : /caisse/rdv-dg/nouveau
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg/nouveau', name: 'app_caisse_rdv_nouveau', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function nouveauRDV(
        Request $request,
        EntityManagerInterface $em
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
    // ✅ DÉLÉGUER UN RDV (SÉCURISÉ — CWE-20, CSRF, IDOR)
    // URL : /caisse/rdv-dg/deleguer/{id}
    // ═══════════════════════════════════════════════════════════
    #[Route('/rdv-dg/deleguer/{id}', name: 'app_caisse_rdv_deleguer', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_DG') or is_granted('ROLE_ADMIN')"))]
    public function deleguerRDV(
        Request $request,
        Visite $visite,
        EntityManagerInterface $em
    ): Response {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // 🔒 V2 — CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('deleguer_rdv_' . $visite->getId(), $token)) {
            $this->addFlash('danger', '❌ Jeton de sécurité invalide. Action refusée.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        // 🔒 V4 — État de la visite
        if (!$visite->isAvecDG()) {
            $this->addFlash('danger', '❌ Ce rendez-vous n\'est pas destiné à la Direction Générale.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        if ($visite->getStatut() !== 'RESERVE') {
            $this->addFlash('warning', sprintf(
                '⚠️ Ce rendez-vous ne peut plus être délégué (statut actuel : %s).',
                $visite->getStatut()
            ));
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        // 🔒 V1 — Appartenance
        $estAdmin = $this->isGranted('ROLE_ADMIN');
        if (!$estAdmin) {
            if (method_exists($visite, 'getDestinataireDG') && $visite->getDestinataireDG() !== null) {
                if ($visite->getDestinataireDG() !== $currentUser) {
                    $this->addFlash('danger', '❌ Vous ne pouvez déléguer que les rendez-vous qui vous sont destinés.');
                    return $this->redirectToRoute('app_caisse_rdv_dg');
                }
            }
        }

        // 🔒 V3 — Validation stricte du délégué (CWE-20)
        $rawDelegueId = $request->request->get('delegue_id');

        if (!is_scalar($rawDelegueId)) {
            $this->addFlash('danger', '❌ Identifiant de délégué invalide.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        $rawDelegueId = trim((string) $rawDelegueId);

        if (!preg_match('/^\d+$/', $rawDelegueId)) {
            $this->addFlash('danger', '❌ Identifiant de délégué invalide.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        $delegueId = (int) $rawDelegueId;

        if ($delegueId <= 0 || strlen($rawDelegueId) > 10) {
            $this->addFlash('danger', '❌ Aucun délégué valide sélectionné.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        if ($delegueId === $currentUser->getId()) {
            $this->addFlash('danger', '❌ Vous ne pouvez pas vous déléguer un rendez-vous à vous-même.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        $delegue = $em->getRepository(User::class)->find($delegueId);

        if (!$delegue) {
            $this->addFlash('danger', '❌ Le délégué sélectionné n\'existe pas.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        if (method_exists($delegue, 'isActive') && !$delegue->isActive()) {
            $this->addFlash('danger', '❌ Ce compte utilisateur est désactivé.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }
        if (method_exists($delegue, 'isActif') && !$delegue->isActif()) {
            $this->addFlash('danger', '❌ Ce compte utilisateur est désactivé.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        $rolesAutorises = [
            'ROLE_DG', 'ROLE_ADMIN', 'ROLE_CHEF_COMPTA',
            'ROLE_COORDON', 'ROLE_COMPTA', 'ROLE_STOCK',
        ];
        $aUnRoleValide = false;
        foreach ($rolesAutorises as $role) {
            if (in_array($role, $delegue->getRoles(), true)) {
                $aUnRoleValide = true;
                break;
            }
        }
        if (!$aUnRoleValide) {
            $this->addFlash('danger', '❌ Ce collaborateur n\'a pas les permissions nécessaires pour recevoir une délégation.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        // 🔒 V5 — Motif borné
        $motif = trim((string) $request->request->get('motif_delegation', ''));

        if (mb_strlen($motif) > 500) {
            $this->addFlash('danger', '❌ Le motif de délégation ne peut pas dépasser 500 caractères.');
            return $this->redirectToRoute('app_caisse_rdv_dg');
        }

        // ✅ Application de la délégation
        $visite->setAvecDG(false);
        $visite->setResponsableDelegue($delegue);
        $visite->setMotifDelegation($motif !== '' ? $motif : null);
        $visite->setStatut('DELEGUE');

        $em->flush();

        $this->addFlash('success', sprintf(
            '📤 RDV #%d délégué à %s%s',
            $visite->getId(),
            $delegue->getUserIdentifier(),
            $motif !== '' ? ' — Motif : ' . $motif : ''
        ));

        return $this->redirectToRoute('app_caisse_rdv_dg');
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ IMPRESSION D'UN RDV (PDF)
    // URL : /caisse/rdv-dg/imprimer/{id}
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
    // URL : /caisse/nouvelle-visite
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
    // URL : /caisse/devis/rejeter/{id}
    // ═══════════════════════════════════════════════════════════
    #[Route('/devis/rejeter/{id}', name: 'app_caisse_devis_rejeter', methods: ['POST'])]
    #[IsGranted(new Expression(self::ROLES_CAISSE))]
    public function rejeterDevisCaisse(
        Request $request,
        Devis $devis,
        EntityManagerInterface $em
    ): Response {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CAISSE) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté (statut : ' . $devis->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $motif = trim((string) $request->request->get('motif_rejet', ''));

        $devis->setStatut(Devis::STATUT_REJETE);

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