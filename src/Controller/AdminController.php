<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Entity\Machine;
use App\Entity\SubCategory;
use App\Entity\Service;

use App\Form\UserType;
use App\Form\CategoryType;
use App\Form\MachineType;
use App\Form\SubCategoryType;
use App\Form\ServiceType;

use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use App\Repository\CategoryRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Repository\SubCategoryRepository;
use App\Repository\HistoriqueReapprovisionnementRepository;
use App\Repository\VisiteRepository;
use App\Repository\DevisRepository;
use App\Repository\MouvementCaisseRepository;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    /**
     * ✅ SÉCURITÉ — Parse et valide un entier issu d'une requête HTTP.
     *
     * Empêche les injections de valeurs non numériques, négatives ou
     * hors plage dans les identifiants et les quantités.
     *
     * @param mixed $raw Valeur brute issue de $request->request->get()
     * @param int   $min Borne minimale autorisée (incluse)
     * @param int   $max Borne maximale autorisée (incluse)
     * @return int|null L'entier validé, ou null si invalide
     */
    private function parseIntValide($raw, int $min = 1, int $max = 2147483647): ?int
    {
        if ($raw === null) {
            return null;
        }
        $raw = trim((string) $raw);
        if ($raw === '' || !preg_match('/^\d+$/', $raw)) {
            return null;
        }
        // Rejette les dépassements avant cast (évite le wrap-around de (int))
        if (strlen($raw) > 10) {
            return null;
        }
        $val = (int) $raw;
        if ($val < $min || $val > $max) {
            return null;
        }
        return $val;
    }

    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(
        Request $request,
        DemandeRepository $demandeRepository,
        BonSortieRepository $bonSortieRepository,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        HistoriqueReapprovisionnementRepository $historiqueRepo,
        VisiteRepository $visiteRepo,
        DevisRepository $devisRepo,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // ═══════════════════════════════════════════════════════
        // FORMULAIRE : Créer un Service
        // ═══════════════════════════════════════════════════════
        $service = new Service();
        $formService = $this->createForm(ServiceType::class, $service);
        $formService->handleRequest($request);

        if ($formService->isSubmitted() && $formService->isValid()) {
            $em->persist($service);
            $em->flush();
            $this->addFlash('success', 'Le service a été créé avec succès.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ═══════════════════════════════════════════════════════
        // FILTRES DE DATE
        // ═══════════════════════════════════════════════════════
        $dateDebutParam = $request->query->get('date_debut');
        $dateFinParam   = $request->query->get('date_fin');

        if (!$dateDebutParam) {
            $dateDebutParam = (new \DateTime('first day of this month'))->format('Y-m-d');
        }
        if (!$dateFinParam) {
            $dateFinParam = (new \DateTime('today'))->format('Y-m-d');
        }

        // ═══════════════════════════════════════════════════════
        // DEMANDES avec filtre de date
        // ═══════════════════════════════════════════════════════
        $qbDemandes = $demandeRepository->createQueryBuilder('d');

        if ($dateDebutParam && $dateFinParam) {
            $qbDemandes->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
                       ->setParameter('dateDebut', new \DateTime($dateDebutParam . ' 00:00:00'))
                       ->setParameter('dateFin',   new \DateTime($dateFinParam   . ' 23:59:59'));
        }

        $allDemandes = $qbDemandes->orderBy('d.id', 'DESC')
                                  ->getQuery()
                                  ->getResult();

        $allBons  = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();

        $listeComptables = [];
        foreach ($allUsers as $u) {
            $roles = $u->getRoles();
            if (in_array('ROLE_COMPTA', $roles) && !in_array('ROLE_CHEF_COMPTA', $roles)) {
                $listeComptables[] = $u;
            }
        }

        // ═══════════════════════════════════════════════════════
        // RAPPORT GLOBAL
        // ═══════════════════════════════════════════════════════
        $qbGlobal = $demandeRepository->createQueryBuilder('d');

        if ($dateDebutParam && $dateFinParam) {
            $qbGlobal->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
                     ->setParameter('dateDebut', new \DateTime($dateDebutParam . ' 00:00:00'))
                     ->setParameter('dateFin',   new \DateTime($dateFinParam   . ' 23:59:59'));
        }

        $rapportGlobal = $qbGlobal->orderBy('d.createdAt', 'DESC')
                                  ->getQuery()
                                  ->getResult();

        // ═══════════════════════════════════════════════════════
        // FORMULAIRE : Créer un utilisateur
        // ═══════════════════════════════════════════════════════
        $newUser = new User();
        $form = $this->createForm(UserType::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $roleChoisi = $form->has('roles') ? $form->get('roles')->getData() : null;
            if (!empty($roleChoisi)) {
                $newUser->setRoles([$roleChoisi]);
            }

            // ✅ SÉCURITÉ : Plus AUCUN mot de passe en dur.
            // Si l'admin ne saisit pas de mot de passe, on en génère un aléatoire et fort.
            $plainPassword = $form->has('password') ? $form->get('password')->getData() : null;
            
            if (empty($plainPassword)) {
                $passwordReel = $this->genererMotDePasseFort(14);
            } else {
                $passwordReel = $plainPassword;
            }

            $newUser->setPassword($hasher->hashPassword($newUser, $passwordReel));

            if (method_exists($newUser, 'setActif')) {
                $newUser->setActif(true);
            } elseif (method_exists($newUser, 'setIsActif')) {
                $newUser->setIsActif(true);
            }

            $em->persist($newUser);
            $em->flush();

            $roleLabel = $roleChoisi ?: 'ROLE_USER';
            $this->addFlash('success', sprintf(
                '✅ Compte créé pour %s — rôle : %s — mot de passe : %s (Notez-le, il ne sera plus affiché)',
                $newUser->getEmail(),
                $roleLabel,
                $passwordReel
            ));

            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-agents']);
        }

        // ═══════════════════════════════════════════════════════
        // FORMULAIRE : Catégorie
        // ═══════════════════════════════════════════════════════
        $category = new Category();
        $formCategory = $this->createForm(CategoryType::class, $category);
        $formCategory->handleRequest($request);

        if ($formCategory->isSubmitted() && $formCategory->isValid()) {
            $em->persist($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ═══════════════════════════════════════════════════════
        // FORMULAIRE : Sous-catégorie
        // ═══════════════════════════════════════════════════════
        $subCategory = new SubCategory();
        $formSubCategory = $this->createForm(SubCategoryType::class, $subCategory);
        $formSubCategory->handleRequest($request);

        if ($formSubCategory->isSubmitted() && $formSubCategory->isValid()) {
            $em->persist($subCategory);
            $em->flush();
            $this->addFlash('success', 'Sous-catégorie ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ═══════════════════════════════════════════════════════
        // FORMULAIRE : Machine
        // ═══════════════════════════════════════════════════════
        $machine = new Machine();
        $formMachine = $this->createForm(MachineType::class, $machine);
        $formMachine->handleRequest($request);

        if ($formMachine->isSubmitted() && $formMachine->isValid()) {
            $em->persist($machine);
            $em->flush();
            $this->addFlash('success', 'Machine ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // ═══════════════════════════════════════════════════════════════
        // ✅ SYNTHÈSE STOCK — MÊME LOGIQUE QUE LA DG
        // ═══════════════════════════════════════════════════════════════
        $articlesStock = $em->getRepository(Article::class)->findAll();

        $totalQuantiteStock   = 0;
        $articlesParCategorie = [];
        $topArticles          = [];

        foreach ($articlesStock as $art) {
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

            $machineNom = '';
            if (method_exists($art, 'getMachine') && $art->getMachine()) {
                $machineNom = $art->getMachine()->getNom();
            }

            $seuil = method_exists($art, 'getSeuilAlerte') ? ($art->getSeuilAlerte() ?? 0) : 0;

            $attributsArticle = [];
            $attrLabels       = [];
            if (method_exists($art, 'getAttributs')) {
                foreach ($art->getAttributs() as $attr) {
                    $details = [];
                    if ($attr->getCouleur())    $details[] = 'Couleur: ' . $attr->getCouleur();
                    if ($attr->getGrammage())   $details[] = 'Grammage: ' . $attr->getGrammage();
                    if ($attr->getDimension())  $details[] = 'Dimension: ' . $attr->getDimension();
                    if ($attr->getLongueur())   $details[] = 'Longueur: ' . $attr->getLongueur();
                    if ($attr->getKilogramme()) $details[] = 'Kg: ' . $attr->getKilogramme();

                    $labelAttr = implode(' - ', $details) ?: 'Standard';
                    $attrLabels[] = $labelAttr;

                    $attributsArticle[] = [
                        'label'    => $labelAttr,
                        'quantite' => method_exists($attr, 'getQuantite') ? ($attr->getQuantite() ?? 0) : 0,
                    ];
                }
            }
            $attrStr = implode(', ', $attrLabels);

            $labelUnique = $art->getNom();
            if ($machineNom) $labelUnique .= ' [' . $machineNom . ']';
            if ($attrStr)    $labelUnique .= ' - ' . $attrStr;

            $topArticles[] = [
                'nom'       => $art->getNom(),
                'label'     => $labelUnique,
                'machine'   => $machineNom,
                'quantite'  => $qte,
                'seuil'     => $seuil,
                'categorie' => $catNom,
                'attributs' => $attributsArticle,
            ];
        }

        usort($topArticles, fn($a, $b) => $b['quantite'] <=> $a['quantite']);
        $topArticlesTop10 = array_slice($topArticles, 0, 10);
        $topArticlesTotal = count($articlesStock);

        $categoriesLabels    = [];
        $categoriesQuantites = [];
        foreach ($articlesParCategorie as $cat => $data) {
            $categoriesLabels[]    = $cat;
            $categoriesQuantites[] = $data['quantite'];
        }

        // ═══════════════════════════════════════════════════════
        // ✅ ALERTES STOCK ENRICHIES (qui a lancé le réappro)
        // ═══════════════════════════════════════════════════════
        $articlesEnAlerte = array_filter($articlesStock, function ($art) {
            if (!method_exists($art, 'getSeuilAlerte') || !method_exists($art, 'getQuantite')) {
                return false;
            }
            return $art->getQuantite() <= $art->getSeuilAlerte();
        });

        usort($articlesEnAlerte, function ($a, $b) {
            $aLance = method_exists($a, 'isReapprovisionnementEnCours') && $a->isReapprovisionnementEnCours();
            $bLance = method_exists($b, 'isReapprovisionnementEnCours') && $b->isReapprovisionnementEnCours();
            if ($aLance !== $bLance) return $aLance ? 1 : -1;
            return $a->getQuantite() <=> $b->getQuantite();
        });

        $alerteDetails = [];
        foreach ($articlesEnAlerte as $art) {
            $detail = ['lanceur' => null, 'dateLancement' => null];
            if (method_exists($art, 'isReapprovisionnementEnCours') && $art->isReapprovisionnementEnCours()) {
                $dernier = $historiqueRepo->findDernierLancement($art->getId());
                if ($dernier) {
                    $detail['lanceur']       = $dernier->getEffectuePar();
                    $detail['dateLancement'] = $dernier->getDateAction();
                }
            }
            $alerteDetails[$art->getId()] = $detail;
        }
        $nbAlertesStock = count($articlesEnAlerte);

        // ═══════════════════════════════════════════════════════
        // ✅ VUE 360° — VISITES & RDV
        // ═══════════════════════════════════════════════════════
        $toutesVisites     = $visiteRepo->findBy([], ['dateArrivee' => 'DESC']);
        $visitesReservees  = $visiteRepo->findBy(['statut' => 'RESERVE'],     ['dateArrivee' => 'DESC']);
        $visitesAccueil    = $visiteRepo->findBy(['statut' => 'A_L_ACCUEIL'], ['dateArrivee' => 'DESC']);
        $visitesTerminees  = $visiteRepo->findBy(['statut' => 'TERMINE'],     ['dateArrivee' => 'DESC']);
        $visitesDeleguees  = $visiteRepo->findBy(['statut' => 'DELEGUE'],     ['dateArrivee' => 'DESC']);
        $nbVisitesTotal    = count($toutesVisites);
        $nbVisitesJour     = 0;
        $aujourdhui        = new \DateTime('today');
        foreach ($toutesVisites as $v) {
            if ($v->getDateArrivee() && $v->getDateArrivee()->format('Y-m-d') === $aujourdhui->format('Y-m-d')) {
                $nbVisitesJour++;
            }
        }

        // ═══════════════════════════════════════════════════════
        // ✅ VUE 360° — DEVIS & FINANCES
        // ═══════════════════════════════════════════════════════
        $tousDevis = $devisRepo->createQueryBuilder('d')
            ->orderBy('d.id', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult();

        // ═══════════════════════════════════════════════════════════
        // ✅ Devis DÉCAISSÉS dont le délai de travaux est dépassé
        //    - Uniquement statut = DECAISSE (argent sorti)
        //    - Exclut ceux marqués [TERMINE:...] dans la description
        //    - Extrait le délai initial depuis [DELAI:XX]
        // ═══════════════════════════════════════════════════════════
        $devisEnRetard    = [];
        $aujourdhuiRetard = new \DateTime('today');

        foreach ($tousDevis as $d) {
            if ($d->getStatut() !== 'DECAISSE') continue;
            if ($d->getDateLimite() === null) continue;

            // Exclusion si marqué terminé
            if (preg_match('/\[TERMINE:/', (string) $d->getDescription())) continue;

            $dateLimit = clone $d->getDateLimite();
            $dateLimit->setTime(0, 0, 0);
            if ($dateLimit >= $aujourdhuiRetard) continue;

            $joursRetard = (int) $aujourdhuiRetard->diff($dateLimit)->days;

            $delaiInitial = null;
            if (preg_match('/\[DELAI:(\d+)\]/', (string) $d->getDescription(), $m)) {
                $delaiInitial = (int) $m[1];
            }

            $devisEnRetard[] = [
                'devis'        => $d,
                'joursRetard'  => $joursRetard,
                'delaiInitial' => $delaiInitial,
            ];
        }
        usort($devisEnRetard, fn($a, $b) => $b['joursRetard'] <=> $a['joursRetard']);
        $nbDevisEnRetard = count($devisEnRetard);

        $nbDevisTotal     = count($tousDevis);
        $nbDevisEnCours   = 0;
        $nbDevisDecaisses = 0;
        $nbDevisRejetes   = 0;
        $totalDevisUSD    = 0;
        $totalDevisCDF    = 0;

        foreach ($tousDevis as $d) {
            $statut  = $d->getStatut();
            $montant = (float) $d->getMontant();

            if ($statut === 'DECAISSE') {
                $nbDevisDecaisses++;
                if ($d->getDevise() === 'CDF') $totalDevisCDF += $montant;
                else                           $totalDevisUSD += $montant;
            } elseif ($statut === 'REJETE') {
                $nbDevisRejetes++;
            } else {
                $nbDevisEnCours++;
            }
        }

        // ═══════════════════════════════════════════════════════
        // ✅ VUE 360° — MOUVEMENTS DE CAISSE
        // ═══════════════════════════════════════════════════════
        $mouvementsCaisse  = $mouvementRepo->findAllOrdered();
        $soldeCaisseUSD    = $mouvementRepo->getSolde('USD');
        $soldeCaisseCDF    = $mouvementRepo->getSolde('CDF');

        $totalEntreesUSD = 0;
        $totalEntreesCDF = 0;
        $totalSortiesUSD = 0;
        $totalSortiesCDF = 0;

        foreach ($mouvementsCaisse as $m) {
            $mt = (float) $m->getMontant();
            if ($m->getType() === 'ENTREE') {
                if ($m->getDevise() === 'CDF') $totalEntreesCDF += $mt;
                else                           $totalEntreesUSD += $mt;
            } else {
                if ($m->getDevise() === 'CDF') $totalSortiesCDF += $mt;
                else                           $totalSortiesUSD += $mt;
            }
        }

        // ═══════════════════════════════════════════════════════
        // ✅ VUE 360° — SOLDES SÉPARÉS
        // ═══════════════════════════════════════════════════════
        $soldeGlobalUSD   = $mouvementRepo->getSolde('USD');
        $soldeGlobalCDF   = $mouvementRepo->getSolde('CDF');
        $soldeComptaUSD   = $mouvementRepo->getSoldeComptabilite('USD');
        $soldeComptaCDF   = $mouvementRepo->getSoldeComptabilite('CDF');
        $soldeCaissierUSD = $mouvementRepo->getSoldeCaisse('USD');
        $soldeCaissierCDF = $mouvementRepo->getSoldeCaisse('CDF');

        $nbMvtCompta   = 0;
        $nbMvtCaissier = 0;

        foreach ($mouvementsCaisse as $m) {
            $user = method_exists($m, 'getEffectuePar') ? $m->getEffectuePar() : null;
            if (!$user) continue;

            $roles = $user->getRoles();
            if (in_array('ROLE_COMPTA', $roles) || in_array('ROLE_CHEF_COMPTA', $roles)) $nbMvtCompta++;
            if (in_array('ROLE_CAISSIER', $roles)) $nbMvtCaissier++;
        }

        // ═══════════════════════════════════════════════════════
        // ✅ VUE 360° — DEVIS EN ATTENTE
        // ═══════════════════════════════════════════════════════
        $devisAiguiller       = [];
        $devisPretsADecaisser = [];
        $devisEnAttenteCaisse = [];

        foreach ($tousDevis as $d) {
            $statut = $d->getStatut();
            if ($statut === 'ATTENTE_COMPTA')       $devisAiguiller[]       = $d;
            if ($statut === 'ATTENTE_DECAISSEMENT') $devisPretsADecaisser[] = $d;
            if ($statut === 'ATTENTE_CAISSE')       $devisEnAttenteCaisse[] = $d;
        }

        $historiqueReappro = $historiqueRepo->findAllOrdered();

        // ═══════════════════════════════════════════════════════
        // RENDER
        // ═══════════════════════════════════════════════════════
        return $this->render('admin/index.html.twig', [
            'demandes' => $allDemandes,
            'bons' => $allBons,
            'form' => $form->createView(),
            'formService' => $formService->createView(),
            'services' => $serviceRepository->findAll(),
            'users' => $allUsers,
            'tousLesUtilisateurs' => $allUsers,
            'listeComptables' => $listeComptables,
            'articles' => $articlesStock,
            'rapportGlobal' => $rapportGlobal,
            'date_debut' => $dateDebutParam,
            'date_fin' => $dateFinParam,
            'formCategory' => $formCategory->createView(),
            'categories' => $em->getRepository(Category::class)->findAll(),
            'formSubCategory' => $formSubCategory->createView(),
            'subCategories' => $em->getRepository(SubCategory::class)->findAll(),
            'formMachine' => $formMachine->createView(),
            'machines' => $em->getRepository(Machine::class)->findAll(),

            'articlesStock'        => $articlesStock,
            'totalQuantiteStock'   => $totalQuantiteStock,
            'articlesParCategorie' => $articlesParCategorie,
            'topArticles'          => $topArticlesTop10,
            'topArticlesTotal'     => $topArticlesTotal,
            'categoriesLabels'     => $categoriesLabels,
            'categoriesQuantites'  => $categoriesQuantites,
            'articlesEnAlerte'     => $articlesEnAlerte,
            'alerteDetails'        => $alerteDetails,
            'nbAlertesStock'       => $nbAlertesStock,
            'topArticlesAll'       => $topArticles,

            'toutesVisites'     => $toutesVisites,
            'visitesReservees'  => $visitesReservees,
            'visitesAccueil'    => $visitesAccueil,
            'visitesTerminees'  => $visitesTerminees,
            'visitesDeleguees'  => $visitesDeleguees,
            'nbVisitesTotal'    => $nbVisitesTotal,
            'nbVisitesJour'     => $nbVisitesJour,

            'tousDevis'         => $tousDevis,
            'nbDevisTotal'      => $nbDevisTotal,
            'nbDevisEnCours'    => $nbDevisEnCours,
            'nbDevisDecaisses'  => $nbDevisDecaisses,
            'nbDevisRejetes'    => $nbDevisRejetes,
            'totalDevisUSD'     => $totalDevisUSD,
            'totalDevisCDF'     => $totalDevisCDF,

            'mouvementsCaisse'  => $mouvementsCaisse,
            'soldeCaisseUSD'    => $soldeCaisseUSD,
            'soldeCaisseCDF'    => $soldeCaisseCDF,
            'totalEntreesUSD'   => $totalEntreesUSD,
            'totalEntreesCDF'   => $totalEntreesCDF,
            'totalSortiesUSD'   => $totalSortiesUSD,
            'totalSortiesCDF'   => $totalSortiesCDF,

            'historiqueReappro' => $historiqueReappro,

            'soldeGlobalUSD'      => $soldeGlobalUSD,
            'soldeGlobalCDF'      => $soldeGlobalCDF,
            'soldeComptaUSD'      => $soldeComptaUSD,
            'soldeComptaCDF'      => $soldeComptaCDF,
            'soldeCaissierUSD'    => $soldeCaissierUSD,
            'soldeCaissierCDF'    => $soldeCaissierCDF,
            'nbMvtCompta'         => $nbMvtCompta,
            'nbMvtCaissier'       => $nbMvtCaissier,

            'devisAiguiller'       => $devisAiguiller,
            'devisPretsADecaisser' => $devisPretsADecaisser,
            'devisEnAttenteCaisse' => $devisEnAttenteCaisse,

            // ✅ NOUVEAU : devis en retard (décaissés dont délai dépassé)
            'devisEnRetard'        => $devisEnRetard,
            'nbDevisEnRetard'      => $nbDevisEnRetard,
        ]);
    }

    private function genererMotDePasseFort(int $longueur = 12): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%&*';
        $pwd = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $longueur; $i++) {
            $pwd .= $chars[random_int(0, $max)];
        }
        return $pwd;
    }

    // ═══════════════════════════════════════════════════════════
    // NOMINATIONS
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/assigner-chef-compta', name: 'app_admin_assigner_chef_compta', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerChefCompta(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        // ✅ SÉCURITÉ : validation stricte de user_id
        $userId = $this->parseIntValide($request->request->get('user_id'), 1);
        $user   = $userId !== null ? $userRepository->find($userId) : null;

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable ou identifiant invalide.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_CHEF_COMPTA', $roles)) {
            $roles[] = 'ROLE_CHEF_COMPTA';
            $user->setRoles(array_values(array_unique($roles)));
            $em->flush();
            $this->addFlash('success', sprintf('L\'utilisateur %s a bien été promu Chef Comptable.', $user->getEmail()));
        } else {
            $this->addFlash('info', 'Cet utilisateur possède déjà ce rôle.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/destituer-chef-compta/{id}', name: 'app_admin_destituer_chef_compta', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function destituerChefCompta(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);

        if ($user) {
            $roles = array_diff($user->getRoles(), ['ROLE_CHEF_COMPTA']);
            $user->setRoles(array_values($roles));
            $em->flush();
            $this->addFlash('success', sprintf('Le rôle de Chef Comptable a été retiré à %s.', $user->getEmail()));
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/user/{id}/toggle', name: 'app_admin_user_toggle', methods: ['POST'])]
    public function toggleUserStatus(User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($user === $this->getUser()) {
            $this->addFlash('danger', '🛑 Action impossible : Vous ne pouvez pas désactiver votre propre compte.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if (method_exists($user, 'isActif') && method_exists($user, 'setActif')) {
            $user->setActif(!$user->isActif());
            $em->flush();
            $statut = $user->isActif() ? 'réactivé ✅' : 'désactivé ❌';
            $this->addFlash('success', 'Le compte de ' . $user->getEmail() . ' a été ' . $statut);
        } else {
            $this->addFlash('danger', 'Le champ "actif" n\'existe pas sur l\'entité User.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/user/{id}/modifier', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ServiceRepository $serviceRepository,
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $emailSaisi   = $user->getEmail();
            $userExistant = $userRepository->findOneBy(['email' => $emailSaisi]);

            if ($userExistant && $userExistant->getId() !== $user->getId()) {
                $this->addFlash('danger', '❌ Cet email est déjà utilisé par un autre compte.');
                return $this->render('admin/edit_user.html.twig', [
                    'user'     => $user,
                    'services' => $serviceRepository->findAll(),
                    'form'     => $form->createView(),
                ]);
            }

            if ($form->has('roles')) {
                $roleChoisi = $form->get('roles')->getData();
                if (!empty($roleChoisi)) {
                    $user->setRoles([$roleChoisi]);
                }
            }

            if ($form->has('password')) {
                $plainPassword = $form->get('password')->getData();
                if (!empty($plainPassword)) {
                    $user->setPassword($hasher->hashPassword($user, $plainPassword));
                    $this->addFlash('success', '🔑 Mot de passe mis à jour.');
                }
            }

            $em->flush();
            $this->addFlash('success', '📝 Le compte a été mis à jour.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/edit_user.html.twig', [
            'user'     => $user,
            'services' => $serviceRepository->findAll(),
            'form'     => $form->createView(),
        ]);
    }

    #[Route('/admin/user/{id}/supprimer', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($user === $this->getUser()) {
            $this->addFlash('danger', '🛑 Action refusée : Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', '❌ Token CSRF invalide — suppression annulée.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $em->remove($user);
        $em->flush();
        $this->addFlash('success', '🗑️ Le compte a été supprimé.');

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/assigner-caissier', name: 'app_admin_assigner_caissier', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerCaissier(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        // ✅ SÉCURITÉ : validation stricte de user_id
        $userId = $this->parseIntValide($request->request->get('user_id'), 1);
        $user   = $userId !== null ? $userRepository->find($userId) : null;

        if ($user && !in_array('ROLE_CAISSIER', $user->getRoles())) {
            $roles   = $user->getRoles();
            $roles[] = 'ROLE_CAISSIER';
            $user->setRoles(array_values(array_unique($roles)));
            $em->flush();
            $this->addFlash('success', 'Utilisateur promu Caissier.');
        }
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/destituer-caissier/{id}', name: 'app_admin_destituer_caissier', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function destituerCaissier(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);
        if ($user) {
            $roles = array_diff($user->getRoles(), ['ROLE_CAISSIER']);
            $user->setRoles(array_values($roles));
            $em->flush();
            $this->addFlash('success', 'Rôle Caissier retiré.');
        }
        return $this->redirectToRoute('app_admin_dashboard');
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : CATÉGORIES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/category/{id}/edit', name: 'app_admin_category_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editCategory(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('edit_category_' . $category->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $category->setNom($nom);
        $em->flush();
        $this->addFlash('success', '📝 Catégorie « ' . $nom . ' » mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    #[Route('/admin/category/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_category_' . $category->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        if ($category->getSubCategories()->count() > 0) {
            $this->addFlash('danger', '🛑 Impossible de supprimer « ' . $category->getNom() . ' » : elle contient ' . $category->getSubCategories()->count() . ' sous-catégorie(s).');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = $category->getNom();
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', '🗑️ Catégorie « ' . $nom . ' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : SOUS-CATÉGORIES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/sub-category/{id}/edit', name: 'app_admin_sub_category_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editSubCategory(Request $request, SubCategory $subCategory, EntityManagerInterface $em, CategoryRepository $categoryRepo): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('edit_sub_category_' . $subCategory->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        // ✅ SÉCURITÉ : validation stricte de category_id
        $categoryId = $this->parseIntValide($request->request->get('category_id'), 1);
        if ($categoryId === null) {
            $this->addFlash('danger', '❌ Identifiant de catégorie invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $subCategory->setNom($nom);
        $category = $categoryRepo->find($categoryId);
        if ($category) {
            $subCategory->setCategory($category);
        } else {
            $this->addFlash('danger', '❌ Catégorie parente introuvable.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $em->flush();
        $this->addFlash('success', '📝 Sous-catégorie mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    #[Route('/admin/sub-category/{id}/delete', name: 'app_admin_sub_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSubCategory(Request $request, SubCategory $subCategory, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_sub_category_' . $subCategory->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = $subCategory->getNom();
        $em->remove($subCategory);
        $em->flush();
        $this->addFlash('success', '🗑️ Sous-catégorie « ' . $nom . ' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : MACHINES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/machine/{id}/edit', name: 'app_admin_machine_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editMachine(Request $request, Machine $machine, EntityManagerInterface $em, SubCategoryRepository $subCatRepo): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('edit_machine_' . $machine->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        // ✅ SÉCURITÉ : validation stricte de sub_category_id
        $subCatId = $this->parseIntValide($request->request->get('sub_category_id'), 1);
        if ($subCatId === null) {
            $this->addFlash('danger', '❌ Identifiant de sous-catégorie invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $machine->setNom($nom);
        $subCat = $subCatRepo->find($subCatId);
        if ($subCat) {
            $machine->setSubCategory($subCat);
        } else {
            $this->addFlash('danger', '❌ Sous-catégorie introuvable.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $em->flush();
        $this->addFlash('success', '📝 Machine mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
    }

    #[Route('/admin/machine/{id}/delete', name: 'app_admin_machine_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteMachine(Request $request, Machine $machine, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_machine_' . $machine->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $nom = $machine->getNom();
        $em->remove($machine);
        $em->flush();
        $this->addFlash('success', '🗑️ Machine « ' . $nom . ' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : ARTICLES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/article/{id}/edit', name: 'app_admin_article_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editArticle(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('edit_article_' . $article->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $nom = trim((string) $request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        // ✅ SÉCURITÉ : validation stricte de quantite et seuil_alerte
        $quantite = $this->parseIntValide($request->request->get('quantite'), 0, 1000000);
        $seuil    = $this->parseIntValide($request->request->get('seuil_alerte'), 0, 1000000);

        if ($quantite === null || $seuil === null) {
            $this->addFlash('danger', '❌ Quantité ou seuil invalide (entiers entre 0 et 1 000 000 attendus).');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $article->setNom($nom);
        $article->setQuantite($quantite);
        $article->setSeuilAlerte($seuil);

        $em->flush();
        $this->addFlash('success', '📝 Article « ' . $nom . ' » mis à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
    }

    #[Route('/admin/article/{id}/delete', name: 'app_admin_article_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteArticle(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_article_' . $article->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $nom = $article->getNom();
        $em->remove($article);
        $em->flush();
        $this->addFlash('success', '🗑️ Article « ' . $nom . ' » supprimé.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ NOMMER UN PDG (un seul à la fois)
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/assigner-pdg', name: 'app_admin_assigner_pdg', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerPDG(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        // ✅ SÉCURITÉ : validation stricte de user_id
        $userId = $this->parseIntValide($request->request->get('user_id'), 1);
        $user   = $userId !== null ? $userRepository->find($userId) : null;

        if (!$user) {
            $this->addFlash('danger', '❌ Utilisateur introuvable ou identifiant invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
        }

        foreach ($userRepository->findBy(['isPDG' => true]) as $ancien) {
            $ancien->setIsPDG(false);
        }

        $user->setIsPDG(true);

        $roles = $user->getRoles();
        if (!in_array('ROLE_DG', $roles)) {
            $roles[] = 'ROLE_DG';
            $user->setRoles(array_values(array_unique($roles)));
        }

        $em->flush();

        $this->addFlash('success', sprintf(
            '👑 %s a été nommé PDG. La vue exclusive lui est maintenant accessible.',
            $user->getEmail()
        ));

        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ DESTITUER LE PDG
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/destituer-pdg/{id}', name: 'app_admin_destituer_pdg', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function destituerPDG(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);

        if ($user && $user->isPDG()) {
            $user->setIsPDG(false);
            $em->flush();
            $this->addFlash('success', sprintf(
                'Le statut PDG a été retiré à %s.',
                $user->getEmail()
            ));
        }

        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ NOMMER UN ADJOINT CHEF COMPTABLE (un seul à la fois)
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/assigner-adjoint-chef-compta', name: 'app_admin_assigner_adjoint_chef_compta', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerAdjointChefCompta(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        // ✅ SÉCURITÉ : validation stricte de user_id
        $userId = $this->parseIntValide($request->request->get('user_id'), 1);
        $user   = $userId !== null ? $userRepository->find($userId) : null;

        if (!$user) {
            $this->addFlash('danger', '❌ Utilisateur introuvable ou identifiant invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
        }

        foreach ($userRepository->findAll() as $u) {
            if (in_array('ROLE_ADJOINT_CHEF_COMPTA', $u->getRoles())) {
                $roles = array_diff($u->getRoles(), ['ROLE_ADJOINT_CHEF_COMPTA']);
                $u->setRoles(array_values($roles));
            }
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_ADJOINT_CHEF_COMPTA', $roles)) {
            $roles[] = 'ROLE_ADJOINT_CHEF_COMPTA';
        }
        $roles = array_diff($roles, ['ROLE_CHEF_COMPTA']);
        $user->setRoles(array_values(array_unique($roles)));

        $em->flush();

        $this->addFlash('success', sprintf(
            '👔 %s a été nommé Adjoint Chef Comptable.',
            $user->getEmail()
        ));

        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
    }

    // ═══════════════════════════════════════════════════════════
    // ✅ DESTITUER L'ADJOINT CHEF COMPTABLE
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/destituer-adjoint-chef-compta/{id}', name: 'app_admin_destituer_adjoint_chef_compta', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function destituerAdjointChefCompta(int $id, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($id);

        if ($user) {
            $roles = array_diff($user->getRoles(), ['ROLE_ADJOINT_CHEF_COMPTA']);
            $user->setRoles(array_values($roles));
            $em->flush();
            $this->addFlash('success', sprintf(
                'Le rôle Adjoint Chef Comptable a été retiré à %s.',
                $user->getEmail()
            ));
        }

        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-nominations']);
    }
}