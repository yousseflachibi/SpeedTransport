<?php
use App\Kernel;
use App\Entity\Invoice;
use App\Entity\User;

require __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$agents = $em->getRepository(User::class)->createQueryBuilder('u')
    ->where('u.roles LIKE :r')->setParameter('r', '%"ROLE_AGENT"%')
    ->getQuery()->getResult();

if (empty($agents)) {
    echo "No agents found\n";
    exit(0);
}

$months = ['2026-01','2026-02','2026-03'];
foreach ($agents as $agent) {
    foreach ($months as $i => $m) {
        $inv = new Invoice();
        $inv->setAgent($agent);
        $inv->setMonth($m);
        $amt = 150 + ($i * 50);
        $inv->setAmount($amt);
        $inv->setPaid($i % 2 === 0);
        $inv->setDetails('Facture seed ' . $m);
        $em->persist($inv);
    }
}
$em->flush();
echo "Seeded invoices for " . count($agents) . " agents\n";
