<?php

namespace App\Controller;

use App\Entity\ContactUs;
use App\Entity\Subscription;
use App\Form\ContactUsType;
use App\Form\SubscriptionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\ZoneKine;
use App\Entity\VilleKine;
use App\Entity\CentreKine;
use App\Entity\CentreKineImage;

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

        return $this->render('admin/index.html.twig', [
            'contactForm' => $contactForm->createView(),
            'subscriptionForm' => $subscriptionForm->createView(),
            'services' => $services,
            'categories_kine' => $categories_kine,
            'unreadContactCount' => $unreadContactCount,
        ]);
    }

    /**
     * @Route("/admin/partial/dashboard", name="admin_partial_dashboard")
     */
    public function partialDashboard(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
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
        
        $qb = $em->createQueryBuilder();
        $qb->select('d.status, COUNT(d.id) as count')
           ->from('App\Entity\DemandeKine', 'd')
           ->where('d.dateDemande BETWEEN :start AND :end')
           ->setParameter('start', $firstDayOfMonth)
           ->setParameter('end', $lastDayOfMonth)
           ->groupBy('d.status');
        
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
        
        return $this->render('admin/_dashboard.html.twig', [
            'demandesStats' => $stats,
            'availableMonths' => $availableMonths,
            'selectedMonth' => $currentMonth,
            'categoriesStats' => $categoriesStats,
            'evolutionData' => $evolutionData,
            'topVilles' => $topVilles,
            'revenueTotal' => $revenueTotal,
            'revenueVariation' => $revenueVariation,
            'revenueEnCours' => $revenueEnCours
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
        if ($this->isGranted('ROLE_ADMIN')) {
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->orderBy('d.dateDemande', 'DESC');
        } elseif ($this->isGranted('ROLE_AGENT')) {
            // Pour les agents, filtrer par nomAgent (email de l'agent)
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->where('d.nomAgent = :userEmail')
               ->setParameter('userEmail', $userEmail)
               ->orderBy('d.dateDemande', 'DESC');
        } else {
            // Pour les ROLE_USER, voir les demandes affectées à eux (id_compte) OU créées par eux (nom_agent)
            $qb = $em->getRepository(\App\Entity\DemandeKine::class)->createQueryBuilder('d');
            $qb->where('d.idCompte = :userId OR d.nomAgent = :userEmail')
               ->setParameter('userId', $userId)
               ->setParameter('userEmail', $userEmail)
               ->orderBy('d.dateDemande', 'DESC');
        }

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

        // Filtre par statut si demandé
        if ($statusFilter !== null) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $statusFilter);
        }
        
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
            'statusFilter' => $statusFilter
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
        $demande->setStatus((int)$request->request->get('status', 0));
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
     * @Route("/admin/partial/contact-us", name="admin_partial_contact_us")
     */
    public function partialContactUs(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 15; // Nombre de contacts par page
        $sujetFilter = $request->query->get('sujet');
        
        // Construction de la requête avec filtre
        $qb = $em->getRepository(ContactUs::class)->createQueryBuilder('c');
        
        if ($sujetFilter && $sujetFilter !== '') {
            $qb->where('c.choiceList = :sujet')
               ->setParameter('sujet', $sujetFilter);
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
            'sujetFilter' => $sujetFilter
        ]);
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
}
