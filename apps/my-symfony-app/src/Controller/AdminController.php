<?php

namespace App\Controller;

use App\Entity\ContactUs;
use App\Entity\Subscription;
use App\Form\ContactUsType;
use App\Form\SubscriptionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\ZoneKine;
use App\Entity\VilleKine;
use App\Entity\CentreKine;
use App\Entity\CentreKineImage;
use App\Entity\DemandeKine;
use App\Entity\DemandeKineSeance;
use App\Entity\Invoice;
use App\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin/service/{id}", name="admin_service_get", methods={"GET"})
     */
    public function getService($id)
    {
        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository(\App\Entity\ServiceKine::class)->find($id);
        if (!$service) {
            return new JsonResponse(['success' => false, 'message' => 'Service non trouvé'], 404);
        }
        return new JsonResponse([
            'success' => true,
            'service' => [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'categorie' => $service->getCategorie() ? [
                    'id' => $service->getCategorie()->getId(),
                    'nom' => $service->getCategorie()->getNom()
                ] : null,
                'price' => $service->getPrice(),
            ]
        ]);
    }
    /**
     * @Route("/admin", name="admin_index")
     */
    public function index()
    {
        $contactus = new ContactUs();
        $contactForm = $this->createForm(ContactUsType::class, $contactus);
        $subscription = new Subscription();
        $subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);

        $serviceRepo = $this->getDoctrine()->getRepository(\App\Entity\ServiceKine::class);
        $services = $serviceRepo->findAll();
        $categorieRepo = $this->getDoctrine()->getRepository(\App\Entity\CategorieServiceKine::class);
        $categories_kine = $categorieRepo->findAll();
        
        // Compter les messages Contact Us non lus
        $em = $this->getDoctrine()->getManager();
        $unreadContactCount = $em->getRepository(ContactUs::class)->count(['isRead' => false]);
        $missingServicesCount = $this->calculateMissingServicesCount();

        return $this->render('admin/index.html.twig', [
            'contactForm' => $contactForm->createView(),
            'subscriptionForm' => $subscriptionForm->createView(),
            'services' => $services,
            'categories_kine' => $categories_kine,
            'unreadContactCount' => $unreadContactCount,
            'missingServicesCount' => $missingServicesCount,
        ]);
    }

    /**
     * @Route("/admin/partial/dashboard", name="admin_partial_dashboard")
     */
    public function partialDashboard(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        // Récupérer l'utilisateur connecté
        $currentUser = $this->getUser();
        $userEmail = $currentUser ? $currentUser->getEmail() : null;
        $userId = $currentUser ? $currentUser->getId() : null;
        
        // Récupérer le mois sélectionné ou utiliser le mois en cours
        $selectedMonth = $request->query->get('month');
        
        if ($selectedMonth) {
            // Format attendu: YYYY-MM
            $date = \DateTime::createFromFormat('Y-m', $selectedMonth);
            if ($date) {
                $firstDayOfMonth = new \DateTime($date->format('Y-m-01 00:00:00'));
                $lastDayOfMonth = new \DateTime($date->format('Y-m-t 23:59:59'));
            } else {
                $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
                $lastDayOfMonth = new \DateTime('last day of this month 23:59:59');
            }
        } else {
            $firstDayOfMonth = new \DateTime('first day of this month 00:00:00');
            $lastDayOfMonth = new \DateTime('last day of this month 23:59:59');
        }
        
        // Construire la requête en fonction du rôle de l'utilisateur
        $qb = $em->createQueryBuilder();
        $qb->select('d.status, COUNT(d.id) as count')
           ->from('App\Entity\DemandeKine', 'd')
           ->where('d.dateDemande BETWEEN :start AND :end')
           ->setParameter('start', $firstDayOfMonth)
           ->setParameter('end', $lastDayOfMonth);
        
        // Filtrer par agent si l'utilisateur a le rôle AGENT (et pas ADMIN)
        if ($this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            $qb->andWhere('d.nomAgent = :userEmail')
               ->setParameter('userEmail', $userEmail);
        } elseif ($this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            // Pour les utilisateurs simples, voir leurs propres demandes
            $qb->andWhere('d.idCompte = :userId OR d.nomAgent = :userEmail')
               ->setParameter('userId', $userId)
               ->setParameter('userEmail', $userEmail);
        }
        
        $qb->groupBy('d.status');
        
        $results = $qb->getQuery()->getResult();
        
        // Préparer les données pour le graphique
        $stats = [
            'en_attente' => 0,    // status = 0
            'acceptee' => 0,      // status = 1
            'refusee' => 0,       // status = 2
            'en_cours' => 0       // status = 3
        ];
        
        foreach ($results as $result) {
            switch ($result['status']) {
                case 0:
                    $stats['en_attente'] = (int)$result['count'];
                    break;
                case 1:
                    $stats['acceptee'] = (int)$result['count'];
                    break;
                case 2:
                    $stats['refusee'] = (int)$result['count'];
                    break;
                case 3:
                    $stats['en_cours'] = (int)$result['count'];
                    break;
            }
        }
        
        // Récupérer les mois disponibles depuis la base de données
        $monthsInFrench = [
            'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
            'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
            'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
            'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
        ];
        
        // Requête pour récupérer les mois distincts avec des demandes
        $conn = $em->getConnection();
        $sql = "SELECT DISTINCT DATE_FORMAT(date_demande, '%Y-%m') as month_year 
                FROM demande_kine 
                WHERE date_demande IS NOT NULL 
                ORDER BY month_year DESC";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $monthsData = $result->fetchAllAssociative();
        
        $availableMonths = [];
        foreach ($monthsData as $row) {
            $monthYearValue = $row['month_year'];
            $date = \DateTime::createFromFormat('Y-m', $monthYearValue);
            if ($date) {
                $monthName = $date->format('F');
                $label = $monthsInFrench[$monthName] . ' ' . $date->format('Y');
                $availableMonths[] = [
                    'value' => $monthYearValue,
                    'label' => $label
                ];
            }
        }
        
        // Si aucune demande, ajouter au moins le mois en cours
        if (empty($availableMonths)) {
            $currentDate = new \DateTime();
            $monthName = $currentDate->format('F');
            $availableMonths[] = [
                'value' => $currentDate->format('Y-m'),
                'label' => $monthsInFrench[$monthName] . ' ' . $currentDate->format('Y')
            ];
        }
        
        $currentMonth = $selectedMonth ?: (isset($availableMonths[0]) ? $availableMonths[0]['value'] : (new \DateTime())->format('Y-m'));
        
        // Statistiques par catégorie pour les derniers 90 jours
        $today = new \DateTime();
        $date90DaysAgo = (new \DateTime())->modify('-90 days');
        
        // Utiliser une requête SQL native pour compter les demandes par catégorie
        $conn = $em->getConnection();
        $sqlCategories = "
            SELECT c.nom as categorie, COUNT(DISTINCT dks.demande_id) as total
            FROM categorie_service_kine c
            LEFT JOIN service_kine s ON s.categorie_id = c.id
            LEFT JOIN demande_kine_service dks ON dks.service_id = s.id
            LEFT JOIN demande_kine d ON d.id = dks.demande_id AND d.date_demande >= :date90days
            GROUP BY c.id, c.nom
            ORDER BY total DESC
        ";
        
        $stmt = $conn->prepare($sqlCategories);
        $stmt->bindValue('date90days', $date90DaysAgo->format('Y-m-d H:i:s'));
        $resultCategories = $stmt->executeQuery();
        $categoriesStats = $resultCategories->fetchAllAssociative();
        
        // 1. Évolution des demandes par jour (90 derniers jours)
        $sqlEvolution = "
            SELECT DATE(date_demande) as date, COUNT(*) as total
            FROM demande_kine
            WHERE date_demande >= :date90days
            GROUP BY DATE(date_demande)
            ORDER BY date ASC
        ";
        $stmtEvolution = $conn->prepare($sqlEvolution);
        $stmtEvolution->bindValue('date90days', $date90DaysAgo->format('Y-m-d'));
        $resultEvolution = $stmtEvolution->executeQuery();
        $evolutionData = $resultEvolution->fetchAllAssociative();
        
        // 2. Top 10 villes avec le plus de demandes (90 derniers jours)
        $sqlTopVilles = "
            SELECT v.nom as ville, COUNT(d.id) as total
            FROM demande_kine d
            LEFT JOIN ville_kine v ON v.id = d.id_ville
            WHERE d.date_demande >= :date90days AND v.nom IS NOT NULL
            GROUP BY v.id, v.nom
            ORDER BY total DESC
            LIMIT 10
        ";
        $stmtTopVilles = $conn->prepare($sqlTopVilles);
        $stmtTopVilles->bindValue('date90days', $date90DaysAgo->format('Y-m-d'));
        $resultTopVilles = $stmtTopVilles->executeQuery();
        $topVilles = $resultTopVilles->fetchAllAssociative();
        
        // 3. Revenue estimé (demandes acceptées × prix des services × nombre de séances × 10% commission) - 90 derniers jours
        $sqlRevenue = "
            SELECT COALESCE(SUM(CAST(s.price AS DECIMAL(10,2)) * d.nombre_seance * 0.10), 0) as revenue_total
            FROM demande_kine d
            INNER JOIN demande_kine_service dks ON dks.demande_id = d.id
            INNER JOIN service_kine s ON s.id = dks.service_id
            WHERE d.status = 1 AND d.date_demande >= :date90days AND s.price IS NOT NULL AND d.nombre_seance > 0
        ";
        $stmtRevenue = $conn->prepare($sqlRevenue);
        $stmtRevenue->bindValue('date90days', $date90DaysAgo->format('Y-m-d'));
        $resultRevenue = $stmtRevenue->executeQuery();
        $revenueData = $resultRevenue->fetchAssociative();
        $revenueTotal = (float)($revenueData['revenue_total'] ?? 0);
        
        // Calculer le revenue du mois précédent pour la variation
        $date180DaysAgo = (new \DateTime())->modify('-180 days');
        $sqlRevenuePrevious = "
            SELECT COALESCE(SUM(CAST(s.price AS DECIMAL(10,2)) * d.nombre_seance * 0.10), 0) as revenue_total
            FROM demande_kine d
            INNER JOIN demande_kine_service dks ON dks.demande_id = d.id
            INNER JOIN service_kine s ON s.id = dks.service_id
            WHERE d.status = 1 
            AND d.date_demande >= :date180days 
            AND d.date_demande < :date90days
            AND s.price IS NOT NULL
            AND d.nombre_seance > 0
        ";
        $stmtRevenuePrevious = $conn->prepare($sqlRevenuePrevious);
        $stmtRevenuePrevious->bindValue('date180days', $date180DaysAgo->format('Y-m-d'));
        $stmtRevenuePrevious->bindValue('date90days', $date90DaysAgo->format('Y-m-d'));
        $resultRevenuePrevious = $stmtRevenuePrevious->executeQuery();
        $revenuePreviousData = $resultRevenuePrevious->fetchAssociative();
        $revenuePrevious = (float)($revenuePreviousData['revenue_total'] ?? 0);
        
        // Calculer la variation en pourcentage
        $revenueVariation = 0;
        if ($revenuePrevious > 0) {
            $revenueVariation = (($revenueTotal - $revenuePrevious) / $revenuePrevious) * 100;
        } elseif ($revenueTotal > 0) {
            $revenueVariation = 100;
        }
        
        // 4. Revenue potentiel des demandes en cours (status = 3) - 90 derniers jours
        $sqlRevenueEnCours = "
            SELECT COALESCE(SUM(CAST(s.price AS DECIMAL(10,2)) * d.nombre_seance * 0.10), 0) as revenue_en_cours
            FROM demande_kine d
            INNER JOIN demande_kine_service dks ON dks.demande_id = d.id
            INNER JOIN service_kine s ON s.id = dks.service_id
            WHERE d.status = 3 AND d.date_demande >= :date90days AND s.price IS NOT NULL AND d.nombre_seance > 0
        ";
        $stmtRevenueEnCours = $conn->prepare($sqlRevenueEnCours);
        $stmtRevenueEnCours->bindValue('date90days', $date90DaysAgo->format('Y-m-d'));
        $resultRevenueEnCours = $stmtRevenueEnCours->executeQuery();
        $revenueEnCoursData = $resultRevenueEnCours->fetchAssociative();
        $revenueEnCours = (float)($revenueEnCoursData['revenue_en_cours'] ?? 0);
        
        // Debug: log pour vérifier les données
        error_log('Evolution Data count: ' . count($evolutionData));
        error_log('Top Villes Data count: ' . count($topVilles));
        if (!empty($evolutionData)) {
            error_log('Sample evolution: ' . json_encode($evolutionData[0]));
        }
        if (!empty($topVilles)) {
            error_log('Sample ville: ' . json_encode($topVilles[0]));
        }
        
        // Pour les agents : calculer les statistiques du mois actuel
        $currentMonthStart = new \DateTime('first day of this month 00:00:00');
        $currentMonthEnd = new \DateTime('last day of this month 23:59:59');
        $currentMonthName = $monthsInFrench[$currentMonthStart->format('F')] . ' ' . $currentMonthStart->format('Y');
        
        // Nombre total de patients inscrits ce mois (filtré par agent si nécessaire)
        $sqlTotalCurrentMonth = "SELECT COUNT(*) as total FROM demande_kine WHERE date_demande BETWEEN :start AND :end";
        
        // Ajouter le filtre par agent si l'utilisateur est AGENT (et pas ADMIN)
        if ($this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            $sqlTotalCurrentMonth .= " AND nom_agent = :userEmail";
        } elseif ($this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            $sqlTotalCurrentMonth .= " AND (id_compte = :userId OR nom_agent = :userEmail)";
        }
        
        $stmtTotal = $conn->prepare($sqlTotalCurrentMonth);
        $stmtTotal->bindValue('start', $currentMonthStart->format('Y-m-d H:i:s'));
        $stmtTotal->bindValue('end', $currentMonthEnd->format('Y-m-d H:i:s'));
        
        if ($this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            $stmtTotal->bindValue('userEmail', $userEmail);
        } elseif ($this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            $stmtTotal->bindValue('userId', $userId);
            $stmtTotal->bindValue('userEmail', $userEmail);
        }
        
        $resultTotal = $stmtTotal->executeQuery();
        $totalPatientsCurrentMonth = (int)($resultTotal->fetchAssociative()['total'] ?? 0);

        // Cartes Agent: alimenter depuis la BD uniquement sur les mois où l'agent a des demandes, hors mois en cours
        $agentMonths = [];
        $isAgentOnly = ($this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN'));
        if ($isAgentOnly) {
            $currentMonthVal = (new \DateTime())->format('Y-m');
            $sqlAgentMonths = "
                SELECT DISTINCT DATE_FORMAT(d.date_demande, '%Y-%m') AS month_year
                FROM demande_kine d
                WHERE d.date_demande IS NOT NULL AND d.nom_agent = :userEmail
                ORDER BY month_year DESC
                LIMIT 12
            ";
            $stmtAgentMonths = $conn->prepare($sqlAgentMonths);
            $stmtAgentMonths->bindValue('userEmail', $userEmail);
            $agentMonthsRows = $stmtAgentMonths->executeQuery()->fetchAllAssociative();

            // Exclure explicitement le mois en cours de la liste de travail
            $workMonths = array_values(array_filter($agentMonthsRows, function($r) use ($currentMonthVal){
                return $r['month_year'] !== $currentMonthVal;
            }));
            // Déterminer le mois le plus ancien (pour lequel "mois précédent" doit afficher 0)
            $minMonthVal = null;
            if (!empty($workMonths)) {
                $minMonthVal = min(array_map(function($r){ return $r['month_year']; }, $workMonths));
            }

            foreach ($workMonths as $row) {
                $monthVal = $row['month_year'];
                if ($monthVal === $currentMonthVal) {
                    // Exclure mois en cours du slider
                    continue;
                }
                $monthStart = new \DateTime($monthVal . '-01 00:00:00');
                $monthEnd = new \DateTime($monthVal . '-01 00:00:00');
                $monthEnd->modify('last day of this month 23:59:59');
                $prevMonthStart = (clone $monthStart)->modify('-1 month');
                $prevMonthEnd = (clone $monthEnd)->modify('-1 month');

                $monthNameEn = $monthStart->format('F');
                $label = $monthsInFrench[$monthNameEn] . ' ' . $monthStart->format('Y');

                // Comptages courant (filtré agent)
                // Règle:
                // - Acceptée/Non Acceptée: compter par date_fin_demande dans le mois (finalisation)
                // - En cours/En attente: compter par date_demande du mois (création)
                $sqlCounts = "
                    SELECT 
                        SUM(CASE WHEN d.status = 1 AND d.date_demande BETWEEN :start AND :end AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS accepted,
                        SUM(CASE WHEN d.status = 2 AND d.date_demande BETWEEN :start AND :end AND d.date_fin_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS rejected,
                        SUM(CASE WHEN d.status = 3 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_cours,
                        SUM(CASE WHEN d.status = 0 AND d.date_demande BETWEEN :start AND :end THEN 1 ELSE 0 END) AS en_attente
                    FROM demande_kine d
                    WHERE d.nom_agent = :userEmail
                ";
                $stmtCounts = $conn->prepare($sqlCounts);
                $stmtCounts->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
                $stmtCounts->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
                $stmtCounts->bindValue('userEmail', $userEmail);
                $counts = $stmtCounts->executeQuery()->fetchAssociative() ?: ['accepted'=>0,'rejected'=>0,'en_cours'=>0,'en_attente'=>0];

                // Nouvelle logique "mois précédent" :
                // - En cours : demandes créées avant ce mois, toujours en cours (status=3)
                // - Acceptées : demandes créées avant ce mois, acceptées pendant ce mois (date_fin_demande dans l'intervalle)
                // - Rejetées : demandes créées avant ce mois, rejetées pendant ce mois (date_fin_demande dans l'intervalle)

                // Demandes en cours provenant des mois précédents
                $sqlPrevEnCours = "
                    SELECT COUNT(*) AS cnt
                    FROM demande_kine d
                    WHERE d.nom_agent = :userEmail
                      AND d.date_demande < :monthStart
                      AND d.status = 3
                ";
                $stmtPrevEnCours = $conn->prepare($sqlPrevEnCours);
                $stmtPrevEnCours->bindValue('userEmail', $userEmail);
                $stmtPrevEnCours->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
                $prevEnCours = (int)($stmtPrevEnCours->executeQuery()->fetchAssociative()['cnt'] ?? 0);

                                // Demandes en attente provenant des mois précédents
                                $sqlPrevPending = "
                                        SELECT COUNT(*) AS cnt
                                        FROM demande_kine d
                                        WHERE d.nom_agent = :userEmail
                                            AND d.date_demande < :monthStart
                                            AND d.status = 0
                                ";
                                $stmtPrevPending = $conn->prepare($sqlPrevPending);
                                $stmtPrevPending->bindValue('userEmail', $userEmail);
                                $stmtPrevPending->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
                                $prevPending = (int)($stmtPrevPending->executeQuery()->fetchAssociative()['cnt'] ?? 0);

                // Demandes acceptées ce mois mais créées avant
                $sqlPrevAccepted = "
                    SELECT COUNT(*) AS cnt
                    FROM demande_kine d
                    WHERE d.nom_agent = :userEmail
                      AND d.date_demande < :monthStart
                      AND d.status = 1
                      AND d.date_fin_demande BETWEEN :start AND :end
                ";
                $stmtPrevAccepted = $conn->prepare($sqlPrevAccepted);
                $stmtPrevAccepted->bindValue('userEmail', $userEmail);
                $stmtPrevAccepted->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
                $stmtPrevAccepted->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
                $stmtPrevAccepted->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
                $prevAccepted = (int)($stmtPrevAccepted->executeQuery()->fetchAssociative()['cnt'] ?? 0);

                // Demandes rejetées ce mois mais créées avant
                $sqlPrevRejected = "
                    SELECT COUNT(*) AS cnt
                    FROM demande_kine d
                    WHERE d.nom_agent = :userEmail
                      AND d.date_demande < :monthStart
                      AND d.status = 2
                      AND d.date_fin_demande BETWEEN :start AND :end
                ";
                $stmtPrevRejected = $conn->prepare($sqlPrevRejected);
                $stmtPrevRejected->bindValue('userEmail', $userEmail);
                $stmtPrevRejected->bindValue('monthStart', $monthStart->format('Y-m-d H:i:s'));
                $stmtPrevRejected->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
                $stmtPrevRejected->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
                $prevRejected = (int)($stmtPrevRejected->executeQuery()->fetchAssociative()['cnt'] ?? 0);

                // Premier mois: pas de "mois précédent"
                if (isset($minMonthVal) && $minMonthVal !== null && $monthVal === $minMonthVal) {
                    $prevEnCours = 0;
                    $prevPending = 0;
                    $prevAccepted = 0;
                    $prevRejected = 0;
                }

                // Revenue du mois (acceptées)
                $sqlRevenueMonth = "
                    SELECT COALESCE(SUM(CAST(s.price AS DECIMAL(10,2)) * d.nombre_seance * 0.10), 0) AS revenue
                    FROM demande_kine d
                    INNER JOIN demande_kine_service dks ON dks.demande_id = d.id
                    INNER JOIN service_kine s ON s.id = dks.service_id
                    WHERE d.status = 1 AND d.date_demande BETWEEN :start AND :end AND d.nom_agent = :userEmail
                ";
                $stmtRev = $conn->prepare($sqlRevenueMonth);
                $stmtRev->bindValue('start', $monthStart->format('Y-m-d H:i:s'));
                $stmtRev->bindValue('end', $monthEnd->format('Y-m-d H:i:s'));
                $stmtRev->bindValue('userEmail', $userEmail);
                $revRow = $stmtRev->executeQuery()->fetchAssociative();
                $revenue = (float)($revRow['revenue'] ?? 0);

                // Revenue du mois précédent
                $stmtPrevRev = $conn->prepare(str_replace('BETWEEN :start AND :end', 'BETWEEN :pstart AND :pend', $sqlRevenueMonth));
                $stmtPrevRev->bindValue('pstart', $prevMonthStart->format('Y-m-d H:i:s'));
                $stmtPrevRev->bindValue('pend', $prevMonthEnd->format('Y-m-d H:i:s'));
                $stmtPrevRev->bindValue('userEmail', $userEmail);
                $prevRevRow = $stmtPrevRev->executeQuery()->fetchAssociative();
                $prevRevenue = (float)($prevRevRow['revenue'] ?? 0);

                // Variation et flèche
                $variation = 0.0;
                if ($prevRevenue > 0) {
                    $variation = (($revenue - $prevRevenue) / $prevRevenue) * 100.0;
                } elseif ($revenue > 0) {
                    $variation = 100.0;
                }
                $isPositive = ($variation >= 0);
                $trendArrow = $isPositive ? '▲' : '▼';
                $trendClass = $isPositive ? 'text-success' : 'text-danger';
                $trend = sprintf('%s %s%%', $trendArrow, number_format(abs($variation), 2, '.', ''));

                $valueStr = number_format($revenue, 2, ',', ' ') . ' DH';
                $statusText = ($counts['accepted'] > 0 || $revenue > 0) ? 'Payer' : 'En attente';

                // Debug: tracer les valeurs pour le mois
                error_log(sprintf('[AgentMonths] %s | prev_pending=%d prev_en_cours=%d prev_acc=%d prev_rej=%d curr_acc=%d curr_rej=%d',
                    $label, $prevPending, $prevEnCours, $prevAccepted, $prevRejected,
                    (int)($counts['accepted'] ?? 0), (int)($counts['rejected'] ?? 0)
                ));

                $agentMonths[] = [
                    'label' => $label,
                    'value' => $valueStr,
                    'trend' => $trend,
                    'trendClass' => $trendClass,
                    'accepted' => (int)($counts['accepted'] ?? 0),
                    'rejected' => (int)($counts['rejected'] ?? 0),
                    'status' => $statusText,
                    'prev_en_cours' => $prevEnCours,
                    'prev_pending' => $prevPending,
                    'prev_accepted' => $prevAccepted,
                    'prev_rejected' => $prevRejected,
                    'curr_accepted' => (int)($counts['accepted'] ?? 0),
                    'curr_rejected' => (int)($counts['rejected'] ?? 0),
                ];
            }
        }
        
        return $this->render('admin/_dashboard.html.twig', [
            'demandesStats' => $stats,
            'availableMonths' => $availableMonths,
            'selectedMonth' => $currentMonth,
            'categoriesStats' => $categoriesStats,
            'evolutionData' => $evolutionData,
            'topVilles' => $topVilles,
            'revenueTotal' => $revenueTotal,
            'revenueVariation' => $revenueVariation,
            'revenueEnCours' => $revenueEnCours,
            'currentMonthName' => $currentMonthName,
            'totalPatientsCurrentMonth' => $totalPatientsCurrentMonth,
            'agentMonths' => $agentMonths,
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'is_agent' => $isAgentOnly
        ]);
    }

    /**
     * @Route("/admin/partial/services-kine", name="admin_partial_services_kine")
     */
    public function partialServicesKine(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $serviceRepo = $em->getRepository(\App\Entity\ServiceKine::class);
        $categorieRepo = $em->getRepository(\App\Entity\CategorieServiceKine::class);
        
        // Filtre par catégorie
        $categorieFilter = $request->query->get('categorie', '');
        
        // Pagination
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Construire les critères de recherche
        $criteria = [];
        if (!empty($categorieFilter)) {
            $categorie = $categorieRepo->find($categorieFilter);
            if ($categorie) {
                $criteria['categorie'] = $categorie;
            }
        }
        
        // Compter le total avec filtre
        $total = count($serviceRepo->findBy($criteria));
        $totalPages = (int)ceil($total / $limit);
        
        // Récupérer les services pour la page courante avec filtre
        $services = $serviceRepo->findBy($criteria, ['id' => 'DESC'], $limit, $offset);
        
        // Récupérer toutes les catégories
        $categories = $categorieRepo->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/_services_kine.html.twig', [
            'services' => $services,
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'categorieFilter' => $categorieFilter,
        ]);
    }

    /**
     * @Route("/admin/partial/centres-kine", name="admin_partial_centres_kine")
     */
    public function partialCentresKine(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        // Récupération des filtres
        $villeId = $request->query->get('ville_id');
        $zoneId = $request->query->get('zone_id');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10; // Nombre de centres par page
        
        // Construction de la requête avec filtres
        $qb = $em->getRepository(CentreKine::class)->createQueryBuilder('c')
            ->leftJoin('c.villeKine', 'v')
            ->leftJoin('c.zone', 'z');
        
        if ($villeId) {
            $qb->andWhere('c.villeKine = :villeId')
               ->setParameter('villeId', $villeId);
        }
        
        if ($zoneId) {
            $qb->andWhere('c.zone = :zoneId')
               ->setParameter('zoneId', $zoneId);
        }
        
        // Calcul du total
        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, ceil($total / $limit));
        
        // Pagination
        $centres = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('c.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
        
        $services = $em->getRepository(\App\Entity\ServiceKine::class)->findAll();
        $zones = $em->getRepository(ZoneKine::class)->findBy([], ['nom' => 'ASC']);
        $villes = $em->getRepository(\App\Entity\VilleKine::class)->findBy([], ['nom' => 'ASC']);
        
        // Filtrer les zones par ville si une ville est sélectionnée
        if ($villeId) {
            $villeObj = $em->getRepository(\App\Entity\VilleKine::class)->find($villeId);
            if ($villeObj) {
                $zones = $em->getRepository(ZoneKine::class)->findBy(
                    ['ville' => $villeObj->getNom()],
                    ['nom' => 'ASC']
                );
            }
        }
        
        return $this->render('admin/_centres_kine.html.twig', [ 
            'centres' => $centres, 
            'services' => $services,
            'zones' => $zones,
            'villes' => $villes,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'selectedVilleId' => $villeId,
            'selectedZoneId' => $zoneId
        ]);
    }

    /**
     * @Route("/admin/partial/centre/{id}/gallery", name="admin_partial_centre_gallery")
     */
    public function partialCentreGallery($id)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if(!$centre){
            return $this->render('admin/_centre_gallery.html.twig', [ 'centre' => null, 'images' => [] ]);
        }
        $images = $em->getRepository(CentreKineImage::class)->findBy(['centre' => $centre], ['createdAt' => 'DESC']);
        return $this->render('admin/_centre_gallery.html.twig', [ 'centre' => $centre, 'images' => $images ]);
    }

    /**
     * @Route("/admin/centre/{id}/image/create", name="admin_centre_image_create", methods={"POST"})
     */
    public function createCentreImage($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if(!$centre){ return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404); }
        $url = trim($request->request->get('url', ''));
        if(!$url){ return new JsonResponse(['success' => false, 'message' => 'URL requise'], 400); }
        $img = new CentreKineImage();
        $img->setCentre($centre);
        $img->setUrl($url);
        $img->setCreatedAt(new \DateTime());
        $em->persist($img);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/centre/{id}/image/upload", name="admin_centre_image_upload", methods={"POST"})
     */
    public function uploadCentreImage($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if(!$centre){ return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404); }

        $file = $request->files->get('image');
        if(!$file){ return new JsonResponse(['success' => false, 'message' => 'Fichier requis'], 400); }

        $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/centres/'.$centre->getId().'/gallery';
        if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
        $safeName = uniqid('img_').'.'.$file->guessExtension();
        $file->move($uploadsDir, $safeName);
        $relativePath = 'uploads/centres/'.$centre->getId().'/gallery/'.$safeName;

        $img = new CentreKineImage();
        $img->setCentre($centre);
        $img->setUrl($relativePath);
        $img->setCreatedAt(new \DateTime());
        $em->persist($img);
        $em->flush();

        return new JsonResponse(['success' => true, 'path' => $relativePath]);
    }

    /**
     * @Route("/admin/centre/image/delete/{id}", name="admin_centre_image_delete", methods={"POST"})
     */
    public function deleteCentreImage($id)
    {
        $em = $this->getDoctrine()->getManager();
        $img = $em->getRepository(CentreKineImage::class)->find($id);
        if(!$img){ return new JsonResponse(['success' => false, 'message' => 'Image non trouvée'], 404); }
        $em->remove($img);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/centre/create", name="admin_centre_create", methods={"POST"})
     */
    public function createCentre(Request $request)
    {
        $nom = trim((string)$request->request->get('nom'));
        $adresse = $request->request->get('adresse');
        $ville = $request->request->get('ville');
        $mapX = $request->request->get('map_x');
        $mapY = $request->request->get('map_y');
        $zoneId = $request->request->get('zone_id');
        if (!$nom) return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);

        $em = $this->getDoctrine()->getManager();
        $centre = new CentreKine();
        $centre->setNom($nom);
        $centre->setAdresse($adresse ?: null);
        
        // Associer la ville si fournie
        if ($ville) {
            $villeKine = $em->getRepository(VilleKine::class)->findOneBy(['nom' => $ville]);
            if (!$villeKine) {
                $villeKine = new VilleKine();
                $villeKine->setNom($ville);
                $em->persist($villeKine);
            }
            $centre->setVilleKine($villeKine);
        }
        
        $centre->setMapX($mapX ?: null);
        $centre->setMapY($mapY ?: null);
        $centre->setDateInscription(new \DateTime());
        
        // Associer la zone si fournie
        if ($zoneId) {
            $zone = $em->getRepository(ZoneKine::class)->find($zoneId);
            if ($zone) {
                $centre->setZone($zone);
            }
        }

        // handle file upload
        $file = $request->files->get('image_principale');
        if ($file) {
            // IMPORTANT: Nginx sert le contenu depuis /usr/src/app (montage de apps/my-symfony-app/public)
            // Donc l'upload doit cibler le dossier public directement
            $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/centres';
            if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
            $safeName = uniqid('centre_').'.'.$file->guessExtension();
            $file->move($uploadsDir, $safeName);
            $centre->setImagePrincipale('uploads/centres/'.$safeName);
        }

        $em->persist($centre);

        // attach services if provided
        $serviceIds = (array)$request->request->get('services', []);
        if(!empty($serviceIds)){
            $svcRepo = $em->getRepository(\App\Entity\ServiceKine::class);
            foreach($serviceIds as $sid){
                if(!$sid) continue;
                $svc = $svcRepo->find($sid);
                if($svc) $centre->addService($svc);
            }
        }
        $em->flush();

        return new JsonResponse(['success' => true, 'centre' => [
            'id' => $centre->getId(),
            'nom' => $centre->getNom(),
            'adresse' => $centre->getAdresse(),
            'ville' => $centre->getVilleKine() ? $centre->getVilleKine()->getNom() : null,
            'ville_id' => $centre->getVilleKine() ? $centre->getVilleKine()->getId() : null,
            'image_principale' => $centre->getImagePrincipale(),
            'map_x' => $centre->getMapX(),
            'map_y' => $centre->getMapY(),
            'date_inscription' => $centre->getDateInscription()->format('Y-m-d H:i:s'),
            'zone' => $centre->getZone() ? ['id' => $centre->getZone()->getId(), 'nom' => $centre->getZone()->getNom(), 'ville' => $centre->getZone()->getVille()] : null,
        ]]);
    }

    /**
     * @Route("/admin/centre/{id}", name="admin_centre_get", methods={"GET"})
     */
    public function getCentre($id)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if (!$centre) return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404);
        return new JsonResponse(['success' => true, 'centre' => [
            'id' => $centre->getId(),
            'nom' => $centre->getNom(),
            'adresse' => $centre->getAdresse(),
            'ville' => $centre->getVilleKine() ? $centre->getVilleKine()->getNom() : null,
            'ville_id' => $centre->getVilleKine() ? $centre->getVilleKine()->getId() : null,
            'image_principale' => $centre->getImagePrincipale(),
            'map_x' => $centre->getMapX(),
            'map_y' => $centre->getMapY(),
            'date_inscription' => $centre->getDateInscription()->format('Y-m-d H:i:s'),
            'services' => array_map(function($s){ return [ 'id' => $s->getId(), 'name' => $s->getName() ]; }, $centre->getServices()->toArray()),
            'zone_id' => $centre->getZone() ? $centre->getZone()->getId() : null,
        ]]);
    }

    /**
     * @Route("/admin/centre/update/{id}", name="admin_centre_update", methods={"POST"})
     */
    public function updateCentre(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if (!$centre) return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404);

        $nom = trim((string)$request->request->get('nom'));
        if (!$nom) return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);
        $centre->setNom($nom);
        $centre->setAdresse($request->request->get('adresse') ?: null);
        
        // Associer la ville si fournie
        $ville = $request->request->get('ville');
        if ($ville) {
            $villeKine = $em->getRepository(VilleKine::class)->findOneBy(['nom' => $ville]);
            if (!$villeKine) {
                $villeKine = new VilleKine();
                $villeKine->setNom($ville);
                $em->persist($villeKine);
            }
            $centre->setVilleKine($villeKine);
        } else {
            $centre->setVilleKine(null);
        }
        
        $centre->setMapX($request->request->get('map_x') ?: null);
        $centre->setMapY($request->request->get('map_y') ?: null);
        
        // Associer la zone si fournie
        $zoneId = $request->request->get('zone_id');
        if ($zoneId) {
            $zone = $em->getRepository(ZoneKine::class)->find($zoneId);
            if ($zone) {
                $centre->setZone($zone);
            }
        } else {
            $centre->setZone(null);
        }

        $file = $request->files->get('image_principale');
        if ($file) {
            // Voir commentaire ci-dessus: écrire dans le dossier public
            $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/centres';
            if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
            $safeName = uniqid('centre_').'.'.$file->guessExtension();
            $file->move($uploadsDir, $safeName);
            $centre->setImagePrincipale('uploads/centres/'.$safeName);
        }

        // sync services
        $serviceIds = (array)$request->request->get('services', []);
        $current = $centre->getServices();
        $svcRepo = $em->getRepository(\App\Entity\ServiceKine::class);
        // remove services not in selection
        foreach($current as $svc){ if(!in_array($svc->getId(), $serviceIds)) { $centre->removeService($svc); } }
        // add selected services
        foreach($serviceIds as $sid){ $svc = $svcRepo->find($sid); if($svc) $centre->addService($svc); }

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/centre/delete/{id}", name="admin_centre_delete", methods={"POST"})
     */
    public function deleteCentre($id)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        if (!$centre) return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404);
        $em->remove($centre);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/service/create", name="admin_service_create", methods={"POST"})
     */
    public function createService(Request $request)
    {
        $name = trim((string)$request->request->get('name'));
        $category = trim((string)$request->request->get('category'));
        $price = trim((string)$request->request->get('price'));

        if (!$name) {
            return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        $service = new \App\Entity\ServiceKine();
        $service->setName($name);
        $categorie = $em->getRepository(\App\Entity\CategorieServiceKine::class)->find($category);
        $service->setCategorie($categorie);
        $service->setPrice($price ?: null);

        $em->persist($service);
        $em->flush();

        return new JsonResponse(['success' => true, 'service' => [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'category' => $categorie ? $categorie->getNom() : null,
            'price' => $service->getPrice(),
        ]]);
    }

    /**
     * @Route("/admin/service/update/{id}", name="admin_service_update", methods={"POST"})
     */
    public function updateService(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository(\App\Entity\ServiceKine::class)->find($id);
        if (!$service) {
            return new JsonResponse(['success' => false, 'message' => 'Service non trouvé'], 404);
        }
        $name = trim((string)$request->request->get('name'));
        $category = trim((string)$request->request->get('category'));
        $price = trim((string)$request->request->get('price'));
        if (!$name) {
            return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);
        }
        $service->setName($name);
        if ($category) {
            $categorieObj = $em->getRepository(\App\Entity\CategorieServiceKine::class)->find($category);
            $service->setCategorie($categorieObj);
        } else {
            $service->setCategorie(null);
        }
        $service->setPrice($price ?: null);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/service/delete/{id}", name="admin_service_delete", methods={"POST"})
     */
    public function deleteService($id)
    {
        $em = $this->getDoctrine()->getManager();
        $service = $em->getRepository(\App\Entity\ServiceKine::class)->find($id);
        if (!$service) {
            return new JsonResponse(['success' => false, 'message' => 'Service non trouvé'], 404);
        }
        $em->remove($service);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/partial/zone-kine", name="admin_partial_zone_kine")
     */
    public function partialZoneKine(Request $request)
    {
        $zoneKineRepository = $this->getDoctrine()->getRepository(\App\Entity\ZoneKine::class);
        
        // Filtre par ville
        $villeFilter = $request->query->get('ville', '');
        
        // Pagination
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Construire les critères de recherche
        $criteria = [];
        if (!empty($villeFilter)) {
            $criteria['ville'] = $villeFilter;
        }
        
        // Compter le total avec filtre
        $total = count($zoneKineRepository->findBy($criteria));
        $totalPages = (int)ceil($total / $limit);
        
        // Récupérer les zones pour la page courante avec filtre
        $zones = $zoneKineRepository->findBy($criteria, ['id' => 'DESC'], $limit, $offset);

        // Charger la liste des villes pour le select dans le modal et pour le filtre
        $villeRepository = $this->getDoctrine()->getRepository(\App\Entity\VilleKine::class);
        $villes = $villeRepository->findBy([], ['nom' => 'ASC']);
        
        // Récupérer les villes qui ont des zones pour le filtre
        $em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
        $qb->select('DISTINCT z.ville')
           ->from('App\Entity\ZoneKine', 'z')
           ->orderBy('z.ville', 'ASC');
        $villesAvecZones = array_column($qb->getQuery()->getResult(), 'ville');

        return $this->render('admin/_zone_kine.html.twig', [
            'zones' => $zones,
            'villes' => $villes,
            'villesAvecZones' => $villesAvecZones,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'villeFilter' => $villeFilter,
        ]);
    }

    /**
     * @Route("/admin/partial/users", name="admin_partial_users")
     */
    public function partialUsers(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $role = trim((string)$request->query->get('role', ''));
        $q = trim((string)$request->query->get('q', ''));

        $users = $em->getRepository(\App\Entity\User::class)->findBy([], ['id' => 'DESC']);

        // Filtrage en PHP pour éviter les spécificités JSON MySQL
        $users = array_values(array_filter($users, function($u) use ($role, $q) {
            if ($role) {
                $roles = method_exists($u, 'getRoles') ? $u->getRoles() : [];
                if (!in_array($role, $roles, true)) return false;
            }
            if ($q) {
                $full = method_exists($u, 'getFullName') ? (string)$u->getFullName() : '';
                $email = method_exists($u, 'getEmail') ? (string)$u->getEmail() : '';
                $hay = mb_strtolower($full.' '.$email);
                if (mb_strpos($hay, mb_strtolower($q)) === false) return false;
            }
            return true;
        }));

        // Rôles proposés pour le filtre
        $roleOptions = [
            'ROLE_ADMIN' => 'Administrateur',
            'ROLE_AGENT' => 'Agent',
            'ROLE_USER'  => 'Utilisateur',
        ];

        return $this->render('admin/_users.html.twig', [
            'users' => $users,
            'role' => $role,
            'q' => $q,
            'roleOptions' => $roleOptions,
        ]);
    }

    /**
     * @Route("/admin/user/create", name="admin_user_create", methods={"POST"})
     */
    public function createUser(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $email = trim((string)$request->request->get('email', ''));
        $password = (string)$request->request->get('password', '');
        $fullName = trim((string)$request->request->get('full_name', ''));
        $telephone = trim((string)$request->request->get('telephone', ''));
        $rolesParam = $request->request->get('roles', []);
        $roles = is_array($rolesParam) ? array_values(array_filter($rolesParam)) : array_filter([$rolesParam]);

        if (!$email || !$password) {
            return new JsonResponse(['success' => false, 'message' => 'Email et mot de passe sont requis'], 400);
        }

        $exist = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => $email]);
        if ($exist) {
            return new JsonResponse(['success' => false, 'message' => 'Email déjà utilisé'], 400);
        }

        $user = new \App\Entity\User();
        $user->setEmail($email);
        $user->setFullName($fullName ?: null);
        $user->setTelephone($telephone ?: null);
        $user->setRoles($roles ?: []);
        $user->setPassword(password_hash($password, PASSWORD_BCRYPT));

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['success' => true, 'user' => [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getFullName(),
            'telephone' => $user->getTelephone(),
            'roles' => $user->getRoles(),
        ]]);
    }

    /**
     * @Route("/admin/zone-kine/create", name="admin_zone_kine_create", methods={"POST"})
     */
    public function createZoneKine(Request $request)
    {
        $nom = trim((string)$request->request->get('nom'));
        $prefecture = trim((string)$request->request->get('prefecture'));
        $ville = trim((string)$request->request->get('ville'));
        $codePostal = trim((string)$request->request->get('code_postal'));

        if (!$nom || !$prefecture || !$ville || !$codePostal) {
            return new JsonResponse(['success' => false, 'message' => 'Tous les champs sont requis'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        $zone = new ZoneKine();
        $zone->setNom($nom);
        $zone->setPrefecture($prefecture);
        $zone->setVille($ville);
        $zone->setCodePostal($codePostal);

        $em->persist($zone);
        $em->flush();

        return new JsonResponse(['success' => true, 'zone' => [
            'id' => $zone->getId(),
            'nom' => $zone->getNom(),
            'prefecture' => $zone->getPrefecture(),
            'ville' => $zone->getVille(),
            'code_postal' => $zone->getCodePostal(),
        ]]);
    }

    /**
     * @Route("/admin/zone-kine/delete/{id}", name="admin_zone_kine_delete", methods={"POST"})
     */
    public function deleteZoneKine($id)
    {
        $em = $this->getDoctrine()->getManager();
        $zone = $em->getRepository(ZoneKine::class)->find($id);
        if (!$zone) {
            return new JsonResponse(['success' => false, 'message' => 'Zone non trouvée'], 404);
        }
        $em->remove($zone);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/zone-kine/update/{id}", name="admin_zone_kine_update", methods={"POST"})
     */
    public function updateZoneKine(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $zone = $em->getRepository(ZoneKine::class)->find($id);
        if (!$zone) {
            return new JsonResponse(['success' => false, 'message' => 'Zone non trouvée'], 404);
        }
        $nom = trim((string)$request->request->get('nom'));
        $prefecture = trim((string)$request->request->get('prefecture'));
        $ville = trim((string)$request->request->get('ville'));
        $codePostal = trim((string)$request->request->get('code_postal'));
        if (!$nom || !$prefecture || !$ville || !$codePostal) {
            return new JsonResponse(['success' => false, 'message' => 'Tous les champs sont requis'], 400);
        }
        $zone->setNom($nom);
        $zone->setPrefecture($prefecture);
        $zone->setVille($ville);
        $zone->setCodePostal($codePostal);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/category/{id}", name="admin_category_get", methods={"GET"})
     */
    public function getCategory($id)
    {
        $em = $this->getDoctrine()->getManager();
        $category = $em->getRepository(\App\Entity\CategorieServiceKine::class)->find($id);
        if (!$category) {
            return new JsonResponse(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }
        return new JsonResponse([
            'success' => true,
            'category' => [
                'id' => $category->getId(),
                'nom' => $category->getNom(),
            ]
        ]);
    }

    /**
     * @Route("/admin/category/create", name="admin_category_create", methods={"POST"})
     */
    public function createCategory(Request $request)
    {
        $nom = trim((string)$request->request->get('nom'));
        if (!$nom) {
            return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);
        }
        $em = $this->getDoctrine()->getManager();
        $category = new \App\Entity\CategorieServiceKine();
        $category->setNom($nom);
        $em->persist($category);
        $em->flush();
        return new JsonResponse(['success' => true, 'category' => [
            'id' => $category->getId(),
            'nom' => $category->getNom(),
        ]]);
    }

    /**
     * @Route("/admin/category/update/{id}", name="admin_category_update", methods={"POST"})
     */
    public function updateCategory(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $category = $em->getRepository(\App\Entity\CategorieServiceKine::class)->find($id);
        if (!$category) {
            return new JsonResponse(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }
        $nom = trim((string)$request->request->get('nom'));
        if (!$nom) {
            return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);
        }
        $category->setNom($nom);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/category/delete/{id}", name="admin_category_delete", methods={"POST"})
     */
    public function deleteCategory($id)
    {
        $em = $this->getDoctrine()->getManager();
        $category = $em->getRepository(\App\Entity\CategorieServiceKine::class)->find($id);
        if (!$category) {
            return new JsonResponse(['success' => false, 'message' => 'Catégorie non trouvée'], 404);
        }
        $em->remove($category);
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/admin/partial/demandes-kine", name="admin_partial_demandes_kine")
     */
    public function partialDemandesKine(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        // Pagination
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 15; // 15 demandes par page
        $offset = ($page - 1) * $limit;
        $suiviToday = (bool)$request->query->get('suivi_today');
        $noSuivi = (bool)$request->query->get('no_suivi');
        $statusFilter = $request->query->get('status');
        $statusFilter = ($statusFilter !== null && $statusFilter !== '' && in_array((int)$statusFilter, [0,1,2,3], true)) ? (int)$statusFilter : null;
        
        // Filtrer les demandes selon le rôle de l'utilisateur
        $currentUser = $this->getUser();
        $userEmail = $currentUser ? $currentUser->getEmail() : null;
        $userId = $currentUser ? $currentUser->getId() : null;
        
        // Si l'utilisateur est ADMIN, voir toutes les demandes
        // Si l'utilisateur est AGENT, voir uniquement ses demandes (où il est nomAgent)
        // Si l'utilisateur est USER, voir les demandes affectées à lui (id_compte) OU créées par lui (nom_agent)
        // Construire la portée de base selon le rôle
        if ($this->isGranted('ROLE_ADMIN')) {
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->orderBy('d.dateDemande', 'DESC');
        } elseif ($this->isGranted('ROLE_AGENT')) {
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->where('d.nomAgent = :userEmail')
               ->setParameter('userEmail', $userEmail)
               ->orderBy('d.dateDemande', 'DESC');
        } else {
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->where('d.idCompte = :userId OR d.nomAgent = :userEmail')
               ->setParameter('userId', $userId)
               ->setParameter('userEmail', $userEmail)
               ->orderBy('d.dateDemande', 'DESC');
        }

        // Cloner la portée pour calculer les compteurs indépendamment des toggles
        $scopeQb = clone $qb;

        // Filtre "Date de suivi = aujourd'hui"
        // Filtre "Date de suivi = aujourd'hui" (s'applique seulement si on ne demande pas les sans suivi)
        if ($suiviToday && !$noSuivi) {
            $startToday = (new \DateTime())->setTime(0, 0, 0);
            $endToday = (new \DateTime())->setTime(23, 59, 59);
            $qb->andWhere('d.dateSuivi BETWEEN :startToday AND :endToday')
               ->setParameter('startToday', $startToday)
               ->setParameter('endToday', $endToday);
        }

        // Filtre "Sans date de suivi"
        if ($noSuivi) {
            $qb->andWhere('d.dateSuivi IS NULL');
        }

        // Filtre par statut si demandé (appliqué à la requête principale)
        if ($statusFilter !== null) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $statusFilter);
            // Appliquer aussi au scope pour les compteurs
            $scopeQb->andWhere('d.status = :status')
                    ->setParameter('status', $statusFilter);
        }

        // Compteurs pour filtres (basés sur la portée et éventuellement le statut)
        $startToday = (new \DateTime())->setTime(0, 0, 0);
        $endToday = (new \DateTime())->setTime(23, 59, 59);
        $countSuiviToday = (int)(clone $scopeQb)
            ->select('COUNT(d.id)')
            ->andWhere('d.dateSuivi BETWEEN :startToday AND :endToday')
            ->setParameter('startToday', $startToday)
            ->setParameter('endToday', $endToday)
            ->getQuery()
            ->getSingleScalarResult();
        $countNoSuivi = (int)(clone $scopeQb)
            ->select('COUNT(d.id)')
            ->andWhere('d.dateSuivi IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Compter le total pour la pagination
        $totalCount = (clone $qb)->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, ceil($totalCount / $limit));
        
        // Récupérer les demandes paginées
        $demandes = $qb->setFirstResult($offset)
                      ->setMaxResults($limit)
                      ->getQuery()
                      ->getResult();
        
        // Récupérer les zones pour les afficher
        $zones = $em->getRepository(ZoneKine::class)->findAll();
        $zonesMap = [];
        foreach ($zones as $zone) {
            $zonesMap[$zone->getId()] = $zone->getNom();
        }
        // Récupérer les villes pour le select dynamique
        $villes = $em->getRepository(\App\Entity\VilleKine::class)->findBy([], ['nom' => 'ASC']);
        $villesMap = [];
        foreach ($villes as $ville) {
            $villesMap[$ville->getId()] = $ville->getNom();
        }
        // Récupérer tous les services pour le select multiple
        $services = $em->getRepository(\App\Entity\ServiceKine::class)->findAll();
        
        return $this->render('admin/_demandes_kine.html.twig', [
            'demandes' => $demandes,
            'zonesMap' => $zonesMap,
            'villesMap' => $villesMap,
            'villes' => $villes,
            'services' => $services,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'suiviToday' => $suiviToday,
            'noSuivi' => $noSuivi,
            'statusFilter' => $statusFilter,
            'countSuiviToday' => $countSuiviToday,
            'countNoSuivi' => $countNoSuivi
        ]);
    }

    /**
     * @Route("/admin/partial/missing-services", name="admin_partial_missing_services")
     * @IsGranted("ROLE_ADMIN")
     */
    public function partialMissingServices(Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 15;
        $villeFilterId = $request->query->get('ville');
        $villeFilterId = ($villeFilterId !== null && $villeFilterId !== '') ? (int) $villeFilterId : null;

        // Cartographie des services déjà proposés par zone
        $zoneCoverageRows = $em->createQueryBuilder()
            ->select('IDENTITY(c.zone) AS zoneId', 's.id AS serviceId', 'COUNT(c.id) AS centreCount')
            ->from(CentreKine::class, 'c')
            ->innerJoin('c.services', 's')
            ->groupBy('zoneId', 'serviceId')
            ->getQuery()
            ->getArrayResult();

        $zoneCoverage = [];
        foreach ($zoneCoverageRows as $row) {
            if (!$row['zoneId']) {
                continue;
            }
            $zoneCoverage[$row['zoneId']][$row['serviceId']] = (int) $row['centreCount'];
        }

        // Cartographie des services déjà proposés par ville (fallback si zone absente)
        $villeCoverageRows = $em->createQueryBuilder()
            ->select('IDENTITY(c.villeKine) AS villeId', 's.id AS serviceId', 'COUNT(c.id) AS centreCount')
            ->from(CentreKine::class, 'c')
            ->innerJoin('c.services', 's')
            ->groupBy('villeId', 'serviceId')
            ->getQuery()
            ->getArrayResult();

        $villeCoverage = [];
        foreach ($villeCoverageRows as $row) {
            if (!$row['villeId']) {
                continue;
            }
            $villeCoverage[$row['villeId']][$row['serviceId']] = (int) $row['centreCount'];
        }

        // Demandes associées à leurs services
        $demandeRows = $em->createQueryBuilder()
            ->select('d.id AS demandeId', 'd.idVille AS villeId', 'd.idZone AS zoneId', 's.id AS serviceId', 's.name AS serviceName', 'd.dateDemande AS dateDemande')
            ->from(DemandeKine::class, 'd')
            ->leftJoin('d.services', 's')
            ->getQuery()
            ->getArrayResult();

        $missing = [];
        foreach ($demandeRows as $row) {
            if (!$row['serviceId']) {
                continue;
            }

            $zoneId = $row['zoneId'];
            $villeId = $row['villeId'];
            $serviceId = $row['serviceId'];

            $covered = false;
            if ($zoneId && ($zoneCoverage[$zoneId][$serviceId] ?? 0) > 0) {
                $covered = true;
            }
            if (!$covered && $villeId && ($villeCoverage[$villeId][$serviceId] ?? 0) > 0) {
                $covered = true;
            }

            if ($covered) {
                continue;
            }

            $key = ($zoneId ?: 'none') . '-' . ($villeId ?: 'none') . '-' . $serviceId;
            if (!isset($missing[$key])) {
                $missing[$key] = [
                    'serviceId' => $serviceId,
                    'serviceName' => $row['serviceName'] ?? 'Service inconnu',
                    'zoneId' => $zoneId,
                    'villeId' => $villeId,
                    'demandeCount' => 0,
                    'lastDemandeAt' => null,
                ];
            }

            $missing[$key]['demandeCount']++;

            $date = $row['dateDemande'] ?? null;
            if ($date instanceof \DateTimeInterface) {
                $current = $missing[$key]['lastDemandeAt'];
                if ($current === null || $date > $current) {
                    $missing[$key]['lastDemandeAt'] = $date;
                }
            }
        }

        $zonesMap = [];
        foreach ($em->getRepository(ZoneKine::class)->findAll() as $zone) {
            $zonesMap[$zone->getId()] = [
                'nom' => $zone->getNom(),
                'ville' => $zone->getVille(),
            ];
        }

        $villesMap = [];
        foreach ($em->getRepository(VilleKine::class)->findAll() as $ville) {
            $villesMap[$ville->getId()] = $ville->getNom();
        }

        $missingList = array_values($missing);
        usort($missingList, function (array $a, array $b) {
            return $b['demandeCount'] <=> $a['demandeCount'];
        });

        if ($villeFilterId) {
            $selectedVilleName = $villesMap[$villeFilterId] ?? null;

            // Zones autorisées pour la ville choisie
            $allowedZoneIds = [];
            if ($selectedVilleName) {
                foreach ($zonesMap as $zId => $zData) {
                    if (($zData['ville'] ?? null) === $selectedVilleName) {
                        $allowedZoneIds[] = $zId;
                    }
                }
            }

            $missingList = array_values(array_filter($missingList, function (array $entry) use ($villeFilterId, $allowedZoneIds) {
                // Si la zone est renseignée, elle doit appartenir à la ville sélectionnée
                if ($entry['zoneId']) {
                    return in_array((int) $entry['zoneId'], $allowedZoneIds, true);
                }
                // Sinon, on filtre par villeId
                if ($entry['villeId']) {
                    return (int) $entry['villeId'] === $villeFilterId;
                }
                return false;
            }));
        }

        $totalCount = count($missingList);
        $totalPages = max(1, (int) ceil($totalCount / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $missingList = array_slice($missingList, ($page - 1) * $limit, $limit);

        return $this->render('admin/_missing_services.html.twig', [
            'missingServices' => $missingList,
            'zonesMap' => $zonesMap,
            'villesMap' => $villesMap,
            'villeFilter' => $villeFilterId,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * @Route("/admin/partial/kine-patient", name="admin_partial_kine_patient")
     * @IsGranted("ROLE_ADMIN")
     */
    public function partialKinePatient(Request $request): Response
    {
        $em = $this->getDoctrine()->getManager();

        $selectedCentreId = (int) $request->query->get('centre', 0);
        $selectedMonth = $request->query->get('month'); // format YYYY-MM
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $centres = $em->getRepository(CentreKine::class)->findBy([], ['nom' => 'ASC']);

        // Mois disponibles depuis les demandes
        $monthsData = $em->getConnection()->fetchAllAssociative(
            "SELECT DISTINCT DATE_FORMAT(date_demande, '%Y-%m') AS month_year
             FROM demande_kine
             WHERE date_demande IS NOT NULL
             ORDER BY month_year DESC"
        );
        $availableMonths = array_column($monthsData, 'month_year');

        $demandes = [];
        $selectedCentreServices = [];
        $totalCount = 0;
        $totalPages = 1;
        if ($selectedCentreId > 0) {
            $selectedCentre = $em->getRepository(CentreKine::class)->find($selectedCentreId);
            if ($selectedCentre) {
                foreach ($selectedCentre->getServices() as $service) {
                    $selectedCentreServices[$service->getId()] = $service->getName();
                }
            }

            // Doctrine DQL ne connaît pas JSON_CONTAINS par défaut, on fait une requête SQL pour récupérer les IDs puis on hydrate via DQL
            $conn = $em->getConnection();
            // Support both numeric and string-stored IDs in JSON array
            $params = [
                'cidNum' => json_encode($selectedCentreId),
                'cidStr' => json_encode((string)$selectedCentreId),
            ];
            $sql = "SELECT id FROM demande_kine WHERE centres_assignes IS NOT NULL AND (JSON_CONTAINS(centres_assignes, :cidNum, '$') OR JSON_CONTAINS(centres_assignes, :cidStr, '$'))";

            if ($selectedMonth) {
                $start = \DateTime::createFromFormat('Y-m-d H:i:s', $selectedMonth . '-01 00:00:00') ?: new \DateTime('first day of this month 00:00:00');
                $end = (clone $start)->modify('last day of this month 23:59:59');
                $sql .= " AND date_demande BETWEEN :start AND :end";
                $params['start'] = $start->format('Y-m-d H:i:s');
                $params['end'] = $end->format('Y-m-d H:i:s');
            }

            $ids = array_column($conn->fetchAllAssociative($sql, $params), 'id');
            $totalCount = count($ids);
            $totalPages = $totalCount > 0 ? (int) ceil($totalCount / $limit) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $ids = array_slice($ids, ($page - 1) * $limit, $limit);

            if ($ids) {
                $demandes = $em->getRepository(DemandeKine::class)->createQueryBuilder('d')
                    ->leftJoin('d.services', 's')->addSelect('s')
                    ->where('d.id IN (:ids)')
                    ->setParameter('ids', $ids)
                    ->orderBy('d.dateDemande', 'DESC')
                    ->getQuery()
                    ->getResult();
            }
        }

        $zonesMap = [];
        foreach ($em->getRepository(ZoneKine::class)->findAll() as $zone) {
            $zonesMap[$zone->getId()] = [
                'nom' => $zone->getNom(),
                'ville' => $zone->getVille(),
            ];
        }

        $villesMap = [];
        foreach ($em->getRepository(VilleKine::class)->findAll() as $ville) {
            $villesMap[$ville->getId()] = $ville->getNom();
        }

        return $this->render('admin/_kine_patient.html.twig', [
            'centres' => $centres,
            'selectedCentreId' => $selectedCentreId,
            'selectedMonth' => $selectedMonth,
            'availableMonths' => $availableMonths,
            'demandes' => $demandes,
            'zonesMap' => $zonesMap,
            'villesMap' => $villesMap,
            'selectedCentreServices' => $selectedCentreServices,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * @Route("/admin/zones/by-ville", name="admin_zones_by_ville", methods={"GET"})
     */
    public function zonesByVille(Request $request)
    {
        $villeIdOrName = trim((string)$request->query->get('ville'));
        if ($villeIdOrName === '') {
            return new JsonResponse(['success' => false, 'message' => 'Paramètre ville manquant'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        
        // Si c'est un ID numérique, récupérer le nom de la ville
        if (is_numeric($villeIdOrName)) {
            $villeEntity = $em->getRepository(VilleKine::class)->find((int)$villeIdOrName);
            if (!$villeEntity) {
                return new JsonResponse(['success' => false, 'message' => 'Ville non trouvée'], 404);
            }
            $villeName = $villeEntity->getNom();
        } else {
            $villeName = $villeIdOrName;
        }
        
        $zones = $em->getRepository(ZoneKine::class)->findBy(['ville' => $villeName], ['nom' => 'ASC']);
        $data = array_map(function(ZoneKine $z){
            return ['id' => $z->getId(), 'nom' => $z->getNom()];
        }, $zones);

        return new JsonResponse(['success' => true, 'zones' => $data]);
    }

    /**
     * @Route("/admin/centres/by-zone", name="admin_centres_by_zone", methods={"GET"})
     */
    public function centresByZone(Request $request)
    {
        $zoneId = (int)$request->query->get('zone_id');
        if (!$zoneId) {
            return new JsonResponse(['success' => false, 'message' => 'Paramètre zone_id manquant'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        $zone = $em->getRepository(ZoneKine::class)->find($zoneId);
        
        if (!$zone) {
            return new JsonResponse(['success' => false, 'message' => 'Zone non trouvée'], 404);
        }
        
        $centres = $em->getRepository(CentreKine::class)->findBy(['zone' => $zone], ['nom' => 'ASC']);
        $data = array_map(function(CentreKine $c){
            return [
                'id' => $c->getId(),
                'nom' => $c->getNom(),
                'adresse' => $c->getAdresse()
            ];
        }, $centres);

        return new JsonResponse(['success' => true, 'centres' => $data]);
    }

    /**
     * @Route("/admin/centre/{id}/services", name="admin_centre_services", methods={"GET"})
     */
    public function centreServices($id)
    {
        $em = $this->getDoctrine()->getManager();
        $centre = $em->getRepository(CentreKine::class)->find($id);
        
        if (!$centre) {
            return new JsonResponse(['success' => false, 'message' => 'Centre non trouvé'], 404);
        }
        
        $services = $centre->getServices();
        $data = array_map(function($s){
            return [
                'id' => $s->getId(),
                'name' => $s->getName(),
                'price' => $s->getPrice()
            ];
        }, $services->toArray());

        return new JsonResponse(['success' => true, 'services' => $data]);
    }

    /**
     * @Route("/admin/demande/{id}", name="admin_demande_detail", methods={"GET"})
     */
    public function demandeDetail($id)
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(\App\Entity\DemandeKine::class)->find($id);
        
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée');
        }
        
        // Récupérer la zone si elle existe
        $zone = null;
        if ($demande->getIdZone()) {
            $zone = $em->getRepository(ZoneKine::class)->find($demande->getIdZone());
        }
        
        // Récupérer la ville si elle existe
        $ville = null;
        if ($demande->getIdVille()) {
            $ville = $em->getRepository(VilleKine::class)->find($demande->getIdVille());
        }
        
        // Récupérer les échanges
        $echanges = $em->getRepository(\App\Entity\DemandeKineEchange::class)->findBy(
            ['demandeId' => $id],
            ['dateEchange' => 'ASC']
        );
        
        // Récupérer tous les services pour le select
        $services = $em->getRepository(\App\Entity\ServiceKine::class)->findAll();
        
        // Récupérer les zones de la même ville que la demande pour la recommandation
        $zonesRecommendation = [];
        if ($ville) {
            $zonesRecommendation = $em->getRepository(ZoneKine::class)->findBy(
                ['ville' => $ville->getNom()],
                ['nom' => 'ASC']
            );
        }
        
        // Créer un tableau des villes pour l'affichage
        $villes = [];
        $allVilles = $em->getRepository(VilleKine::class)->findAll();
        foreach ($allVilles as $v) {
            $villes[$v->getId()] = $v->getNom();
        }
        
        // Calculer les recommandations de centres
        $centresRecommandes = [];
        if ($ville && count($demande->getServices()) > 0) {
            $servicesDemandesIds = [];
            foreach ($demande->getServices() as $service) {
                $servicesDemandesIds[] = $service->getId();
            }
            
            // Récupérer tous les centres de la même ville
            $qb = $em->createQueryBuilder();
            $qb->select('c')
               ->from(CentreKine::class, 'c')
               ->leftJoin('c.villeKine', 'v')
               ->where('v.id = :villeId')
               ->setParameter('villeId', $ville->getId());
            
            $centresMemeVille = $qb->getQuery()->getResult();
            
            foreach ($centresMemeVille as $centre) {
                $servicesCommuns = [];
                $servicesIds = [];
                
                foreach ($centre->getServices() as $service) {
                    $servicesIds[] = $service->getId();
                    if (in_array($service->getId(), $servicesDemandesIds)) {
                        $servicesCommuns[] = [
                            'id' => $service->getId(),
                            'nom' => $service->getName(),
                            'prix' => $service->getPrice()
                        ];
                    }
                }
                
                // Garder seulement les centres qui ont au moins 1 service en commun
                if (count($servicesCommuns) > 0) {
                    $memeZone = ($zone && $centre->getZone() && $centre->getZone()->getId() === $zone->getId());
                    
                    $centresRecommandes[] = [
                        'centre' => $centre,
                        'servicesCommuns' => $servicesCommuns,
                        'nombreServicesCommuns' => count($servicesCommuns),
                        'totalServicesDemandes' => count($servicesDemandesIds),
                        'memeZone' => $memeZone,
                        'priorite' => $memeZone ? 1 : 2 // Pour le tri
                    ];
                }
            }
            
            // Trier : même zone d'abord, puis par nombre de services décroissant
            usort($centresRecommandes, function($a, $b) {
                if ($a['priorite'] !== $b['priorite']) {
                    return $a['priorite'] - $b['priorite'];
                }
                return $b['nombreServicesCommuns'] - $a['nombreServicesCommuns'];
            });
        }
        
        return $this->render('admin/demande_detail.html.twig', [
            'demande' => $demande,
            'zone' => $zone,
            'ville' => $ville,
            'echanges' => $echanges,
            // Centres pour affichage sur carte (mapX/mapY)
            'centres' => $em->getRepository(CentreKine::class)->findAll(),
            'services' => $services,
            'zonesRecommendation' => $zonesRecommendation,
            'villes' => $villes,
            'centresRecommandes' => $centresRecommandes
        ]);
    }

    /**
     * @Route("/admin/demande/{id}/assign-centres", name="admin_demande_assign_centres", methods={"POST"})
     */
    public function assignCentres(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(\App\Entity\DemandeKine::class)->find($id);
        
        if (!$demande) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        $centreIds = $data['centreIds'] ?? [];
        
        // Sauvegarder les IDs des centres assignés
        $demande->setCentresAssignes($centreIds);
        $em->flush();
        
        return new JsonResponse([
            'success' => true, 
            'message' => count($centreIds) . ' centre(s) assigné(s) avec succès'
        ]);
    }

    /**
     * @Route("/admin/demande/update/{id}", name="admin_demande_update", methods={"POST"})
     */
    public function updateDemande(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(\App\Entity\DemandeKine::class)->find($id);
        
        if (!$demande) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }
        
        $demande->setNomPrenom($request->request->get('nom_prenom'));
        $demande->setNumeroTele($request->request->get('numero_tele'));
        $demande->setNumeroTeleWtp($request->request->get('numero_tele_wtp'));
        $demande->setCin($request->request->get('cin'));
        $demande->setEmail($request->request->get('email'));
        $newStatus = (int)$request->request->get('status', 0);
        $demande->setStatus($newStatus);
        // Si enregistré avec statut Acceptée (1) ou Refusée (2), fixer la date de fin à maintenant
        if (in_array($newStatus, [1, 2], true)) {
            $demande->setDateFinDemande(new \DateTime());
        }
        $demande->setNombreSeance((int)$request->request->get('nombre_seance', 0));
        $demande->setMotifKine($request->request->get('motif_kine'));
        $demande->setAdresseRejete($request->request->get('adresse_rejete'));
        $demande->setIdVille((int)$request->request->get('id_ville') ?: null);
        $demande->setIdZone((int)$request->request->get('id_zone') ?: null);
        
        // Parse date_suivi if provided
        $dateSuivi = $request->request->get('date_suivi');
        if ($dateSuivi) {
            try {
                $demande->setDateSuivi(new \DateTime($dateSuivi));
            } catch (\Exception $e) {
                $demande->setDateSuivi(null);
            }
        } else {
            $demande->setDateSuivi(null);
        }
        
        // Mettre à jour les services sélectionnés
        // D'abord, supprimer tous les services existants
        foreach ($demande->getServices() as $service) {
            $demande->removeService($service);
        }
        // Puis ajouter les nouveaux services sélectionnés
        $serviceIds = (array)$request->request->get('services', []);
        if (!empty($serviceIds)) {
            $svcRepo = $em->getRepository(\App\Entity\ServiceKine::class);
            foreach ($serviceIds as $sid) {
                if (!$sid) continue;
                $svc = $svcRepo->find($sid);
                if ($svc) $demande->addService($svc);
            }
        }
        
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Demande mise à jour']);
    }

    /**
     * @Route("/admin/demande/create", name="admin_demande_create", methods={"POST"})
     */
    public function createDemande(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $demande = new \App\Entity\DemandeKine();
        
        $demande->setNomPrenom($request->request->get('nom_prenom'));
        $demande->setNumeroTele($request->request->get('numero_tele'));
        $demande->setNumeroTeleWtp($request->request->get('numero_tele_wtp'));
        $demande->setCin($request->request->get('cin'));
        $demande->setEmail($request->request->get('email'));
        $demande->setStatus(0); // En attente par défaut
        $demande->setNombreSeance((int)$request->request->get('nombre_seance', 1));
        $demande->setMotifKine($request->request->get('motif_kine'));
        $demande->setDateDemande(new \DateTime());
        $demande->setIdVille((int)$request->request->get('id_ville') ?: null);
        $demande->setIdZone((int)$request->request->get('id_zone') ?: null);
        
        // Assigner automatiquement l'agent connecté
        $currentUser = $this->getUser();
        if ($currentUser) {
            $demande->setNomAgent($currentUser->getEmail());
        }
        
        // Affecter la demande selon le rôle de l'utilisateur connecté
        if ($currentUser && $this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_AGENT') && !$this->isGranted('ROLE_ADMIN')) {
            // Si c'est un ROLE_USER qui crée, affecter la demande à lui-même
            $demande->setIdCompte($currentUser->getId());
        } else {
            // Si c'est un ADMIN ou AGENT qui crée, affecter à l'utilisateur ayant le moins de demandes
            $userRepo = $em->getRepository(\App\Entity\User::class);
            $demandeRepo = $em->getRepository(\App\Entity\DemandeKine::class);
            
            // Récupérer tous les utilisateurs avec ROLE_USER
            $allUsers = $userRepo->createQueryBuilder('u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%"ROLE_USER"%')
                ->getQuery()
                ->getResult();
            
            if (!empty($allUsers)) {
                $userWithMinDemandes = null;
                $minCount = PHP_INT_MAX;
                
                foreach ($allUsers as $user) {
                    // Compter le nombre de demandes affectées à cet utilisateur
                    $count = $demandeRepo->createQueryBuilder('d')
                        ->select('COUNT(d.id)')
                        ->where('d.idCompte = :userId')
                        ->setParameter('userId', $user->getId())
                        ->getQuery()
                        ->getSingleScalarResult();
                    
                    if ($count < $minCount) {
                        $minCount = $count;
                        $userWithMinDemandes = $user;
                    }
                }
                
                // Affecter la demande à l'utilisateur ayant le moins de demandes
                if ($userWithMinDemandes) {
                    $demande->setIdCompte($userWithMinDemandes->getId());
                }
            }
        }
        
        $em->persist($demande);

        // Associer les services sélectionnés
        $serviceIds = (array)$request->request->get('services', []);
        if (!empty($serviceIds)) {
            $svcRepo = $em->getRepository(\App\Entity\ServiceKine::class);
            foreach ($serviceIds as $sid) {
                if (!$sid) continue;
                $svc = $svcRepo->find($sid);
                if ($svc) $demande->addService($svc);
            }
        }
        
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Demande créée avec succès']);
    }

    /**
     * @Route("/admin/demande/{id}/echange/add", name="admin_demande_echange_add", methods={"POST"})
     */
    public function addEchange(Request $request, $id)
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(\App\Entity\DemandeKine::class)->find($id);
        
        if (!$demande) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }
        
        $type = $request->request->get('type');
        $commentaire = $request->request->get('commentaire');
        
        if (!$type || !$commentaire) {
            return new JsonResponse(['success' => false, 'message' => 'Type et commentaire requis'], 400);
        }
        
        $echange = new \App\Entity\DemandeKineEchange();
        $echange->setDemandeId($id);
        $echange->setType($type);
        $echange->setCommentaire($commentaire);
        $echange->setDateEchange(new \DateTime());
        $echange->setAuteur('Admin'); // À adapter selon l'utilisateur connecté
        
        $em->persist($echange);
        $em->flush();
        
        return new JsonResponse([
            'success' => true,
            'echange' => [
                'id' => $echange->getId(),
                'type' => $echange->getType(),
                'commentaire' => $echange->getCommentaire(),
                'dateEchange' => $echange->getDateEchange()->format('Y-m-d H:i'),
                'auteur' => $echange->getAuteur()
            ]
        ]);
    }

    /**
     * @Route("/admin/demande/{id}/seances", name="admin_demande_seances_add", methods={"POST"})
     */
    public function addSeance(Request $request, int $id): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(DemandeKine::class)->find($id);

        if (!$demande) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }

        $dateInput = trim((string) $request->request->get('date_seance', ''));
        $commentaire = trim((string) $request->request->get('commentaire', ''));
        $ratingRaw = $request->request->get('rating');

        $dateSeance = $dateInput
            ? \DateTime::createFromFormat('Y-m-d\TH:i', $dateInput)
            : new \DateTime();

        if (!$dateSeance) {
            return new JsonResponse(['success' => false, 'message' => 'Date de séance invalide'], 400);
        }

        $rating = null;
        if ($ratingRaw !== null && $ratingRaw !== '') {
            $rating = (int) $ratingRaw;
            if ($rating < 1 || $rating > 5) {
                return new JsonResponse(['success' => false, 'message' => 'Note invalide (1 à 5)'], 400);
            }
        }

        $seance = new DemandeKineSeance();
        $seance->setDemande($demande);
        $seance->setDateSeance($dateSeance);
        if ($commentaire !== '') {
            $seance->setCommentaire($commentaire);
        }
        if ($rating !== null) {
            $seance->setRating($rating);
        }

        $em->persist($seance);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'seance' => [
                'id' => $seance->getId(),
                'dateSeance' => $seance->getDateSeance()->format('Y-m-d H:i'),
                'commentaire' => $seance->getCommentaire(),
                'rating' => $seance->getRating(),
                'createdAt' => $seance->getCreatedAt()->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * @Route("/admin/demande/{id}/seances", name="admin_demande_seances_list", methods={"GET"})
     */
    public function listSeances(int $id): JsonResponse
    {
        $em = $this->getDoctrine()->getManager();
        $demande = $em->getRepository(DemandeKine::class)->find($id);

        if (!$demande) {
            return new JsonResponse(['success' => false, 'message' => 'Demande non trouvée'], 404);
        }

        $seances = $em->getRepository(DemandeKineSeance::class)->findBy([
            'demande' => $demande,
        ], ['dateSeance' => 'DESC']);

        $payload = array_map(function (DemandeKineSeance $s) {
            return [
                'id' => $s->getId(),
                'dateSeance' => $s->getDateSeance()->format('Y-m-d H:i'),
                'commentaire' => $s->getCommentaire(),
                'rating' => $s->getRating(),
                'createdAt' => $s->getCreatedAt()->format('Y-m-d H:i'),
            ];
        }, $seances);

        return new JsonResponse(['success' => true, 'seances' => $payload]);
    }

    /**
     * @Route("/admin/partial/contact-us", name="admin_partial_contact_us")
     */
    public function partialContactUs(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 15; // Nombre de contacts par page
        $sujetFilter = $request->query->get('sujet');
        $hasDemande = $request->query->get('has_demande', 'all'); // all|yes|no
        
        // Construction de la requête avec filtre
        $qb = $em->getRepository(ContactUs::class)->createQueryBuilder('c');
        
        if ($sujetFilter && $sujetFilter !== '') {
            $qb->where('c.choiceList = :sujet')
               ->setParameter('sujet', $sujetFilter);
        }
        
        // Calcul des contacts liés à une demande
        $linkedRows = $em->getRepository(\App\Entity\DemandeKine::class)
            ->createQueryBuilder('d')
            ->select('d.idContactUs, d.id')
            ->where('d.idContactUs IS NOT NULL')
            ->getQuery()
            ->getArrayResult();
        $linkedContactIds = [];
        $linkedMap = [];
        foreach ($linkedRows as $r) {
            $cid = (int)($r['idContactUs'] ?? 0);
            $did = (int)($r['id'] ?? 0);
            if ($cid) {
                $linkedContactIds[] = $cid;
                $linkedMap[$cid] = $did;
            }
        }
        
        if ($hasDemande === 'yes') {
            if (!empty($linkedContactIds)) {
                $qb->andWhere('c.id IN (:linked)')->setParameter('linked', $linkedContactIds);
            } else {
                // Aucun lié: forcer résultat vide
                $qb->andWhere('1 = 0');
            }
        } elseif ($hasDemande === 'no') {
            if (!empty($linkedContactIds)) {
                $qb->andWhere('c.id NOT IN (:linked)')->setParameter('linked', $linkedContactIds);
            }
        }
        
        // Calcul du total
        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, ceil($total / $limit));
        
        // Pagination
        $contacts = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('c.dateAction', 'DESC')
            ->getQuery()
            ->getResult();
        
        return $this->render('admin/_contact_us.html.twig', [
            'contacts' => $contacts,
            'total' => $total,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'sujetFilter' => $sujetFilter,
            'hasDemande' => $hasDemande,
            'linkedContactIds' => $linkedContactIds,
            'linkedMap' => $linkedMap
        ]);
    }

    /**
     * @Route("/admin/contact-us/{id}/create-demande", name="admin_contact_us_create_demande", methods={"POST"})
     */
    public function createDemandeFromContact($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $contact = $em->getRepository(ContactUs::class)->find($id);
        if (!$contact) {
            return new JsonResponse(['success' => false, 'message' => 'Contact non trouvé'], 404);
        }
        
        // Vérifier si déjà lié
        $existing = $em->getRepository(\App\Entity\DemandeKine::class)->findOneBy(['idContactUs' => $contact->getId()]);
        if ($existing) {
            return new JsonResponse(['success' => false, 'message' => 'Demande déjà créée pour ce contact'], 400);
        }
        
        $demande = new \App\Entity\DemandeKine();
        $demande->setNomPrenom($contact->getFullName());
        $demande->setEmail($contact->getEmail());
        $demande->setNumeroTele($contact->getPhoneNumber());
        $demande->setMotifKine($contact->getDescription());
        $demande->setDateDemande(new \DateTime());
        $demande->setStatus(0);
        // Valeur par défaut pour éviter NULL en base
        $demande->setNombreSeance(0);
        $demande->setIdContactUs($contact->getId());
        
        // Ville par nom si trouvée
        if ($contact->getCity()) {
            $ville = $em->getRepository(\App\Entity\VilleKine::class)->findOneBy(['nom' => $contact->getCity()]);
            if ($ville) $demande->setIdVille($ville->getId());
        }
        
        // Agent = utilisateur courant
        $currentUser = $this->getUser();
        if ($currentUser) {
            $demande->setNomAgent($currentUser->getEmail());
        }
        
        // Affecter à l'utilisateur ROLE_USER le moins chargé
        $userRepo = $em->getRepository(\App\Entity\User::class);
        $demandeRepo = $em->getRepository(\App\Entity\DemandeKine::class);
        $users = $userRepo->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_USER"%')
            ->getQuery()->getResult();
        
        if (!empty($users)) {
            $minUser = null; $minCount = PHP_INT_MAX;
            foreach ($users as $u) {
                $cnt = (int)$demandeRepo->createQueryBuilder('d')
                    ->select('COUNT(d.id)')
                    ->where('d.idCompte = :uid')
                    ->setParameter('uid', $u->getId())
                    ->getQuery()->getSingleScalarResult();
                if ($cnt < $minCount) { $minCount = $cnt; $minUser = $u; }
            }
            if ($minUser) $demande->setIdCompte($minUser->getId());
        }
        
        $em->persist($demande);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Demande créée', 'demandeId' => $demande->getId()]);
    }

    /**
     * @Route("/admin/contact-us/{id}/mark-read", name="admin_contact_us_mark_read", methods={"POST"})
     */
    public function markContactAsRead($id)
    {
        $em = $this->getDoctrine()->getManager();
        $contact = $em->getRepository(ContactUs::class)->find($id);
        
        if (!$contact) {
            return new JsonResponse(['success' => false, 'message' => 'Contact non trouvé'], 404);
        }
        
        $contact->setIsRead(true);
        $em->flush();
        
        // Compter les messages non lus
        $unreadCount = $em->getRepository(ContactUs::class)->count(['isRead' => false]);
        
        return new JsonResponse([
            'success' => true,
            'unreadCount' => $unreadCount
        ]);
    }

    /**
     * @Route("/admin/partial/facture", name="admin_partial_facture")
     */
    public function partialFacture(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $agents = $em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_AGENT"%')
            ->getQuery()
            ->getResult();

        return $this->render('admin/_facture.html.twig', [
            'agents' => $agents
        ]);
    }

    /**
     * @Route("/admin/api/invoices", name="admin_api_invoices")
     */
    public function apiInvoices(Request $request)
    {
        $agentId = (int)$request->query->get('agentId');
        if (!$agentId) {
            return new JsonResponse(['success' => false, 'message' => 'Agent manquant']);
        }
        $em = $this->getDoctrine()->getManager();
        $invoices = $em->getRepository(Invoice::class)->findBy(['agent' => $agentId], ['month' => 'DESC']);
        $payload = array_map(function(Invoice $i) {
            return [
                'id' => $i->getId(),
                'month' => $i->getMonth(),
                'amount' => (string)$i->getAmount(),
                'paid' => $i->isPaid(),
            ];
        }, $invoices);
        return new JsonResponse(['success' => true, 'invoices' => $payload]);
    }

    /**
     * @Route("/admin/api/invoice/{id}", name="admin_api_invoice_detail")
     */
    public function apiInvoiceDetail($id)
    {
        $em = $this->getDoctrine()->getManager();
        $inv = $em->getRepository(Invoice::class)->find($id);
        if (!$inv) return new JsonResponse(['success' => false, 'message' => 'Facture non trouvée'], 404);
        return new JsonResponse(['success' => true, 'invoice' => [
            'id' => $inv->getId(),
            'month' => $inv->getMonth(),
            'amount' => (string)$inv->getAmount(),
            'paid' => $inv->isPaid(),
            'details' => $inv->getDetails(),
            'createdAt' => $inv->getCreatedAt()->format('Y-m-d H:i:s')
        ]]);
    }

    private function calculateMissingServicesCount(): int
    {
        $em = $this->getDoctrine()->getManager();

        $zoneCoverageRows = $em->createQueryBuilder()
            ->select('IDENTITY(c.zone) AS zoneId', 's.id AS serviceId', 'COUNT(c.id) AS centreCount')
            ->from(CentreKine::class, 'c')
            ->innerJoin('c.services', 's')
            ->groupBy('zoneId', 'serviceId')
            ->getQuery()
            ->getArrayResult();

        $zoneCoverage = [];
        foreach ($zoneCoverageRows as $row) {
            if (!$row['zoneId']) {
                continue;
            }
            $zoneCoverage[$row['zoneId']][$row['serviceId']] = (int) $row['centreCount'];
        }

        $villeCoverageRows = $em->createQueryBuilder()
            ->select('IDENTITY(c.villeKine) AS villeId', 's.id AS serviceId', 'COUNT(c.id) AS centreCount')
            ->from(CentreKine::class, 'c')
            ->innerJoin('c.services', 's')
            ->groupBy('villeId', 'serviceId')
            ->getQuery()
            ->getArrayResult();

        $villeCoverage = [];
        foreach ($villeCoverageRows as $row) {
            if (!$row['villeId']) {
                continue;
            }
            $villeCoverage[$row['villeId']][$row['serviceId']] = (int) $row['centreCount'];
        }

        $demandeRows = $em->createQueryBuilder()
            ->select('d.idVille AS villeId', 'd.idZone AS zoneId', 's.id AS serviceId')
            ->from(DemandeKine::class, 'd')
            ->leftJoin('d.services', 's')
            ->getQuery()
            ->getArrayResult();

        $missing = [];
        foreach ($demandeRows as $row) {
            if (!$row['serviceId']) {
                continue;
            }

            $zoneId = $row['zoneId'];
            $villeId = $row['villeId'];
            $serviceId = $row['serviceId'];

            $covered = false;
            if ($zoneId && ($zoneCoverage[$zoneId][$serviceId] ?? 0) > 0) {
                $covered = true;
            }
            if (!$covered && $villeId && ($villeCoverage[$villeId][$serviceId] ?? 0) > 0) {
                $covered = true;
            }

            if ($covered) {
                continue;
            }

            $key = ($zoneId ?: 'none') . '-' . ($villeId ?: 'none') . '-' . $serviceId;
            $missing[$key] = true;
        }

        return count($missing);
    }
}
