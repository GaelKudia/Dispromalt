<?php

namespace App\Controller;

use App\Entity\Demande;
    use App\Entity\BonSortie;
    use App\Entity\Article;
    use App\Entity\Visite; 
    use App\Entity\Devis; 

    use App\Form\DemandeType;
    use App\Form\BonSortieType;
    use App\Form\VisiteType;
    use App\Form\StockType;
    use App\Form\DevisType;

    use App\Repository\DemandeRepository;
    use App\Repository\BonSortieRepository;
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
            EntityManagerInterface $em
        ): Response {
            
            // 1. UTILISATEUR CONNECTÉ
            $currentUser = $this->getUser();
            if (!$currentUser) {
                return $this->redirectToRoute('app_login');
            }
            
            // --- 1. CALCUL DES STATS (On le fait en tout premier) ---
            $stats = [
                'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
                'bons_a_valider'   => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
                'bons_guerite'     => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
            ];

            // --- 2. CONDITION CAISSIER ---
                                        if ($this->isGranted('ROLE_CAISSIER')) {
                                $devisAPayer = $em->getRepository(Devis::class)->findBy(['statut' => 'Validé Compta']);
                                
                                return $this->render('dashboard/caisse/index.html.twig', [
                                    'devisListe'    => $devisAPayer, // On envoie les devis ici
                                    'totalDevisUSD' => 0, // Tu peux calculer ces totaux si besoin, ou mettre 0
                                    'totalDevisCDF' => 0,
                                    'date_debut'    => (new \DateTime('today'))->format('Y-m-d'),
                                    'date_fin'      => (new \DateTime('today'))->format('Y-m-d'),
                                    'userRole'      => 'ROLE_CAISSIER',
                                    'stats'         => $stats,
                                ]);
                            }

            $userName = $currentUser->getUserIdentifier(); 
            $userDept = 'Impression'; 

            // Initialisation par défaut des vues de formulaires à null
            $formStockView = null;
            $formDemandeView = null;
            $formBonView = null;
            $formVisiteView = null;

            // 1. CALCULER LES STATS D'ABORD (Avant tout rendu)
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider'   => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite'     => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin_dashboard');
            }

            

            // =========================================================================
            // 📦 2. TRAITEMENT DES FORMULAIRES SELON LES PRIVILÈGES
            // =========================================================================
            
            // A. FORMULAIRE DU STOCK (MAGASINIER EXCLUSIVEMENT)
            if ($this->isGranted('ROLE_STOCK')) {
                $formStock = $this->createForm(StockType::class);
                $formStock->handleRequest($request);

                if ($formStock->isSubmitted() && $formStock->isValid()) {
                    $data = $formStock->getData();
                    
                    $seuilAlerte = (int)$request->request->get('seuil_alerte', 5);
                    $nomArticle = $data['article'];
                    $quantiteAjoutee = (int)$data['quantite'];

                    $articleRepository = $em->getRepository(Article::class);
                    $article = $articleRepository->findOneBy(['nom' => $nomArticle]);

                    if (!$article) {
                        $article = new Article();
                        $article->setNom($nomArticle);
                        $article->setQuantite($quantiteAjoutee);
                    } else {
                        $article->setQuantite($article->getQuantite() + $quantiteAjoutee);
                    }

                    $article->setSeuilAlerte($seuilAlerte);

                    $em->persist($article);
                    $em->flush();
                    
                    $this->addFlash('success', '📥 L\'article "' . $nomArticle . '" a bien été enregistré ! Stock total : ' . $article->getQuantite());
                    return $this->redirectToRoute('app_dashboard');
                }
                $formStockView = $formStock->createView();
            }

            // B & C. FORMULAIRES DE DEMANDE ET DE BON (UNIQUEMENT COMPTES OPÉRATIONNELS)
            if (!$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_STOCK') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_GUERITE')) {
                
                // Demande de matériel
                $demande = new Demande();
                $formDemande = $this->createForm(DemandeType::class, $demande);
                $formDemande->handleRequest($request);

                if ($formDemande->isSubmitted() && $formDemande->isValid()) {
                    $nomMaterielSaisi = strtolower($demande->getMateriel());
                    if (str_contains($nomMaterielSaisi, 'gilet') && !$demande->getDestination()) {
                        $this->addFlash('danger', '🛑 Action refusée. La commune de destination est obligatoire pour toute demande de gilets.');
                        return $this->redirectToRoute('app_dashboard');
                    }

                    $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
                    $quantiteDemandee = $demande->getQuantite() ?? 1;

                    if (!$article || $article->getQuantite() < $quantiteDemandee) {
                        $stockActuel = $article ? $article->getQuantite() : 0;
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
                    $bonSortie->setNomEmploye($userName);
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

            // D. FORMULAIRE DE RÉSERVATION DE VISITE
            if (!$this->isGranted('ROLE_GUERITE') && !($this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_ADMIN')) && class_exists('App\Form\VisiteType')) {
                $visite = class_exists('App\Entity\Visite') ? new Visite() : new \stdClass(); 
                if ($visite instanceof Visite) {
                    $formVisite = $this->createForm(VisiteType::class, $visite);
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
                
            }  
            
            // INTERROGATION BASE DE DONNÉES
            $demandes = [];
            $bonsSortie = [];
            $visites = [];
            $rapportGlobal = [];
            $articles = []; 
            $devisListe = [];
            
            $totalDevisUSD = 0;
            $totalDevisCDF = 0;

            $debutAujourdhui = new \DateTime('today 00:00:00');
            $finAujourdhui = new \DateTime('today 23:59:59');

            $dateDebutFiltre = $request->query->get('date_debut');
            $dateFinFiltre = $request->query->get('date_fin');

            if ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
                if (class_exists('App\Entity\Visite')) {
                    $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'A_L_ACCUEIL'], ['id' => 'DESC']);
                }
            } else {
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
                    $articles = $em->getRepository(Article::class)->findAll();
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

                    $rapportGlobal = $qbGlobal->orderBy('d.createdAt', 'DESC')
                                            ->getQuery()
                                            ->getResult();
                }

                if (class_exists('App\Entity\Devis')) {
        $qbDevis = $em->getRepository(Devis::class)->createQueryBuilder('d');

        // Gestion des filtres de date
        if ($dateDebutFiltre && $dateFinFiltre) {
            $qbDevis->where('d.createdAt BETWEEN :start AND :end')
                    ->setParameter('start', new \DateTime($dateDebutFiltre . ' 00:00:00'))
                    ->setParameter('end', new \DateTime($dateFinFiltre . ' 23:59:59'));
        } else {
            $qbDevis->where('d.createdAt BETWEEN :debut AND :fin')
                    ->setParameter('debut', $debutAujourdhui)
                    ->setParameter('fin', $finAujourdhui);
        }

        $devisListe = $qbDevis->orderBy('d.id', 'DESC')->getQuery()->getResult();

        // Calcul des totaux
        foreach ($devisListe as $d) {
            // Condition simplifiée (une seule fois)
            if ($d->getStatut() === Devis::STATUT_DECAISSE) {
                if ($d->getDevise() === 'CDF') {
                    $totalDevisCDF += $d->getMontant();
                } else {
                    $totalDevisUSD += $d->getMontant();
                }
            }
        }
    }

                if ($this->isGranted('ROLE_COORDON')) {
                    $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
                } elseif ($this->isGranted('ROLE_GUERITE')) {
                    $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
                    if (class_exists('App\Entity\Visite')) {
                        $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
                    }
                } elseif (!$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
                    $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
                }
            }

            if (!$dateDebutFiltre || !$dateFinFiltre) {
                $dateDebutFiltre = $debutAujourdhui->format('Y-m-d');
                $dateFinFiltre = $finAujourdhui->format('Y-m-d');
            }

            $stats = [
                'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
                'bons_a_valider' => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
                'bons_guerite' => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
            ];

            $roleLabel = !empty($currentUser->getRoles()) ? $currentUser->getRoles()[0] : 'ROLE_USER'; 

            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_admin_dashboard');
            }

                

            if ($this->isGranted('ROLE_GUERITE')) {
                return $this->render('dashboard/guerite.html.twig', [
                    'bonsSortie' => $bonsSortie,
                    'visites' => $visites,
                    'userRole' => $roleLabel,
                    'stats' => $stats
                ]);
            }

            

            if ($this->isGranted('ROLE_ACCUEIL') || $this->isGranted('ROLE_RECEPTION')) {
                return $this->render('dashboard/accueil.html.twig', [
                    'visites' => $visites,
                    'userRole' => $roleLabel,
                    'stats' => $stats,
                    'formVisite' => $formVisiteView, 
                    'formBon' => $formBonView 
                ]);
            }

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

            if ($this->isGranted('ROLE_STOCK')) {
                return $this->render('dashboard/stock.html.twig', [
                    'demandes' => $demandes,         
                    'rapportGlobal' => $rapportGlobal, 
                    'userRole' => $roleLabel,
                    'stats' => $stats,
                    'formStock' => $formStockView,
                    'articles' => $articles,
                    'date_debut' => $dateDebutFiltre,
                    'date_fin' => $dateFinFiltre
                ]);
            }

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

        // =========================================================================
        // 📄 WORKFLOW DES DEVIS
        // =========================================================================

        

        // =========================================================================
        // 🎛️ ACTIONS DE VALIDATIONS DES DEMANDES DE STOCK
        // =========================================================================


        #[Route('/dashboard/caissier/valider-decaissement/{id}', name: 'app_caissier_valider_decaissement', methods: ['POST'])]
        #[IsGranted('ROLE_CAISSIER')]
        public function validerDecaissement(Demande $demande, EntityManagerInterface $em): Response
        {
            if ($demande->getStatut() !== 'Validé Compta') {
                $this->addFlash('danger', '❌ Action impossible.');
                return $this->redirectToRoute('app_dashboard');
            }

            $demande->setStatut('Decaissé');
            $em->flush();
            
            $this->addFlash('success', '✅ Décaissement validé avec succès.');
            return $this->redirectToRoute('app_dashboard'); 
        }
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
            // Vérification des droits (ajuste selon tes besoins)
            if (!$this->isGranted('ROLE_GUERITE')) {
                throw $this->createAccessDeniedException('Accès refusé.');
            }

            $visite->setStatut('VALIDE'); // Ou le statut que tu souhaites appliquer
            $em->flush();

            $this->addFlash('success', '✅ La visite a été validée à la guérite.');
            return $this->redirectToRoute('app_dashboard');
        }

        // =========================================================================
        // 🖨️ ROUTES POUR LES EXPORTS PDF ET EXCEL
        // =========================================================================

        #[Route('/dashboard/rapport/pdf', name: 'app_rapport_pdf', methods: ['GET'])]
        public function exportPdf(Request $request, DemandeRepository $demandeRepository): Response
        {
            if (!$this->isGranted('ROLE_STOCK') && !$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
                throw $this->createAccessDeniedException();
            }

            $dateDebut = $request->query->get('date_debut');
            $dateFin = $request->query->get('date_fin');

            if (!$dateDebut || !$dateFin) {
                $dateDebut = (new \DateTime('today'))->format('Y-m-d');
                $dateFin = (new \DateTime('today'))->format('Y-m-d');
            }

            $qb = $demandeRepository->createQueryBuilder('d');
            $qb->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
            ->setParameter('end', new \DateTime($dateFin . ' 23:59:59'));

            $donnees = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

            $html = $this->renderView('dashboard/rapport_pdf.html.twig', [
                'rapportGlobal' => $donnees,  
                'date_debut'    => $dateDebut,
                'date_fin'      => $dateFin
            ]);

            $options = new \Dompdf\Options();
            $options->set('defaultFont', 'Helvetica');
            
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rapport_imprimerie_'.$dateDebut.'.pdf"',
            ]);
        }

        #[Route('/dashboard/rapport/excel', name: 'app_rapport_excel', methods: ['GET'])]
        public function exportExcel(Request $request, DemandeRepository $demandeRepository): Response
        {
            if (!$this->isGranted('ROLE_STOCK') && !$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_COMPTA')) {
                throw $this->createAccessDeniedException();
            }

            $dateDebut = $request->query->get('date_debut');
            $dateFin = $request->query->get('date_fin');

            if (!$dateDebut || !$dateFin) {
                $dateDebut = (new \DateTime('today'))->format('Y-m-d');
                $dateFin = (new \DateTime('today'))->format('Y-m-d');
            }

            $qb = $demandeRepository->createQueryBuilder('d');
            $qb->where('d.createdAt BETWEEN :start AND :end')
            ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
            ->setParameter('end', new \DateTime($dateFin . ' 23:59:59'));

            $donnees = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'Date / Heure');
            $sheet->setCellValue('B1', 'Département');
            $sheet->setCellValue('C1', 'Localisation');
            $sheet->setCellValue('D1', 'Matériel / Article');
            $sheet->setCellValue('E1', 'Quantité');
            $sheet->setCellValue('F1', 'Statut');

            $ligne = 2;
            foreach ($donnees as $rg) {
                $sheet->setCellValue('A' . $ligne, $rg->getCreatedAt() ? $rg->getCreatedAt()->format('d/m/Y H:i') : '');
                $sheet->setCellValue('B' . $ligne, $rg->getDepartement());
                $sheet->setCellValue('C' . $ligne, $rg->getLocalisation());
                $sheet->setCellValue('D' . $ligne, $rg->getMateriel());
                $sheet->setCellValue('E' . $ligne, $rg->getQuantite());
                $sheet->setCellValue('F' . $ligne, $rg->getStatut());
                $ligne++;
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'excel');
            $writer->save($tempFile);

            return $this->file($tempFile, 'rapport_imprimerie_'.$dateDebut.'.xlsx');
        }

    // =========================================================================
    //  les caissier 
    // AJOUTE CETTE ROUTE DANS TON DashboardController
                
            
    }
