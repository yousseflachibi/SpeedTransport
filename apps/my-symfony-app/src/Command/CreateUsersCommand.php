<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateUsersCommand extends Command
{
    protected static $defaultName = 'app:create-users';
    protected static $defaultDescription = 'Crée des utilisateurs de test avec différents rôles';

    private $entityManager;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande crée des utilisateurs de test avec les rôles ROLE_USER et ROLE_AGENT');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Création des utilisateurs de test');

        // Utilisateurs avec ROLE_USER (3 utilisateurs)
        $normalUsers = [
            [
                'email' => 'user1@test.com',
                'password' => 'user123',
                'fullName' => 'Jean Dupont',
                'telephone' => '0612345678',
                'roles' => ['ROLE_USER']
            ],
            [
                'email' => 'user2@test.com',
                'password' => 'user123',
                'fullName' => 'Marie Martin',
                'telephone' => '0623456789',
                'roles' => ['ROLE_USER']
            ],
            [
                'email' => 'user3@test.com',
                'password' => 'user123',
                'fullName' => 'Pierre Bernard',
                'telephone' => '0634567890',
                'roles' => ['ROLE_USER']
            ]
        ];

        // Agents (5 agents)
        $agents = [
            [
                'email' => 'agent1@allokine.com',
                'password' => 'agent123',
                'fullName' => 'Sophie Dubois',
                'telephone' => '0645678901',
                'roles' => ['ROLE_AGENT']
            ],
            [
                'email' => 'agent2@allokine.com',
                'password' => 'agent123',
                'fullName' => 'Luc Thomas',
                'telephone' => '0656789012',
                'roles' => ['ROLE_AGENT']
            ],
            [
                'email' => 'agent3@allokine.com',
                'password' => 'agent123',
                'fullName' => 'Claire Robert',
                'telephone' => '0667890123',
                'roles' => ['ROLE_AGENT']
            ],
            [
                'email' => 'agent4@allokine.com',
                'password' => 'agent123',
                'fullName' => 'Marc Petit',
                'telephone' => '0678901234',
                'roles' => ['ROLE_AGENT']
            ],
            [
                'email' => 'agent5@allokine.com',
                'password' => 'agent123',
                'fullName' => 'Anne Richard',
                'telephone' => '0689012345',
                'roles' => ['ROLE_AGENT']
            ]
        ];

        $allUsers = array_merge($normalUsers, $agents);
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($allUsers as $userData) {
            // Vérifier si l'utilisateur existe déjà
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);

            if ($existingUser) {
                $io->warning("L'utilisateur {$userData['email']} existe déjà. Ignoré.");
                $skippedCount++;
                continue;
            }

            // Créer le nouvel utilisateur
            $user = new User();
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setFullName($userData['fullName']);
            $user->setTelephone($userData['telephone']);

            // Hasher le mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $createdCount++;

            $role = $userData['roles'][0];
            $io->success("✓ Utilisateur créé : {$userData['email']} ({$role})");
        }

        // Sauvegarder tous les utilisateurs en base
        $this->entityManager->flush();

        $io->newLine();
        $io->section('Résumé');
        $io->text([
            "✓ Utilisateurs créés : $createdCount",
            "⊘ Utilisateurs ignorés (déjà existants) : $skippedCount",
            '',
            'Informations de connexion :',
            '- Utilisateurs standards : user1@test.com / user123',
            '- Agents : agent1@allokine.com / agent123',
        ]);

        return Command::SUCCESS;
    }
}
