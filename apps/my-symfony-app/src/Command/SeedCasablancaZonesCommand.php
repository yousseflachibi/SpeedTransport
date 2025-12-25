<?php

namespace App\Command;

use App\Entity\ZoneKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:casablanca-zones', description: 'Seed Casablanca zones into zone_kine table')]
class SeedCasablancaZonesCommand extends Command
{

    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:casablanca-zones"
    */

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Zones de Casablanca avec leurs préfectures et codes postaux réels
        $zones = [
            // Préfecture Casablanca-Anfa
            ['nom' => 'Maarif', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20100'],
            ['nom' => 'Gauthier', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Bourgogne', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20050'],
            ['nom' => 'Racine', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20070'],
            ['nom' => 'Anfa', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Val d\'Anfa', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Californie', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20150'],
            ['nom' => 'Polo', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            
            // Préfecture Aïn Chock
            ['nom' => 'Aïn Chock', 'prefecture' => 'Aïn Chock', 'ville' => 'Casablanca', 'code_postal' => '20470'],
            ['nom' => 'Oulfa', 'prefecture' => 'Aïn Chock', 'ville' => 'Casablanca', 'code_postal' => '20580'],
            ['nom' => 'Hay el Baraka', 'prefecture' => 'Aïn Chock', 'ville' => 'Casablanca', 'code_postal' => '20600'],
            ['nom' => 'Hay Rahma', 'prefecture' => 'Aïn Chock', 'ville' => 'Casablanca', 'code_postal' => '20610'],
            
            // Préfecture Hay Hassani
            ['nom' => 'Hay Hassani', 'prefecture' => 'Hay Hassani', 'ville' => 'Casablanca', 'code_postal' => '20200'],
            ['nom' => 'Lissasfa', 'prefecture' => 'Hay Hassani', 'ville' => 'Casablanca', 'code_postal' => '20250'],
            ['nom' => 'Aïn Sebaâ', 'prefecture' => 'Hay Hassani', 'ville' => 'Casablanca', 'code_postal' => '20600'],
            ['nom' => 'Sbata', 'prefecture' => 'Hay Hassani', 'ville' => 'Casablanca', 'code_postal' => '20300'],
            
            // Préfecture Aïn Sebaâ-Hay Mohammadi
            ['nom' => 'Hay Mohammadi', 'prefecture' => 'Aïn Sebaâ-Hay Mohammadi', 'ville' => 'Casablanca', 'code_postal' => '20400'],
            ['nom' => 'Roches Noires', 'prefecture' => 'Aïn Sebaâ-Hay Mohammadi', 'ville' => 'Casablanca', 'code_postal' => '20450'],
            ['nom' => 'Carrières Centrales', 'prefecture' => 'Aïn Sebaâ-Hay Mohammadi', 'ville' => 'Casablanca', 'code_postal' => '20490'],
            
            // Préfecture Ben M'Sick
            ['nom' => 'Ben M\'Sick', 'prefecture' => 'Ben M\'Sick', 'ville' => 'Casablanca', 'code_postal' => '20700'],
            ['nom' => 'Derb Ghallef', 'prefecture' => 'Ben M\'Sick', 'ville' => 'Casablanca', 'code_postal' => '20360'],
            ['nom' => 'Hay Farah', 'prefecture' => 'Ben M\'Sick', 'ville' => 'Casablanca', 'code_postal' => '20750'],
            
            // Préfecture Sidi Bernoussi
            ['nom' => 'Sidi Bernoussi', 'prefecture' => 'Sidi Bernoussi', 'ville' => 'Casablanca', 'code_postal' => '20600'],
            ['nom' => 'Sidi Moumen', 'prefecture' => 'Sidi Bernoussi', 'ville' => 'Casablanca', 'code_postal' => '20650'],
            ['nom' => 'Hay Salama', 'prefecture' => 'Sidi Bernoussi', 'ville' => 'Casablanca', 'code_postal' => '20680'],
            
            // Préfecture Moulay Rachid
            ['nom' => 'Moulay Rachid', 'prefecture' => 'Moulay Rachid', 'ville' => 'Casablanca', 'code_postal' => '20350'],
            ['nom' => 'Sidi Othmane', 'prefecture' => 'Moulay Rachid', 'ville' => 'Casablanca', 'code_postal' => '20340'],
            ['nom' => 'Hay Inara', 'prefecture' => 'Moulay Rachid', 'ville' => 'Casablanca', 'code_postal' => '20330'],
            
            // Préfecture El Fida-Mers Sultan
            ['nom' => 'Mers Sultan', 'prefecture' => 'El Fida-Mers Sultan', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Derb Sultan', 'prefecture' => 'El Fida-Mers Sultan', 'ville' => 'Casablanca', 'code_postal' => '20100'],
            ['nom' => 'Belvedere', 'prefecture' => 'El Fida-Mers Sultan', 'ville' => 'Casablanca', 'code_postal' => '20070'],
            ['nom' => 'Hay Dakhla', 'prefecture' => 'El Fida-Mers Sultan', 'ville' => 'Casablanca', 'code_postal' => '20100'],
            
            // Zones périphériques importantes
            ['nom' => 'Sidi Maarouf', 'prefecture' => 'Hay Hassani', 'ville' => 'Casablanca', 'code_postal' => '20190'],
            ['nom' => 'Aïn Diab', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => '2 Mars', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Belvédère', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Oasis', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20100'],
            ['nom' => 'Beauséjour', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20000'],
            ['nom' => 'Maârif Extension', 'prefecture' => 'Casablanca-Anfa', 'ville' => 'Casablanca', 'code_postal' => '20100'],
            ['nom' => 'Hay Riad', 'prefecture' => 'Aïn Chock', 'ville' => 'Casablanca', 'code_postal' => '20470'],
        ];

        $repo = $this->em->getRepository(ZoneKine::class);
        $added = 0;
        $updated = 0;

        foreach ($zones as $zoneData) {
            // Vérifier si la zone existe déjà (par nom et ville)
            $existing = $repo->findOneBy([
                'nom' => $zoneData['nom'],
                'ville' => $zoneData['ville']
            ]);

            if ($existing) {
                // Mettre à jour si nécessaire
                if ($existing->getPrefecture() !== $zoneData['prefecture'] || 
                    $existing->getCodePostal() !== $zoneData['code_postal']) {
                    $existing->setPrefecture($zoneData['prefecture']);
                    $existing->setCodePostal($zoneData['code_postal']);
                    $updated++;
                }
                continue;
            }

            // Créer une nouvelle zone
            $zone = new ZoneKine();
            $zone->setNom($zoneData['nom']);
            $zone->setPrefecture($zoneData['prefecture']);
            $zone->setVille($zoneData['ville']);
            $zone->setCodePostal($zoneData['code_postal']);
            
            $this->em->persist($zone);
            $added++;
        }

        if ($added > 0 || $updated > 0) {
            $this->em->flush();
        }

        $total = count($repo->findBy(['ville' => 'Casablanca']));
        $io->success(sprintf(
            'Casablanca zones seeding done. Added: %d, Updated: %d, Total Casablanca zones in DB: %d',
            $added,
            $updated,
            $total
        ));
        
        return Command::SUCCESS;
    }
}
