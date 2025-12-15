<?php

namespace App\Command;

use App\Entity\VilleKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:cities', description: 'Seed all Moroccan cities into ville_kine table')]
class SeedMoroccanCitiesCommand extends Command
{

    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:cities"
    */

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cities = [
            // Principales villes du Maroc (liste concise et utile)
            'Casablanca','Rabat','Fès','Marrakech','Tanger','Agadir','Meknès','Oujda','Kenitra','Tetouan',
            'Safi','El Jadida','Nador','Béni Mellal','Taza','Khouribga','Mohammedia','Laayoune','Dakhla','Errachidia',
            'Ksar El Kebir','Larache','Guelmim','Ouarzazate','Taourirt','Berrechid','Settat','Salé','Temara','Skhirat',
            'Ifrane','Essaouira','Taroudant','Midelt','Azrou','Berkane','Sidi Kacem','Sidi Slimane','Youssoufia','Chefchaouen',
            'Ouazzane','Sidi Bennour','Sidi Ifni','Zagora','Boujdour','Smara','Tiznit','Chichaoua','Kalaat Sraghna','El Kelaa des Sraghna'
        ];

        $repo = $this->em->getRepository(VilleKine::class);
        $added = 0;
        foreach ($cities as $name) {
            $existing = $repo->findOneBy(['nom' => $name]);
            if ($existing) {
                continue;
            }
            $ville = new VilleKine();
            $ville->setNom($name);
            $this->em->persist($ville);
            $added++;
        }
        if ($added > 0) {
            $this->em->flush();
        }

        $io->success(sprintf('Cities seeding done. Added: %d, Total in DB: %d', $added, count($repo->findAll())));
        return Command::SUCCESS;
    }
}
