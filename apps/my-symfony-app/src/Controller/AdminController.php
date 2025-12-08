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
