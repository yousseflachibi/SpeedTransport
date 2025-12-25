<?php

namespace App\Command;

use App\Entity\CategorieServiceKine;
use App\Entity\ServiceKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed:services-kine', description: 'Seed categories and services for kinésithérapie')]
class SeedServicesKineCommand extends Command
{

    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:services-kine"
    */

    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Catégories et leurs services avec prix en MAD
        $categoriesData = [
            'Rééducation Orthopédique' => [
                ['nom' => 'Rééducation post-fracture', 'prix' => '200'],
                ['nom' => 'Rééducation articulaire', 'prix' => '200'],
                ['nom' => 'Rééducation des entorses', 'prix' => '180'],
                ['nom' => 'Rééducation de l\'épaule', 'prix' => '220'],
                ['nom' => 'Rééducation du genou', 'prix' => '220'],
                ['nom' => 'Rééducation de la hanche', 'prix' => '220'],
                ['nom' => 'Rééducation du rachis', 'prix' => '200'],
                ['nom' => 'Rééducation des tendinites', 'prix' => '180'],
            ],
            'Rééducation Neurologique' => [
                ['nom' => 'Rééducation AVC (Hémiplégie)', 'prix' => '250'],
                ['nom' => 'Rééducation Parkinson', 'prix' => '250'],
                ['nom' => 'Rééducation Sclérose en plaques', 'prix' => '250'],
                ['nom' => 'Rééducation des paralysies', 'prix' => '240'],
                ['nom' => 'Rééducation de l\'équilibre', 'prix' => '200'],
            ],
            'Rééducation Respiratoire' => [
                ['nom' => 'Drainage bronchique', 'prix' => '180'],
                ['nom' => 'Rééducation respiratoire BPCO', 'prix' => '200'],
                ['nom' => 'Rééducation post-COVID', 'prix' => '200'],
                ['nom' => 'Kinésithérapie respiratoire enfant', 'prix' => '180'],
            ],
            'Rééducation Post-Opératoire' => [
                ['nom' => 'Rééducation après prothèse de hanche', 'prix' => '250'],
                ['nom' => 'Rééducation après prothèse de genou', 'prix' => '250'],
                ['nom' => 'Rééducation après ligamentoplastie', 'prix' => '240'],
                ['nom' => 'Rééducation après arthroscopie', 'prix' => '220'],
                ['nom' => 'Rééducation après chirurgie rachidienne', 'prix' => '240'],
            ],
            'Rééducation Sportive' => [
                ['nom' => 'Rééducation du sportif', 'prix' => '220'],
                ['nom' => 'Préparation physique', 'prix' => '200'],
                ['nom' => 'Récupération post-effort', 'prix' => '180'],
                ['nom' => 'Traitement des blessures sportives', 'prix' => '200'],
            ],
            'Massothérapie' => [
                ['nom' => 'Massage thérapeutique', 'prix' => '150'],
                ['nom' => 'Massage relaxant', 'prix' => '150'],
                ['nom' => 'Massage sportif', 'prix' => '180'],
                ['nom' => 'Massage des cicatrices', 'prix' => '150'],
            ],
            'Drainage Lymphatique' => [
                ['nom' => 'Drainage lymphatique manuel', 'prix' => '200'],
                ['nom' => 'Drainage post-opératoire', 'prix' => '220'],
                ['nom' => 'Traitement du lymphœdème', 'prix' => '220'],
            ],
            'Kinésithérapie Pédiatrique' => [
                ['nom' => 'Rééducation motrice de l\'enfant', 'prix' => '200'],
                ['nom' => 'Traitement du torticolis congénital', 'prix' => '180'],
                ['nom' => 'Rééducation des troubles posturaux', 'prix' => '180'],
                ['nom' => 'Kinésithérapie respiratoire enfant', 'prix' => '180'],
            ],
            'Kinésithérapie Gériatrique' => [
                ['nom' => 'Prévention des chutes', 'prix' => '180'],
                ['nom' => 'Rééducation de la marche', 'prix' => '200'],
                ['nom' => 'Mobilisation articulaire senior', 'prix' => '180'],
                ['nom' => 'Renforcement musculaire senior', 'prix' => '180'],
            ],
            'Rééducation Périnéale' => [
                ['nom' => 'Rééducation périnéale post-partum', 'prix' => '200'],
                ['nom' => 'Traitement de l\'incontinence', 'prix' => '200'],
                ['nom' => 'Préparation à l\'accouchement', 'prix' => '180'],
            ],
            'Physiothérapie' => [
                ['nom' => 'Électrothérapie', 'prix' => '150'],
                ['nom' => 'Ultrasonothérapie', 'prix' => '150'],
                ['nom' => 'Thermothérapie', 'prix' => '120'],
                ['nom' => 'Cryothérapie', 'prix' => '120'],
                ['nom' => 'Ondes de choc', 'prix' => '250'],
            ],
            'Rééducation Cardiovasculaire' => [
                ['nom' => 'Réadaptation cardiaque', 'prix' => '220'],
                ['nom' => 'Rééducation vasculaire', 'prix' => '200'],
            ],
        ];

        $categorieRepo = $this->em->getRepository(CategorieServiceKine::class);
        $serviceRepo = $this->em->getRepository(ServiceKine::class);
        
        $categoriesAdded = 0;
        $servicesAdded = 0;

        foreach ($categoriesData as $categorieName => $services) {
            // Vérifier si la catégorie existe
            $categorie = $categorieRepo->findOneBy(['nom' => $categorieName]);
            
            if (!$categorie) {
                $categorie = new CategorieServiceKine();
                $categorie->setNom($categorieName);
                $this->em->persist($categorie);
                $categoriesAdded++;
            }
            
            // Forcer le flush pour obtenir l'ID de la catégorie
            $this->em->flush();
            
            // Ajouter les services de cette catégorie
            foreach ($services as $serviceData) {
                $existing = $serviceRepo->findOneBy([
                    'name' => $serviceData['nom'],
                    'categorie' => $categorie
                ]);
                
                if ($existing) {
                    continue;
                }
                
                $service = new ServiceKine();
                $service->setName($serviceData['nom']);
                $service->setPrice($serviceData['prix'] . ' MAD');
                $service->setCategorie($categorie);
                
                $this->em->persist($service);
                $servicesAdded++;
            }
        }
        
        $this->em->flush();

        $totalCategories = count($categorieRepo->findAll());
        $totalServices = count($serviceRepo->findAll());
        
        $io->success(sprintf(
            'Services Kiné seeding done. Categories added: %d (Total: %d), Services added: %d (Total: %d)',
            $categoriesAdded,
            $totalCategories,
            $servicesAdded,
            $totalServices
        ));
        
        return Command::SUCCESS;
    }
}
