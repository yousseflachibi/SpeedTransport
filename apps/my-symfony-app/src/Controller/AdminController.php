<?php

namespace App\Controller;

use App\Entity\ContactUs;
use App\Entity\Subscription;
use App\Form\ContactUsType;
use App\Form\SubscriptionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_index")
     */
    public function index()
    {
        $contactus = new ContactUs();
        $contactForm = $this->createForm(ContactUsType::class, $contactus);

        $subscription = new Subscription();
        $subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);

        return $this->render('admin/index.html.twig', [
            'contactForm' => $contactForm->createView(),
            'subscriptionForm' => $subscriptionForm->createView(),
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
        return $this->render('admin/_services_kine.html.twig');
    }

    /**
     * @Route("/admin/partial/zone-kine", name="admin_partial_zone_kine")
     */
    public function partialZoneKine()
    {
        return $this->render('admin/_zone_kine.html.twig');
    }
}
