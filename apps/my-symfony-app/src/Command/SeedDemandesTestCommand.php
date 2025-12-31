<?php

namespace App\Command;

use App\Entity\DemandeKine;
use App\Entity\VilleKine;
use App\Entity\ZoneKine;
use App\Entity\ServiceKine;
use App\Entity\User;
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
        
        // Récupérer tous les utilisateurs avec ROLE_USER pour l'affectation
        $users = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_USER"%')
            ->getQuery()
            ->getResult();
        
        if (empty($users)) {
            $io->warning('Aucun utilisateur avec ROLE_USER trouvé. Les demandes seront créées sans affectation.');
        }
        
        // Récupérer tous les agents pour nom_agent
        $agents = $this->entityManager->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_AGENT"%')
            ->getQuery()
            ->getResult();
        
        if (empty($agents)) {
            $io->warning('Aucun utilisateur avec ROLE_AGENT trouvé. Le champ nom_agent sera vide.');
        }
        
        $io->info('Génération de demandes de test sur 90 jours...');
        
        $statusLabels = [0 => 'En attente', 1 => 'Acceptée', 2 => 'Refusée', 3 => 'En cours'];
        $count = 0;
        
        // Initialiser le compteur de demandes par utilisateur
        $userDemandesCount = [];
        if (!empty($users)) {
            foreach ($users as $user) {
                // Compter les demandes existantes en base
                $existingCount = $this->entityManager->getRepository(DemandeKine::class)
                    ->createQueryBuilder('d')
                    ->select('COUNT(d.id)')
                    ->where('d.idCompte = :userId')
                    ->setParameter('userId', $user->getId())
                    ->getQuery()
                    ->getSingleScalarResult();
                
                $userDemandesCount[$user->getId()] = (int)$existingCount;
            }
        }
        
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
            
            // Affecter à un agent aléatoire (nom_agent)
            if (!empty($agents)) {
                $randomAgent = $agents[array_rand($agents)];
                $demande->setNomAgent($randomAgent->getEmail());
            }
            
            // Affecter automatiquement à l'utilisateur ayant le moins de demandes (id_compte)
            if (!empty($users) && !empty($userDemandesCount)) {
                // Trouver l'utilisateur avec le minimum de demandes
                $minUserId = array_keys($userDemandesCount, min($userDemandesCount))[0];
                
                $demande->setIdCompte($minUserId);
                
                // Incrémenter le compteur pour cet utilisateur
                $userDemandesCount[$minUserId]++;
            }
            
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
        
        // Post-traitement cron-like: fixer dateFinDemande après 10 jours pour statuts Acceptée (1) ou Refusée (2)
        $threshold = (new \DateTimeImmutable())->modify('-10 days');
        $now = new \DateTime();
        
        $eligible = $this->entityManager->getRepository(DemandeKine::class)
            ->createQueryBuilder('d')
            ->where('d.status IN (:st)')
            ->andWhere('d.dateDemande <= :th')
            ->andWhere('d.dateFinDemande IS NULL')
            ->setParameter('st', [1, 2])
            ->setParameter('th', $threshold)
            ->getQuery()
            ->getResult();
        
        $updated = 0;
        foreach ($eligible as $d) {
            $d->setDateFinDemande($now);
            $updated++;
        }
        if ($updated > 0) {
            $this->entityManager->flush();
            $io->info("Mise à jour date_fin_demande pour {$updated} demande(s) arrivées à échéance (10 jours)");
        }

        // Seeding ciblé pour l'agent agent1@allokine.com sur 5 mois, couvrant tous les cas
        $agentEmail = 'agent1@allokine.com';
        $io->info('Ajout de demandes de test dédiées pour l\'agent ' . $agentEmail . ' sur 5 mois…');

        // Helper pour choisir une ville au hasard
        $pickVille = function() use ($villes) {
            return $villes[array_rand($villes)];
        };
        // Helper pour choisir une zone au hasard si disponible
        $pickZone = function() use ($zones) {
            return !empty($zones) ? $zones[array_rand($zones)] : null;
        };
        // Helper pour ajouter 1 service au minimum
        $addRandomServices = function(DemandeKine $demande) use ($services) {
            if (empty($services)) return;
            $nb = min(2, max(1, rand(1, 2)));
            $indices = array_rand($services, $nb);
            if (!is_array($indices)) { $indices = [$indices]; }
            foreach ($indices as $idx) { $demande->addService($services[$idx]); }
        };

        for ($m = 1; $m <= 5; $m++) {
            // Mois cible = courant - m mois
            $monthStart = (new \DateTime('first day of this month 00:00:00'));
            $monthStart->modify("-{$m} month");
            $monthEnd = (clone $monthStart);
            $monthEnd->modify('last day of this month 23:59:59');

            // 1) Prev En cours: créé avant le mois, status=3
            $dPrevCours = new DemandeKine();
            $dPrevCours->setNomAgent($agentEmail);
            $dPrevCours->setDateDemande((clone $monthStart)->modify('-15 days'));
            $dPrevCours->setStatus(3);
            $dPrevCours->setNombreSeance(rand(1, 6));
            $dPrevCours->setMotifKine('Cas de test en cours');
            $ville = $pickVille();
            $dPrevCours->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dPrevCours->setIdZone($zone->getId()); }
            $addRandomServices($dPrevCours);
            $this->entityManager->persist($dPrevCours);

            // 2) Prev Acceptée: créée avant le mois, acceptée pendant le mois (date_fin_demande dans l\'intervalle)
            $dPrevAcc = new DemandeKine();
            $dPrevAcc->setNomAgent($agentEmail);
            $dPrevAcc->setDateDemande((clone $monthStart)->modify('-20 days'));
            $dPrevAcc->setStatus(1);
            $dPrevAcc->setDateFinDemande((clone $monthStart)->modify('+' . rand(1, 20) . ' days'));
            $dPrevAcc->setNombreSeance(rand(2, 10));
            $dPrevAcc->setMotifKine('Cas de test accepté (finalisé dans le mois)');
            $ville = $pickVille();
            $dPrevAcc->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dPrevAcc->setIdZone($zone->getId()); }
            $addRandomServices($dPrevAcc);
            $this->entityManager->persist($dPrevAcc);

            // 3) Prev Rejetée: créée avant le mois, rejetée pendant le mois (date_fin_demande dans l\'intervalle)
            $dPrevRej = new DemandeKine();
            $dPrevRej->setNomAgent($agentEmail);
            $dPrevRej->setDateDemande((clone $monthStart)->modify('-25 days'));
            $dPrevRej->setStatus(2);
            $dPrevRej->setDateFinDemande((clone $monthStart)->modify('+' . rand(5, 25) . ' days'));
            $dPrevRej->setNombreSeance(rand(0, 3));
            $dPrevRej->setMotifKine('Cas de test refusé (finalisé dans le mois)');
            $ville = $pickVille();
            $dPrevRej->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dPrevRej->setIdZone($zone->getId()); }
            $addRandomServices($dPrevRej);
            $this->entityManager->persist($dPrevRej);

            // 4) Demandes du mois courant (M): diversité des statuts
            // En attente
            $dCurrAtt = new DemandeKine();
            $dCurrAtt->setNomAgent($agentEmail);
            $dCurrAtt->setDateDemande((clone $monthStart)->modify('+' . rand(1, 10) . ' days'));
            $dCurrAtt->setStatus(0);
            $dCurrAtt->setNombreSeance(rand(1, 6));
            $dCurrAtt->setMotifKine('Cas en attente (mois)');
            $ville = $pickVille(); $dCurrAtt->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dCurrAtt->setIdZone($zone->getId()); }
            $addRandomServices($dCurrAtt);
            $this->entityManager->persist($dCurrAtt);

            // Acceptée dans le mois
            $dCurrAcc = new DemandeKine();
            $dCurrAcc->setNomAgent($agentEmail);
            $dCurrAcc->setDateDemande((clone $monthStart)->modify('+' . rand(5, 15) . ' days'));
            $dCurrAcc->setStatus(1);
            $dCurrAcc->setDateFinDemande((clone $monthStart)->modify('+' . rand(10, 20) . ' days'));
            $dCurrAcc->setNombreSeance(rand(2, 8));
            $dCurrAcc->setMotifKine('Cas accepté (mois)');
            $ville = $pickVille(); $dCurrAcc->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dCurrAcc->setIdZone($zone->getId()); }
            $addRandomServices($dCurrAcc);
            $this->entityManager->persist($dCurrAcc);

            // Rejetée dans le mois
            $dCurrRej = new DemandeKine();
            $dCurrRej->setNomAgent($agentEmail);
            $dCurrRej->setDateDemande((clone $monthStart)->modify('+' . rand(3, 12) . ' days'));
            $dCurrRej->setStatus(2);
            $dCurrRej->setDateFinDemande((clone $monthStart)->modify('+' . rand(12, 25) . ' days'));
            $dCurrRej->setNombreSeance(rand(0, 4));
            $dCurrRej->setMotifKine('Cas refusé (mois)');
            $ville = $pickVille(); $dCurrRej->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dCurrRej->setIdZone($zone->getId()); }
            $addRandomServices($dCurrRej);
            $this->entityManager->persist($dCurrRej);

            // En cours dans le mois
            $dCurrCours = new DemandeKine();
            $dCurrCours->setNomAgent($agentEmail);
            $dCurrCours->setDateDemande((clone $monthStart)->modify('+' . rand(7, 18) . ' days'));
            $dCurrCours->setStatus(3);
            $dCurrCours->setNombreSeance(rand(1, 5));
            $dCurrCours->setMotifKine('Cas en cours (mois)');
            $ville = $pickVille(); $dCurrCours->setIdVille($ville->getId());
            $zone = $pickZone(); if ($zone) { $dCurrCours->setIdZone($zone->getId()); }
            $addRandomServices($dCurrCours);
            $this->entityManager->persist($dCurrCours);
        }

        $this->entityManager->flush();
        $io->info('Seeding dédié agent terminé.');
        
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
