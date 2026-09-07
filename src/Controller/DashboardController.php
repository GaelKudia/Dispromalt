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

        // Si l'utilisateur est un Chef de Service
        // Si l'utilisateur est un Chef de Service
        // Si l'utilisateur est un Chef de Service
        // Si l'utilisateur est un Chef de Service
        if ($currentUser->isChefService() && !$this->isGranted('ROLE_ADMIN')) {
            $serviceChef = $currentUser->getService();
            $userDept = $serviceChef ? $serviceChef->getNom() : 'Impression';

            // 1. FORMULAIRE DE DEMANDE DE MATÉRIEL (pour lui-même)
            $demande = new Demande();
            $formDemande = $this->createForm(DemandeType::class, $demande);
            $formDemande->handleRequest($request);

            if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                $nomMaterielSaisi = $formDemande->get('materiel')->getData();
                $attribut = $formDemande->get('attribut')->getData();

                if (empty($nomMaterielSaisi)) {
                    $this->addFlash('danger', 'Veuillez saisir ou sélectionner un article.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $quantiteDemandee = $demande->getQuantite() ?? 1;
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

            // 2. Récupérer les demandes des utilisateurs de son service en attente
            $demandesService = $demandeRepository->createQueryBuilder('d')
                ->join('d.demandeur', 'u')
                ->where('u.service = :service')
                ->andWhere('d.statut = :statut')
                ->setParameter('service', $serviceChef)
                ->setParameter('statut', 'En attente')
                ->orderBy('d.id', 'DESC')
                ->getQuery()
                ->getResult();

            // 3. Récupérer les bons de sortie de son département
            $bonsSortieService = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);

            // 4. Filtrer les DEVIS pour qu'il ne voie QUE ceux de son service
            $devisServiceListe = [];
            $totalDevisUSD = 0;
            $totalDevisCDF = 0;

            if (isset($devisRepository)) {
                $qbDevisService = $devisRepository->createQueryBuilder('dev')
                    // ⚠️ VÉRIFIEZ LE NOM DE LA RELATION DANS VOTRE ENTITÉ DEVIS :
                    // Si la relation vers l'utilisateur s'appelle 'demandeur' ou 'user' ou 'createur'
                    ->join('dev.user', 'u') 
                    ->where('u.service = :service')
                    ->andWhere('dev.createdAt BETWEEN :start AND :end')
                    ->setParameter('service', $serviceChef)
                    ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                    ->setParameter('end', new \DateTime($dateFinFiltre . ' 23:59:59'))
                    ->orderBy('dev.id', 'DESC');

                $devisServiceListe = $qbDevisService->getQuery()->getResult();

                foreach ($devisServiceListe as $d) {
                    if (method_exists($d, 'getDevise') && method_exists($d, 'getMontant')) {
                        // S'assurer de couvrir tous les statuts valides/encaissés
                        if (in_array($d->getStatut(), ['Validé Compta', 'Decaissé', 'DECAISSE'])) {
                            if (strtoupper($d->getDevise()) === 'USD') {
                                $totalDevisUSD += $d->getMontant();
                            } elseif (strtoupper($d->getDevise()) === 'CDF') {
                                $totalDevisCDF += $d->getMontant();
                            }
                        }
                    }
                }
            }

            return $this->render('dashboard/membre.html.twig', [
                'demandes'      => $demandesService,
                'bonsSortie'    => $bonsSortieService,
                'devisListe'    => $devisServiceListe, // 👈 Variable injectée explicitement pour le chef de service
                'totalDevisUSD' => $totalDevisUSD,
                'totalDevisCDF' => $totalDevisCDF,
                'formDemande'   => $formDemande->createView(),
                'formBon'       => null,
                'userRole'      => 'Chef de Service (' . ($serviceChef ? $serviceChef->getNom() : 'Général') . ')',
                'stats'         => $stats,
                'date_debut'    => $dateDebutFiltre,
                'date_fin'      => $dateFinFiltre,
            ]);
        }
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
        if (!$this->isGranted('ROLE_COORDON') &&
            !$this->isGranted('ROLE_STOCK') &&
            !$this->isGranted('ROLE_DG') &&
            !$this->isGranted('ROLE_GUERITE') &&
            !$this->isGranted('ROLE_COMPTA') &&
            !$this->isGranted('ROLE_CAISSIER') &&
            !$this->isGranted('ROLE_ADMIN')) {

            $demande = new Demande();
            $formDemande = $this->createForm(DemandeType::class, $demande);
            $formDemande->handleRequest($request);

            if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                $nomMaterielSaisi = $formDemande->get('materiel')->getData();
                /** @var ArticleAttribut|null $attribut */
                $attribut = $formDemande->get('attribut')->getData();

                if (empty($nomMaterielSaisi)) {
                    $this->addFlash('danger', 'Veuillez saisir ou sélectionner un article.');
                    return $this->redirectToRoute('app_dashboard');
                }

                $quantiteDemandee = $demande->getQuantite() ?? 1;
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
                    } else {
                        $this->addFlash('warning', 'Article introuvable en stock. La demande sera enregistrée sans contrôle de stock.');
                    }
                    $demande->setMateriel($nomMaterielSaisi);
                }

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

        if ($this->isGranted('ROLE_COORDON')) {
            $demandesCoordon = $demandeRepository->findBy(['statut' => 'Validé Chef'], ['id' => 'DESC']);
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
            
            return $this->render('dashboard/membre.html.twig', [
                'demandes' => $demandesCoordon,
                'bonsSortie' => $bonsSortie,
                'userRole' => $roleLabel,
                'stats' => $stats,
                'date_debut' => $dateDebutFiltre,
                'date_fin'   => $dateFinFiltre,
                'rapportGlobal'  => $rapportGlobal,
            ]);
        }

        if ($this->isGranted('ROLE_COORDON')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
        } elseif ($this->isGranted('ROLE_GUERITE')) {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
            $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
        } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        // COMPTA
        if ($this->isGranted('ROLE_COMPTA')) {
            return $this->render('dashboard/compta.html.twig', [
            //    'demandes'      => $demandeRepository->findBy(['statut' => 'En attente'], ['id' => 'DESC']),
                'demandes'      => $demandeRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']),
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
        if ($bonSortie->getStatut() !== 'Validé Coordon') {
            $this->addFlash('danger', 'Ce bon n\'est pas prêt pour la sortie (statut actuel : ' . $bonSortie->getStatut() . ').');
            return $this->redirectToRoute('app_dashboard');
        }

        $bonSortie->setStatut('Sortie effective');
        $em->flush();

        $this->addFlash('success', '✅ Sortie du matériel confirmée pour le bon #' . $bonSortie->getId() . '.');
        return $this->redirectToRoute('app_dashboard');
    }

    // ============================================================
    // RECHERCHE API ARTICLE
    // ============================================================
   /* #[Route('/api/recherche-article', name: 'api_recherche_article', methods: ['GET'])]
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
    } */

    #[Route('/dashboard/chef-service/traiter/{id}', name: 'app_chef_service_traiter', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function traiterDemandeChefService(Request $request, Demande $demande, EntityManagerInterface $em): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // 1. Vérifier si l'utilisateur est bien chef de service
        if (!$currentUser->isChefService()) {
            throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
        }

        // 2. Vérifier que la demande appartient au même service que le chef
        $demandeur = $demande->getUser(); // Assure-toi que la relation user est bien mappée dans Demande
        if (!$demandeur || $demandeur->getService() !== $currentUser->getService()) {
            $this->addFlash('danger', '🛑 Vous ne pouvez valider que les demandes des membres de votre service.');
            return $this->redirectToRoute('app_dashboard');
        }

        $action = $request->request->get('action');

        if ($action === 'valider') {
            $demande->setStatut('Validé Chef');
            $this->addFlash('success', '✅ Demande validée et transmise à l\'étape suivante.');
        } elseif ($action === 'rejeter') {
            $demande->setStatut('Rejeté Chef');
            $this->addFlash('warning', '❌ La demande a été rejetée.');
        }

        $em->flush();
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/coordonnateur/valider/{id}', name: 'app_coordonnateur_valider_demande', methods: ['POST'])]
    #[IsGranted('ROLE_COORDON')]
    public function validerDemandeCoordonnateur(Request $request, Demande $demande, EntityManagerInterface $em): Response
    {
        // Vérifier que la demande est bien dans l'état attendu
        if ($demande->getStatut() !== 'Validé Chef') {
            $this->addFlash('danger', '❌ Cette demande ne peut pas être validée à cette étape.');
            return $this->redirectToRoute('app_dashboard');
        }

        $action = $request->request->get('action'); // 'valider' ou 'rejeter'

        if ($action === 'valider') {
            // Passe au statut validé par le coordonnateur (prêt pour la compta ou stock)
            $demande->setStatut('Validé Coordon');
            $this->addFlash('success', '🖋️ Demande validée par le Coordonnateur et transmise.');
        } elseif ($action === 'rejeter') {
            $demande->setStatut('Rejeté Coordon');
            $this->addFlash('warning', '❌ La demande a été rejetée par le Coordonnateur.');
        }

        $em->flush();
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
            
            // Fin de l'affectation du tableau associatif pour chaque attribut
            $attributs[] = [   
                'id' => $attr->getId(),
                'valeur' => $label,
                'quantite' => $attr->getQuantite()
            ];
        }

        // Retourne la réponse JSON avec les données trouvées
        return new JsonResponse([
            'found' => true,
            'nom' => $article->getNom(),
            'quantite' => $article->getQuantite(),
            'attributs' => $attributs
        ]);
    }
}
