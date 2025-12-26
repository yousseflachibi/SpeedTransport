<?php

namespace App\Command;

use App\Entity\DemandeKine;
use App\Entity\VilleKine;
use App\Entity\ZoneKine;
use App\Entity\ServiceKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedDemandesTestCommand extends Command
{

    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:demandes-test"
    */

    protected static $defaultName = 'app:seed:demandes-test';
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this->setDescription('Génère des demandes de test pour les 90 derniers jours');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Récupérer toutes les villes
        $villes = $this->entityManager->getRepository(VilleKine::class)->findAll();
        if (empty($villes)) {
            $io->error('Aucune ville trouvée. Exécutez d\'abord app:seed:moroccan-cities');
            return Command::FAILURE;
        }
        
        // Récupérer toutes les zones
        $zones = $this->entityManager->getRepository(ZoneKine::class)->findAll();
        if (empty($zones)) {
            $io->warning('Aucune zone trouvée.');
        }
        
        // Récupérer tous les services
        $services = $this->entityManager->getRepository(ServiceKine::class)->findAll();
        if (empty($services)) {
            $io->error('Aucun service trouvé. Exécutez d\'abord app:seed:services-kine');
            return Command::FAILURE;
        }
        
        $io->info('Génération de demandes de test sur 90 jours...');
        
        $statusLabels = [0 => 'En attente', 1 => 'Acceptée', 2 => 'Refusée', 3 => 'En cours'];
        $count = 0;
        
        // Générer 100 demandes sur les 90 derniers jours
        for ($i = 0; $i < 100; $i++) {
            $demande = new DemandeKine();
            
            // Date aléatoire dans les 90 derniers jours
            $daysAgo = rand(0, 90);
            $date = new \DateTime();
            $date->modify("-{$daysAgo} days");
            $date->setTime(rand(8, 18), rand(0, 59));
            
            $demande->setDateDemande($date);
            
            // Ville aléatoire (avec probabilité plus élevée pour Casablanca et Rabat)
            $villeIndex = rand(0, 100);
            if ($villeIndex < 40) {
                // 40% Casablanca
                $ville = $this->findVilleByName($villes, 'Casablanca');
            } elseif ($villeIndex < 65) {
                // 25% Rabat
                $ville = $this->findVilleByName($villes, 'Rabat');
            } elseif ($villeIndex < 80) {
                // 15% Marrakech
                $ville = $this->findVilleByName($villes, 'Marrakech');
            } else {
                // 20% autres villes
                $ville = $villes[array_rand($villes)];
            }
            
            $demande->setIdVille($ville->getId());
            
            // Zone aléatoire de la ville (si disponible)
            if (!empty($zones)) {
                $zone = $zones[array_rand($zones)];
                $demande->setIdZone($zone->getId());
            }
            
            // Status avec distribution réaliste
            $statusRand = rand(0, 100);
            if ($statusRand < 20) {
                $status = 0; // 20% En attente
            } elseif ($statusRand < 70) {
                $status = 1; // 50% Acceptée
            } elseif ($statusRand < 85) {
                $status = 2; // 15% Refusée
            } else {
                $status = 3; // 15% En cours
            }
            $demande->setStatus($status);
            
            // Nombre de séances aléatoire
            $demande->setNombreSeance(rand(1, 12));
            
            // Informations patient
            $prenoms = ['Mohammed', 'Fatima', 'Ahmed', 'Aicha', 'Youssef', 'Khadija', 'Hassan', 'Nadia', 'Omar', 'Samira'];
            $noms = ['Alaoui', 'Bennani', 'El Amrani', 'Berrada', 'Tazi', 'Filali', 'Idrissi', 'Lahlou', 'Fassi', 'Kettani'];
            $demande->setNomPrenom($prenoms[array_rand($prenoms)] . ' ' . $noms[array_rand($noms)]);
            $demande->setNumeroTele('06' . rand(10000000, 99999999));
            $demande->setEmail(strtolower(str_replace(' ', '.', $demande->getNomPrenom())) . '@example.com');
            
            // Motif
            $motifs = [
                'Douleur lombaire chronique',
                'Rééducation post-opératoire genou',
                'Entorse cheville',
                'Cervicalgie',
                'Rééducation respiratoire',
                'Tendinite épaule',
                'Lombalgie aiguë',
                'Rééducation neurologique',
                'Arthrose hanche',
                'Syndrome canal carpien'
            ];
            $demande->setMotifKine($motifs[array_rand($motifs)]);
            
            // Ajouter 1 à 3 services aléatoires
            $nbServices = rand(1, 3);
            $selectedServices = array_rand($services, min($nbServices, count($services)));
            if (!is_array($selectedServices)) {
                $selectedServices = [$selectedServices];
            }
            foreach ($selectedServices as $serviceIndex) {
                $demande->addService($services[$serviceIndex]);
            }
            
            $this->entityManager->persist($demande);
            $count++;
            
            // Flush tous les 20 pour la performance
            if ($count % 20 === 0) {
                $this->entityManager->flush();
                $io->text("Généré: {$count} demandes...");
            }
        }
        
        $this->entityManager->flush();
        
        $io->success("✅ {$count} demandes de test générées avec succès !");
        
        return Command::SUCCESS;
    }
    
    private function findVilleByName(array $villes, string $name): ?VilleKine
    {
        foreach ($villes as $ville) {
            if ($ville->getNom() === $name) {
                return $ville;
            }
        }
        return $villes[0]; // Fallback
    }
}
