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
        $repo = $this->getDoctrine()->getRepository(CentreKine::class);
        $centres = $repo->findAll();
        return $this->render('admin/_centres_kine.html.twig', [ 'centres' => $centres ]);
    }

    /**
     * @Route("/admin/centre/create", name="admin_centre_create", methods={"POST"})
     */
    public function createCentre(Request $request)
    {
        $nom = trim((string)$request->request->get('nom'));
        $adresse = $request->request->get('adresse');
        $mapX = $request->request->get('map_x');
        $mapY = $request->request->get('map_y');
        if (!$nom) return new JsonResponse(['success' => false, 'message' => 'Le nom est requis'], 400);

        $centre = new CentreKine();
        $centre->setNom($nom);
        $centre->setAdresse($adresse ?: null);
        $centre->setMapX($mapX ?: null);
        $centre->setMapY($mapY ?: null);
        $centre->setDateInscription(new \DateTime());

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

        $em = $this->getDoctrine()->getManager();
        $em->persist($centre);
        $em->flush();

        return new JsonResponse(['success' => true, 'centre' => [
            'id' => $centre->getId(),
            'nom' => $centre->getNom(),
            'adresse' => $centre->getAdresse(),
            'image_principale' => $centre->getImagePrincipale(),
            'map_x' => $centre->getMapX(),
            'map_y' => $centre->getMapY(),
            'date_inscription' => $centre->getDateInscription()->format('Y-m-d H:i:s'),
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
            'image_principale' => $centre->getImagePrincipale(),
            'map_x' => $centre->getMapX(),
            'map_y' => $centre->getMapY(),
            'date_inscription' => $centre->getDateInscription()->format('Y-m-d H:i:s'),
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
        $centre->setMapX($request->request->get('map_x') ?: null);
        $centre->setMapY($request->request->get('map_y') ?: null);

        $file = $request->files->get('image_principale');
        if ($file) {
            // Voir commentaire ci-dessus: écrire dans le dossier public
            $uploadsDir = $this->getParameter('kernel.project_dir').'/public/uploads/centres';
            if (!is_dir($uploadsDir)) @mkdir($uploadsDir, 0775, true);
            $safeName = uniqid('centre_').'.'.$file->guessExtension();
            $file->move($uploadsDir, $safeName);
            $centre->setImagePrincipale('uploads/centres/'.$safeName);
        }

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

        return $this->render('admin/_zone_kine.html.twig', [
            'zones' => $zones,
        ]);
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
}
