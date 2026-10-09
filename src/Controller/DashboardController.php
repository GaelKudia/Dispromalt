<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Entity\Article;
use App\Entity\Visite;
use App\Entity\Devis;
use App\Entity\Machine;
use App\Entity\User;
use App\Entity\Service;

use App\Entity\MouvementCaisse;
use App\Repository\MouvementCaisseRepository;
use App\Form\MouvementCaisseType;

use App\Form\DemandeType;
use App\Form\BonSortieType;
use App\Form\VisiteType;
use App\Form\StockType;
use App\Form\DevisType;
use App\Form\MachineType;
use App\Repository\DevisRepository;
use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use App\Repository\CategoryRepository;
use App\Repository\ArticleAttributRepository;
use App\Repository\SubCategoryRepository;
use App\Repository\VisiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        return $this->redirectToRoute('app_login');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request,
        DemandeRepository $demandeRepository,
        BonSortieRepository $bonSortieRepository,
        SubCategoryRepository $subCategoryRepo,
        CategoryRepository $categoryRepo,
        DevisRepository $devisRepository,
        ArticleAttributRepository $attributRepo,
        EntityManagerInterface $em,
        \App\Repository\HistoriqueReapprovisionnementRepository $histoRepo,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        // Définition du label du rôle
        $roleLabel = 'Utilisateur';
        if ($this->isGranted('ROLE_STOCK')) {
            $roleLabel = 'Magasinier / Gestionnaire de stock';
        } elseif ($this->isGranted('ROLE_COMPTA')) {
            $roleLabel = 'Comptable';
        } elseif ($this->isGranted('ROLE_GUERITE')) {
            $roleLabel = 'Agent de Guérite';
        } elseif ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
            $roleLabel = 'Accueil / Réception';
        } elseif ($this->isGranted('ROLE_COORDON')) {
            $roleLabel = 'Coordonnateur';
        } elseif ($this->isGranted('ROLE_DG')) {
            $roleLabel = 'Direction Générale';
        } elseif ($this->isGranted('ROLE_CAISSIER')) {
            $roleLabel = 'Caissier';
        }

        // 🚫 DÉTECTION DU SITE DISTANT (Sedwe / Bonagère)
        $userLocalisation = method_exists($currentUser, 'getLocalisation') ? $currentUser->getLocalisation() : null;
        $estSiteDistant = in_array(strtolower((string) $userLocalisation), ['sedwe', 'bonagere'], true);

        // 🛡️ Sécurité : bloquer toute tentative POST de visite depuis un site distant
        if ($estSiteDistant && $request->isMethod('POST') && $request->request->has('visite')) {
            $this->addFlash('danger', '🚫 La réservation de visite n\'est pas autorisée depuis votre site distant.');
            return $this->redirectToRoute('app_dashboard');
        }

        // STATS
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider'   => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite'     => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];

        // 🚨 ALERTES STOCK
        $articlesEnAlerte = $em->getRepository(Article::class)->findArticlesEnAlerte();
        $nbAlertesStock = count($articlesEnAlerte);

        // FILTRES DATES
        $dateDebutFiltre = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFinFiltre = $request->query->get('date_fin', (new \DateTime('today'))->format('Y-m-d'));

        // DEVIS (global, utilisé par plusieurs rôles)
        $qbDevis = $em->getRepository(Devis::class)->createQueryBuilder('d')
            ->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
            ->setParameter('end', new \DateTime($dateFinFiltre . ' 23:59:59'))
            ->orderBy('d.id', 'DESC');
        $devisListe = $qbDevis->getQuery()->getResult();

        $totalDevisUSD = 0;
        $totalDevisCDF = 0;
        foreach ($devisListe as $d) {
            if (in_array($d->getStatut(), ['Validé Compta', 'Decaissé'])) {
                if ($d->getDevise() === 'CDF') {
                    $totalDevisCDF += $d->getMontant();
                } else {
                    $totalDevisUSD += $d->getMontant();
                }
            }
        }

        // ============================================================
        // REDIRECTION CAISSIER
        // ============================================================
        if ($this->isGranted('ROLE_CAISSIER')) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            $devisCaisse = $em->getRepository(Devis::class)->createQueryBuilder('d')
                ->where('d.statut = :attente')
                ->orWhere('(d.statut = :decaisse AND d.decaissePar = :user)')
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('attente',  Devis::STATUT_ATTENTE_CAISSE)
                ->setParameter('decaisse', Devis::STATUT_DECAISSE)
                ->setParameter('user',     $currentUser)
                ->setParameter('start',    new \DateTime($dateDebutFiltre . ' 00:00:00'))
                ->setParameter('end',      new \DateTime($dateFinFiltre   . ' 23:59:59'))
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $totalDevisUSD = 0;
            $totalDevisCDF = 0;
            foreach ($devisCaisse as $d) {
                if ($d->getStatut() === Devis::STATUT_DECAISSE && $d->getDecaissePar() === $currentUser) {
                    if ($d->getDevise() === 'CDF') {
                        $totalDevisCDF += (float) $d->getMontant();
                    } else {
                        $totalDevisUSD += (float) $d->getMontant();
                    }
                }
            }

            return $this->render('dashboard/caisse/index.html.twig', [
                'devisListe'     => $devisCaisse,
                'totalDevisUSD'  => $totalDevisUSD,
                'totalDevisCDF'  => $totalDevisCDF,
                'date_debut'     => $dateDebutFiltre,
                'date_fin'       => $dateFinFiltre,
                'stats'          => $stats,
                'userRole'       => $roleLabel,
                'estSiteDistant' => $estSiteDistant,
            ]);
        }

        // ============================================================
        // DÉTERMINER LE DÉPARTEMENT DE L'UTILISATEUR
        // ============================================================
        $userDept = 'Impression';
        if ($currentUser->getService()) {
            $userDept = $currentUser->getService()->getNom();
        }

        // ============================================================
        // INITIALISATION DES VARIABLES
        // ============================================================
        $demandes = [];
        $bonsSortie = [];
        $visites = [];
        $rapportGlobal = [];
        $articles = [];
        $attributs = [];
        $categories = [];
        $subCategories = [];
        $selectedCategory = null;
        $selectedSubCategory = null;
        $formDemandeView = null;
        $formBonView = null;
        $formVisiteView = null;
        $formStockView = null;
        $formVisiteGueriteView = null;
        // 👔 Liste des chefs de service (pour la délégation des bons de sortie)
$chefsService = $em->getRepository(User::class)->findBy(
    ['isChefService' => true],
    ['email' => 'ASC']
);

        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui   = new \DateTime('today 23:59:59');

        $filtreType      = $request->query->get('filtre_type');
        $filtreDateDebut = $request->query->get('filtre_date_debut');
        $filtreDateFin   = $request->query->get('filtre_date_fin');
        $filtreStatut    = $request->query->get('filtre_statut');

        // 🔐 DÉTERMINER QUI PEUT DÉLÉGUER UNE VISITE
        $rolesAutorisesPourDeleguer = [
            'ROLE_CAISSIER',
            'ROLE_ACCUEIL',
            'ROLE_RECEPTION',
        ];

        $peutDeleguer = false;
        foreach ($rolesAutorisesPourDeleguer as $role) {
            if ($this->isGranted($role)) {
                $peutDeleguer = true;
                break;
            }
        }

        // ============================================================
        // TRAITEMENT DES FORMULAIRES (selon les rôles)
        // ============================================================

        // 1. FORMULAIRE DE VISITE
        if (!$estSiteDistant) {

            if ($this->isGranted('ROLE_GUERITE')) {
                $visiteGuerite = new Visite();
                $formVisiteGuerite = $this->createForm(VisiteType::class, $visiteGuerite, [
                    'with_extra_fields' => true,
                    'with_delegation'   => false,
                ]);
                $formVisiteGuerite->handleRequest($request);
                if ($formVisiteGuerite->isSubmitted() && $formVisiteGuerite->isValid()) {
                    $visiteGuerite->setStatut('A_L_ACCUEIL');
                    $visiteGuerite->setDateArrivee(new \DateTime());
                    $em->persist($visiteGuerite);
                    $em->flush();
                    $this->addFlash('success', '✅ Visiteur enregistré avec succès !');
                    return $this->redirectToRoute('app_dashboard');
                }
                $formVisiteGueriteView = $formVisiteGuerite->createView();

            } elseif ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
                $visite = new Visite();
                $formVisite = $this->createForm(VisiteType::class, $visite, [
                    'with_extra_fields' => true,
                    'with_delegation'   => $peutDeleguer,
                ]);
                $formVisite->handleRequest($request);
                if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                    $visite->setStatut('RESERVE');
                    $em->persist($visite);
                    $em->flush();
                    $this->addFlash('success', '🗓️ Visite réservée.');
                    return $this->redirectToRoute('app_dashboard');
                }
                $formVisiteView = $formVisite->createView();

            } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_ADMIN')) {
                $visite = new Visite();
                $formVisite = $this->createForm(VisiteType::class, $visite, [
                    'with_extra_fields' => false,
                    'with_delegation'   => $peutDeleguer,
                ]);
                $formVisite->handleRequest($request);
                if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                    $visite->setStatut('RESERVE');
                    $em->persist($visite);
                    $em->flush();
                    $this->addFlash('success', '🗓️ Visite réservée.');
                    return $this->redirectToRoute('app_dashboard');
                }
                $formVisiteView = $formVisite->createView();
            }

        }

        // 2. FORMULAIRE STOCK
        if ($this->isGranted('ROLE_STOCK')) {
            $article = new Article();
            $formStock = $this->createForm(StockType::class, $article);
            $formStock->handleRequest($request);

            if ($formStock->isSubmitted() && $formStock->isValid()) {
                $nomArticle      = $article->getNom();
                $quantiteAjoutee = $article->getQuantite() ?? 0;

                $articleExistant = $em->getRepository(Article::class)->findOneBy(['nom' => $nomArticle]);

                if ($articleExistant) {
                    $articleExistant->setQuantite($articleExistant->getQuantite() + $quantiteAjoutee);
                    if ($article->getSeuilAlerte()) {
                        $articleExistant->setSeuilAlerte($article->getSeuilAlerte());
                    }
                    if ($articleExistant->getAttributs()->isEmpty()) {
                        $attr = new \App\Entity\ArticleAttribut();
                        $attr->setArticle($articleExistant);
                        $attr->setCouleur('Standard');
                        if (method_exists($attr, 'setQuantite')) {
                            $attr->setQuantite($articleExistant->getQuantite());
                        }
                        $em->persist($attr);
                    }
                    $em->flush();
                    $this->addFlash('success', '📥 Article existant « ' . $nomArticle . ' » — Stock incrémenté de ' . $quantiteAjoutee . ' (total : ' . $articleExistant->getQuantite() . ')');

                } else {
                    $em->persist($article);
                    $em->flush();

                    if ($article->getAttributs()->isEmpty()) {
                        $attr = new \App\Entity\ArticleAttribut();
                        $attr->setArticle($article);
                        $attr->setCouleur('Standard');
                        if (method_exists($attr, 'setQuantite')) {
                            $attr->setQuantite($article->getQuantite());
                        }
                        $em->persist($attr);
                        $em->flush();
                    }
                    $this->addFlash('success', '📥 Nouvel article « ' . $nomArticle . ' » enregistré.');
                }

                return $this->redirectToRoute('app_dashboard');
            }
            $formStockView = $formStock->createView();
        }

        // 3. FORMULAIRE BON DE SORTIE
        // 3. FORMULAIRE BON DE SORTIE — avec choix de destination
if ($this->isGranted('ROLE_STOCK') ||
    (!$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_GUERITE') && !$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_CAISSIER') && !$this->isGranted('ROLE_ADMIN'))) {

    $bonSortie = new BonSortie();
$bonSortie->setNomEmploye($currentUser->getUserIdentifier());  // ← Pré-remplissage
$bonSortie->setDepartement($userDept);                         // ← Pré-remplissage
$formBon = $this->createForm(BonSortieType::class, $bonSortie);
    $formBon->handleRequest($request);

    if ($formBon->isSubmitted() && $formBon->isValid()) {
        $bonSortie->setNomEmploye($currentUser->getUserIdentifier());
        $bonSortie->setDepartement($userDept);
        $bonSortie->setDateCreation(new \DateTime());

        // 🎯 NOUVEAU : lire la destination choisie par l'agent d'accueil
        $destination   = $request->request->get('destination');
        $chefServiceId = $request->request->get('chef_service_id');

        // 🅰️ Cas 1 : Délégation à un chef de service
        if ($destination === 'chef_service' && $chefServiceId) {
            $chefService = $em->getRepository(User::class)->find((int) $chefServiceId);

            if ($chefService && method_exists($chefService, 'isChefService') && $chefService->isChefService()) {
                $bonSortie->setStatut('ATTENTE_CHEF_SERVICE');

                // On journalise dans la raison qui est le chef concerné
                if (method_exists($bonSortie, 'setRaison')) {
                    $raison = $bonSortie->getRaison() ?: '';
                    $bonSortie->setRaison(
                        trim($raison . ' | Délégué à : ' . $chefService->getUserIdentifier())
                    );
                }

                $this->addFlash('success', '🚀 Bon transmis au chef de service : ' . $chefService->getUserIdentifier());
            } else {
                // Fallback : chef introuvable → on envoie au coordon
                $bonSortie->setStatut('Créé');
                $this->addFlash('warning', '⚠️ Chef de service introuvable — bon transmis au coordonnateur par défaut.');
            }

        // 🅱️ Cas 2 : Transmission directe à la caisse
        } elseif ($destination === 'caisse') {
            $bonSortie->setStatut('ATTENTE_CAISSE');
            $this->addFlash('success', '🚀 Bon transmis à la Caisse.');

        // 🅲 Cas 3 : Fallback — envoi au coordonnateur (comportement classique)
        } else {
            $bonSortie->setStatut('Créé');
            $this->addFlash('success', '🚀 Le bon de sortie a été transmis au coordonnateur.');
        }

        $em->persist($bonSortie);
        $em->flush();
        return $this->redirectToRoute('app_dashboard');
    }
    $formBonView = $formBon->createView();
}
        // 4. FORMULAIRE DEMANDE
        if (!$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_STOCK') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_GUERITE') && !$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_CAISSIER') && !$this->isGranted('ROLE_ADMIN')) {
            $demande = new Demande();
            $formDemande = $this->createForm(DemandeType::class, $demande);
            $formDemande->handleRequest($request);
            if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                $nomMaterielSaisi = $formDemande->get('materiel')->getData();
                $attribut = $formDemande->get('attribut')->getData();
                $quantiteDemandee = $demande->getQuantite() ?? 1;

                if (empty($nomMaterielSaisi)) {
                    $this->addFlash('danger', 'Veuillez saisir ou sélectionner un article.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $article = $em->getRepository(Article::class)->findOneBy(['nom' => $nomMaterielSaisi]);

                if ($attribut && method_exists($attribut, 'getQuantite')) {
                    if ($attribut->getQuantite() < $quantiteDemandee) {
                        $this->addFlash('danger', 'Stock insuffisant pour cette variante.');
                        return $this->redirectToRoute('app_dashboard');
                    }
                    $attribut->setQuantite($attribut->getQuantite() - $quantiteDemandee);
                    $demande->setMateriel($nomMaterielSaisi . ' (' . $attribut->getValeur() . ')');
                } else {
                    if ($article) {
                        if ($article->getQuantite() < $quantiteDemandee) {
                            $this->addFlash('danger', 'Stock insuffisant pour cet article.');
                            return $this->redirectToRoute('app_dashboard');
                        }
                        $article->setQuantite($article->getQuantite() - $quantiteDemandee);
                    }
                    $demande->setMateriel($nomMaterielSaisi);
                }

                $demande->setDepartement($userDept);
                $demande->setStatut('En attente');
                if (method_exists($demande, 'setDemandeur')) {
                    $demande->setDemandeur($currentUser);
                }

                $em->persist($demande);
                $em->flush();

$this->addFlash('success', sprintf(
    '✅ Demande #%d enregistrée : %s × %d — statut « En attente »',
    $demande->getId(),
    $demande->getMateriel(),
    $demande->getQuantite() ?? 1
));                return $this->redirectToRoute('app_dashboard', ['_fragment' => 'pane-demandes']);
            }
            $formDemandeView = $formDemande->createView();
        }

        // ============================================================
        // GESTION DES CAS SPÉCIAUX (CHEF DE SERVICE)
        // ============================================================
        if ($currentUser->isChefService() && !$this->isGranted('ROLE_ADMIN')) {
            $serviceChef = $currentUser->getService();
            $userDept = $serviceChef ? $serviceChef->getNom() : 'Impression';

            $demandes = $demandeRepository->createQueryBuilder('d')
                ->where('d.departement = :dept')
                ->andWhere('d.statut = :statut')
                ->setParameter('dept', $userDept)
                ->setParameter('statut', 'En attente')
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);

            // 🎯 Bons délégués au chef de service (en attente de validation)
$bonsChefService = $bonSortieRepository->findBy(
    ['statut' => 'ATTENTE_CHEF_SERVICE'],
    ['id' => 'DESC']
);

            $devisServiceListe = [];
            $totalDevisUSD = 0;
            $totalDevisCDF = 0;

            if ($serviceChef) {
                $devisServiceListe = $devisRepository->createQueryBuilder('dev')
                    ->leftJoin('dev.user', 'u')
                    ->where('u.service = :service OR dev.createdBy IN (
                        SELECT usr.email FROM App\Entity\User usr WHERE usr.service = :service
                    )')
                    ->andWhere('dev.createdAt BETWEEN :start AND :end')
                    ->setParameter('service', $serviceChef)
                    ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                    ->setParameter('end',   new \DateTime($dateFinFiltre   . ' 23:59:59'))
                    ->orderBy('dev.id', 'DESC')
                    ->getQuery()
                    ->getResult();

                foreach ($devisServiceListe as $d) {
                    if ($d->getDevise() === 'CDF') {
                        $totalDevisCDF += (float) $d->getMontant();
                    } else {
                        $totalDevisUSD += (float) $d->getMontant();
                    }
                }
            }

            $rapportGlobal = $demandeRepository->createQueryBuilder('d')
                ->where('d.departement = :dept')
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('dept', $userDept)
                ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                ->setParameter('end', new \DateTime($dateFinFiltre . ' 23:59:59'))
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $articles = $em->getRepository(Article::class)->findAll();

            if (!$formDemandeView) {
                $demande = new Demande();
                $formDemande = $this->createForm(DemandeType::class, $demande);
                $formDemande->handleRequest($request);
                if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                    $nomMaterielSaisi = $formDemande->get('materiel')->getData();
                    $attribut = $formDemande->get('attribut')->getData();
                    $quantiteDemandee = $demande->getQuantite() ?? 1;

                    if (empty($nomMaterielSaisi)) {
                        $this->addFlash('danger', 'Veuillez saisir ou sélectionner un article.');
                        return $this->redirectToRoute('app_dashboard');
                    }

                    $article = $em->getRepository(Article::class)->findOneBy(['nom' => $nomMaterielSaisi]);
                    if ($article) {
                        $articlesSimilaires = $em->getRepository(Article::class)->findBy(['nom' => $nomMaterielSaisi]);
                        $stockTotal = 0;
                        foreach ($articlesSimilaires as $a) {
                            $stockTotal += $a->getQuantite() ?? 0;
                        }

                        if ($stockTotal < $quantiteDemandee) {
                            $this->addFlash('danger', 'Stock insuffisant pour cet article (stock total : ' . $stockTotal . ').');
                            return $this->redirectToRoute('app_dashboard');
                        }

                        $article->setQuantite($article->getQuantite() - $quantiteDemandee);
                    } else {
                        if ($article) {
                            if ($article->getQuantite() < $quantiteDemandee) {
                                $this->addFlash('danger', 'Stock insuffisant pour cet article.');
                                return $this->redirectToRoute('app_dashboard');
                            }
                            $article->setQuantite($article->getQuantite() - $quantiteDemandee);
                        }
                        $demande->setMateriel($nomMaterielSaisi);
                    }

                    $demande->setDepartement($userDept);
                    $demande->setStatut('En attente');
                    if (method_exists($demande, 'setDemandeur')) {
                        $demande->setDemandeur($currentUser);
                    }

                    $em->persist($demande);
                    $em->flush();

                    $this->addFlash('success', sprintf(
    '✅ Demande #%d enregistrée : %s × %d — statut « En attente »',
    $demande->getId(),
    $demande->getMateriel(),
    $demande->getQuantite() ?? 1
));
return $this->redirectToRoute('app_dashboard', ['_fragment' => 'pane-demandes']);
                }
                $formDemandeView = $formDemande->createView();
            }

            return $this->render('dashboard/membre.html.twig', [
                'demandes'       => $demandes,
                'bonsSortie'     => $bonsSortie,
                'rapportGlobal'  => $rapportGlobal,
                'articles'       => $articles,
                'devisListe'     => $devisServiceListe,
                'totalDevisUSD'  => $totalDevisUSD,
                'totalDevisCDF'  => $totalDevisCDF,
                'userRole'       => $roleLabel,
                'stats'          => $stats,
                'formDemande'    => $formDemandeView,
                'formBon'        => $formBonView,
                'formVisite'     => $formVisiteView,
                'date_debut'     => $dateDebutFiltre,
                'date_fin'       => $dateFinFiltre,
                'bonsChefService' => $bonsChefService,   // ← AJOUT
                'isChefService'  => true,
                'estSiteDistant' => $estSiteDistant,
            ]);
        }

        // ============================================================
        // ADMIN
        // ============================================================
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ============================================================
        // INTERROGATION BASE DE DONNÉES (RÔLES RESTANTS)
        // ============================================================

        // --- POUR LA GUERITE ---
        if ($this->isGranted('ROLE_GUERITE')) {
            $qbBon = $bonSortieRepository->createQueryBuilder('b');
            if ($filtreDateDebut && $filtreDateFin) {
                $qbBon->where('b.dateCreation BETWEEN :debut AND :fin')
                    ->setParameter('debut', new \DateTime($filtreDateDebut . ' 00:00:00'))
                    ->setParameter('fin', new \DateTime($filtreDateFin . ' 23:59:59'));
            } else {
                $qbBon->where('b.dateCreation BETWEEN :debut AND :fin')
                    ->setParameter('debut', $debutAujourdhui)
                    ->setParameter('fin', $finAujourdhui);
            }
            if ($filtreStatut && $filtreStatut !== 'tous') {
                $qbBon->andWhere('b.statut = :statut')
                    ->setParameter('statut', $filtreStatut);
            }
            $bonsSortie = $qbBon->orderBy('b.dateCreation', 'DESC')->getQuery()->getResult();

            $qbVisite = $em->getRepository(Visite::class)->createQueryBuilder('v');
            if ($filtreDateDebut && $filtreDateFin) {
                $qbVisite->where('v.dateArrivee BETWEEN :debut AND :fin')
                    ->setParameter('debut', new \DateTime($filtreDateDebut . ' 00:00:00'))
                    ->setParameter('fin', new \DateTime($filtreDateFin . ' 23:59:59'));
            } else {
                $qbVisite->where('v.dateArrivee BETWEEN :debut AND :fin')
                    ->setParameter('debut', $debutAujourdhui)
                    ->setParameter('fin', $finAujourdhui);
            }
            if ($filtreStatut && $filtreStatut !== 'tous') {
                $qbVisite->andWhere('v.statut = :statut')
                    ->setParameter('statut', $filtreStatut);
            }
            $visites = $qbVisite->orderBy('v.dateArrivee', 'DESC')->getQuery()->getResult();
            $rdvReserves = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['dateArrivee' => 'DESC']);
            $visitesTerminees = count($em->getRepository(Visite::class)->findBy(['statut' => 'TERMINE']));

            return $this->render('dashboard/guerite.html.twig', [
                'bonsSortie' => $bonsSortie,
                'visites' => $visites,
                'rdvReserves' => $rdvReserves,
                'visitesTerminees' => $visitesTerminees,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'formVisiteGuerite' => $formVisiteGueriteView,
                'filtre_date_debut' => $filtreDateDebut,
                'filtre_date_fin' => $filtreDateFin,
                'filtre_statut' => $filtreStatut,
                'filtre_type' => $filtreType,
                'estSiteDistant' => $estSiteDistant,
            ]);
        }

        // --- POUR L'ACCUEIL / RECEPTION ---
        if ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
            $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'A_L_ACCUEIL'], ['dateArrivee' => 'DESC']);
            $rdvReserves = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['dateArrivee' => 'DESC']);

            return $this->render('dashboard/accueil.html.twig', [
                'visites' => $visites,
                'chefsService' => $chefsService,   // ← AJOUT
                'rdvReserves' => $rdvReserves,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'formVisite' => $formVisiteView,
                'formBon' => $formBonView,
                'estSiteDistant' => $estSiteDistant,
            ]);
        }

        // --- POUR LES AUTRES RÔLES ---
        if ($this->isGranted('ROLE_STOCK') || $this->isGranted('ROLE_COORDON')) {
            $demandes = $demandeRepository->createQueryBuilder('d')
                ->where('d.createdAt BETWEEN :debut AND :fin')
                ->setParameter('debut', $debutAujourdhui)
                ->setParameter('fin', $finAujourdhui)
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();
        } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
            $demandes = $demandeRepository->createQueryBuilder('d')
                ->where('d.departement = :dept')
                ->andWhere('d.createdAt BETWEEN :debut AND :fin')
                ->setParameter('dept', $userDept)
                ->setParameter('debut', $debutAujourdhui)
                ->setParameter('fin', $finAujourdhui)
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();
        }

        if ($this->isGranted('ROLE_STOCK') || $this->isGranted('ROLE_COORDON') ||
            $this->isGranted('ROLE_DG') || $this->isGranted('ROLE_COMPTA')) {
            $categoryId = $request->query->get('category');
            $subCategoryId = $request->query->get('subCategory');

            if ($categoryId) {
                $selectedCategory = $categoryRepo->find($categoryId);
                $subCategories = $subCategoryRepo->findBy(['category' => $selectedCategory]);
            }
            if ($subCategoryId) {
                $selectedSubCategory = $subCategoryRepo->find($subCategoryId);
            }

            $attributs = $attributRepo->findAllWithDetails($selectedCategory, $selectedSubCategory);
            $categories = $categoryRepo->findAll();

            $siteParam = $request->query->get('localisation');
            $qbGlobal = $demandeRepository->createQueryBuilder('d');
            if ($dateDebutFiltre && $dateFinFiltre) {
                $qbGlobal->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
                    ->setParameter('dateDebut', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                    ->setParameter('dateFin', new \DateTime($dateFinFiltre . ' 23:59:59'));
            } else {
                $qbGlobal->where('d.createdAt BETWEEN :debutAujourdhui AND :finAujourdhui')
                    ->setParameter('debutAujourdhui', $debutAujourdhui)
                    ->setParameter('finAujourdhui', $finAujourdhui);
            }
            if ($siteParam) {
                $qbGlobal->andWhere('d.localisation = :site')
                    ->setParameter('site', $siteParam);
            }
            $rapportGlobal = $qbGlobal->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();
        }

        if ($this->isGranted('ROLE_GUERITE')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
            $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
        } elseif (
            $this->isGranted('ROLE_STOCK') ||
            $this->isGranted('ROLE_COORDON')
        ) {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        } else {
            $bonsSortie = [];
        }

        // ============================================================
        // RENDU SELON LE RÔLE
        // ============================================================

        // ============================================================
        // COORDONNATEUR — CORRIGÉ : ne voit QUE les statuts pertinents
        // ============================================================
        if ($this->isGranted('ROLE_COORDON')) {
            $demandesCoordon = $demandeRepository->findBy(['statut' => 'Validé Chef'], ['id' => 'DESC']);
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);

            // 🎯 Filtre : le coordonnateur ne voit QUE les statuts qui le concernent
            $statutsVisiblesParCoordon = [
                Devis::STATUT_VALIDE_CHEF,       // À arbitrer (action requise)
                Devis::STATUT_ATTENTE_COMPTA,    // Déjà transmis au Comptable (traçabilité)
                Devis::STATUT_REJETE,            // Rejeté (information)
            ];

            $devisCoordon = $em->getRepository(Devis::class)->createQueryBuilder('d')
                ->leftJoin('d.user', 'u')
                ->addSelect('u')
                ->where('d.statut IN (:statuts)')
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('statuts', $statutsVisiblesParCoordon)
                ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                ->setParameter('end',   new \DateTime($dateFinFiltre   . ' 23:59:59'))
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $totalDevisUSD = 0;
            $totalDevisCDF = 0;
            foreach ($devisCoordon as $d) {
                if ($d->getDevise() === 'CDF') {
                    $totalDevisCDF += (float) $d->getMontant();
                } else {
                    $totalDevisUSD += (float) $d->getMontant();
                }
            }

            // Formulaire de visite
            if (!$formVisiteView && !$estSiteDistant) {
                $visite = new Visite();
                $formVisiteCoordon = $this->createForm(VisiteType::class, $visite, [
                    'with_extra_fields' => true,
                    'with_delegation'   => false,
                ]);
                $formVisiteCoordon->handleRequest($request);
                if ($formVisiteCoordon->isSubmitted() && $formVisiteCoordon->isValid()) {
                    $visite->setStatut('RESERVE');
                    $visite->setDateArrivee(new \DateTime());
                    $em->persist($visite);
                    $em->flush();
                    $this->addFlash('success', '🗓️ Visite réservée avec succès.');
                    return $this->redirectToRoute('app_dashboard');
                }
                $formVisiteView = $formVisiteCoordon->createView();
            }

            return $this->render('dashboard/coordonnateur.html.twig', [
                'demandes'         => $demandesCoordon,
                'bonsSortie'       => $bonsSortie,
                'devisListe'       => $devisCoordon,
                'totalDevisUSD'    => $totalDevisUSD,
                'totalDevisCDF'    => $totalDevisCDF,
                'userRole'         => $roleLabel,
                'stats'            => $stats,
                'date_debut'       => $dateDebutFiltre,
                'date_fin'         => $dateFinFiltre,
                'rapportGlobal'    => $rapportGlobal,
                'formVisite'       => $formVisiteView,
                'articlesEnAlerte' => $articlesEnAlerte,
                'nbAlertesStock'   => $nbAlertesStock,
                'estSiteDistant'   => $estSiteDistant,
            ]);
        }

        // ═══════════════════════════════════════════════════════════
        // FORMULAIRE D'ENTRÉE DE FONDS
        // ═══════════════════════════════════════════════════════════
        $mouvement = new MouvementCaisse();
        $mouvement->setType(MouvementCaisse::TYPE_ENTREE);
        $mouvement->setEffectuePar($currentUser);

        $formEntree = $this->createForm(MouvementCaisseType::class, $mouvement);
        $formEntree->handleRequest($request);

        if ($formEntree->isSubmitted() && $formEntree->isValid()) {
            $em->persist($mouvement);
            $em->flush();

            $this->addFlash('success', sprintf(
                '✅ Entrée enregistrée : %s %s — %s',
                number_format((float) $mouvement->getMontant(), 2, ',', ' '),
                $mouvement->getDevise(),
                $mouvement->getProvenance()
            ));
            return $this->redirectToRoute('app_dashboard');
        }
            // Solde de la CAISSE COMPTABILITÉ (tous les comptables confondus)
            $soldeUSD = $mouvementRepo->getSoldeComptabilite(MouvementCaisse::DEVISE_USD);
            $soldeCDF = $mouvementRepo->getSoldeComptabilite(MouvementCaisse::DEVISE_CDF);

            // Mouvements de la caisse comptabilité
            $mouvements = $em->getRepository(MouvementCaisse::class)->createQueryBuilder('m')
                ->join('m.effectuePar', 'u')
                ->where('u.roles LIKE :compta OR u.roles LIKE :chef')
                ->setParameter('compta', '%ROLE_COMPTA%')
                ->setParameter('chef',   '%ROLE_CHEF_COMPTA%')
                ->orderBy('m.dateMouvement', 'DESC')
                ->getQuery()
                ->getResult();

        // ============================================================
        // COMPTABLE
        // ============================================================
        if ($this->isGranted('ROLE_COMPTA')) {
            /** @var User $currentUser */
            $currentUser = $this->getUser();

            $mouvement = new MouvementCaisse();
            $mouvement->setType(MouvementCaisse::TYPE_ENTREE);
            $mouvement->setEffectuePar($currentUser);

            $formEntree = $this->createForm(MouvementCaisseType::class, $mouvement);
            $formEntree->handleRequest($request);

            if ($formEntree->isSubmitted() && $formEntree->isValid()) {
                $em->persist($mouvement);
                $em->flush();
                $this->addFlash('success', sprintf(
                    '✅ Entrée enregistrée : %s %s — %s',
                    number_format((float) $mouvement->getMontant(), 2, ',', ' '),
                    $mouvement->getDevise(),
                    $mouvement->getProvenance()
                ));
                return $this->redirectToRoute('app_dashboard');
            }

            $soldeUSD   = $mouvementRepo->getSolde(MouvementCaisse::DEVISE_USD);
            $soldeCDF   = $mouvementRepo->getSolde(MouvementCaisse::DEVISE_CDF);
            $mouvements = $mouvementRepo->findAllOrdered();

            $statutsActifs = [
                Devis::STATUT_ATTENTE_COMPTA,
                Devis::STATUT_ATTENTE_CHEF_COMPTABLE,
                Devis::STATUT_ATTENTE_DIR,
                Devis::STATUT_ATTENTE_DECAISSEMENT,
                Devis::STATUT_ATTENTE_CAISSE,
            ];

            $devisActifs = $em->getRepository(Devis::class)->createQueryBuilder('d')
                ->leftJoin('d.user', 'u')
                ->addSelect('u')
                ->where('d.statut IN (:actifs)')
                ->setParameter('actifs', $statutsActifs)
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $devisHistorique = $em->getRepository(Devis::class)->createQueryBuilder('d')
                ->leftJoin('d.user', 'u')
                ->addSelect('u')
                ->where('d.statut IN (:histo)')
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('histo', [Devis::STATUT_DECAISSE, Devis::STATUT_REJETE])
                ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                ->setParameter('end',   new \DateTime($dateFinFiltre   . ' 23:59:59'))
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $devisListe = array_merge($devisActifs, $devisHistorique);

            $devisA_Aiguiller      = [];
            $devisPretsADecaisser  = [];
            $devisEnAttenteCaisse  = [];
            $mesDecaissements      = [];

            foreach ($devisActifs as $d) {
                $statut = $d->getStatut();

                if ($statut === Devis::STATUT_ATTENTE_COMPTA) {
                    $devisA_Aiguiller[] = $d;
                }
                if ($statut === Devis::STATUT_ATTENTE_DECAISSEMENT) {
                    $devisPretsADecaisser[] = $d;
                }
                if ($statut === Devis::STATUT_ATTENTE_CAISSE) {
                    $devisEnAttenteCaisse[] = $d;
                }
            }

            foreach ($devisHistorique as $d) {
                if ($d->getStatut() === Devis::STATUT_DECAISSE && $d->getDecaissePar() === $currentUser) {
                    $mesDecaissements[] = $d;
                }
            }

            $totalDevisUSD = 0;
            $totalDevisCDF = 0;
            foreach ($mesDecaissements as $d) {
                if ($d->getDevise() === 'CDF') {
                    $totalDevisCDF += (float) $d->getMontant();
                } else {
                    $totalDevisUSD += (float) $d->getMontant();
                }
            }

            return $this->render('dashboard/compta.html.twig', [
                'demandes' => $demandeRepository->createQueryBuilder('d')
                    ->where('d.statut IN (:statuts)')
                    ->setParameter('statuts', ['En attente', 'Validé Chef'])
                    ->orderBy('d.id', 'DESC')
                    ->getQuery()
                    ->getResult(),
                'rapportGlobal'        => $rapportGlobal,
                'devisListe'           => $devisListe,
                'mesDecaissements'     => $mesDecaissements,
                'devisA_Aiguiller'     => $devisA_Aiguiller,
                'devisPretsADecaisser' => $devisPretsADecaisser,
                'devisEnAttenteCaisse' => $devisEnAttenteCaisse,
                'totalDevisUSD'        => $totalDevisUSD,
                'totalDevisCDF'        => $totalDevisCDF,
                'userRole'             => $roleLabel,
                'stats'                => $stats,
                'formVisite'           => $formVisiteView,
                'date_debut'           => $dateDebutFiltre,
                'date_fin'             => $dateFinFiltre,
                'articles'             => $articles,
                'categories'           => $categories,
                'subCategories'        => $subCategories,
                'selectedCategory'     => $selectedCategory,
                'selectedSubCategory'  => $selectedSubCategory,
                'attributs'            => $attributs,
                'articlesEnAlerte'     => $articlesEnAlerte,
                'nbAlertesStock'       => $nbAlertesStock,
                'formEntree'           => $formEntree->createView(),
                'mouvements'           => $mouvements,
                'soldeUSD'             => $mouvementRepo->getSolde(MouvementCaisse::DEVISE_USD),
                'soldeCDF'             => $mouvementRepo->getSolde(MouvementCaisse::DEVISE_CDF),
                'estSiteDistant'       => $estSiteDistant,
            ]);
        }

        // STOCK
        if ($this->isGranted('ROLE_STOCK')) {
            $articles = $em->getRepository(Article::class)->findAll();
            return $this->render('dashboard/stock.html.twig', [
                'demandes'            => $demandes,
                'rapportGlobal'       => $rapportGlobal,
                'userRole'            => $roleLabel,
                'stats'               => $stats,
                'formStock'           => $formStockView,
                'articles'            => $articles,
                'attributs'           => $attributs,
                'categories'          => $categories,
                'subCategories'       => $subCategories,
                'selectedCategory'    => $selectedCategory,
                'selectedSubCategory' => $selectedSubCategory,
                'date_debut'          => $dateDebutFiltre,
                'date_fin'            => $dateFinFiltre,
                'estSiteDistant'      => $estSiteDistant,
            ]);
        }

        // DG
        if ($this->isGranted('ROLE_DG')) {
            $devisListeDG = $em->getRepository(Devis::class)->createQueryBuilder('d')
                ->leftJoin('d.user', 'u')
                ->addSelect('u')
                ->where('d.passageDG = :oui')
                ->andWhere('d.createdAt BETWEEN :start AND :end')
                ->setParameter('oui', true)
                ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                ->setParameter('end',   new \DateTime($dateFinFiltre   . ' 23:59:59'))
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            $articlesDG = $em->getRepository(Article::class)->findAll();

            $totalQuantiteStock = 0;
            $valeurStockUSD     = 0;
            $articlesParCategorie = [];
            $topArticles = [];

            foreach ($articlesDG as $art) {
                $qte = $art->getQuantite() ?? 0;
                $totalQuantiteStock += $qte;

                $catNom = 'Sans catégorie';
                if (method_exists($art, 'getCategory') && $art->getCategory()) {
                    $catNom = $art->getCategory()->getNom();
                } elseif (method_exists($art, 'getSubCategory') && $art->getSubCategory()) {
                    $catNom = $art->getSubCategory()->getNom();
                }

                if (!isset($articlesParCategorie[$catNom])) {
                    $articlesParCategorie[$catNom] = ['count' => 0, 'quantite' => 0];
                }
                $articlesParCategorie[$catNom]['count']++;
                $articlesParCategorie[$catNom]['quantite'] += $qte;

                $topArticles[] = [
                    'nom'       => $art->getNom(),
                    'quantite'  => $qte,
                    'seuil'     => $art->getSeuilAlerte() ?? 0,
                    'categorie' => $catNom,
                ];
            }

            usort($topArticles, fn($a, $b) => $b['quantite'] <=> $a['quantite']);
            $topArticles = array_slice($topArticles, 0, 10);

            $categoriesLabels = [];
            $categoriesQuantites = [];
            foreach ($articlesParCategorie as $cat => $data) {
                $categoriesLabels[] = $cat;
                $categoriesQuantites[] = $data['quantite'];
            }

            // ═══════════════════════════════════════════════════════════
            // ═══════════════════════════════════════════════════════
            // ✅ DONNÉES COMMUNES DG + PDG
            // ═══════════════════════════════════════════════════════
            $soldeCaisseUSD = $mouvementRepo->getSolde(MouvementCaisse::DEVISE_USD);
            $soldeCaisseCDF = $mouvementRepo->getSolde(MouvementCaisse::DEVISE_CDF);

            $vars = [
                'demandes'             => $demandes,
                'rapportGlobal'        => $rapportGlobal,
                'bonsSortie'           => $bonsSortie,
                'articles'             => $articles,
                'devisListe'           => $devisListeDG,
                'userRole'             => $roleLabel,
                'stats'                => $stats,
                'date_debut'           => $dateDebutFiltre,
                'date_fin'             => $dateFinFiltre,
                'articlesEnAlerte'     => $articlesEnAlerte,
                'nbAlertesStock'       => $nbAlertesStock,
                'soldeCaisseUSD'       => $soldeCaisseUSD,
                'soldeCaisseCDF'       => $soldeCaisseCDF,
                'articlesDG'           => $articlesDG,
                'totalQuantiteStock'   => $totalQuantiteStock,
                'articlesParCategorie' => $articlesParCategorie,
                'topArticles'          => $topArticles,
                'categoriesLabels'     => $categoriesLabels,
                'categoriesQuantites'  => $categoriesQuantites,
                'estSiteDistant'       => $estSiteDistant,
                               'soldeUSD'             => $mouvementRepo->getSoldeComptabilite(MouvementCaisse::DEVISE_USD),
                'soldeCDF'             => $mouvementRepo->getSoldeComptabilite(MouvementCaisse::DEVISE_CDF),
            ];

            // ═══════════════════════════════════════════════════════
            // ✅ RDV — chargés uniquement si l'utilisateur est PDG
            // ═══════════════════════════════════════════════════════
            $mesRdv      = [];
            $rdvDelegues = [];

            if ($currentUser->isPDG()) {
                $mesRdv = $em->getRepository(Visite::class)->createQueryBuilder('v')
                    ->where('v.avecDG = :oui')
                    ->setParameter('oui', true)
                    ->orderBy('v.dateArrivee', 'DESC')
                    ->setMaxResults(50)
                    ->getQuery()
                    ->getResult();

                $rdvDelegues = $em->getRepository(Visite::class)->createQueryBuilder('v')
                    ->where('v.responsableDelegue IS NOT NULL')
                    ->orderBy('v.dateArrivee', 'DESC')
                    ->setMaxResults(50)
                    ->getQuery()
                    ->getResult();
            }

            $vars['mesRdv']      = $mesRdv;
            $vars['rdvDelegues'] = $rdvDelegues;
            $vars['isPDG']       = $currentUser->isPDG();

            // ═══════════════════════════════════════════════════════
            // ✅ Vue unique — le template gère l'onglet RDV en conditionnel
            // ═══════════════════════════════════════════════════════
                        return $this->render('dashboard/directeur.html.twig', $vars);
        
            // ═══════════════════════════════════════════════════════
            // ✅ DG standard
            // ═══════════════════════════════════════════════════════
                        // ═══════════════════════════════════════════════════════
            // ✅ DG standard
            // ═══════════════════════════════════════════════════════
            return $this->render('dashboard/directeur.html.twig', $vars);
        }

        // PAR DÉFAUT
        return $this->render('dashboard/membre.html.twig', [
            'demandes'       => $demandes,
            'bonsSortie'     => $bonsSortie,
            'rapportGlobal'  => $rapportGlobal,
            'articles'       => $articles,
            'devisListe'     => $devisListe,
            'totalDevisUSD'  => $totalDevisUSD,
            'totalDevisCDF'  => $totalDevisCDF,
            'userRole'       => $roleLabel,
            'stats'          => $stats,
            'formDemande'    => $formDemandeView,
            'formBon'        => $formBonView,
            'formVisite'     => $formVisiteView,
            'date_debut'     => $dateDebutFiltre,
            'date_fin'       => $dateFinFiltre,
            'isChefService'  => false,
            'estSiteDistant' => $estSiteDistant,
        ]);
    }

    #[Route('/dashboard/compta/article/reappro/{id}', name: 'app_article_reappro', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function updateReapprovisionnement(
        Request $request,
        Article $article,
        EntityManagerInterface $em
    ): Response {
        $dateReappro = $request->request->get('date_reapprovisionnement');
        $enCours = $request->request->get('reapprovisionnement_en_cours');
        $ancienneDate = $article->getDateReapprovisionnement();
        $ancienStatut = $article->isReapprovisionnementEnCours();

        if (!empty($dateReappro)) {
            $article->setDateReapprovisionnement(new \DateTime($dateReappro));
        } else {
            $article->setDateReapprovisionnement(null);
        }
        $article->setReapprovisionnementEnCours($enCours === '1');

        if ($ancienneDate != $article->getDateReapprovisionnement()) {
            $h = new \App\Entity\HistoriqueReapprovisionnement();
            $h->setArticle($article);
            $h->setTypeAction('DATE_MODIFIEE');
            $h->setAncienneDateReappro($ancienneDate);
            $h->setNouvelleDateReappro($article->getDateReapprovisionnement());
            $h->setDateAction(new \DateTime());
            $h->setEffectuePar($this->getUser());
            $h->setQuantiteAuMoment($article->getQuantite());
            $h->setSeuilAuMoment($article->getSeuilAlerte());
            $h->setCommentaire('Date de réapprovisionnement mise à jour.');
            $em->persist($h);
        }

        if ($ancienStatut !== $article->isReapprovisionnementEnCours()) {
            $h = new \App\Entity\HistoriqueReapprovisionnement();
            $h->setArticle($article);
            $h->setTypeAction($article->isReapprovisionnementEnCours() ? 'REAPPRO_LANCE' : 'REAPPRO_TERMINE');
            $h->setDateAction(new \DateTime());
            $h->setEffectuePar($this->getUser());
            $h->setQuantiteAuMoment($article->getQuantite());
            $h->setSeuilAuMoment($article->getSeuilAlerte());
            $h->setCommentaire($article->isReapprovisionnementEnCours()
                ? 'Réapprovisionnement lancé.'
                : 'Réapprovisionnement terminé.');
            $em->persist($h);
        }

        if (!$article->isReapprovisionnementEnCours() && $article->getQuantite() > $article->getSeuilAlerte()) {
            $article->setAlerteDeclenchee(false);
        }

        $em->flush();
        $this->addFlash('success', '✅ Réapprovisionnement mis à jour.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/compta/article/alerte-toggle/{id}', name: 'app_article_alerte_toggle', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function toggleAlerte(Article $article, EntityManagerInterface $em): Response
    {
        $article->setAlerteDeclenchee(!$article->isAlerteDeclenchee());
        if (!$article->isAlerteDeclenchee()) {
            $article->setDateSignalementAlerte(null);
        }
        $em->flush();
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/historique-reappro', name: 'app_historique_reappro')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function historiqueReappro(
        \App\Repository\HistoriqueReapprovisionnementRepository $repo
    ): Response {
        if (!$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_DG')) {
            throw $this->createAccessDeniedException('Accès réservé au Comptable et à la DG.');
        }

        return $this->render('dashboard/historique_reappro.html.twig', [
            'historique' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/dashboard/visite/cloturer/{id}', name: 'app_visite_cloturer', methods: ['POST'])]
    public function cloturerVisite(Visite $visite, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_GUERITE') && !$this->isGranted('ROLE_ACCUEIL') && !$this->isGranted('ROLE_RECEPTION')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $visite->setStatut('TERMINE');
        $em->flush();
        $this->addFlash('success', '🚪 Le départ du visiteur ' . $visite->getNomVisiteur() . ' a bien été signalé.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/visite/valider-guerite/{id}', name: 'app_visite_valider_guerite', methods: ['POST'])]
    public function validerGuerite(Visite $visite, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_GUERITE')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        $visite->setStatut('VALIDE');
        $em->flush();
        $this->addFlash('success', '✅ La visite a été validée à la guérite.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/rapport/pdf', name: 'app_rapport_pdf')]
    public function rapportPdf(Request $request, DemandeRepository $demandeRepository): Response
    {
        $dateDebut = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFin   = $request->query->get('date_fin',   (new \DateTime('today'))->format('Y-m-d'));

        $rapportGlobal = $demandeRepository->createQueryBuilder('d')
            ->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
            ->setParameter('end',   new \DateTime($dateFin   . ' 23:59:59'))
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('dashboard/rapport_pdf.html.twig', [
            'rapportGlobal' => $rapportGlobal,
            'date_debut'    => $dateDebut,
            'date_fin'      => $dateFin,
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
                'Content-Disposition' => 'attachment; filename="rapport_' . $dateDebut . '_' . $dateFin . '.pdf"',
            ]
        );
    }

    #[Route('/rapport/excel', name: 'app_rapport_excel')]
    public function rapportExcel(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        return new Response("Génération de l'Excel du $dateDebut au $dateFin");
    }

    #[Route('/dashboard/compta/devis/aiguiller/{id}', name: 'app_devis_aiguiller', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function aiguillerDevis(Request $request, Devis $devis, EntityManagerInterface $em, MouvementCaisseRepository $mouvementRepo): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_COMPTA) {
            $this->addFlash('danger', '❌ Ce devis n\'est pas prêt à être aiguillé.');
            return $this->redirectToRoute('app_dashboard');
        }

        $choix = $request->request->get('choix_procedure');

        switch ($choix) {
            case 'circuit_complet':
                $devis->setStatut(Devis::STATUT_ATTENTE_CHEF_COMPTABLE);
                $this->addFlash('success', '📤 Devis transmis au Chef Comptable.');
                break;

            case 'bon_caisse':
                $devis->setStatut(Devis::STATUT_ATTENTE_CAISSE);
                $this->addFlash('success', '📤 Bon de demande transmis à la Caisse.');
                break;

            case 'decaisser_direct':
                $devise  = $devis->getDevise();
                $montant = (float) $devis->getMontant();

                if (!$mouvementRepo->peutDecaisser($devise, $montant)) {
                    $solde = $mouvementRepo->getSolde($devise);
                    $this->addFlash('danger', sprintf(
                        '🛑 Décaissement refusé. Solde %s insuffisant : disponible %s, demandé %s.',
                        $devise,
                        number_format($solde, 2, ',', ' '),
                        number_format($montant, 2, ',', ' ')
                    ));
                    return $this->redirectToRoute('app_dashboard');
                }

                $mouvement = new MouvementCaisse();
                $mouvement->setType(MouvementCaisse::TYPE_SORTIE);
                $mouvement->setMontant((string) $montant);
                $mouvement->setDevise($devise);
                $mouvement->setProvenance('Décaissement direct compta — devis #' . $devis->getId());
                $mouvement->setCategorie('DEVIS');
                $mouvement->setDevis($devis);
                $mouvement->setEffectuePar($this->getUser());
                $mouvement->setDateMouvement(new \DateTime());
                $em->persist($mouvement);

                $devis->setStatut(Devis::STATUT_DECAISSE);
                $devis->setDecaissePar($this->getUser());
                $devis->setDateDecaissementPar(new \DateTime());
                $devis->setDateDecaissement(new \DateTime());
                $devis->setPassageDG(false);

                $this->addFlash('success', '✅ Décaissement direct effectué — devis #' . $devis->getId() . '.');
                break;

            case 'rejeter_devis':
                $devis->setStatut(Devis::STATUT_REJETE);
                $em->flush();

                $this->addFlash('warning', sprintf(
                    '❌ Devis #%d rejeté par la comptabilité (montant : %s %s).',
                    $devis->getId(),
                    number_format((float) $devis->getMontant(), 2, ',', ' '),
                    $devis->getDevise()
                ));
                return $this->redirectToRoute('app_dashboard');

            default:
                $this->addFlash('danger', '❌ Choix invalide.');
                return $this->redirectToRoute('app_dashboard');
        }

        $em->flush();
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function validerCompta(Demande $demande, EntityManagerInterface $em): Response
    {
        $statutsAcceptes = ['En attente', 'Validé Chef'];

        if (!in_array($demande->getStatut(), $statutsAcceptes)) {
            $this->addFlash('danger', '❌ Cette demande ne peut pas être validée à cette étape (statut actuel : ' . $demande->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        $quantiteDemandee = $demande->getQuantite() ?? 1;

        if (!$article || $article->getQuantite() < $quantiteDemandee) {
            $this->addFlash('danger', '🛑 Impossible de valider. Stock insuffisant pour "' . $demande->getMateriel() . '".');
            return $this->redirectToRoute('app_dashboard');
        }

        $demande->setStatut('Validé Compta');
        $em->flush();

        $this->addFlash('success', '💰 Demande validée. Elle est transmise au dépôt pour livraison.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/livrer/{id}', name: 'app_demande_livrer', methods: ['POST'])]
    #[IsGranted('ROLE_STOCK')]
    public function livrer(Demande $demande, EntityManagerInterface $em): Response
    {
        if ($demande->getStatut() !== 'Validé Compta') {
            $this->addFlash('danger', '❌ Action interdite. La demande doit d\'abord être validée par la comptabilité.');
            return $this->redirectToRoute('app_dashboard');
        }

        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        if ($article) {
            $em->refresh($article);
            $quantiteDemandee = $demande->getQuantite() ?? 1;
            $nouvelleQuantite = $article->getQuantite() - $quantiteDemandee;

            if ($nouvelleQuantite < 0) {
                $this->addFlash('danger', '❌ Stock insuffisant au moment de la validation finale.');
                return $this->redirectToRoute('app_dashboard');
            }

            $article->setQuantite($nouvelleQuantite);
            $demande->setStatut('Livré par le Stock');

            $departementDemandeur = $demande->getDepartement() ?? 'Non renseigné';

            if (strtolower(trim($departementDemandeur)) !== 'direction') {
                $bonSortie = new BonSortie();
                $bonSortie->setNomEmploye($demande->getDemandeur() ? $demande->getDemandeur()->getUserIdentifier() : 'Demandeur inconnu');
                $bonSortie->setDepartement($departementDemandeur);
                $bonSortie->setDateCreation(new \DateTime());
                $bonSortie->setStatut('Validé Coordon');

                $description = 'Sortie stock : ' . $demande->getMateriel() . ' (Qté: ' . $quantiteDemandee . ') — Demande #' . $demande->getId();
                if (method_exists($bonSortie, 'setRaison')) {
                    $bonSortie->setRaison($description);
                }

                $em->persist($bonSortie);
            }

            $em->flush();

            if (strtolower(trim($departementDemandeur)) === 'direction') {
                $this->addFlash('success', '🚚 Matériel livré et déduit du stock (Direction — pas de bon de sortie).');
            } else {
                $this->addFlash('success', '🚚 Matériel livré, déduit du stock et bon de sortie généré pour la guérite.');
            }
        } else {
            $this->addFlash('warning', '⚠️ Article introuvable dans le stock.');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/bon/valider/{id}', name: 'app_bon_valider', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function validerBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        $bonSortie->setStatut('Validé Coordon');
        $em->flush();
        $this->addFlash('success', '🖋️ Le bon de sortie a été signé et validé par le coordonnateur.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/guerite/sortie/{id}', name: 'app_guerite_sortie_bon', methods: ['POST'])]
    #[IsGranted('ROLE_GUERITE')]
    #[Route('/dashboard/guerite/sortie/{id}', name: 'app_guerite_sortie_bon', methods: ['POST'])]
#[IsGranted('ROLE_GUERITE')]
public function sortieBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
{
    // 🎯 Tous les statuts "prêts pour la sortie physique"
    $statutsPretsPourSortie = [
        'Validé Coordon',
        'Validé Chef Service',
        'Validé Caisse',
        'Validé Secrétariat',
        'Valide Coordon',    // au cas où
    ];

    if (!in_array($bonSortie->getStatut(), $statutsPretsPourSortie, true)) {
        $this->addFlash('danger', '❌ Ce bon n\'est pas prêt pour la sortie (statut actuel : ' . $bonSortie->getStatut() . ').');
        return $this->redirectToRoute('app_dashboard');
    }

    $bonSortie->setStatut('Sortie effective');
    $em->flush();

    $this->addFlash('success', '✅ Sortie du matériel confirmée pour le bon #' . $bonSortie->getId() . '.');
    return $this->redirectToRoute('app_dashboard');
}
    #[Route('/dashboard/chef-service/traiter/{id}', name: 'app_chef_service_traiter', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function traiterDemandeChefService(Request $request, Demande $demande, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isChefService()) {
            throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
        }

        $demandeur = $demande->getDemandeur();

        if ($demandeur && $demandeur->getService() !== $currentUser->getService()) {
            $this->addFlash('danger', '🛑 Vous ne pouvez valider que les demandes des membres de votre service.');
            return $this->redirectToRoute('app_dashboard');
        }

        $action = $request->request->get('action');

        if ($action === 'valider') {
            $demande->setStatut('Validé Chef');
            $this->addFlash('success', '✅ Demande validée et transmise à la comptabilité.');
        } elseif ($action === 'rejeter') {
            $demande->setStatut('Rejeté Chef');
            $this->addFlash('warning', '❌ La demande a été rejetée.');
        } else {
            $this->addFlash('danger', '❌ Action non reconnue.');
            return $this->redirectToRoute('app_dashboard');
        }

        $em->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/coordonnateur/traiter-demande/{id}', name: 'app_coordonnateur_valider_demande', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function traiterDemandeCoordon(
        Request $request,
        Demande $demande,
        EntityManagerInterface $em
    ): Response {
        if ($demande->getStatut() !== 'Validé Chef') {
            $this->addFlash('danger', sprintf(
                '❌ Cette demande ne peut pas être traitée à cette étape (statut actuel : %s).',
                $demande->getStatut()
            ));
            return $this->redirectToRoute('app_dashboard');
        }

        $action = $request->request->get('action');

        if ($action === 'valider') {
            $demande->setStatut('Validé Coordon');
            $this->addFlash('success', '✅ Demande validée et transmise à la comptabilité.');
        } elseif ($action === 'rejeter') {
            $demande->setStatut('Rejeté Coordon');
            $this->addFlash('warning', '❌ La demande a été rejetée par le Coordonnateur.');
        } else {
            $this->addFlash('danger', '❌ Action non reconnue.');
            return $this->redirectToRoute('app_dashboard');
        }

        $em->flush();

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/coordonnateur/valider-devis/{id}', name: 'app_coordonnateur_valider_devis', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function validerDevisCoordon(Request $request, Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_VALIDE_CHEF) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être validé à cette étape.');
            return $this->redirectToRoute('app_dashboard');
        }

        $nouveauMontant = $request->request->get('nouveau_montant');
        if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
            if ($devis->getMontantInitial() === null) {
                $devis->setMontantInitial((string) $devis->getMontant());
            }
            $devis->setMontant((float) $nouveauMontant);
        }

        $devis->setStatut(Devis::STATUT_ATTENTE_COMPTA);
        $em->flush();

        $this->addFlash('success', '📤 Devis transmis au Comptable pour aiguillage.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/coordonnateur/rejeter-devis/{id}', name: 'app_coordonnateur_rejeter_devis', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function rejeterDevisCoordon(Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_VALIDE_CHEF) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté à cette étape.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_REJETE);
        $em->flush();

        $this->addFlash('warning', '❌ Le devis a été rejeté par le Coordonnateur.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/api/recherche-article', name: 'api_recherche_article', methods: ['GET'])]
    public function rechercheArticle(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $nom = $request->query->get('nom');
        if (empty($nom)) {
            return new JsonResponse(['error' => 'Nom requis'], 400);
        }

        try {
            $articles = $em->getRepository(Article::class)->createQueryBuilder('a')
                ->leftJoin('a.attributs', 'attr')
                ->addSelect('attr')
                ->where('a.nom LIKE :nom')
                ->setParameter('nom', '%' . $nom . '%')
                ->orderBy('a.id', 'ASC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            if (empty($articles)) {
                return new JsonResponse(['found' => false]);
            }

            $attributsGlobal = [];
            $nomAffiche = $articles[0]->getNom();

            foreach ($articles as $article) {
                foreach ($article->getAttributs() as $attr) {
                    $details = [];
                    if ($attr->getCouleur())    $details[] = 'Couleur: ' . $attr->getCouleur();
                    if ($attr->getGrammage())   $details[] = 'Grammage: ' . $attr->getGrammage();
                    if ($attr->getDimension())  $details[] = 'Dimension: ' . $attr->getDimension();
                    if ($attr->getLongueur())   $details[] = 'Longueur: ' . $attr->getLongueur();
                    if ($attr->getKilogramme()) $details[] = 'Kg: ' . $attr->getKilogramme();

                    $label = implode(' - ', $details) ?: 'Standard';
                    $quantite = method_exists($attr, 'getQuantite') ? $attr->getQuantite() : null;

                    if (empty($details)) {
                        $label = $article->getNom() . ' (stock : ' . $article->getQuantite() . ')';
                    }

                    $attributsGlobal[] = [
                        'id'       => $attr->getId(),
                        'valeur'   => $label,
                        'quantite' => $quantite,
                        'article'  => $article->getNom(),
                    ];
                }
            }

            return new JsonResponse([
                'found'     => true,
                'nom'       => $nomAffiche,
                'quantite'  => $articles[0]->getQuantite(),
                'attributs' => $attributsGlobal,
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error'   => 'Erreur serveur',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/dashboard/devis/valider-chef/{id}', name: 'app_devis_valider_chef', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function validerDevisChef(Request $request, Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CHEF_COMPTABLE) {
            $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le visa du chef comptable.');
            return $this->redirectToRoute('app_dashboard');
        }

        $nouveauMontant = $request->request->get('nouveau_montant');
        if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
            if ($devis->getMontantInitial() === null) {
                $devis->setMontantInitial((string) $devis->getMontant());
            }
            $devis->setMontant((float) $nouveauMontant);
        }

        $devis->setPassageDG(true);
        $devis->setStatut(Devis::STATUT_ATTENTE_DIR);
        $em->flush();

        $this->addFlash('success', '📤 Devis #' . $devis->getId() . ' visé et transmis à la Direction Générale.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/devis/valider-dg/{id}', name: 'app_devis_valider_dg', methods: ['POST'])]
    #[IsGranted('ROLE_DG')]
    public function validerDevisDirecteur(
        Request $request,
        Devis $devis,
        EntityManagerInterface $em
    ): Response {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_DIR) {
            $this->addFlash('danger', '❌ Ce devis n\'est pas en attente d\'arbitrage de la Direction.');
            return $this->redirectToRoute('app_dashboard');
        }

        $nouveauMontant = $request->request->get('nouveau_montant');
        if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
            if ($devis->getMontantInitial() === null) {
                $devis->setMontantInitial((string) $devis->getMontant());
            }
            $devis->setMontant((float) $nouveauMontant);
        }

        $devis->setStatut(Devis::STATUT_ATTENTE_DECAISSEMENT);
        $em->flush();

        $this->addFlash('success', '✍️ Devis #' . $devis->getId() . ' validé par la Direction — retourné à la Comptabilité pour décaissement.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/devis/rejeter-chef-comptable/{id}', name: 'app_devis_rejeter_chef_comptable', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function rejeterDevisChefComptable(Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CHEF_COMPTABLE) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté à cette étape.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_REJETE);
        $em->flush();

        $this->addFlash('warning', '❌ Le devis #' . $devis->getId() . ' a été rejeté par le Chef Comptable.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/devis/decaisser-compta/{id}', name: 'app_devis_compta_decaisser', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function decaisserDevisCompta(
        Devis $devis,
        EntityManagerInterface $em,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_DECAISSEMENT) {
            $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le décaissement.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devise  = $devis->getDevise();
        $montant = (float) $devis->getMontant();

        if (!$mouvementRepo->peutDecaisser($devise, $montant)) {
            $solde = $mouvementRepo->getSolde($devise);
            $this->addFlash('danger', sprintf(
                '🛑 Décaissement refusé. Solde %s insuffisant : disponible %s, demandé %s.',
                $devise,
                number_format($solde, 2, ',', ' '),
                number_format($montant, 2, ',', ' ')
            ));
            return $this->redirectToRoute('app_dashboard');
        }

        $mouvement = new MouvementCaisse();
        $mouvement->setType(MouvementCaisse::TYPE_SORTIE);
        $mouvement->setMontant((string) $montant);
        $mouvement->setDevise($devise);
        $mouvement->setProvenance('Décaissement compta — devis #' . $devis->getId());
        $mouvement->setCategorie('DEVIS');
        $mouvement->setDevis($devis);
        $mouvement->setEffectuePar($this->getUser());
        $mouvement->setDateMouvement(new \DateTime());
        $em->persist($mouvement);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $devis->setStatut(Devis::STATUT_DECAISSE);
        $devis->setDecaissePar($currentUser);
        $devis->setDateDecaissementPar(new \DateTime());
        $devis->setDateDecaissement(new \DateTime());

        $em->flush();
        $this->addFlash('success', '💰 Décaissement effectué — devis #' . $devis->getId() . '.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/devis/decaisser-caisse/{id}', name: 'app_devis_caisse_decaisser', methods: ['POST'])]
    #[IsGranted('ROLE_CAISSIER')]
    public function decaisserDevisCaisse(Devis $devis, EntityManagerInterface $em, MouvementCaisseRepository $mouvementRepo): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CAISSE) {
            $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le décaissement Caisse.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devise  = $devis->getDevise();
        $montant = (float) $devis->getMontant();

        if (!$mouvementRepo->peutDecaisser($devise, $montant)) {
            $solde = $mouvementRepo->getSolde($devise);
            $this->addFlash('danger', sprintf(
                '🛑 Décaissement refusé. Solde %s insuffisant : disponible %s, demandé %s.',
                $devise,
                number_format($solde, 2, ',', ' '),
                number_format($montant, 2, ',', ' ')
            ));
            return $this->redirectToRoute('app_dashboard');
        }

        $mouvement = new MouvementCaisse();
        $mouvement->setType(MouvementCaisse::TYPE_SORTIE);
        $mouvement->setMontant((string) $montant);
        $mouvement->setDevise($devise);
        $mouvement->setProvenance('Décaissement caisse — devis #' . $devis->getId());
        $mouvement->setCategorie('DEVIS');
        $mouvement->setDevis($devis);
        $mouvement->setEffectuePar($this->getUser());
        $mouvement->setDateMouvement(new \DateTime());
        $em->persist($mouvement);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $devis->setStatut(Devis::STATUT_DECAISSE);
        $devis->setDecaissePar($currentUser);
        $devis->setDateDecaissementPar(new \DateTime());
        $devis->setDateDecaissement(new \DateTime());
        $em->flush();

        $this->addFlash('success', '💰 Décaissement effectué par la Caisse pour le devis #' . $devis->getId() . '.');
        return $this->redirectToRoute('app_dashboard');
    }

    private function verifierSoldeOuRefuser(
        MouvementCaisseRepository $repo,
        string $devise,
        float $montant
    ): ?Response {
        if (!$repo->peutDecaisser($devise, $montant)) {
            $solde = $repo->getSolde($devise);
            $this->addFlash('danger', sprintf(
                '🛑 Solde %s insuffisant. Disponible : %s | Demandé : %s.',
                $devise,
                number_format($solde, 2, ',', ' '),
                number_format($montant, 2, ',', ' ')
            ));
            return $this->redirectToRoute('app_dashboard');
        }
        return null;
    }

    #[Route('/dashboard/bon/rejeter/{id}', name: 'app_bon_rejeter', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function rejeterBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        if ($bonSortie->getStatut() !== 'Créé') {
            $this->addFlash('danger', '❌ Ce bon ne peut pas être rejeté à cette étape (statut actuel : ' . $bonSortie->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $bonSortie->setStatut('Rejeté Coordon');
        $em->flush();

        $this->addFlash('warning', '❌ Le bon de sortie #' . $bonSortie->getId() . ' a été rejeté par le Coordonnateur.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/devis/rejeter-dg/{id}', name: 'app_devis_rejeter_dg', methods: ['POST'])]
    #[IsGranted('ROLE_DG')]
    public function rejeterDevisDirecteur(Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_DIR) {
            $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté à cette étape.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_REJETE);
        $em->flush();

        $this->addFlash('warning', '❌ Le devis #' . $devis->getId() . ' a été rejeté par la Direction Générale.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/chef-service/valider-devis/{id}', name: 'app_chef_service_valider_devis', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function chefServiceValiderDevis(Request $request, Devis $devis, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        if (!$currentUser->isChefService()) {
            throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
        }

        $createur = $devis->getUser();
        if (!$createur || $createur->getService() !== $currentUser->getService()) {
            $this->addFlash('danger', '🛑 Ce devis ne concerne pas votre service.');
            return $this->redirectToRoute('app_dashboard');
        }

        if (!in_array($devis->getStatut(), [Devis::STATUT_ATTENTE_CHEF])) {
            $this->addFlash('danger', '❌ Ce devis ne peut plus être validé (statut : ' . $devis->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $nouveauMontant = $request->request->get('nouveau_montant');
        if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
            if ($devis->getMontantInitial() === null) {
                $devis->setMontantInitial((string) $devis->getMontant());
            }
            $devis->setMontant((float) $nouveauMontant);
        }

        $devis->setStatut(Devis::STATUT_VALIDE_CHEF);
        $em->flush();

        $this->addFlash('success', '✅ Devis validé et transmis au Coordonnateur.');
        return $this->redirectToRoute('app_dashboard');
    }

    // ═══════════════════════════════════════════════════════════════
// ✅ VALIDATION D'UN BON PAR LE CHEF DE SERVICE
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/chef-service/bon/valider/{id}', name: 'app_chef_service_bon_valider', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function validerBonChefService(BonSortie $bonSortie, EntityManagerInterface $em): Response
{
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    if (!$currentUser->isChefService()) {
        throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
    }

    if ($bonSortie->getStatut() !== 'ATTENTE_CHEF_SERVICE') {
        $this->addFlash('danger', '❌ Ce bon n\'est pas en attente de votre validation.');
        return $this->redirectToRoute('app_dashboard');
    }

    $bonSortie->setStatut('Validé Chef Service');

    if (method_exists($bonSortie, 'setRaison')) {
        $raison = $bonSortie->getRaison() ?: '';
        $bonSortie->setRaison(trim($raison . ' | Validé par chef : ' . $currentUser->getUserIdentifier()));
    }

    $em->flush();

    $this->addFlash('success', '✅ Bon #' . $bonSortie->getId() . ' validé — transmis au Coordonnateur.');
    return $this->redirectToRoute('app_dashboard');
}

// ═══════════════════════════════════════════════════════════════
// ❌ REJET D'UN BON PAR LE CHEF DE SERVICE
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/chef-service/bon/rejeter/{id}', name: 'app_chef_service_bon_rejeter', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function rejeterBonChefService(Request $request, BonSortie $bonSortie, EntityManagerInterface $em): Response
{
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    if (!$currentUser->isChefService()) {
        throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
    }

    if ($bonSortie->getStatut() !== 'ATTENTE_CHEF_SERVICE') {
        $this->addFlash('danger', '❌ Ce bon n\'est pas en attente de votre validation.');
        return $this->redirectToRoute('app_dashboard');
    }

    $motif = trim((string) $request->request->get('motif_rejet', ''));

    $bonSortie->setStatut('Rejeté Chef Service');

    if (method_exists($bonSortie, 'setRaison')) {
        $raison = $bonSortie->getRaison() ?: '';
        $log = ' | Rejeté par chef : ' . $currentUser->getUserIdentifier();
        if ($motif !== '') {
            $log .= ' — Motif : ' . $motif;
        }
        $bonSortie->setRaison(trim($raison . $log));
    }

    $em->flush();

    $this->addFlash('warning', '❌ Bon #' . $bonSortie->getId() . ' rejeté.');
    return $this->redirectToRoute('app_dashboard');
}
}