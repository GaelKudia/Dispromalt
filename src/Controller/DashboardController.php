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
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request,
        DemandeRepository $demandeRepository,
        BonSortieRepository $bonSortieRepository,
        SubCategoryRepository $subCategoryRepo,
        CategoryRepository $categoryRepo,
        DevisRepository $devisRepository,
        ArticleAttributRepository $attributRepo,
        EntityManagerInterface $em
    ): Response {
        // 1. UTILISATEUR CONNECTÉ
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

        // REDIRECTION CAISSIER
        // ============================================================
// REDIRECTION CAISSIER — ne voit QUE le cas 2 + ses décaissements
// ============================================================
if ($this->isGranted('ROLE_CAISSIER')) {
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    $devisCaisse = $em->getRepository(Devis::class)->createQueryBuilder('d')
        ->where('d.statut = :attente')
        ->orWhere('(d.statut = :decaisse AND d.decaissePar = :user)')
        ->setParameter('attente',  Devis::STATUT_ATTENTE_CAISSE)
        ->setParameter('decaisse', Devis::STATUT_DECAISSE)
        ->setParameter('user',     $currentUser)
        ->orderBy('d.id', 'DESC')
        ->getQuery()
        ->getResult();

    // Totaux sur ses propres décaissements uniquement
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
        'devisListe'    => $devisCaisse,
        'totalDevisUSD' => $totalDevisUSD,
        'totalDevisCDF' => $totalDevisCDF,
        'date_debut'    => $dateDebutFiltre,
        'date_fin'      => $dateFinFiltre,
        'stats'         => $stats,
        'userRole'      => $roleLabel,
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
        $demandes = [];
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

        // Dates pour les requêtes
        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui   = new \DateTime('today 23:59:59');

        // Filtres supplémentaires
        $filtreType      = $request->query->get('filtre_type');
        $filtreDateDebut = $request->query->get('filtre_date_debut');
        $filtreDateFin   = $request->query->get('filtre_date_fin');
        $filtreStatut    = $request->query->get('filtre_statut');

        // ============================================================
        // 🔐 DÉTERMINER QUI PEUT DÉLÉGUER UNE VISITE
        // ============================================================
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

        // 1. FORMULAIRE DE VISITE (GÉNÉRIQUE SELON RÔLE)
        if ($this->isGranted('ROLE_GUERITE')) {
            // Guérite : formulaire complet, sans délégation
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
            // Accueil / Réception : formulaire complet + délégation possible
            $visite = new Visite();
            $formVisite = $this->createForm(VisiteType::class, $visite, [
                'with_extra_fields' => true,
                'with_delegation'   => $peutDeleguer, // ← true pour ACCUEIL / RECEPTION
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
            // Autres rôles : formulaire avec délégation SEULEMENT si autorisé
            $visite = new Visite();
            $formVisite = $this->createForm(VisiteType::class, $visite, [
                'with_extra_fields' => false,
                'with_delegation'   => $peutDeleguer, // ← true uniquement pour CAISSIER
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

        // 2. FORMULAIRE STOCK
        // 2. FORMULAIRE STOCK
if ($this->isGranted('ROLE_STOCK')) {
    $article = new Article();
    $formStock = $this->createForm(StockType::class, $article);
    $formStock->handleRequest($request);
    
    if ($formStock->isSubmitted() && $formStock->isValid()) {
        // 🎯 Vérifier si un article avec le même nom existe déjà
        $nomArticle = $article->getNom();
        $quantiteAjoutee = $article->getQuantite() ?? 0;
        
        $articleExistant = $em->getRepository(Article::class)->findOneBy(['nom' => $nomArticle]);
        
        if ($articleExistant) {
            // 🅰️ Article existant → AUGMENTER la quantité
            $nouvelleQuantite = $articleExistant->getQuantite() + $quantiteAjoutee;
            $articleExistant->setQuantite($nouvelleQuantite);
            
            // Mettre à jour le seuil d'alerte si fourni
            if ($article->getSeuilAlerte()) {
                $articleExistant->setSeuilAlerte($article->getSeuilAlerte());
            }
            
            $em->flush();
            $this->addFlash('success', '📥 Article existant « ' . $nomArticle . ' » — Stock incrémenté de ' . $quantiteAjoutee . ' (total : ' . $nouvelleQuantite . ')');
        } else {
            // 🅱️ Nouvel article → CRÉER
            $em->persist($article);
            $em->flush();
            $this->addFlash('success', '📥 Nouvel article « ' . $nomArticle . ' » enregistré.');
        }
        
        return $this->redirectToRoute('app_dashboard');
    }
    $formStockView = $formStock->createView();
}
        // 3. FORMULAIRE BON DE SORTIE
        if ($this->isGranted('ROLE_STOCK') ||
            (!$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_GUERITE') && !$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_CAISSIER') && !$this->isGranted('ROLE_ADMIN'))) {

            $bonSortie = new BonSortie();
            $formBon = $this->createForm(BonSortieType::class, $bonSortie);
            $formBon->handleRequest($request);
            if ($formBon->isSubmitted() && $formBon->isValid()) {
                $bonSortie->setNomEmploye($currentUser->getUserIdentifier());
                $bonSortie->setDepartement($userDept);
                $bonSortie->setDateCreation(new \DateTime());
                $bonSortie->setStatut('Créé');
                $em->persist($bonSortie);
                $em->flush();
                $this->addFlash('success', '🚀 Le bon de sortie a été transmis avec succès !');
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

                $this->addFlash('success', 'Demande de matériel enregistrée avec succès.');
                return $this->redirectToRoute('app_dashboard');
            }
            $formDemandeView = $formDemande->createView();
        }

        // ============================================================
        // GESTION DES CAS SPÉCIAUX (CHEF DE SERVICE)
        // ============================================================
        // ============================================================
// GESTION DES CAS SPÉCIAUX (CHEF DE SERVICE)
// ============================================================
if ($currentUser->isChefService() && !$this->isGranted('ROLE_ADMIN')) {
    $serviceChef = $currentUser->getService();
    $userDept = $serviceChef ? $serviceChef->getNom() : 'Impression';

    // Demandes en attente de validation
    $demandes = $demandeRepository->createQueryBuilder('d')
        ->where('d.departement = :dept')
        ->andWhere('d.statut = :statut')
        ->setParameter('dept', $userDept)
        ->setParameter('statut', 'En attente')
        ->orderBy('d.id', 'DESC')
        ->getQuery()
        ->getResult();

    // Bons de sortie du service
    $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);

    // ============================================================
    // ✅ DEVIS DU SERVICE — SANS filtres restrictifs
    // ------------------------------------------------------------
    // - On ne filtre PLUS par statut (le chef doit tout voir)
    // - On ne filtre PLUS par date (ou optionnel)
    // - LEFT JOIN pour capter aussi les devis sans user (après delete)
    // ============================================================
    $devisServiceListe = [];
    $totalDevisUSD = 0;
    $totalDevisCDF = 0;

    if ($serviceChef) {
        $devisServiceListe = $devisRepository->createQueryBuilder('dev')
            ->leftJoin('dev.user', 'u')
            ->where('u.service = :service')
            ->orWhere('dev.createdBy IN (
                SELECT usr.email FROM App\Entity\User usr WHERE usr.service = :service
            )')
            ->setParameter('service', $serviceChef)
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

    // Rapport global (demandes du service)
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

    // Formulaire de demande du chef
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

            // 🎯 Récupérer le PREMIER article correspondant
$article = $em->getRepository(Article::class)->findOneBy(['nom' => $nomMaterielSaisi]);
if ($article) {
    // Calculer le stock total (article + tous ses doublons éventuels)
    $articlesSimilaires = $em->getRepository(Article::class)->findBy(['nom' => $nomMaterielSaisi]);
    $stockTotal = 0;
    foreach ($articlesSimilaires as $a) {
        $stockTotal += $a->getQuantite() ?? 0;
    }
    
    if ($stockTotal < $quantiteDemandee) {
        $this->addFlash('danger', 'Stock insuffisant pour cet article (stock total : ' . $stockTotal . ').');
        return $this->redirectToRoute('app_dashboard');
    }
    
    // Déduire du stock sur le premier article
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

            $this->addFlash('success', 'Demande de matériel enregistrée avec succès.');
            return $this->redirectToRoute('app_dashboard');
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
        'isChefService'  => true,
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
                'filtre_type' => $filtreType
            ]);
        }

        // --- POUR L'ACCUEIL / RECEPTION ---
        if ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
            $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'A_L_ACCUEIL'], ['dateArrivee' => 'DESC']);
            $rdvReserves = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['dateArrivee' => 'DESC']);

            return $this->render('dashboard/accueil.html.twig', [
                'visites' => $visites,
                'rdvReserves' => $rdvReserves,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'formVisite' => $formVisiteView,
                'formBon' => $formBonView
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
        } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        // ============================================================
        // RENDU SELON LE RÔLE
        // ============================================================

        // COORDONNATEUR
        // ============================================================
// COORDONNATEUR
// ============================================================
if ($this->isGranted('ROLE_COORDON')) {
    // Demandes en attente de validation coordon
    $demandesCoordon = $demandeRepository->findBy(['statut' => 'Validé Chef'], ['id' => 'DESC']);
    $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);

    // ✅ TOUS les devis de TOUS les départements (sans filtre de date ni de statut)
    $devisCoordon = $em->getRepository(Devis::class)->createQueryBuilder('d')
        ->leftJoin('d.user', 'u')
        ->addSelect('u')
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

    // Formulaire de visite (inchangé)
    if (!$formVisiteView) {
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
        'demandes'       => $demandesCoordon,
        'bonsSortie'     => $bonsSortie,
        'devisListe'     => $devisCoordon,
        'totalDevisUSD'  => $totalDevisUSD,
        'totalDevisCDF'  => $totalDevisCDF,
        'userRole'       => $roleLabel,
        'stats'          => $stats,
        'date_debut'     => $dateDebutFiltre,
        'date_fin'       => $dateFinFiltre,
        'rapportGlobal'  => $rapportGlobal,
        'formVisite'     => $formVisiteView,
            'articlesEnAlerte' => $articlesEnAlerte,   // ✅ AJOUT
            'nbAlertesStock'   => $nbAlertesStock,     // ✅ AJOUT
    ]);
}
// ============================================================
// COMPTABLE — Voit TOUS les devis + ses décaissements
// ============================================================
if ($this->isGranted('ROLE_COMPTA')) {
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    // 🎯 TOUS les devis SANS filtre de date
    $devisListe = $em->getRepository(Devis::class)->createQueryBuilder('d')
        ->leftJoin('d.user', 'u')
        ->addSelect('u')
        ->orderBy('d.id', 'DESC')
        ->getQuery()
        ->getResult();

    // 🎯 Construire les compteurs à partir de la liste
    $devisA_Aiguiller      = [];
$devisPretsADecaisser  = [];   // 🆕 cas 1 & 3 : le COMPTABLE décaisse
$devisEnAttenteCaisse  = [];   // 🆕 cas 2 : suivi visuel mais action = caisse
$mesDecaissements      = [];

foreach ($devisListe as $d) {
    $statut = $d->getStatut();

    // 🔹 Devis à aiguiller (entrée dans le circuit)
    if ($statut === Devis::STATUT_ATTENTE_COMPTA) {
        $devisA_Aiguiller[] = $d;
    }

    // 🔹 Retour du DG → le COMPTABLE décaisse lui-même (cas 1)
    if ($statut === Devis::STATUT_ATTENTE_DECAISSEMENT) {
        $devisPretsADecaisser[] = $d;
    }

    // 🔹 En attente de la Caisse (cas 2) — suivi passif, pas d'action côté Compta
    if ($statut === Devis::STATUT_ATTENTE_CAISSE) {
        $devisEnAttenteCaisse[] = $d;
    }

    // 🔹 Décaissements effectués PAR le comptable (cas 1 & 3)
    if ($statut === Devis::STATUT_DECAISSE && $d->getDecaissePar() === $currentUser) {
        $mesDecaissements[] = $d;
    }
}

// 💰 Totaux : uniquement MES décaissements (cas 1 & 3)
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
    'demandes'              => $demandeRepository->findBy(['statut' => 'Validé Chef'], ['id' => 'DESC']),
    'rapportGlobal'         => $rapportGlobal,
    'devisListe'            => $devisListe,
    'mesDecaissements'      => $mesDecaissements,
    'devisA_Aiguiller'      => $devisA_Aiguiller,
    'devisPretsADecaisser'  => $devisPretsADecaisser,
    'devisEnAttenteCaisse' => $devisEnAttenteCaisse,   // 🆕 pour affichage passif'devisEnAttenteCaisse' => $devisEnAttenteCaisse,   // 🆕 pour affichage passif
    'totalDevisUSD'         => $totalDevisUSD,
    'totalDevisCDF'         => $totalDevisCDF,
    'userRole'              => $roleLabel,
    'stats'                 => $stats,
    'formVisite'            => $formVisiteView,
    'date_debut'            => $dateDebutFiltre,
    'date_fin'              => $dateFinFiltre,
    'articles'              => $articles,
    'categories'            => $categories,
    'subCategories'         => $subCategories,
    'selectedCategory'      => $selectedCategory,
    'selectedSubCategory'   => $selectedSubCategory,
    'attributs'             => $attributs,
        'articlesEnAlerte'      => $articlesEnAlerte,   // ✅ AJOUT
        'nbAlertesStock'        => $nbAlertesStock,      // ✅ AJOUT
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
            ]);
        }

        // DG
        if ($this->isGranted('ROLE_DG')) {
            return $this->render('dashboard/directeur.html.twig', [
                'demandes'      => $demandes,
                'rapportGlobal' => $rapportGlobal,
                'bonsSortie'    => $bonsSortie,
                'articles'      => $articles,
                'devisListe'    => $devisListe,
                'userRole'      => $roleLabel,
                'stats'         => $stats,
                'date_debut'    => $dateDebutFiltre,
                'date_fin'      => $dateFinFiltre,
                    'articlesEnAlerte' => $articlesEnAlerte,   // ✅ AJOUT
                            'nbAlertesStock'        => $nbAlertesStock,      // ✅ AJOUT

            ]);
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

    // 📝 Journalisation
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
#[IsGranted('IS_AUTHENTICATED_FULLY')]   // ← juste authentifié
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
    // ============================================================
    // ROUTES POUR LES VISITES
    // ============================================================
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

    // ============================================================
    // ROUTES POUR LES RAPPORTS
    // ============================================================
    #[Route('/rapport/pdf', name: 'app_rapport_pdf')]
    public function rapportPdf(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        return new Response("Génération du PDF du $dateDebut au $dateFin");
    }

    #[Route('/rapport/excel', name: 'app_rapport_excel')]
    public function rapportExcel(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');
        return new Response("Génération de l'Excel du $dateDebut au $dateFin");
    }

    // ============================================================
    // ACTIONS DE VALIDATION
    // ============================================================

    #[Route('/dashboard/compta/devis/aiguiller/{id}', name: 'app_devis_aiguiller', methods: ['POST'])]
#[IsGranted('ROLE_COMPTA')]
public function aiguillerDevis(Request $request, Devis $devis, EntityManagerInterface $em): Response
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
            $devis->setStatut(Devis::STATUT_DECAISSE);
            $devis->setDecaissePar($this->getUser());
            $devis->setDateDecaissementPar(new \DateTime());
            $this->addFlash('success', '✅ Décaissement direct effectué.');
            break;

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
    // 🎯 Accepte maintenant les demandes validées par le chef de service
    if ($demande->getStatut() !== 'Validé Chef') {
        $this->addFlash('danger', '❌ Cette demande ne peut pas être validée à cette étape.');
        return $this->redirectToRoute('app_dashboard');
    }

    // Vérifier le stock disponible
    $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
    $quantiteDemandee = $demande->getQuantite() ?? 1;

    if (!$article || $article->getQuantite() < $quantiteDemandee) {
        $this->addFlash('danger', '🛑 Impossible de valider le budget. Stock insuffisant pour "' . $demande->getMateriel() . '".');
        return $this->redirectToRoute('app_dashboard');
    }

    $demande->setStatut('Validé Compta');
    $em->flush();
    $this->addFlash('success', '💰 Budget accordé. La demande est transmise au Stock pour livraison.');
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
            $em->flush();
            $this->addFlash('success', '🚚 Matériel livré et déduit du stock.');
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
    public function sortieBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        if ($bonSortie->getStatut() !== 'Validé Coordon') {
            $this->addFlash('danger', 'Ce bon n\'est pas prêt pour la sortie (statut actuel : ' . $bonSortie->getStatut() . ').');
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

    // 🎯 Vérifier que la demande appartient bien au service du chef
    $demandeur = $demande->getDemandeur();

    if ($demandeur && $demandeur->getService() !== $currentUser->getService()) {
        $this->addFlash('danger', '🛑 Vous ne pouvez valider que les demandes des membres de votre service.');
        return $this->redirectToRoute('app_dashboard');
    }

    // 🎯 Traiter l'action (valider / rejeter)
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
    
    // ============================================================
// VALIDATION DEVIS PAR LE COORDONNATEUR
// ============================================================
// ============================================================
// VALIDATION DEVIS PAR LE COORDONNATEUR
// → Envoie au COMPTABLE (pas au DG !)
// ============================================================
#[Route('/dashboard/coordonnateur/valider-devis/{id}', name: 'app_coordonnateur_valider_devis', methods: ['POST'])]
#[IsGranted('ROLE_COORDON')]
public function validerDevisCoordon(Request $request, Devis $devis, EntityManagerInterface $em): Response
{
    if ($devis->getStatut() !== 'VALIDÉ_CHEF_SERVICE') {
        $this->addFlash('danger', '❌ Ce devis ne peut pas être validé à cette étape.');
        return $this->redirectToRoute('app_dashboard');
    }

    // Le coordonnateur peut ajuster le montant avant transmission
    $nouveauMontant = $request->request->get('nouveau_montant');
    if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
        if ($devis->getMontantInitial() === null) {
            $devis->setMontantInitial((string) $devis->getMontant());
        }
        $devis->setMontant((float) $nouveauMontant);
    }

    // ⚠️ CORRECTION : envoyer au COMPTABLE, pas au DG
    $devis->setStatut(Devis::STATUT_ATTENTE_COMPTA);
    $em->flush();

    $this->addFlash('success', '📤 Devis transmis au Comptable pour aiguillage.');
    return $this->redirectToRoute('app_dashboard');
}
#[Route('/dashboard/coordonnateur/rejeter-devis/{id}', name: 'app_coordonnateur_rejeter_devis', methods: ['POST'])]
#[IsGranted('ROLE_COORDON')]
public function rejeterDevisCoordon(Devis $devis, EntityManagerInterface $em): Response
{
    if ($devis->getStatut() !== 'VALIDÉ_CHEF_SERVICE') {
        $this->addFlash('danger', '❌ Ce devis ne peut pas être rejeté à cette étape.');
        return $this->redirectToRoute('app_dashboard');
    }

    $devis->setStatut(Devis::STATUT_REJETE);
    $em->flush();

    $this->addFlash('warning', '❌ Le devis a été rejeté par le Coordonnateur.');
    return $this->redirectToRoute('app_dashboard');
}



    // ============================================================
    // RECHERCHE API ARTICLE
    // ============================================================
    #[Route('/api/recherche-article', name: 'api_recherche_article', methods: ['GET'])]
public function rechercheArticle(Request $request, EntityManagerInterface $em): JsonResponse
{
    $nom = $request->query->get('nom');
    if (empty($nom)) {
        return new JsonResponse(['error' => 'Nom requis'], 400);
    }

    try {
        // 🎯 Récupérer TOUS les articles qui matchent
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

        // 🎯 Construire un tableau de tous les attributs de tous les articles
        $attributsGlobal = [];
        $nomAffiche = $articles[0]->getNom(); // Premier nom trouvé

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

                // 🆕 Si pas de couleur mais un article différent, ajouter le nom de l'article
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

// ═══════════════════════════════════════════════════════════════
// ✅ ÉTAPE 1 : VISA DU CHEF COMPTABLE (transmet au DG)
// ═══════════════════════════════════════════════════════════════
// ═══════════════════════════════════════════════════════════════
// ✅ VISA DU CHEF COMPTABLE (transmet au Directeur)
// ═══════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════
// ✅ VISA DU CHEF COMPTABLE (transmet au DG)
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/devis/valider-chef/{id}', name: 'app_devis_valider_chef', methods: ['POST'])]
#[IsGranted('ROLE_COMPTA')]
public function validerDevisChef(Request $request, Devis $devis, EntityManagerInterface $em): Response
{

            
    if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CHEF_COMPTABLE) {
        $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le visa du chef comptable.');
        return $this->redirectToRoute('app_dashboard');
    }

    // Le chef comptable peut ajuster le montant
    $nouveauMontant = $request->request->get('nouveau_montant');
    if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
        if ($devis->getMontantInitial() === null) {
            $devis->setMontantInitial((string) $devis->getMontant());
        }
        $devis->setMontant((float) $nouveauMontant);
    }

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

    // ⚠️ CORRECTION : retour au COMPTABLE (pas à la caisse)
    $devis->setStatut(Devis::STATUT_ATTENTE_DECAISSEMENT);
    $em->flush();

    $this->addFlash('success', '✍️ Devis #' . $devis->getId() . ' validé par la Direction — retourné à la Comptabilité pour décaissement.');
    return $this->redirectToRoute('app_dashboard');
}// ═══════════════════════════════════════════════════════════════
// ✅ REJET PAR LE CHEF COMPTABLE
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/devis/rejeter-chef-comptable/{id}', name: 'app_devis_rejeter_chef_comptable', methods: ['POST'])]
#[IsGranted('ROLE_CHEF_COMPTA')]
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

// ═══════════════════════════════════════════════════════════════
// DÉCAISSEMENT PAR LA COMPTABILITÉ
// → Cas 1 (circuit complet après DG) et Cas 3 (décaissement direct)
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/devis/decaisser-compta/{id}', name: 'app_devis_compta_decaisser', methods: ['POST'])]
#[IsGranted('ROLE_COMPTA')]
public function decaisserDevisCompta(Devis $devis, EntityManagerInterface $em): Response
{
    if ($devis->getStatut() !== Devis::STATUT_ATTENTE_DECAISSEMENT) {
        $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le décaissement Comptabilité.');
        return $this->redirectToRoute('app_dashboard');
    }

    /** @var User $currentUser */
    $currentUser = $this->getUser();

    $devis->setStatut(Devis::STATUT_DECAISSE);
    $devis->setDecaissePar($currentUser);
    $devis->setDateDecaissementPar(new \DateTime());
    $devis->setDateDecaissement(new \DateTime());
    $em->flush();

    $this->addFlash('success', '💰 Décaissement effectué par la Comptabilité pour le devis #' . $devis->getId() . '.');
    return $this->redirectToRoute('app_dashboard');
}

// ═══════════════════════════════════════════════════════════════
// DÉCAISSEMENT PAR LA CAISSE
// → Cas 2 UNIQUEMENT (bon de caisse)
// ═══════════════════════════════════════════════════════════════
#[Route('/dashboard/devis/decaisser-caisse/{id}', name: 'app_devis_caisse_decaisser', methods: ['POST'])]
#[IsGranted('ROLE_CAISSIER')]
public function decaisserDevisCaisse(Devis $devis, EntityManagerInterface $em): Response
{
    if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CAISSE) {
        $this->addFlash('danger', '❌ Ce devis n\'est pas prêt pour le décaissement Caisse.');
        return $this->redirectToRoute('app_dashboard');
    }

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

// ═══════════════════════════════════════════════════════════════
// ✅ ÉTAPE 2 : VALIDATION DU DIRECTEUR GÉNÉRAL (fixe le montant, envoie à la caisse)
// ═══════════════════════════════════════════════════════════════

// ═══════════════════════════════════════════════════════════════
// ✅ REJET PAR LE DIRECTEUR GÉNÉRAL
// ═══════════════════════════════════════════════════════════════
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

// ═══════════════════════════════════════════════════════════════
// ✅ ÉTAPE 3 : DÉCAISSEMENT (CAISSE / COMPTA / DG)
// ═══════════════════════════════════════════════════════════════



// ============================================================
// VALIDATION DEVIS PAR LE CHEF DE SERVICE
// ============================================================
#[Route('/dashboard/chef-service/valider-devis/{id}', name: 'app_chef_service_valider_devis', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function chefServiceValiderDevis(Request $request, Devis $devis, EntityManagerInterface $em): Response
{
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    // 1. Vérifier que l'utilisateur est bien chef de service
    if (!$currentUser->isChefService()) {
        throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
    }

    // 2. Vérifier que le devis appartient à un utilisateur du même service
    $createur = $devis->getUser();
    if (!$createur || $createur->getService() !== $currentUser->getService()) {
        $this->addFlash('danger', '🛑 Ce devis ne concerne pas votre service.');
        return $this->redirectToRoute('app_dashboard');
    }

    // 3. Vérifier le statut
    if (!in_array($devis->getStatut(), ['ATTENTE_TRAITEMENT', 'ATTENTE_CHEF_COMPTA'])) {
        $this->addFlash('danger', '❌ Ce devis ne peut plus être validé (statut : ' . $devis->getStatut() . ').');
        return $this->redirectToRoute('app_dashboard');
    }

    // 4. Récupérer le nouveau montant (optionnel)
    $nouveauMontant = $request->request->get('nouveau_montant');
    if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
        if ($devis->getMontantInitial() === null) {
            $devis->setMontantInitial((string) $devis->getMontant());
        }
        $devis->setMontant((float) $nouveauMontant);
    }

    // 5. Passer au statut validé par le chef
    $devis->setStatut('VALIDÉ_CHEF_SERVICE');
    $em->flush();

    $this->addFlash('success', '✅ Devis validé et transmis au Coordonnateur.');
    return $this->redirectToRoute('app_dashboard');
}
}