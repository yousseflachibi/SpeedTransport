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

        return $this->render('admin/index.html.twig', [
            'contactForm' => $contactForm->createView(),
            'subscriptionForm' => $subscriptionForm->createView(),
            'services' => $services,
            'categories_kine' => $categories_kine,
        ]);
    }

    /**
     * @Route("/admin/partial/dashboard", name="admin_partial_dashboard")
     */
    public function partialDashboard()
    {
        return $this->render('admin/_dashboard.html.twig');
    }

    /**
     * @Route("/admin/partial/services-kine", name="admin_partial_services_kine")
     */
    public function partialServicesKine()
    {
        $serviceRepo = $this->getDoctrine()->getRepository(\App\Entity\ServiceKine::class);
        $services = $serviceRepo->findAll();
        $categorieRepo = $this->getDoctrine()->getRepository(\App\Entity\CategorieServiceKine::class);
        $categories = $categorieRepo->findAll();
        return $this->render('admin/_services_kine.html.twig', [
            'services' => $services,
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/admin/partial/centres-kine", name="admin_partial_centres_kine")
     */
    public function partialCentresKine()
    {
        $em = $this->getDoctrine()->getManager();
        $centres = $em->getRepository(CentreKine::class)->findAll();
        $services = $em->getRepository(\App\Entity\ServiceKine::class)->findAll();
        $zones = $em->getRepository(ZoneKine::class)->findAll();
        $villes = $em->getRepository(\App\Entity\VilleKine::class)->findBy([], ['nom' => 'ASC']);
        return $this->render('admin/_centres_kine.html.twig', [ 
            'centres' => $centres, 
            'services' => $services,
            'zones' => $zones,
            'villes' => $villes 
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
        $centre->setVille($ville ?: null);
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
            'ville' => $centre->getVille(),
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
            'ville' => $centre->getVille(),
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
        $centre->setVille($request->request->get('ville') ?: null);
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
    public function partialZoneKine()
    {
        $zoneKineRepository = $this->getDoctrine()->getRepository(\App\Entity\ZoneKine::class);
        $zones = $zoneKineRepository->findAll();

        // Charger la liste des villes pour le select dans le modal Zone Kiné
        $villeRepository = $this->getDoctrine()->getRepository(\App\Entity\VilleKine::class);
        $villes = $villeRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/_zone_kine.html.twig', [
            'zones' => $zones,
            'villes' => $villes,
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
    public function partialDemandesKine()
    {
        $em = $this->getDoctrine()->getManager();
        $demandes = $em->getRepository(\App\Entity\DemandeKine::class)->findBy([], ['dateDemande' => 'DESC']);
        
        // Récupérer les zones pour les afficher
        $zones = $em->getRepository(ZoneKine::class)->findAll();
        $zonesMap = [];
        foreach ($zones as $zone) {
            $zonesMap[$zone->getId()] = $zone->getNom();
        }
        // Récupérer les villes pour le select dynamique
        $villes = $em->getRepository(\App\Entity\VilleKine::class)->findBy([], ['nom' => 'ASC']);
        
        return $this->render('admin/_demandes_kine.html.twig', [
            'demandes' => $demandes,
            'zonesMap' => $zonesMap,
            'villes' => $villes
        ]);
    }

    /**
     * @Route("/admin/zones/by-ville", name="admin_zones_by_ville", methods={"GET"})
     */
    public function zonesByVille(Request $request)
    {
        $ville = trim((string)$request->query->get('ville'));
        if ($ville === '') {
            return new JsonResponse(['success' => false, 'message' => 'Paramètre ville manquant'], 400);
        }

        $em = $this->getDoctrine()->getManager();
        $zones = $em->getRepository(ZoneKine::class)->findBy(['ville' => $ville], ['nom' => 'ASC']);
        $data = array_map(function(ZoneKine $z){
            return ['id' => $z->getId(), 'nom' => $z->getNom()];
        }, $zones);

        return new JsonResponse(['success' => true, 'zones' => $data]);
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
        
        // Récupérer les échanges
        $echanges = $em->getRepository(\App\Entity\DemandeKineEchange::class)->findBy(
            ['demandeId' => $id],
            ['dateEchange' => 'ASC']
        );
        
        return $this->render('admin/demande_detail.html.twig', [
            'demande' => $demande,
            'zone' => $zone,
            'echanges' => $echanges,
            // Centres pour affichage sur carte (mapX/mapY)
            'centres' => $em->getRepository(CentreKine::class)->findAll()
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
        $demande->setTraiteParNotreCote((int)$request->request->get('traite_par_notre_cote', 0));
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
        $demande->setIdZone((int)$request->request->get('id_zone') ?: null);
        $demande->setTraiteParNotreCote(0);
        
        $em->persist($demande);
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
}
