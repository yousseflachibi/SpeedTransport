<?php

namespace App\Controller;

use App\Entity\ContactUs;
use App\Entity\Subscription;
use App\Form\ContactUsType;
use App\Form\SubscriptionType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Symfony\Component\HttpFoundation\RedirectResponse;
use DateTime;

class landingPageController extends AbstractController {

	/**
	 * @Route("/", name="app_landingpage")
	 */
	public function homepage(Request $request): Response{
        return $this->render('landingpage/homepage.html.twig');
	}

	/**
     * @Route("/processcontactusform", name="app_processcontactusform", methods={"POST"})
     */
    public function processContactUsForm(Request $request): Response
    {
        // Création d'une nouvelle instance de contactUs Entity
        $contactus = new contactUs();

        // Création d'un formulaire pour insérer les données de Task
        $contactForm = $this->createForm(ContactUsType::class, $contactus);

        // Vérification de la soumission du formulaire
        $contactForm->handleRequest($request);

        if ($contactForm->isSubmitted() && $contactForm->isValid()) {
            // Logique pour traiter les données soumises ici
			$entityManager = $this->getDoctrine()->getManager();
			$contactus->setDateAction(new DateTime());
            $entityManager->persist($contactus);
            $entityManager->flush();
			
			$url = $this->generateUrl('app_landingpage', ['successMSG' => 'Votre demande a été enregistrée avec succès!', 'successAction' => 'con']);
			return new RedirectResponse($url);
        }else{
			$contactus = new contactUs();
			$contactForm = $this->createForm(ContactUsType::class, $contactus);
			$subscription = new subscription();
			$subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);
			return $this->render('landingpage/homepage.html.twig', [
				'contactForm' => $contactForm->createView(),
				'subscriptionForm' => $subscriptionForm->createView(),
				'successMSG' => '',
				'successAction' => 'con'
			]);
		}
    }

	/**
     * @Route("/processsubform", name="app_processsubform", methods={"POST"})
     */
    public function processsubform(Request $request): Response
    {
		// Création d'une nouvelle instance de subscription Entity
        $subscription = new subscription();

		// Vérification de la soumission du formulaire
		$subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);

        // Vérification de la soumission du formulaire
        $subscriptionForm->handleRequest($request);

        if ($subscriptionForm->isSubmitted() && $subscriptionForm->isValid()) {
            // Logique pour traiter les données soumises ici
			$entityManager = $this->getDoctrine()->getManager();
			$subscription->setDateAction(new DateTime());
            $entityManager->persist($subscription);
            $entityManager->flush();
			
			$url = $this->generateUrl('app_landingpage', ['successMSG' => 'Votre souscription a été enregistré avec succès!', 'successAction' => 'sub']);
			return new RedirectResponse($url);
        }else{
			$subscription = new subscription();
			$subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);
			return $this->render('landingpage/homepage.html.twig', [
				'subscriptionForm' => $subscriptionForm->createView(),
				'successMSG' => '',
				'successAction' => 'sub'
			]);
		}
    }
}