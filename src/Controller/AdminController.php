<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Article;
use App\Form\UserType;
use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use App\Repository\UserRepository;
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
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        // Sécurité globale sur la page d'administration
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        
        // Récupérations des données d'origine pour l'admin
        $allDemandes = $demandeRepository->findBy([], ['id' => 'DESC']);
        $allBons = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();
        $articles = $em->getRepository(Article::class)->findAll();

        // Filtrage spécifique pour la sélection des agents comptables (ex: ayant le rôle de base)
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
            $hashedPassword = $hasher->hashPassword($newUser, $newUser->getPassword());
            $newUser->setPassword($hashedPassword);

            if (method_exists($newUser, 'setActif')) {
                $newUser->setActif(true);
            }

            $chosenRoles = $form->get('roles')->getData();
            if (!empty($chosenRoles)) {
                $newUser->setRoles(is_array($chosenRoles) ? $chosenRoles : [$chosenRoles]);
            } else {
                $newUser->setRoles(['ROLE_USER']);
            }

            $em->persist($newUser);
            $em->flush();

            $this->addFlash('success', 'Nouvel identifiant créé avec succès !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/index.html.twig', [
            'demandes' => $allDemandes,
            'bons' => $allBons,
            'users' => $allUsers, // Utilisé pour 'tousLesUtilisateurs' dans ta page
            'tousLesUtilisateurs' => $allUsers, 
            'listeComptables' => $listeComptables,
            'articles' => $articles,
            'rapportGlobal' => $rapportGlobal,
            'date_debut' => $dateDebutParam,
            'date_fin' => $dateFinParam,
            'form' => $form->createView(),
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
    public function editUser(Request $request, User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $email = $request->get('email');
            $role = $request->get('role');
            $plainPassword = $request->get('password');

            if (!empty($email)) {
                $user->setEmail($email);
            }

            if (!empty($role)) {
                $user->setRoles([$role]);
            }

            if (!empty($plainPassword)) {
                $user->setPassword($hasher->hashPassword($user, $plainPassword));
            }

            $em->flush();
            $this->addFlash('success', '📝 Le compte de ' . $user->getEmail() . ' a bien été mis à jour.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/edit_user.html.twig', [
            'user' => $user
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
}