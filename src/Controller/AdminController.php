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
        ServiceRepository $serviceRepository, // 👈 AJOUTÉ ICI pour corriger l'erreur de variable indéfinie
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        // Sécurité globale sur la page d'administration
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Gestion du formulaire de création de Service
        $service = new Service();
        $formService = $this->createForm(ServiceType::class, $service);
        $formService->handleRequest($request);

        if ($formService->isSubmitted() && $formService->isValid()) {
            $em->persist($service); // 👈 Corrigé de $entityManager à $em
            $em->flush();

            $this->addFlash('success', 'Le service a été créé avec succès.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Récupérations des données d'origine pour l'admin
        $allDemandes = $demandeRepository->findBy([], ['id' => 'DESC']);
        $allBons = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();
        $articles = $em->getRepository(Article::class)->findAll();

        // Filtrage spécifique pour la sélection des agents comptables
        $listeComptables = [];
        foreach ($allUsers as $u) {
            if (in_array('ROLE_COMPTA', $u->getRoles()) && !in_array('ROLE_CHEF_COMPTA', $u->getRoles())) {
                $listeComptables[] = $u;
            }
        }

        // Logique du Rapport Général Historique
        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui = new \DateTime('today 23:59:59');

        $dateDebutParam = $request->query->get('date_debut');
        $dateFinParam = $request->query->get('date_fin');

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

        // Gestion du formulaire de création d'utilisateur
        $newUser = new User();
        $form = $this->createForm(UserType::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            //$plainPassword = $newUser->getPassword();

            // Vérifier si un mot de passe a été fourni
            if (!empty($plainPassword)) {
                $hashedPassword = $hasher->hashPassword($newUser, $plainPassword);
                $newUser->setPassword($hashedPassword);
            } else {
                // Optionnel : Définir un mot de passe temporaire par défaut si vide
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

        // Gestion du formulaire de catégorie
        $category = new Category();
        $formCategory = $this->createForm(CategoryType::class, $category);
        $formCategory->handleRequest($request);

        if ($formCategory->isSubmitted() && $formCategory->isValid()) {
            $em->persist($category);
            $em->flush();
            $this->addFlash('success', 'Catégorie ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Gestion du formulaire de SOUS-CATÉGORIE
        $subCategory = new SubCategory();
        $formSubCategory = $this->createForm(SubCategoryType::class, $subCategory);
        $formSubCategory->handleRequest($request);

        if ($formSubCategory->isSubmitted() && $formSubCategory->isValid()) {
            $em->persist($subCategory);
            $em->flush();
            $this->addFlash('success', 'Sous-catégorie ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Gestion du formulaire d'ajout de machine 
        $machine = new Machine();
        $formMachine = $this->createForm(MachineType::class, $machine);
        $formMachine->handleRequest($request);

        if ($formMachine->isSubmitted() && $formMachine->isValid()) {
            $em->persist($machine);
            $em->flush();
            $this->addFlash('success', 'Machine ajoutée !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

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
        ]);
    }

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
            $roles = $user->getRoles();
            $roles = array_diff($roles, ['ROLE_CHEF_COMPTA']);
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
            $this->addFlash('danger', '🛑 Action impossible : Vous ne pouvez pas désactiver votre propre compte administrateur.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if (method_exists($user, 'isActif') && method_exists($user, 'setActif')) {
            $user->setActif(!$user->isActif());
            $em->flush();

            $statut = $user->isActif() ? 'réactivé ✅' : 'désactivé et rendu inactif ❌';
            $this->addFlash('success', 'Le compte de ' . $user->getEmail() . ' a été ' . $statut);
        } else {
            $this->addFlash('danger', 'Le champ "actif" n\'existe pas encore sur votre entité User.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/admin/user/{id}/modifier', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function editUser(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher,ServiceRepository $serviceRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, [
            'is_edit' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1. Gestion du mot de passe
            $plainPassword = $form->get('password')->getData();
            if (!empty($plainPassword)) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            // 2. Gestion de l'attachement au service selon le rôle
            $roles = $user->getRoles();
            
            // Si l'utilisateur a le rôle utilisateur standard (ou n'a pas de rôle admin/comptable exclusif)
            // Tu peux ajuster la condition selon ton, par exemple s'il a 'ROLE_USER'
            if (in_array('ROLE_USER', $roles)) {
                // Le service est déjà lié automatiquement par le formulaire si le champ y est présent.
                // Tu peux ajouter ici une validation spécifique si le service est obligatoire pour un simple utilisateur :
                if (!$user->getService()) {
                    $this->addFlash('warning', '⚠️ Un utilisateur standard devrait idéalement être rattaché à un service.');
                }
            } else {
                // Optionnel : Si l'utilisateur n'est plus un utilisateur standard (ex: admin pur), 
                // tu peux décider de détacher le service si nécessaire :
                // $user->setService(null);
            }

            $em->flush();
            $this->addFlash('success', '📝 Le compte de ' . $user->getEmail() . ' a bien été mis à jour.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/edit_user.html.twig', [
            'user' => $user,
            'services' => $serviceRepository->findAll(),
            'form' => $form->createView()

        ]);
    }   
    #[Route('/admin/user/{id}/supprimer', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, User $user, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($user === $this->getUser()) {
            $this->addFlash('danger', '🛑 Action refusée : Vous ne pouvez pas supprimer votre propre compte en cours d\'utilisation.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($this->isCsrfTokenValid('delete_user_'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', '🗑️ Le compte utilisateur a été supprimé définitivement.');
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
}