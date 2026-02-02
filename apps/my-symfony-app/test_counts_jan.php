<?php
require 'config/bootstrap.php';
require 'vendor/autoload.php';

use App\Kernel;
use App\Entity\User;

$kernel = new Kernel('dev', true);
$kernel->boot();
$em = $kernel->getContainer()->get('doctrine')->getEntityManager();
$agent = $em->getRepository(User::class)->find(1);
$conn = $em->getConnection();

$userEmail = $agent->getEmail();
$monthYear = '2026-01';
$monthStart = \DateTime::createFromFormat('Y-m-d', $monthYear . '-01');
$monthEnd = (clone $monthStart)->modify('last day of this month')->setTime(23, 59, 59);

$sqlCounts = "
SELECT 
    SUM(CASE WHEN d.status = 1 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS accepted,
    SUM(CASE WHEN d.status = 2 AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS rejected,
    SUM(CASE WHEN d.status = 3 AND d.date_demande <= :end AND (d.date_fin_demande IS NULL OR d.date_fin_demande > :end) THEN 1 ELSE 0 END) AS en_cours,
    SUM(CASE WHEN d.status = 0 AND d.date_demande <= :end AND (d.date_fin_demande IS NULL OR d.date_fin_demande > :end) THEN 1 ELSE 0 END) AS en_attente
FROM demande_kine d
WHERE d.nom_agent = :email
";

$stmtCounts = $conn->prepare($sqlCounts);
$counts = $stmtCounts->executeQuery([
    'email' => $userEmail,
    'start' => $monthStart->format('Y-m-d H:i:s'),
    'end' => $monthEnd->format('Y-m-d H:i:s')
])->fetchAssociative();

echo json_encode($counts, JSON_PRETTY_PRINT);
$kernel->shutdown();
