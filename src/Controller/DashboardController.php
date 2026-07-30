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
        }

        // STATS
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider'   => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite'     => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];

        // FILTRES DATES
        $dateDebutFiltre = $request->query->get('date_debut', (new \DateTime('today'))->format('Y-m-d'));
        $dateFinFiltre = $request->query->get('date_fin', (new \DateTime('today'))->format('Y-m-d'));

        // DEVIS
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

        $userDept = 'Impression';

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ------------------  Initialisation des vues de formulaires  ------------------
        $formStockView        = null;
        $formDemandeView      = null;
        $formBonView          = null;
        $formVisiteView       = null;
        $formVisiteGueriteView = null;

        // ------------------  Traitement des formulaires selon le rôle  ------------------

        // 1. FORMULAIRE DE VISITE
        if ($this->isGranted('ROLE_GUERITE')) {
            $visiteGuerite = new Visite();
            $formVisiteGuerite = $this->createForm(VisiteType::class, $visiteGuerite, ['with_extra_fields' => true]);
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
            $formVisite = $this->createForm(VisiteType::class, $visite, ['with_extra_fields' => true]);
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
            $formVisite = $this->createForm(VisiteType::class, $visite, ['with_extra_fields' => false]);
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

        // 2. FORMULAIRES POUR LE RÔLE STOCK
        if ($this->isGranted('ROLE_STOCK')) {
            // Formulaire d'ajout d'article
            $article = new Article();
            $formStock = $this->createForm(StockType::class, $article);
            $formStock->handleRequest($request);
            if ($formStock->isSubmitted() && $formStock->isValid()) {
                $em->persist($article);
                $em->flush();
                $this->addFlash('success', '📥 Article enregistré.');
                return $this->redirectToRoute('app_dashboard');
            }
            $formStockView = $formStock->createView();

            // Bon de sortie (pour le stock)
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

        // 3. FORMULAIRES POUR LES UTILISATEURS SIMPLES (MEMBRES)
        // Exclure tous les rôles qui ont leur propre vue ou qui ne doivent pas accéder à ce formulaire
        if (!$this->isGranted('ROLE_COORDON') &&
            !$this->isGranted('ROLE_STOCK') &&
            !$this->isGranted('ROLE_DG') &&
            !$this->isGranted('ROLE_GUERITE') &&
            !$this->isGranted('ROLE_COMPTA') &&
            !$this->isGranted('ROLE_CAISSIER') &&
            !$this->isGranted('ROLE_ADMIN')) {

            // --- Demande de matériel ---
            $demande = new Demande();
            $formDemande = $this->createForm(DemandeType::class, $demande);
            $formDemande->handleRequest($request);

            if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                // Récupération du nom saisi et de l'attribut choisi
                $nomMaterielSaisi = $formDemande->get('materiel')->getData();
                /** @var ArticleAttribut|null $attribut */
                $attribut = $formDemande->get('attribut')->getData();

                if (empty($nomMaterielSaisi)) {
                    $this->addFlash('danger', 'Veuillez saisir ou sélectionner un article.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $quantiteDemandee = $demande->getQuantite() ?? 1;

                // Recherche de l'article en base par son nom
                $article = $em->getRepository(Article::class)->findOneBy(['nom' => $nomMaterielSaisi]);

                // Si un attribut est sélectionné et qu'il a une quantité
                if ($attribut && method_exists($attribut, 'getQuantite')) {
                    if ($attribut->getQuantite() < $quantiteDemandee) {
                        $this->addFlash('danger', 'Stock insuffisant pour cette variante.');
                        return $this->redirectToRoute('app_dashboard');
                    }
                    // Décrémenter le stock de l'attribut
                    $attribut->setQuantite($attribut->getQuantite() - $quantiteDemandee);
                    $demande->setMateriel($nomMaterielSaisi . ' (' . $attribut->getValeur() . ')');
                } else {
                    // Pas d'attribut : on vérifie le stock global de l'article
                    if ($article) {
                        if ($article->getQuantite() < $quantiteDemandee) {
                            $this->addFlash('danger', 'Stock insuffisant pour cet article.');
                            return $this->redirectToRoute('app_dashboard');
                        }
                        $article->setQuantite($article->getQuantite() - $quantiteDemandee);
                    } else {
                        // Si l'article n'existe pas, on pourrait créer une alerte ou l'ignorer
                        $this->addFlash('warning', 'Article introuvable en stock. La demande sera enregistrée sans contrôle de stock.');
                    }
                    $demande->setMateriel($nomMaterielSaisi);
                }

                // Vérification de la destination pour les gilets
                if (str_contains(strtolower($nomMaterielSaisi), 'gilet') && empty($demande->getDestination())) {
                    $this->addFlash('danger', 'La commune de destination est obligatoire pour les gilets.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $demande->setDepartement($userDept);
                $demande->setStatut('En attente');
                $em->persist($demande);
                $em->flush();

                $this->addFlash('success', 'Demande enregistrée avec succès.');
                return $this->redirectToRoute('app_dashboard');
            }
            $formDemandeView = $formDemande->createView();

            // --- Bon de sortie ---
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

        // ------------------  INTERROGATION BASE DE DONNÉES  ------------------
        $demandes      = [];
        $bonsSortie    = [];
        $visites       = [];
        $rapportGlobal = [];
        $articles      = [];
        $attributs     = [];
        $categories    = [];
        $subCategories = [];
        $selectedCategory    = null;
        $selectedSubCategory = null;

        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui   = new \DateTime('today 23:59:59');

        $filtreType      = $request->query->get('filtre_type');
        $filtreDateDebut = $request->query->get('filtre_date_debut');
        $filtreDateFin   = $request->query->get('filtre_date_fin');
        $filtreStatut    = $request->query->get('filtre_statut');

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

        // --- AUTRES RÔLES (STOCK, COORDON, DG, COMPTA) pour les données ---
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

        // Récupération des attributs/catégories pour les rôles concernés
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

        // Bons de sortie
        if ($this->isGranted('ROLE_COORDON')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
        } elseif ($this->isGranted('ROLE_GUERITE')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
            $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
        } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        // ------------------  RENDU SELON LE RÔLE  ------------------

        // COMPTA
        if ($this->isGranted('ROLE_COMPTA')) {
            return $this->render('dashboard/compta.html.twig', [
                'demandes'      => $demandeRepository->findBy(['statut' => 'En attente'], ['id' => 'DESC']),
                'rapportGlobal' => $rapportGlobal,
                'devisListe'    => $devisListe,
                'totalDevisUSD' => $totalDevisUSD,
                'totalDevisCDF' => $totalDevisCDF,
                'userRole'      => $roleLabel,
                'stats'         => $stats,
                'formVisite'    => $formVisiteView,
                'date_debut'    => $dateDebutFiltre,
                'date_fin'      => $dateFinFiltre,
                'articles'      => $articles,
                'categories'    => $categories,
                'subCategories' => $subCategories,
                'selectedCategory'    => $selectedCategory,
                'selectedSubCategory' => $selectedSubCategory,
                'attributs'     => $attributs,
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
            return $this->render('dashboard/membre.html.twig', [
                'demandes'      => $demandes,
                'rapportGlobal' => $rapportGlobal,
                'bonsSortie'    => $bonsSortie,
                'articles'      => $articles,
                'devisListe'    => $devisListe,
                'userRole'      => $roleLabel,
                'stats'         => $stats,
                'date_debut'    => $dateDebutFiltre,
                'date_fin'      => $dateFinFiltre,
            ]);
        }

        // PAR DÉFAUT (UTILISATEUR SIMPLE)
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
    // ROUTES POUR LES RAPPORTS PDF ET EXCEL
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
    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function validerCompta(Demande $demande, EntityManagerInterface $em): Response
    {
        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        $quantiteDemandee = $demande->getQuantite() ?? 1;

        if (!$article || $article->getQuantite() < $quantiteDemandee) {
            $stockActuel = $article ? $article->getQuantite() : 0;
            $this->addFlash('danger', '🛑 Impossible de valider le budget. Le matériel "' . $demande->getMateriel() . '" n\'est pas assez en stock au dépôt.');
            return $this->redirectToRoute('app_dashboard');
        }

        $demande->setStatut('Validé Compta');
        $em->flush();
        $this->addFlash('success', '💰 Budget accordé avec succès !');
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


    // ============================================================
// ROUTE POUR LA SORTIE EFFECTIVE (GUERITE)
// ============================================================
#[Route('/dashboard/guerite/sortie/{id}', name: 'app_guerite_sortie_bon', methods: ['POST'])]
#[IsGranted('ROLE_GUERITE')]
public function sortieBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
{
    // Vérifier que le bon est dans un état où la sortie est possible (Validé Coordon)
    if ($bonSortie->getStatut() !== 'Validé Coordon') {
        $this->addFlash('danger', 'Ce bon n\'est pas prêt pour la sortie (statut actuel : ' . $bonSortie->getStatut() . ').');
        return $this->redirectToRoute('app_dashboard');
    }

    // Changer le statut en "Sortie effective" (ou "Sortie guérite")
    $bonSortie->setStatut('Sortie effective');
    $em->flush();

    $this->addFlash('success', '✅ Sortie du matériel confirmée pour le bon #' . $bonSortie->getId() . '.');
    return $this->redirectToRoute('app_dashboard');
}

    #[Route('/api/recherche-article', name: 'api_recherche_article', methods: ['GET'])]
public function rechercheArticle(Request $request, EntityManagerInterface $em): JsonResponse
{
    $nom = $request->query->get('nom');
    if (empty($nom)) {
        return new JsonResponse(['error' => 'Nom requis'], 400);
    }

    $article = $em->getRepository(Article::class)->findOneBy(['nom' => $nom]);
    if (!$article) {
        return new JsonResponse(['found' => false]);
    }

    $attributs = [];
    foreach ($article->getAttributs() as $attr) {
        $details = [];
        if ($attr->getCouleur()) $details[] = 'Couleur: ' . $attr->getCouleur();
        if ($attr->getGrammage()) $details[] = 'Grammage: ' . $attr->getGrammage();
        if ($attr->getDimension()) $details[] = 'Dimension: ' . $attr->getDimension();
        if ($attr->getLongueur()) $details[] = 'Longueur: ' . $attr->getLongueur();
        $label = implode(' - ', $details) ?: 'Standard';
        $attributs[] = [
            'id' => $attr->getId(),
            'valeur' => $label
        ];
    }

    return new JsonResponse([
        'found' => true,
        'article_id' => $article->getId(),
        'attributs' => $attributs
    ]);
}
}