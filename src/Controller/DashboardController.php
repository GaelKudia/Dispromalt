<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Entity\Article;
use App\Entity\Visite;
use App\Entity\Devis;
use App\Entity\Machine;

use App\Form\DemandeType;
use App\Form\BonSortieType;
use App\Form\VisiteType;
use App\Form\StockType;
use App\Form\DevisType;
use App\Form\MachineType;
use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use App\Repository\CategoryRepository;
use App\Repository\ArticleAttributRepository;
use App\Repository\SubCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        ArticleAttributRepository $attributRepo,
        EntityManagerInterface $em
    ): Response {
        
        // 1. UTILISATEUR CONNECTÉ
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        // --- CORRECTION : Définition et initialisation de $roleLabel dès le début ---
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
        }
        
        // --- 1. CALCUL DES STATS ---
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider'   => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite'     => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];

        // 3. FILTRES DATES
        $dateDebutFiltre = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFinFiltre = $request->query->get('date_fin', (new \DateTime('today'))->format('Y-m-d'));

        // 4. DEVIS POUR CAISSIER ET COMPTA
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

        // 2. RENDU SELON LE RÔLE CAISSIER
        if ($this->isGranted('ROLE_CAISSIER')) {
            return $this->render('dashboard/caisse/index.html.twig', [
                'devisListe'    => $devisListe,
                'totalDevisUSD' => $totalDevisUSD,
                'totalDevisCDF' => $totalDevisCDF,
                'date_debut'    => $dateDebutFiltre,
                'date_fin'      => $dateFinFiltre,
                'stats'         => $stats,
                'userRole'      => $roleLabel
            ]);
        }

        $userName = $currentUser->getUserIdentifier(); 
        $userDept = 'Impression'; 

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Initialisation par défaut des vues de formulaires à null
        $formStockView = null;
        $formDemandeView = null;
        $formBonView = null;
        $formVisiteView = null;
        $formVisiteGueriteView = null;

        // ============================================================
        // 7. FORMULAIRE DE RÉSERVATION DE VISITE SELON LE RÔLE
        // ============================================================
        if ($this->isGranted('ROLE_GUERITE')) {
            $visiteGuerite = new Visite();
            $formVisiteGuerite = $this->createForm(VisiteType::class, $visiteGuerite, [
                'with_extra_fields' => true,
            ]);
            $formVisiteGuerite->handleRequest($request);

            if ($formVisiteGuerite->isSubmitted() && $formVisiteGuerite->isValid()) {
                $visiteGuerite->setStatut('A_L_ACCUEIL');
                $visiteGuerite->setDateArrivee(new \DateTime());
                $em->persist($visiteGuerite);
                $em->flush();

                $this->addFlash('success', '✅ Visiteur enregistré avec succès ! Il sera visible à la réception.');
                return $this->redirectToRoute('app_dashboard');
            }
            $formVisiteGueriteView = $formVisiteGuerite->createView();
        }
        elseif ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
            $visite = new Visite();
            $formVisite = $this->createForm(VisiteType::class, $visite, [
                'with_extra_fields' => true,
            ]);
            $formVisite->handleRequest($request);

            if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                $visite->setStatut('RESERVE');
                $em->persist($visite);
                $em->flush();

                $this->addFlash('success', '🗓️ La visite a été réservée avec succès !');
                return $this->redirectToRoute('app_dashboard');
            }
            $formVisiteView = $formVisite->createView();
        }
        elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_ADMIN')) {
            $visite = new Visite();
            $formVisite = $this->createForm(VisiteType::class, $visite, [
                'with_extra_fields' => false,
            ]);
            $formVisite->handleRequest($request);

            if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                $visite->setStatut('RESERVE');
                $em->persist($visite);
                $em->flush();

                $this->addFlash('success', '🗓️ La visite a été réservée avec succès !');
                return $this->redirectToRoute('app_dashboard');
            }
            $formVisiteView = $formVisite->createView();
        }

        // ============================================================
        // 8. FORMULAIRE STOCK (MAGASINIER)
        // ============================================================
        if ($this->isGranted('ROLE_STOCK')) {
            // 1. Gestion du formulaire Stock
            $article = new Article();
            $formStock = $this->createForm(StockType::class, $article);
            $formStock->handleRequest($request);

            if ($formStock->isSubmitted() && $formStock->isValid()) {
                $em->persist($article);
                $em->flush();
                
                $this->addFlash('success', '📥 L\'article a bien été enregistré !');
                return $this->redirectToRoute('app_dashboard');
            }
            $formStockView = $formStock->createView();

            // 2. Gestion du formulaire Demande
            $demande = new Demande();
            $formDemande = $this->createForm(DemandeType::class, $demande);
            $formDemande->handleRequest($request);

            if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                $nomMaterielSaisi = strtolower($demande->getMateriel());
                if (str_contains($nomMaterielSaisi, 'gilet') && !$demande->getDestination()) {
                    $this->addFlash('danger', '🛑 Action refusée. La commune de destination est obligatoire pour toute demande de gilets.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $articleObj = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
                $quantiteDemandee = $demande->getQuantite() ?? 1;

                if (!$articleObj || $articleObj->getQuantite() < $quantiteDemandee) {
                    $stockActuel = $articleObj ? $articleObj->getQuantite() : 0;
                    $this->addFlash('danger', '🛑 Demande impossible. Le matériel "' . $demande->getMateriel() . '" n\'est pas disponible ou insuffisant en stock (Disponible : ' . $stockActuel . ').');
                    return $this->redirectToRoute('app_dashboard');
                }

                $demande->setDepartement($userDept);
                $demande->setStatut('En attente');
                $em->persist($demande);
                $em->flush();

                $this->addFlash('success', '📦 Votre demande de matériel a été envoyée ! En attente de validation financière.');
                return $this->redirectToRoute('app_dashboard');
            }
            $formDemandeView = $formDemande->createView();

            // Bon de Sortie
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

        // ============================================================
        // 11. INTERROGATION BASE DE DONNÉES
        // ============================================================
        $demandes = [];
        $bonsSortie = [];
        $visites = [];
        $rapportGlobal = [];
        $articles = []; // initialisation par défaut

        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui = new \DateTime('today 23:59:59');

        $filtreType = $request->query->get('filtre_type');
        $filtreDateDebut = $request->query->get('filtre_date_debut');
        $filtreDateFin = $request->query->get('filtre_date_fin');
        $filtreStatut = $request->query->get('filtre_statut');

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

        // --- AUTRES RÔLES ---
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

        if ($this->isGranted('ROLE_STOCK') || $this->isGranted('ROLE_COORDON') || $this->isGranted('ROLE_DG') || $this->isGranted('ROLE_COMPTA')) {
            // Récupération sécurisée des données d'inventaire avec filtrage catégorie / sous-catégorie
            $categoryId = $request->query->get('category');
            $subCategoryId = $request->query->get('subCategory');
            $selectedCategory = null;
            $selectedSubCategory = null;
            $subCategories = [];

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
        } else {
            $attributs = [];
            $categories = [];
            $subCategories = [];
            $selectedCategory = null;
            $selectedSubCategory = null;
        }

        if ($this->isGranted('ROLE_COORDON')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
        } elseif ($this->isGranted('ROLE_GUERITE')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
        } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        // RENDU COMPTA
        if ($this->isGranted('ROLE_COMPTA')) {
            return $this->render('dashboard/compta.html.twig', [
                'demandes' => $demandeRepository->findBy(['statut' => 'En attente'], ['id' => 'DESC']),
                'rapportGlobal' => $rapportGlobal,
                'devisListe' => $devisListe,
                'totalDevisUSD' => $totalDevisUSD,
                'totalDevisCDF' => $totalDevisCDF,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'formVisite' => $formVisiteView,
                'date_debut' => $dateDebutFiltre,
                'date_fin' => $dateFinFiltre
            ]);
        }

        // RENDU STOCK
        // RENDU STOCK
if ($this->isGranted('ROLE_STOCK')) {
    // Ajoutez ici la récupération des articles
    $articles = $em->getRepository(Article::class)->findAll();
    
    return $this->render('dashboard/stock.html.twig', [
        'demandes'            => $demandes,
        'rapportGlobal'       => $rapportGlobal,
        'userRole'            => $roleLabel,
        'stats'               => $stats,
        'formStock'           => $formStockView,
        'articles'            => $articles,   // Maintenant rempli
        'attributs'           => $attributs,
        'categories'          => $categories,
        'subCategories'       => $subCategories,
        'selectedCategory'    => $selectedCategory,
        'selectedSubCategory' => $selectedSubCategory,
        'date_debut'          => $dateDebutFiltre,
        'date_fin'            => $dateFinFiltre
    ]);
}

        // RENDU DG
        if ($this->isGranted('ROLE_DG')) {
            return $this->render('dashboard/membre.html.twig', [
                'demandes' => $demandes,
                'rapportGlobal' => $rapportGlobal,
                'articles' => $articles,
                'devisListe' => $devisListe,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'date_debut' => $dateDebutFiltre,
                'date_fin' => $dateFinFiltre
            ]);
        }

        // RENDU PAR DÉFAUT (MEMBRE)
        return $this->render('dashboard/membre.html.twig', [
            'demandes' => $demandes,
            'bonsSortie' => $bonsSortie,
            'rapportGlobal' => $rapportGlobal,
            'articles' => $articles,
            'devisListe' => $devisListe,
            'totalDevisUSD' => $totalDevisUSD,
            'totalDevisCDF' => $totalDevisCDF,
            'userRole' => $roleLabel,
            'stats' => $stats,
            'formDemande' => $formDemandeView,
            'formBon' => $formBonView,
            'formVisite' => $formVisiteView,
            'date_debut' => $dateDebutFiltre,
            'date_fin' => $dateFinFiltre
        ]);
    }

    // ============================================================
    // ROUTES POUR LES RAPPORTS PDF ET EXCEL
    // ============================================================
    #[Route('/rapport/pdf', name: 'app_rapport_pdf')]
    public function rapportPdf(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        // Ici, vous générez votre PDF (avec Dompdf, KnpSnappy, etc.)
        // Pour l'instant, on retourne une réponse simple pour tester :
        return new Response("Génération du PDF du $dateDebut au $dateFin");
    }

    #[Route('/rapport/excel', name: 'app_rapport_excel')]
    public function rapportExcel(Request $request): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        // Génération du fichier Excel (PhpSpreadsheet, etc.)
        return new Response("Génération de l'Excel du $dateDebut au $dateFin");
    }
}