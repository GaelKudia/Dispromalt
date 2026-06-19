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

        $userName = $currentUser->getUserIdentifier(); 
        $userDept = 'Impression'; 

        // Initialisation par défaut des vues de formulaires à null
        $formStockView = null;
        $formDemandeView = null;
        $formBonView = null;
        $formVisiteView = null;

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
        if (!$this->isGranted('ROLE_COORDON') && !$this->isGranted('ROLE_STOCK') && !$this->isGranted('ROLE_DG') && !$this->isGranted('ROLE_GUERITE') && !$this->isGranted('ROLE_COMPTA')) {
            
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

                $siteDeLAgent = $currentUser->getLocalisation(); 

                if (!empty($siteDeLAgent)) {
                    $demande->setLocalisation($siteDeLAgent);
                    if (!$demande->getDestination()) {
                        $demande->setDestination($siteDeLAgent);
                    }
                } else {
                    $demande->setLocalisation('Direction');
                    if (!$demande->getDestination()) {
                        $demande->setDestination('Direction / Siège');
                    }
                }
                
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
        
        // =========================================================================
        // 🔍 3. RÉCUPÉRATION DES DONNÉES ET FILTRES TEMPORELS SYNCHRONISÉS
        // =========================================================================
        $demandes = [];
        $bonsSortie = [];
        $visites = [];
        $rapportGlobal = [];
        $articles = []; 
        $devisListe = [];
        
        // Variables de cumul séparées par devise pour éviter les totaux erronés
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
            
            // Suivi Journalier des Demandes (Jour J)
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

            // 📑 LOGIQUE DU RAPPORT GÉNÉRAL HISTORIQUE
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

            // 📑 LOGIQUE DU RAPPORT DES DEVIS (Correction des totaux synchronisés)
            if (class_exists('App\Entity\Devis')) {
                $qbDevis = $em->getRepository(Devis::class)->createQueryBuilder('d');

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

                foreach ($devisListe as $d) {
                    if ($d->getStatut() === 'VALIDE') {
                        if ($d->getDevise() === 'CDF') {
                            $totalDevisCDF += $d->getMontant();
                        } else {
                            $totalDevisUSD += $d->getMontant(); // Par défaut ou USD
                        }
                    }
                }
            }

            // Suivi du Registre des Bons de Sortie
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

        // Fix des dates par défaut si vides pour Twig
        if (!$dateDebutFiltre || !$dateFinFiltre) {
            $dateDebutFiltre = $debutAujourdhui->format('Y-m-d');
            $dateFinFiltre = $finAujourdhui->format('Y-m-d');
        }

        // =========================================================================
        // 📊 4. COMPTEURS STATISTIQUES
        // =========================================================================
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider' => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite' => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];

        // =========================================================================
        // 🚀 5. COUCHE RENDU TWIG SÉCURISÉE SANS CONFLITS
        // =========================================================================
        $roleLabel = $currentUser->getRoles()[0]; 

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

        // VUE PAR DÉFAUT : USER / MEMBRE OPERATIONNEL
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
    // 📄 WORKFLOW DES DEVIS (Saisie -> Aperçu -> Envoi Compta)
    // =========================================================================

    #[Route('/dashboard/devis/nouveau', name: 'app_devis_nouveau')]
    public function nouveauDevis(Request $request, EntityManagerInterface $em): Response
    {
        $devis = new Devis();
        $form = $this->createForm(DevisType::class, $devis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entete = $form->has('entete') ? $form->get('entete')->getData() : '';
            
            // Correction 1 : On ajoute bien la clé 'devise' dans le tableau de Session
            $request->getSession()->set('devis_brouillon', [
                'entete' => $entete ?: (method_exists($devis, 'getEntete') ? $devis->getEntete() : 'Nouveau Devis'),
                'description' => $devis->getDescription(),
                'montant' => $devis->getMontant(),
                'devise' => $form->get('devise')->getData() ?? 'USD'
            ]);
            return $this->redirectToRoute('app_devis_apercu');
        }

        return $this->render('dashboard/devis_form.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/dashboard/devis/apercu', name: 'app_devis_apercu')]
    public function apercuDevis(Request $request, EntityManagerInterface $em): Response
    {
        $sessionData = $request->getSession()->get('devis_brouillon');
        if (!$sessionData) {
            return $this->redirectToRoute('app_devis_nouveau');
        }

        if ($request->isMethod('POST')) {
            $devis = new Devis();
            
            if (method_exists($devis, 'setEntete')) {
                $devis->setEntete($sessionData['entete'] ?? 'Nouveau Devis');
            }
            
            $devis->setDescription($sessionData['description']);
            $devis->setMontant($sessionData['montant']);
            $devis->setStatut('EN_ATTENTE');
            $devis->setCreatedAt(new \DateTime());
            $devis->setCreatedBy($this->getUser()->getUserIdentifier());

            // Correction 2 : Récupération sécurisée depuis la Session sans passer par $form
            $devis->setDevise($sessionData['devise'] ?? 'USD');

            $em->persist($devis);
            $em->flush();

            $request->getSession()->remove('devis_brouillon');
            $this->addFlash('success', '🚀 Votre devis a été rédigé et envoyé à la comptabilité !');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('dashboard/devis_apercu.html.twig', [
            'devis' => $sessionData
        ]);
    }

    #[Route('/dashboard/compta/devis/valider/{id}', name: 'app_devis_compta_valider', methods: ['POST'])]
    public function validerDevisCompta(Devis $devis, EntityManagerInterface $em): Response
    {
        $devis->setStatut('VALIDE');
        $em->flush();
        
        $this->addFlash('success', '✅ Le devis a été validé et le montant ajouté aux dépenses cumulées.');
        return $this->redirectToRoute('app_dashboard');
    }

    // =========================================================================
    // 🎛️ ACTIONS DE VALIDATIONS DES DEMANDES CLASSIQUES
    // =========================================================================

    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
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
    public function livrer(Demande $demande, EntityManagerInterface $em): Response
    {
        if ($demande->getStatut() !== 'Validé Compta') {
            $this->addFlash('danger', '❌ Action interdite.');
            return $this->redirectToRoute('app_dashboard');
        }

        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        if ($article) {
            $quantiteDemandee = $demande->getQuantite() ?? 1; 
            $nouvelleQuantite = $article->getQuantite() - $quantiteDemandee; 

            if ($nouvelleQuantite < 0) {
                $this->addFlash('danger', '❌ Stock insuffisant.');
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
        $visite->setStatut('TERMINE'); 
        $em->flush();
        $this->addFlash('success', '🚪 Le départ du visiteur ' . $visite->getNomVisiteur() . ' a bien été signalé.');
        return $this->redirectToRoute('app_dashboard');
    }

    // =========================================================================
    // 🖨️ ROUTES POUR LES EXPORTS PDF ET EXCEL
    // =========================================================================

    #[Route('/dashboard/rapport/pdf', name: 'app_rapport_pdf', methods: ['GET'])]
    public function exportPdf(Request $request, DemandeRepository $demandeRepository): Response
    {
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $qb = $demandeRepository->createQueryBuilder('d');
        if ($dateDebut && $dateFin) {
            $qb->where('d.createdAt BETWEEN :start AND :end')
               ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
               ->setParameter('end', new \DateTime($dateFin . ' 23:59:59'));
        }
        $donnees = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

        $html = $this->renderView('dashboard/rapport_pdf.html.twig', [
            'donnees' => $donnees,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin
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
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $qb = $demandeRepository->createQueryBuilder('d');
        if ($dateDebut && $dateFin) {
            $qb->where('d.createdAt BETWEEN :start AND :end')
               ->setParameter('start', new \DateTime($dateDebut . ' 00:00:00'))
               ->setParameter('end', new \DateTime($dateFin . ' 23:59:59'));
        }
        $donnees = $qb->orderBy('d.createdAt', 'DESC')->getQuery()->getResult();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Code');
        $sheet->setCellValue('B1', 'Département');
        $sheet->setCellValue('C1', 'Localisation');
        $sheet->setCellValue('D1', 'Matériel');
        $sheet->setCellValue('E1', 'Quantité');
        $sheet->setCellValue('F1', 'Statut');

        $ligne = 2;
        foreach ($donnees as $d) {
            $sheet->setCellValue('A' . $ligne, '#' . $d->getId());
            $sheet->setCellValue('B' . $ligne, $d->getDepartement());
            $sheet->setCellValue('C' . $ligne, strtoupper($d->getLocalisation()));
            $sheet->setCellValue('D' . $ligne, $d->getMateriel());
            $sheet->setCellValue('E' . $ligne, $d->getQuantite());
            $sheet->setCellValue('F' . $ligne, $d->getStatut());
            $ligne++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFile);

        return $this->file($tempFile, 'rapport_imprimerie_'.$dateDebut.'.xlsx');
    }
}