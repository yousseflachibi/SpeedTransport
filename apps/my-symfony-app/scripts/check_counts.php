<?php
// Usage: php check_counts.php agent_email YYYY-MM
require __DIR__ . '/../config/bootstrap.php';
require __DIR__ . '/../vendor/autoload.php';

use App\Kernel;

$argv = $_SERVER['argv'];
if (count($argv) < 3) {
    echo json_encode(['success' => false, 'message' => 'Usage: php check_counts.php agent_email YYYY-MM']) . PHP_EOL;
    exit(1);
}
$agentEmail = $argv[1];
$month = $argv[2] ?? 'all'; // YYYY-MM or 'all'

try {
    $kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool)($_SERVER['APP_DEBUG'] ?? true));
    $kernel->boot();
    $container = $kernel->getContainer();
    $doctrine = $container->get('doctrine');
    $conn = $doctrine->getConnection();

    $monthsToCheck = [];

    if ($month === 'all' || $month === 'tt') {
        // récupérer tous les mois distincts pour cet agent
        $sqlMonths = "SELECT DISTINCT DATE_FORMAT(d.date_demande, '%Y-%m') AS month FROM demande_kine d WHERE d.nom_agent = :email AND d.date_demande IS NOT NULL ORDER BY month DESC";
        $stmtMonths = $conn->prepare($sqlMonths);
        $rows = $stmtMonths->executeQuery(['email' => $agentEmail])->fetchAllAssociative();
        foreach ($rows as $r) {
            $monthsToCheck[] = $r['month'];
        }
        if (empty($monthsToCheck)) {
            echo json_encode(['success' => false, 'message' => 'Aucun mois trouvé pour cet agent']) . PHP_EOL;
            exit(1);
        }
    } else {
        $monthsToCheck[] = $month;
    }

    $results = [];

    foreach ($monthsToCheck as $m) {
        $monthStart = new \DateTime($m . '-01 00:00:00');
        $monthEnd = (clone $monthStart)->modify('last day of this month 23:59:59');

        // Dashboard logic (accepted/rejected counted by date_fin_demande)
        $sqlDashboard = "
        SELECT 
            SUM(CASE WHEN d.status = 1 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN d.status = 2 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN d.status = 3 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_cours,
            SUM(CASE WHEN d.status = 0 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_attente
        FROM demande_kine d
        WHERE d.nom_agent = :email
    ";
    $stmt = $conn->prepare($sqlDashboard);
    $stmt->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
    $stmt->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
    $stmt->bindValue('email', $agentEmail);
    $resDash = $stmt->executeQuery()->fetchAssociative() ?: [];

        // Raw counts to help debug differences
        $sqlRaw = "
            SELECT 
                SUM(CASE WHEN d.status = 0 THEN 1 ELSE 0 END) AS raw_en_attente,
                SUM(CASE WHEN d.status = 3 THEN 1 ELSE 0 END) AS raw_en_cours,
                SUM(CASE WHEN d.status = 1 THEN 1 ELSE 0 END) AS raw_accepted,
                SUM(CASE WHEN d.status = 2 THEN 1 ELSE 0 END) AS raw_rejected
            FROM demande_kine d
            WHERE d.nom_agent = :email
        ";
        $stmtRaw = $conn->prepare($sqlRaw);
        $stmtRaw->bindValue('email', $agentEmail);
        $rawAll = $stmtRaw->executeQuery()->fetchAssociative() ?: [];

        $sqlRawMonth = "SELECT 
                SUM(CASE WHEN d.status = 0 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS raw_en_attente_month,
                SUM(CASE WHEN d.status = 3 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS raw_en_cours_month,
                SUM(CASE WHEN d.status = 1 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS raw_accepted_month,
                SUM(CASE WHEN d.status = 2 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS raw_rejected_month
            FROM demande_kine d
            WHERE d.nom_agent = :email
        ";
        $stmtRawMonth = $conn->prepare($sqlRawMonth);
        $stmtRawMonth->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
        $stmtRawMonth->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
        $stmtRawMonth->bindValue('email', $agentEmail);
        $rawMonth = $stmtRawMonth->executeQuery()->fetchAssociative() ?: [];

    // Invoice logic (apiAgentWorkMonths): counts by date_demande between start/end
        // Ancien comportement facture: counts by date_demande (historique)
        $sqlInvoiceOld = "
            SELECT 
                SUM(CASE WHEN d.status = 1 THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN d.status = 2 THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN d.status = 3 THEN 1 ELSE 0 END) AS en_cours,
                SUM(CASE WHEN d.status = 0 THEN 1 ELSE 0 END) AS en_attente
            FROM demande_kine d
            WHERE d.nom_agent = :email AND d.date_demande BETWEEN :start AND :end
        ";
        $stmt2 = $conn->prepare($sqlInvoiceOld);
        $stmt2->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
        $stmt2->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
        $stmt2->bindValue('email', $agentEmail);
        $resInvOld = $stmt2->executeQuery()->fetchAssociative() ?: [];

        // Nouveau comportement facture (après correction): accepted/rejected par date_fin_demande
            $sqlInvoiceNew = "
                SELECT 
                    SUM(CASE WHEN d.status = 1 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS accepted,
                    SUM(CASE WHEN d.status = 2 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN d.status = 3 AND d.date_demande <= :end AND (d.date_fin_demande IS NULL OR d.date_fin_demande > :end) THEN 1 ELSE 0 END) AS en_cours,
                    SUM(CASE WHEN d.status = 0 AND d.date_demande <= :end AND (d.date_fin_demande IS NULL OR d.date_fin_demande > :end) THEN 1 ELSE 0 END) AS en_attente
                FROM demande_kine d
                WHERE d.nom_agent = :email
            ";
        $stmt3 = $conn->prepare($sqlInvoiceNew);
        $stmt3->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
        $stmt3->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
        $stmt3->bindValue('email', $agentEmail);
        $resInvNew = $stmt3->executeQuery()->fetchAssociative() ?: [];

        // Prev en_cours / en_attente (demandes créées avant le mois et encore en status correspondant)
        $sqlPrevEnCours = "SELECT COUNT(*) AS cnt FROM demande_kine d WHERE d.nom_agent = :email AND d.date_demande < :monthStart AND d.status = 3";
        $stmtPrevEnCours = $conn->prepare($sqlPrevEnCours);
        $stmtPrevEnCours->bindValue('email', $agentEmail);
        $stmtPrevEnCours->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
        $prevEnCours = (int)($stmtPrevEnCours->executeQuery()->fetchAssociative()['cnt'] ?? 0);

        $sqlPrevPending = "SELECT COUNT(*) AS cnt FROM demande_kine d WHERE d.nom_agent = :email AND d.date_demande < :monthStart AND d.status = 0";
        $stmtPrevPending = $conn->prepare($sqlPrevPending);
        $stmtPrevPending->bindValue('email', $agentEmail);
        $stmtPrevPending->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
        $prevPending = (int)($stmtPrevPending->executeQuery()->fetchAssociative()['cnt'] ?? 0);

        $resInvNew['en_cours'] = (int)($resInvNew['en_cours'] ?? 0) + $prevEnCours;
        $resInvNew['en_attente'] = (int)($resInvNew['en_attente'] ?? 0) + $prevPending;

    $out = [
        'success' => true,
        'agent' => $agentEmail,
        'month' => $month,
        'dashboard_counts' => [
            'accepted' => (int)($resDash['accepted'] ?? 0),
            'rejected' => (int)($resDash['rejected'] ?? 0),
            'en_cours' => (int)($resDash['en_cours'] ?? 0),
            'en_attente' => (int)($resDash['en_attente'] ?? 0),
        ],
        'invoice_counts_old' => [
            'accepted' => (int)($resInvOld['accepted'] ?? 0),
            'rejected' => (int)($resInvOld['rejected'] ?? 0),
            'en_cours' => (int)($resInvOld['en_cours'] ?? 0),
            'en_attente' => (int)($resInvOld['en_attente'] ?? 0),
        ],
        'invoice_counts_new' => [
            'accepted' => (int)($resInvNew['accepted'] ?? 0),
            'rejected' => (int)($resInvNew['rejected'] ?? 0),
            'en_cours' => (int)($resInvNew['en_cours'] ?? 0),
            'en_attente' => (int)($resInvNew['en_attente'] ?? 0),
        ]
    ];

        $results[] = [
            'month' => $m,
            'raw_all' => [
                'en_attente' => (int)($rawAll['raw_en_attente'] ?? 0),
                'en_cours' => (int)($rawAll['raw_en_cours'] ?? 0),
                'accepted' => (int)($rawAll['raw_accepted'] ?? 0),
                'rejected' => (int)($rawAll['raw_rejected'] ?? 0),
            ],
            'raw_month' => [
                'en_attente' => (int)($rawMonth['raw_en_attente_month'] ?? 0),
                'en_cours' => (int)($rawMonth['raw_en_cours_month'] ?? 0),
                'accepted' => (int)($rawMonth['raw_accepted_month'] ?? 0),
                'rejected' => (int)($rawMonth['raw_rejected_month'] ?? 0),
            ],
            'dashboard_counts' => [
                'accepted' => (int)($resDash['accepted'] ?? 0),
                'rejected' => (int)($resDash['rejected'] ?? 0),
                'en_cours' => (int)($resDash['en_cours'] ?? 0),
                'en_attente' => (int)($resDash['en_attente'] ?? 0),
            ],
            'invoice_counts_old' => [
                'accepted' => (int)($resInvOld['accepted'] ?? 0),
                'rejected' => (int)($resInvOld['rejected'] ?? 0),
                'en_cours' => (int)($resInvOld['en_cours'] ?? 0),
                'en_attente' => (int)($resInvOld['en_attente'] ?? 0),
            ],
            'invoice_counts_new' => [
                'accepted' => (int)($resInvNew['accepted'] ?? 0),
                'rejected' => (int)($resInvNew['rejected'] ?? 0),
                'en_cours' => (int)($resInvNew['en_cours'] ?? 0),
                'en_attente' => (int)($resInvNew['en_attente'] ?? 0),
            ]
        ];
    }

    $out['results'] = $results;

    echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    $kernel->shutdown();
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]) . PHP_EOL;
    exit(1);
}
