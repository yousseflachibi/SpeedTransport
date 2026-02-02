<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SeedInvoicesCommand extends Command
{
    protected static $defaultName = 'app:seed:invoices';
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure()
    {
        $this->setDescription('Seed sample invoices for agents');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $agents = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :r')->setParameter('r', '%"ROLE_AGENT"%')
            ->getQuery()->getResult();

        if (empty($agents)) {
            $io->warning('No agents found');
            return Command::SUCCESS;
        }

        $months = ['2026-01','2026-02','2026-03'];
        foreach ($agents as $agent) {
            foreach ($months as $i => $m) {
                $inv = new Invoice();
                $inv->setAgent($agent);
                $inv->setMonth($m);
                $inv->setAmount(150 + ($i * 50));
                $inv->setPaid($i % 2 === 0);
                $inv->setDetails('Facture seed ' . $m);
                $this->em->persist($inv);
            }
        }
        $this->em->flush();
        $io->success('Invoices seeded for ' . count($agents) . ' agents');
        return Command::SUCCESS;
    }
}
