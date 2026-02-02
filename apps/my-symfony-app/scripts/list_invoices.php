<?php

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "Autoload not found: $autoload\n";
    exit(1);
}
require $autoload;

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Charger les variables d'environnement comme fait par Symfony when using bin/console
if (file_exists(__DIR__ . '/../.env')) {
    (new Dotenv())->loadEnv(__DIR__ . '/../.env');
}

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$doctrine = $container->get('doctrine');
$conn = $doctrine->getConnection();

$sql = "SELECT i.id, i.agent_id, i.month, i.amount, i.paid, u.email, u.full_name FROM invoice i LEFT JOIN `user` u ON u.id = i.agent_id ORDER BY i.agent_id, i.month DESC";
try {
    $stmt = $conn->executeQuery($sql);
    $rows = $stmt->fetchAllAssociative();
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

$kernel->shutdown();
