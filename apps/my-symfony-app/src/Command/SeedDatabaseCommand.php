<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:database', description: 'Exécute toutes les commandes de seeding dans l\'ordre approprié')]
class SeedDatabaseCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /* run command
        cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:database"
        */        
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Seeding de la base de données AlloKine');
        
        // Liste des commandes à exécuter dans l'ordre
        $commands = [
            ['name' => 'app:create-users', 'description' => 'Création des utilisateurs'],
            ['name' => 'app:seed:cities', 'description' => 'Seeding des villes marocaines'],
            ['name' => 'app:seed:casablanca-zones', 'description' => 'Seeding des zones de Casablanca'],
            ['name' => 'app:seed:services-kine', 'description' => 'Seeding des services de kinésithérapie'],
            ['name' => 'app:seed:centres-kine', 'description' => 'Seeding des centres de kinésithérapie'],
            ['name' => 'app:seed:demandes-test', 'description' => 'Seeding des demandes de test'],
            ['name' => 'app:centres:clean-orphan-images', 'description' => 'Nettoyage des images orphelines'],
        ];
        
        $totalCommands = count($commands);
        $successCount = 0;
        
        foreach ($commands as $index => $commandInfo) {
            $commandNumber = $index + 1;
            $io->section(sprintf('[%d/%d] %s', $commandNumber, $totalCommands, $commandInfo['description']));
            
            try {
                $command = $this->getApplication()->find($commandInfo['name']);
                $commandInput = new ArrayInput([]);
                $commandInput->setInteractive(false);
                
                $returnCode = $command->run($commandInput, $output);
                
                if ($returnCode === Command::SUCCESS) {
                    $io->success(sprintf('✓ %s terminé avec succès', $commandInfo['description']));
                    $successCount++;
                } else {
                    $io->error(sprintf('✗ Échec de %s (code: %d)', $commandInfo['description'], $returnCode));
                    $io->warning('Arrêt du processus de seeding');
                    return Command::FAILURE;
                }
            } catch (\Exception $e) {
                $io->error(sprintf('✗ Erreur lors de l\'exécution de %s: %s', $commandInfo['name'], $e->getMessage()));
                $io->warning('Arrêt du processus de seeding');
                return Command::FAILURE;
            }
            
            $io->newLine();
        }
        
        $io->success(sprintf(
            'Seeding terminé avec succès ! %d/%d commandes exécutées',
            $successCount,
            $totalCommands
        ));
        
        return Command::SUCCESS;
    }
}
