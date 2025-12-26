<?php

namespace App\Command;

use App\Entity\ContactUs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedContactUsCommand extends Command
{
    /* run command
    cd /workspaces/AlloKineLandingPage && docker compose exec php bash -lc "php bin/console app:seed:contact-us"
    */

    protected static $defaultName = 'app:seed:contact-us';
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this->setDescription('Génère des messages Contact Us de test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->info('Génération de messages Contact Us...');
        
        // Listes de données pour la génération
        $prénoms = ['Ahmed', 'Fatima', 'Mohamed', 'Aicha', 'Youssef', 'Khadija', 'Hassan', 'Samira', 'Rachid', 'Leila', 'Omar', 'Nadia', 'Karim', 'Zineb', 'Mehdi', 'Salma', 'Amine', 'Hanane', 'Said', 'Meryem'];
        $noms = ['El Alami', 'Benali', 'El Fassi', 'Tazi', 'Benjelloun', 'Cherkaoui', 'Filali', 'Lahlou', 'Chraibi', 'Bennani', 'Tounsi', 'El Amrani', 'Berrada', 'Sefrioui', 'Tahiri', 'Alaoui'];
        
        $villes = [
            'Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir', 'Meknès', 
            'Oujda', 'Kenitra', 'Tétouan', 'Salé', 'Mohammedia', 'El Jadida', 
            'Khouribga', 'Settat', 'Nador', 'Safi', 'Beni Mellal', 'Taza'
        ];
        
        $choix = ['Centre Kiné', 'Patient', 'Kiné à domicile'];
        
        $messagesPatient = [
            'Je cherche un kinésithérapeute pour une rééducation suite à une fracture du poignet.',
            'Besoin de séances de kinésithérapie pour des douleurs lombaires chroniques.',
            'Je recherche un kiné spécialisé dans le sport pour une entorse de la cheville.',
            'Suite à une opération du genou, j\'ai besoin de rééducation.',
            'Douleurs cervicales persistantes, je cherche un bon kinésithérapeute.',
            'Besoin de kinésithérapie respiratoire pour mon enfant asthmatique.',
            'Je voudrais des séances de rééducation post-AVC pour mon père.',
            'Recherche kiné pour rééducation périnéale post-accouchement.',
            'Tendinite de l\'épaule, besoin de soins urgents.',
            'Scoliose de ma fille, nous cherchons un kiné pédiatrique.'
        ];
        
        $messagesCentre = [
            'Je suis kinésithérapeute et je voudrais référencer mon cabinet sur votre plateforme.',
            'Notre centre de kinésithérapie souhaite s\'inscrire sur AlloKiné.',
            'Comment puis-je ajouter mon cabinet à votre annuaire ?',
            'Nous avons ouvert un nouveau centre à [ville] et souhaitons rejoindre votre réseau.',
            'Conditions de partenariat pour les centres de kinésithérapie ?',
            'Je voudrais des informations sur les tarifs de référencement.',
            'Notre centre dispose de 5 kinés qualifiés, comment s\'inscrire ?',
            'Intéressé par un partenariat commercial avec AlloKiné.',
            'Nous sommes un centre spécialisé en kinésithérapie sportive, comment vous rejoindre ?',
            'Quelle est la procédure pour être visible sur votre plateforme ?'
        ];
        
        $messagesDomicile = [
            'Mon père est âgé et ne peut pas se déplacer, nous avons besoin d\'un kiné à domicile.',
            'Suite à une hospitalisation, je cherche un kinésithérapeute qui se déplace.',
            'Besoin de soins à domicile pour ma mère qui a des problèmes de mobilité.',
            'Proposez-vous des kinés qui se déplacent à domicile ?',
            'Je suis en convalescence à la maison, j\'ai besoin de rééducation à domicile.',
            'Kiné à domicile disponible le week-end pour une personne âgée ?',
            'Comment trouver un kinésithérapeute qui fait des visites à domicile ?',
            'Tarifs pour des séances de kinésithérapie à domicile ?',
            'Urgent : besoin d\'un kiné à domicile pour patient post-opératoire.',
            'Nous habitons dans une zone rurale, y a-t-il des kinés qui se déplacent ?'
        ];
        
        $count = 0;
        
        // Générer 50 messages Contact Us
        for ($i = 0; $i < 50; $i++) {
            $contact = new ContactUs();
            
            // Nom complet aléatoire
            $prenom = $prénoms[array_rand($prénoms)];
            $nom = $noms[array_rand($noms)];
            $fullName = $prenom . ' ' . $nom;
            $contact->setFullName($fullName);
            
            // Email
            $email = strtolower(str_replace(' ', '.', $fullName)) . rand(1, 999) . '@' . ['gmail.com', 'yahoo.fr', 'hotmail.com', 'outlook.com'][array_rand(['gmail.com', 'yahoo.fr', 'hotmail.com', 'outlook.com'])];
            $contact->setEmail($email);
            
            // Téléphone marocain
            $phoneNumber = '06' . rand(10000000, 99999999);
            $contact->setPhoneNumber($phoneNumber);
            
            // Ville aléatoire
            $ville = $villes[array_rand($villes)];
            $contact->setCity($ville);
            
            // Choix aléatoire avec distribution : 60% Patient, 25% Centre, 15% Domicile
            $rand = rand(1, 100);
            if ($rand <= 60) {
                $choixSelected = 'Patient';
                $message = $messagesPatient[array_rand($messagesPatient)];
            } elseif ($rand <= 85) {
                $choixSelected = 'Centre Kiné';
                $message = str_replace('[ville]', $ville, $messagesCentre[array_rand($messagesCentre)]);
            } else {
                $choixSelected = 'Kiné à domicile';
                $message = $messagesDomicile[array_rand($messagesDomicile)];
            }
            $contact->setChoiceList($choixSelected);
            $contact->setDescription($message);
            
            // Date aléatoire dans les 60 derniers jours
            $daysAgo = rand(0, 60);
            $date = new \DateTime();
            $date->modify("-{$daysAgo} days");
            $date->setTime(rand(8, 20), rand(0, 59));
            $contact->setDateAction($date);
            
            $this->entityManager->persist($contact);
            $count++;
            
            // Flush tous les 20 pour optimiser
            if ($count % 20 === 0) {
                $this->entityManager->flush();
                $io->text("$count messages créés...");
            }
        }
        
        // Flush final
        $this->entityManager->flush();
        
        $io->success("Génération terminée ! $count messages Contact Us créés avec succès.");
        $io->table(
            ['Statistique', 'Valeur'],
            [
                ['Total messages', $count],
                ['Choix disponibles', implode(', ', $choix)],
                ['Période', 'Derniers 60 jours'],
            ]
        );
        
        return Command::SUCCESS;
    }
}
