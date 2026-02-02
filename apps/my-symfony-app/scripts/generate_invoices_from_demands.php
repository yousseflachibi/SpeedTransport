<?php
use App\Kernel;
use App\Entity\Invoice;
use App\Entity\User;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    (new Dotenv())->loadEnv(__DIR__ . '/../.env');
}

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();
$conn = $container->get('doctrine')->getConnection();

$agentEmail = $argv[1] ?? 'agent1@allokine.com';

// Récupérer l'utilisateur agent
$user = $em->getRepository(User::class)->findOneBy(['email' => $agentEmail]);
if (!$user) {
    echo "Agent not found: $agentEmail\n";
    exit(1);
}

// Calculer totaux par mois depuis les demandes
$sql = "SELECT DATE_FORMAT(d.date_demande, '%Y-%m') AS month, COALESCE(SUM(s.price * d.nombre_seance),0) AS total FROM demande_kine d LEFT JOIN demande_kine_service dks ON dks.demande_id = d.id LEFT JOIN service_kine s ON s.id = dks.service_id WHERE d.nom_agent = :email GROUP BY month";
// DBAL compatibility: use executeQuery for newer versions, fallback to execute + fetchAll
if (method_exists($conn, 'executeQuery')) {
    $rows = $conn->executeQuery($sql, ['email' => $agentEmail])->fetchAllAssociative();
} else {
    $stmt = $conn->prepare($sql);
    $stmt->execute(['email' => $agentEmail]);
    $rows = $stmt->fetchAll();
}

$created = 0;
foreach ($rows as $r) {
    $month = $r['month'];
    $total = $r['total'];
    // vérifier s'il existe déjà une facture pour ce mois et cet agent
    $exists = $em->getRepository(Invoice::class)->createQueryBuilder('i')
        ->select('count(i.id)')
        ->where('i.agent = :agent')
        ->andWhere('i.month = :month')
        ->setParameter('agent', $user)
        ->setParameter('month', $month)
        ->getQuery()->getSingleScalarResult();
    if ($exists > 0) {
        echo "Skipping existing invoice for $agentEmail / $month\n";
        continue;
    }
    $inv = new Invoice();
    $inv->setAgent($user);
    $inv->setMonth($month);
    $inv->setAmount($total);
    $inv->setPaid(false);
    $inv->setDetails('Generated from demandes');
    $em->persist($inv);
    $created++;
}
if ($created > 0) {
    $em->flush();
}

echo "Done. Created: $created invoices for $agentEmail\n";

$kernel->shutdown();
