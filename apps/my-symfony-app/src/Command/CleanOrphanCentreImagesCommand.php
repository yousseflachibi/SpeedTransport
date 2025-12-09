<?php

namespace App\Command;

use App\Entity\CentreKine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:centres:clean-orphan-images', description: 'Supprime les images orphelines dans public/uploads/centres qui ne sont pas référencées en DB')]
class CleanOrphanCentreImagesCommand extends Command
{
    private EntityManagerInterface $em;
    private string $publicDir;

    public function __construct(EntityManagerInterface $em, string $projectDir)
    {
        parent::__construct();
        $this->em = $em;
        // Le dossier public est apps/my-symfony-app/public quand on est dans le projet
        $this->publicDir = rtrim($projectDir, '/').'/public';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $uploadPath = $this->publicDir.'/uploads/centres';
        if (!is_dir($uploadPath)) {
            $io->warning('Le dossier '.$uploadPath.' n’existe pas.');
            return Command::SUCCESS;
        }

        // Récupère toutes les images référencées en DB
        $repo = $this->em->getRepository(CentreKine::class);
        $centres = $repo->findAll();
        $referenced = [];
        foreach ($centres as $c) {
            $path = $c->getImagePrincipale(); // ex: uploads/centres/xxx.jpg
            if ($path) {
                // Normalise pour comparer avec des chemins réels
                $basename = basename($path);
                $referenced[$basename] = true;
            }
        }

        // Liste des fichiers présents sur le disque
        $files = array_filter(scandir($uploadPath) ?: [], function ($f) { return $f !== '.' && $f !== '..'; });
        $removed = 0;
        foreach ($files as $file) {
            $full = $uploadPath.'/'.$file;
            if (!is_file($full)) continue;
            if (!isset($referenced[$file])) {
                // Supprime si non référencé
                @unlink($full);
                $removed++;
                $io->text('Supprimé: '.$file);
            }
        }

        $io->success('Nettoyage terminé. Fichiers supprimés: '.$removed);
        return Command::SUCCESS;
    }
}
