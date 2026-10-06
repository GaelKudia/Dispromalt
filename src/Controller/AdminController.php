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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(
        Request $request,
        DemandeRepository $demandeRepository,
        BonSortieRepository $bonSortieRepository,
        UserRepository $userRepository,
        ServiceRepository $serviceRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        HistoriqueReapprovisionnementRepository $historiqueRepo
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
        // DONNÉES GÉNÉRALES
        // ═══════════════════════════════════════════════════════
        // ═══════════════════════════════════════════════════════
// DEMANDES avec filtre de date
// ═══════════════════════════════════════════════════════
$dateDebutParam = $request->query->get('date_debut');
$dateFinParam = $request->query->get('date_fin');

$qbDemandes = $demandeRepository->createQueryBuilder('d');

if ($dateDebutParam && $dateFinParam) {
    $qbDemandes->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
               ->setParameter('dateDebut', new \DateTime($dateDebutParam . ' 00:00:00'))
               ->setParameter('dateFin', new \DateTime($dateFinParam . ' 23:59:59'));
}

$allDemandes = $qbDemandes->orderBy('d.id', 'DESC')
                          ->getQuery()
                          ->getResult();
        $allBons = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();
        $articles = $em->getRepository(Article::class)->findAll();

        $listeComptables = [];
        foreach ($allUsers as $u) {
            if (in_array('ROLE_COMPTA', $u->getRoles()) && !in_array('ROLE_CHEF_COMPTA', $u->getRoles())) {
                $listeComptables[] = $u;
            }
        }

        // ═══════════════════════════════════════════════════════
        // RAPPORT GLOBAL (filtres dates)
        // ═══════════════════════════════════════════════════════
        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui = new \DateTime('today 23:59:59');

                        $dateDebutParam = $request->query->get('date_debut');
            $dateFinParam = $request->query->get('date_fin');

            // Valeurs par défaut si non fournies (affiche tout le mois en cours par exemple)
            if (!$dateDebutParam) {
                $dateDebutParam = (new \DateTime('first day of this month'))->format('Y-m-d');
            }
            if (!$dateFinParam) {
                $dateFinParam = (new \DateTime('today'))->format('Y-m-d');
            }

        $qbGlobal = $demandeRepository->createQueryBuilder('d');

        if ($dateDebutParam && $dateFinParam) {
            $qbGlobal->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
                     ->setParameter('dateDebut', new \DateTime($dateDebutParam . ' 00:00:00'))
                     ->setParameter('dateFin', new \DateTime($dateFinParam . ' 23:59:59'));
        } else {
            $qbGlobal->where('d.createdAt BETWEEN :debutAujourdhui AND :finAujourdhui')
                     ->setParameter('debutAujourdhui', $debutAujourdhui)
                     ->setParameter('finAujourdhui', $finAujourdhui);
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
            $plainPassword = $form->get('password')->getData();

            if (!empty($plainPassword)) {
                $hashedPassword = $hasher->hashPassword($newUser, $plainPassword);
                $newUser->setPassword($hashedPassword);
            } else {
                $hashedPassword = $hasher->hashPassword($newUser, 'Passer123*');
                $newUser->setPassword($hashedPassword);
            }

            if (method_exists($newUser, 'setActif')) {
                $newUser->setActif(true);
            }
            $em->persist($newUser);
            $em->flush();

            $this->addFlash('success', 'Nouvel identifiant créé avec succès !');
            return $this->redirectToRoute('app_admin_dashboard');
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

        // ═══════════════════════════════════════════════════════
        // ✅ SYNTHÈSE STOCK POUR L'ADMIN
        // ═══════════════════════════════════════════════════════
        $articlesStock = $em->getRepository(Article::class)->findAll();

        $totalQuantiteStock = 0;
        $articlesParCategorie = [];
        $topArticles = [];

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

            $topArticles[] = [
                'nom'       => $art->getNom(),
                'quantite'  => $qte,
                'seuil'     => method_exists($art, 'getSeuilAlerte') ? ($art->getSeuilAlerte() ?? 0) : 0,
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

        // ═══════════════════════════════════════════════════════
        // ✅ ALERTES STOCK ENRICHIES (qui a lancé le réappro)
        // ═══════════════════════════════════════════════════════
        $articlesEnAlerte = $em->getRepository(Article::class)->findBy([], ['id' => 'DESC']);
        $articlesEnAlerte = array_filter($articlesEnAlerte, function ($art) {
            if (!method_exists($art, 'getSeuilAlerte') || !method_exists($art, 'getQuantite')) {
                return false;
            }
            return $art->getQuantite() <= $art->getSeuilAlerte();
        });

        // Tri : non lancés d'abord, puis les plus critiques
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
                    $detail['lanceur'] = $dernier->getEffectuePar();
                    $detail['dateLancement'] = $dernier->getDateAction();
                }
            }
            $alerteDetails[$art->getId()] = $detail;
        }
        $nbAlertesStock = count($articlesEnAlerte);

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
            'articles' => $articles,
            'rapportGlobal' => $rapportGlobal,
            'date_debut' => $dateDebutParam,
            'date_fin' => $dateFinParam,
            'formCategory' => $formCategory->createView(),
            'categories' => $em->getRepository(Category::class)->findAll(),
            'formSubCategory' => $formSubCategory->createView(),
            'subCategories' => $em->getRepository(SubCategory::class)->findAll(),
            'formMachine' => $formMachine->createView(),
            'machines' => $em->getRepository(Machine::class)->findAll(),

            // ✅ NOUVELLES VARIABLES
            'articlesStock'        => $articlesStock,
            'totalQuantiteStock'   => $totalQuantiteStock,
            'articlesParCategorie' => $articlesParCategorie,
            'topArticles'          => $topArticles,
            'categoriesLabels'     => $categoriesLabels,
            'categoriesQuantites'  => $categoriesQuantites,
            'articlesEnAlerte'     => $articlesEnAlerte,
            'alerteDetails'        => $alerteDetails,
            'nbAlertesStock'       => $nbAlertesStock,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // NOMINATIONS
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/assigner-chef-compta', name: 'app_admin_assigner_chef_compta', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerChefCompta(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $userId = $request->request->get('user_id');
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('danger', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $roles = $user->getRoles();
        if (!in_array('ROLE_CHEF_COMPTA', $roles)) {
            $roles[] = 'ROLE_CHEF_COMPTA';
            $user->setRoles($roles);
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
            $emailSaisi = $user->getEmail();
            $userExistant = $userRepository->findOneBy(['email' => $emailSaisi]);

            if ($userExistant && $userExistant->getId() !== $user->getId()) {
                $this->addFlash('danger', '❌ Cet email est déjà utilisé par un autre compte.');
                return $this->render('admin/edit_user.html.twig', [
                    'user'     => $user,
                    'services' => $serviceRepository->findAll(),
                    'form'     => $form->createView(),
                ]);
            }

            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
                $this->addFlash('success', '🔑 Mot de passe mis à jour.');
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

        if ($this->isCsrfTokenValid('delete_user_'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', '🗑️ Le compte a été supprimé.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/assigner-caissier', name: 'app_admin_assigner_caissier', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignerCaissier(Request $request, UserRepository $userRepository, EntityManagerInterface $em): Response
    {
        $user = $userRepository->find($request->request->get('user_id'));
        if ($user && !in_array('ROLE_CAISSIER', $user->getRoles())) {
            $roles = $user->getRoles();
            $roles[] = 'ROLE_CAISSIER';
            $user->setRoles($roles);
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
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('edit_category_'.$category->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = trim($request->getPayload()->getString('nom'));
        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $category->setNom($nom);
        $em->flush();
        $this->addFlash('success', '📝 Catégorie « '.$nom.' » mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    #[Route('/admin/category/{id}/delete', name: 'app_admin_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteCategory(Request $request, Category $category, EntityManagerInterface $em): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('delete_category_'.$category->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        // Vérifier les dépendances
        if ($category->getSubCategories()->count() > 0) {
            $this->addFlash('danger', '🛑 Impossible de supprimer « '.$category->getNom().' » : elle contient '.$category->getSubCategories()->count().' sous-catégorie(s).');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = $category->getNom();
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', '🗑️ Catégorie « '.$nom.' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : SOUS-CATÉGORIES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/sub-category/{id}/edit', name: 'app_admin_sub_category_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editSubCategory(Request $request, SubCategory $subCategory, EntityManagerInterface $em, CategoryRepository $categoryRepo): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('edit_sub_category_'.$subCategory->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = trim($request->getPayload()->getString('nom'));
        $categoryId = $request->getPayload()->getInt('category_id');

        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $subCategory->setNom($nom);
        if ($categoryId) {
            $category = $categoryRepo->find($categoryId);
            if ($category) {
                $subCategory->setCategory($category);
            }
        }

        $em->flush();
        $this->addFlash('success', '📝 Sous-catégorie mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    #[Route('/admin/sub-category/{id}/delete', name: 'app_admin_sub_category_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteSubCategory(Request $request, SubCategory $subCategory, EntityManagerInterface $em): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('delete_sub_category_'.$subCategory->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
        }

        $nom = $subCategory->getNom();
        $em->remove($subCategory);
        $em->flush();
        $this->addFlash('success', '🗑️ Sous-catégorie « '.$nom.' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-structure']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : MACHINES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/machine/{id}/edit', name: 'app_admin_machine_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editMachine(Request $request, Machine $machine, EntityManagerInterface $em, SubCategoryRepository $subCatRepo): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('edit_machine_'.$machine->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $nom = trim($request->getPayload()->getString('nom'));
        $subCatId = $request->getPayload()->getInt('sub_category_id');

        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $machine->setNom($nom);
        if ($subCatId) {
            $subCat = $subCatRepo->find($subCatId);
            if ($subCat) {
                $machine->setSubCategory($subCat);
            }
        }

        $em->flush();
        $this->addFlash('success', '📝 Machine mise à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
    }

    #[Route('/admin/machine/{id}/delete', name: 'app_admin_machine_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteMachine(Request $request, Machine $machine, EntityManagerInterface $em): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('delete_machine_'.$machine->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
        }

        $nom = $machine->getNom();
        $em->remove($machine);
        $em->flush();
        $this->addFlash('success', '🗑️ Machine « '.$nom.' » supprimée.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-machines']);
    }

    // ═══════════════════════════════════════════════════════════
    // CRUD : ARTICLES
    // ═══════════════════════════════════════════════════════════
    #[Route('/admin/article/{id}/edit', name: 'app_admin_article_edit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editArticle(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('edit_article_'.$article->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $nom = trim($request->getPayload()->getString('nom'));
        $quantite = $request->getPayload()->getInt('quantite');
        $seuil = $request->getPayload()->getInt('seuil_alerte');

        if ($nom === '') {
            $this->addFlash('danger', '❌ Le nom ne peut pas être vide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $article->setNom($nom);
        $article->setQuantite($quantite);
        $article->setSeuilAlerte($seuil);

        $em->flush();
        $this->addFlash('success', '📝 Article « '.$nom.' » mis à jour.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
    }

    #[Route('/admin/article/{id}/delete', name: 'app_admin_article_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteArticle(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('delete_article_'.$article->getId(), $token)) {
            $this->addFlash('danger', '❌ Token CSRF invalide.');
            return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
        }

        $nom = $article->getNom();
        $em->remove($article);
        $em->flush();
        $this->addFlash('success', '🗑️ Article « '.$nom.' » supprimé.');
        return $this->redirectToRoute('app_admin_dashboard', ['_fragment' => 'pane-stock']);
    }
}