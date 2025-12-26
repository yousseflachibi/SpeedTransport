<?php

namespace App\Command;

use App\Entity\CentreKine;
use App\Entity\ZoneKine;
use App\Entity\VilleKine;
use App\Entity\ServiceKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedCentresKineCommand extends Command
{
    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:centres-kine"
    */

    protected static $defaultName = 'app:seed:centres-kine';
    protected static $defaultDescription = 'Génère des centres de kinésithérapie de test';

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande crée des centres de kinésithérapie de test dans différentes zones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération de centres de kinésithérapie de test');

        // Récupérer toutes les villes
        $villes = $this->entityManager->getRepository(VilleKine::class)->findAll();
        if (empty($villes)) {
            $io->warning('Aucune ville trouvée. Création de quelques villes...');
            $this->createDefaultVilles();
            $villes = $this->entityManager->getRepository(VilleKine::class)->findAll();
        }

        // Récupérer toutes les zones
        $zones = $this->entityManager->getRepository(ZoneKine::class)->findAll();
        if (empty($zones)) {
            $io->warning('Aucune zone trouvée. Création de quelques zones...');
            $this->createDefaultZones();
            $zones = $this->entityManager->getRepository(ZoneKine::class)->findAll();
        }

        // Récupérer tous les services
        $services = $this->entityManager->getRepository(ServiceKine::class)->findAll();
        if (empty($services)) {
            $io->warning('Aucun service trouvé. Les centres seront créés sans services associés.');
        }

        // Données des centres à créer
        $centresData = [
            [
                'nom' => 'Centre Kiné Anfa',
                'adresse' => 'Boulevard d\'Anfa, Résidence Al Mountazah, Casablanca',
                'ville' => 'Casablanca',
                'mapX' => '33.5731',
                'mapY' => '-7.6298',
            ],
            [
                'nom' => 'Cabinet de Kinésithérapie Maarif',
                'adresse' => 'Rue Abou Faris Al Marini, Maarif, Casablanca',
                'ville' => 'Casablanca',
                'mapX' => '33.5881',
                'mapY' => '-7.6352',
            ],
            [
                'nom' => 'Centre de Rééducation Agdal',
                'adresse' => 'Avenue Imam Malik, Agdal, Rabat',
                'ville' => 'Rabat',
                'mapX' => '33.9978',
                'mapY' => '-6.8629',
            ],
            [
                'nom' => 'Kiné Sport Hassan',
                'adresse' => 'Avenue Hassan II, Hassan, Rabat',
                'ville' => 'Rabat',
                'mapX' => '34.0209',
                'mapY' => '-6.8417',
            ],
            [
                'nom' => 'Centre Kiné Guéliz',
                'adresse' => 'Boulevard Mohammed V, Guéliz, Marrakech',
                'ville' => 'Marrakech',
                'mapX' => '31.6295',
                'mapY' => '-7.9811',
            ],
            [
                'nom' => 'Cabinet Kiné Hivernage',
                'adresse' => 'Avenue Echouhada, Hivernage, Marrakech',
                'ville' => 'Marrakech',
                'mapX' => '31.6226',
                'mapY' => '-8.0089',
            ],
            [
                'nom' => 'Centre Médical Kiné Tanger',
                'adresse' => 'Boulevard Pasteur, Centre Ville, Tanger',
                'ville' => 'Tanger',
                'mapX' => '35.7595',
                'mapY' => '-5.8340',
            ],
            [
                'nom' => 'Kiné Réhab Fès',
                'adresse' => 'Avenue Hassan II, Ville Nouvelle, Fès',
                'ville' => 'Fès',
                'mapX' => '34.0181',
                'mapY' => '-5.0078',
            ],
            [
                'nom' => 'Centre de Physiothérapie Californie',
                'adresse' => 'Boulevard de la Corniche, Ain Diab, Casablanca',
                'ville' => 'Casablanca',
                'mapX' => '33.5886',
                'mapY' => '-7.6652',
            ],
            [
                'nom' => 'Kiné Plus Témara',
                'adresse' => 'Avenue Moulay Ismail, Centre Ville, Témara',
                'ville' => 'Témara',
                'mapX' => '33.9275',
                'mapY' => '-6.9063',
            ],
            [
                'nom' => 'Centre Kiné Océan',
                'adresse' => 'Boulevard de l\'Océan Atlantique, Mohammedia',
                'ville' => 'Mohammedia',
                'mapX' => '33.6866',
                'mapY' => '-7.3833',
            ],
            [
                'nom' => 'Cabinet Kiné Beauséjour',
                'adresse' => 'Rue Beauséjour, Quartier des Hôpitaux, Casablanca',
                'ville' => 'Casablanca',
                'mapX' => '33.5792',
                'mapY' => '-7.6447',
            ],
            [
                'nom' => 'Kiné Moulay Youssef',
                'adresse' => 'Boulevard Moulay Youssef, Casablanca',
                'ville' => 'Casablanca',
                'mapX' => '33.5945',
                'mapY' => '-7.6184',
            ],
            [
                'nom' => 'Centre Médical Kiné Hay Riad',
                'adresse' => 'Quartier Hay Riad, Rabat',
                'ville' => 'Rabat',
                'mapX' => '33.9716',
                'mapY' => '-6.8498',
            ],
            [
                'nom' => 'Kiné Santé Salé',
                'adresse' => 'Avenue Lalla Yacout, Salé',
                'ville' => 'Salé',
                'mapX' => '34.0531',
                'mapY' => '-6.7985',
            ],
        ];

        $createdCount = 0;
        $skippedCount = 0;

        foreach ($centresData as $centreData) {
            // Vérifier si le centre existe déjà
            $existingCentre = $this->entityManager->getRepository(CentreKine::class)
                ->findOneBy(['nom' => $centreData['nom']]);

            if ($existingCentre) {
                $io->warning("Le centre '{$centreData['nom']}' existe déjà. Ignoré.");
                $skippedCount++;
                continue;
            }

            $centre = new CentreKine();
            $centre->setNom($centreData['nom']);
            $centre->setAdresse($centreData['adresse']);
            $centre->setMapX($centreData['mapX']);
            $centre->setMapY($centreData['mapY']);
            $centre->setDateInscription(new \DateTime());

            // Chercher et associer la ville correspondante
            $villeKine = $this->findVilleByName($villes, $centreData['ville']);
            if ($villeKine) {
                $centre->setVilleKine($villeKine);
            }

            // Associer une zone aléatoire de la même ville (si disponible)
            if (!empty($zones)) {
                $matchingZones = array_filter($zones, function($zone) use ($centreData) {
                    return $zone->getVille() === $centreData['ville'];
                });

                if (!empty($matchingZones)) {
                    $zone = $matchingZones[array_rand($matchingZones)];
                } else {
                    $zone = $zones[array_rand($zones)];
                }
                $centre->setZone($zone);
            }

            // Ajouter 3 à 8 services aléatoires au centre
            if (!empty($services)) {
                $nbServices = rand(3, min(8, count($services)));
                $selectedServices = array_rand($services, $nbServices);
                if (!is_array($selectedServices)) {
                    $selectedServices = [$selectedServices];
                }
                foreach ($selectedServices as $serviceIndex) {
                    $centre->addService($services[$serviceIndex]);
                }
            }

            $this->entityManager->persist($centre);
            $createdCount++;

            $io->success("✓ Centre créé : {$centreData['nom']} ({$centreData['ville']})");
        }

        // Sauvegarder tous les centres en base
        $this->entityManager->flush();

        $io->newLine();
        $io->section('Résumé');
        $io->text([
            "✓ Centres créés : $createdCount",
            "⊘ Centres ignorés (déjà existants) : $skippedCount",
        ]);

        return Command::SUCCESS;
    }

    private function findVilleByName(array $villes, string $name): ?VilleKine
    {
        foreach ($villes as $ville) {
            if ($ville->getNom() === $name) {
                return $ville;
            }
        }
        return null;
    }

    private function createDefaultVilles(): void
    {
        $villesData = [
            'Casablanca', 'Rabat', 'Marrakech', 'Tanger', 'Fès',
            'Témara', 'Mohammedia', 'Salé', 'Agadir', 'Meknès'
        ];

        foreach ($villesData as $nomVille) {
            $existingVille = $this->entityManager->getRepository(VilleKine::class)
                ->findOneBy(['nom' => $nomVille]);
            
            if (!$existingVille) {
                $ville = new VilleKine();
                $ville->setNom($nomVille);
                $this->entityManager->persist($ville);
            }
        }

        $this->entityManager->flush();
    }

    private function createDefaultZones(): void
    {
        $zonesData = [
            ['nom' => 'Anfa', 'prefecture' => 'Anfa', 'ville' => 'Casablanca', 'codePostal' => '20000'],
            ['nom' => 'Maarif', 'prefecture' => 'Maarif', 'ville' => 'Casablanca', 'codePostal' => '20100'],
            ['nom' => 'Agdal', 'prefecture' => 'Agdal', 'ville' => 'Rabat', 'codePostal' => '10000'],
            ['nom' => 'Hassan', 'prefecture' => 'Hassan', 'ville' => 'Rabat', 'codePostal' => '10010'],
            ['nom' => 'Guéliz', 'prefecture' => 'Guéliz', 'ville' => 'Marrakech', 'codePostal' => '40000'],
        ];

        foreach ($zonesData as $zoneData) {
            $zone = new ZoneKine();
            $zone->setNom($zoneData['nom']);
            $zone->setPrefecture($zoneData['prefecture']);
            $zone->setVille($zoneData['ville']);
            $zone->setCodePostal($zoneData['codePostal']);
            $this->entityManager->persist($zone);
        }

        $this->entityManager->flush();
    }
}
