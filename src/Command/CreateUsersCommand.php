<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-users', description: 'Création des utilisateurs de test')]
class CreateUsersCommand extends Command
{
    private $em;
    private $hasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher)
    {
        parent::__construct();
        $this->em = $em;
        $this->hasher = $hasher;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 📝 Liste des comptes à créer [Email, Rôle]
        $comptes = [
            ['coordon@imprimerie.com', 'ROLE_COORDON'],
            ['guerite@imprimerie.com', 'ROLE_GUERITE'],
            ['compta@imprimerie.com', 'ROLE_COMPTA'],
            ['admin@imprimerie.com', 'ROLE_ADMIN']
        ];

        // On récupère le Repository des utilisateurs pour éviter les doublons
        $userRepository = $this->em->getRepository(User::class);

        foreach ($comptes as $donnees) {
            $email = $donnees[0];
            $role = $donnees[1];

            // Vérification si l'utilisateur existe déjà en Base de Données
            $userExiste = $userRepository->findOneBy(['email' => $email]);

            if (!$userExiste) {
                $user = new User();
                $user->setEmail($email);
                $user->setRoles([$role]);
                
                // Mot de passe général pour les tests : "password"
                $hashedPassword = $this->hasher->hashPassword($user, 'password');
                $user->setPassword($hashedPassword);

                $this->em->persist($user);
                $io->text("➕ Création du compte : $email ($role)");
            } else {
                $io->text("ℹ️ Le compte $email existe déjà. Ignoré.");
            }
        }

        $this->em->flush();
        $io->success('🚀 Synchronisation des comptes terminée avec succès ! Mot de passe général : password');

        return Command::SUCCESS;
    }
}