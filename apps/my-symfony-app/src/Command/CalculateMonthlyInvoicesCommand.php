<?php

namespace App\Command;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CalculateMonthlyInvoicesCommand extends Command
{
    //docker compose exec php bash -lc "cd /usr/src/app && php bin/console app:calculate-invoices"
    
    protected static $defaultName = 'app:calculate-invoices';
    protected static $defaultDescription = 'Calcule et enregistre les comptages de factures pour le mois précédent pour tous les agents';

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->setHelp('Cette commande calcule les comptages de factures pour le mois précédent et les enregistre dans la base de données.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Calcul des factures mensuelles');

        // Déterminer le mois précédent
        $now = new \DateTime();
        $previousMonth = (clone $now)->modify('first day of last month');
        $monthStr = $previousMonth->format('Y-m');

        $io->info("Calcul pour le mois: {$monthStr}");

        // Récupérer tous les utilisateurs et filtrer sur ROLE_AGENT
        $allUsers = $this->entityManager->getRepository(User::class)->findAll();

        // DEBUG : Afficher les rôles de chaque utilisateur
        foreach ($allUsers as $u) {
            $io->text(sprintf('User: %s | Roles: %s', $u->getEmail(), json_encode($u->getRoles())));
        }

        $agents = array_filter($allUsers, function($u) {
            return in_array('ROLE_AGENT', $u->getRoles(), true);
        });
        $io->info("Traitement de " . count($agents) . " agent(s)");

        foreach ($agents as $agent) {
            $this->calculateAgentInvoice($agent, $monthStr, $io);
        }

        // Flush les changements
        $this->entityManager->flush();

        $io->success('Calcul des factures terminé');
        return Command::SUCCESS;
    }

    private function calculateAgentInvoice(User $agent, string $monthStr, SymfonyStyle $io): void
    {
        $monthStart = \DateTime::createFromFormat('Y-m-d', $monthStr . '-01');
        $monthEnd = (clone $monthStart)->modify('last day of this month')->setTime(23, 59, 59);

        $conn = $this->entityManager->getConnection();
        $agentEmail = $agent->getEmail();

        // ========== COMPTAGES MOIS COURANT ==========
        // accepted_current et rejected_current : statut, date de fin ET date de création dans le mois courant
        $sqlCurrentCounts = "
            SELECT 
                SUM(CASE WHEN d.status = 1 AND d.date_fin_demande BETWEEN :start AND :end AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN d.status = 2 AND d.date_fin_demande BETWEEN :start AND :end AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN d.status = 3 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN d.status = 0 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_attente
            FROM demande_kine d
            WHERE d.nom_agent = :email
        ";
        $stmtCurrentCounts = $conn->prepare($sqlCurrentCounts);
        $currentCounts = $stmtCurrentCounts->executeQuery([
            'email' => $agentEmail,
            'start' => $monthStart->format('Y-m-d H:i:s'),
            'end' => $monthEnd->format('Y-m-d H:i:s')
        ])->fetchAssociative();

        // ========== COMPTAGES MOIS PRÉCÉDENTS ==========
        // Demandes créées AVANT ce mois mais finalisées/status durant ce mois
        $monthStartPrev = $monthStart; // Pour les demandes < monthStart
        
        // En cours créé avant ce mois, toujours en cours
        $sqlPrevEnCours = "
            SELECT COUNT(*) AS cnt
            FROM demande_kine d
            WHERE d.nom_agent = :email
              AND d.date_demande < :monthStart
              AND d.status = 3
        ";
        $stmtPrevEnCours = $conn->prepare($sqlPrevEnCours);
        $prevEnCours = (int)($stmtPrevEnCours->executeQuery([
            'email' => $agentEmail,
            'monthStart' => $monthStartPrev->format('Y-m-d H:i:s')
        ])->fetchAssociative()['cnt'] ?? 0);

        // En attente créé avant ce mois, toujours en attente
        $sqlPrevEnAttente = "
            SELECT COUNT(*) AS cnt
            FROM demande_kine d
            WHERE d.nom_agent = :email
              AND d.date_demande < :monthStart
              AND d.status = 0
        ";
        $stmtPrevEnAttente = $conn->prepare($sqlPrevEnAttente);
        $prevEnAttente = (int)($stmtPrevEnAttente->executeQuery([
            'email' => $agentEmail,
            'monthStart' => $monthStartPrev->format('Y-m-d H:i:s')
        ])->fetchAssociative()['cnt'] ?? 0);

        // Accepté finalisé avant ce mois
        $sqlPrevAccepted = "
            SELECT COUNT(*) AS cnt
            FROM demande_kine d
            WHERE d.nom_agent = :email
              AND d.date_demande < :monthStart
              AND d.status = 1
        ";
        $stmtPrevAccepted = $conn->prepare($sqlPrevAccepted);
        $prevAccepted = (int)($stmtPrevAccepted->executeQuery([
            'email' => $agentEmail,
            'monthStart' => $monthStartPrev->format('Y-m-d H:i:s')
        ])->fetchAssociative()['cnt'] ?? 0);

        // Rejeté finalisé avant ce mois
        $sqlPrevRejected = "
            SELECT COUNT(*) AS cnt
            FROM demande_kine d
            WHERE d.nom_agent = :email
              AND d.date_demande < :monthStart
              AND d.status = 2
        ";
        $stmtPrevRejected = $conn->prepare($sqlPrevRejected);
        $prevRejected = (int)($stmtPrevRejected->executeQuery([
            'email' => $agentEmail,
            'monthStart' => $monthStartPrev->format('Y-m-d H:i:s')
        ])->fetchAssociative()['cnt'] ?? 0);

        // Calculer le montant (revenue) pour ce mois
        $sqlRevenue = "
            SELECT COALESCE(SUM(CAST(s.price AS DECIMAL(10,2)) * d.nombre_seance * 0.10), 0) AS revenue
            FROM demande_kine d
            INNER JOIN demande_kine_service dks ON dks.demande_id = d.id
            INNER JOIN service_kine s ON s.id = dks.service_id
            WHERE d.status = 1 AND d.date_demande BETWEEN :start AND :end AND d.nom_agent = :email
        ";
        $stmtRev = $conn->prepare($sqlRevenue);
        $revData = $stmtRev->executeQuery([
            'start' => $monthStart->format('Y-m-d H:i:s'),
            'end' => $monthEnd->format('Y-m-d H:i:s'),
            'email' => $agentEmail
        ])->fetchAssociative();
        $revenue = (float)($revData['revenue'] ?? 0);

        // Chercher ou créer la facture pour ce mois et cet agent
        $invoice = $this->entityManager->getRepository(Invoice::class)->findOneBy([
            'agent' => $agent,
            'month' => $monthStr
        ]);

        if (!$invoice) {
            $invoice = new Invoice();
            $invoice->setAgent($agent);
            $invoice->setMonth($monthStr);
        }

        // Mettre à jour les comptages mois courant
        $invoice->setAcceptedCurrent((int)($currentCounts['accepted'] ?? 0));
        $invoice->setRejectedCurrent((int)($currentCounts['rejected'] ?? 0));
        $invoice->setEnCoursCurrent((int)($currentCounts['en_cours'] ?? 0));
        $invoice->setEnAttenteCurrent((int)($currentCounts['en_attente'] ?? 0));

        // Mettre à jour les comptages mois précédents
        $invoice->setAcceptedPrevious($prevAccepted);
        $invoice->setRejectedPrevious($prevRejected);
        $invoice->setEnCoursPrevious($prevEnCours);
        $invoice->setEnAttentePrevious($prevEnAttente);

        // Montant pour le mois
        $invoice->setAmount($revenue);

        $this->entityManager->persist($invoice);

        $io->text(sprintf(
            "Agent %s (%s): Accepté=%d+%d, Rejeté=%d+%d, En cours=%d+%d, En attente=%d+%d, Montant=%.2f DH",
            $agent->getFullName() ?: $agent->getEmail(),
            $monthStr,
            $invoice->getAcceptedCurrent(),
            $invoice->getAcceptedPrevious(),
            $invoice->getRejectedCurrent(),
            $invoice->getRejectedPrevious(),
            $invoice->getEnCoursCurrent(),
            $invoice->getEnCoursPrevious(),
            $invoice->getEnAttenteCurrent(),
            $invoice->getEnAttentePrevious(),
            $revenue
        ));
    }
}
