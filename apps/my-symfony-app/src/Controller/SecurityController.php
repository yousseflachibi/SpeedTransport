<?php

namespace App\Controller;

use App\Entity\ContactUs;
use App\Entity\Subscription;
use App\Form\ContactUsType;
use App\Form\SubscriptionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // create an unnamed form so input names are flat: `email` and `password`
        $loginForm = $this->get('form.factory')->createNamed('', \App\Form\LoginFormType::class, [
            'email' => $lastUsername
        ]);

        $contactus = new ContactUs();
        $contactForm = $this->createForm(ContactUsType::class, $contactus);

        $subscription = new Subscription();
        $subscriptionForm = $this->createForm(SubscriptionType::class, $subscription);

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'loginForm' => $loginForm->createView(),
            'contactForm' => $contactForm->createView(),
            'subscriptionForm' => $subscriptionForm->createView(),
        ]);
    }

    /**
     * This is the route the user can use to logout
     *
     * @Route("/logout", name="logout")
     */
    public function logout()
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall');
    }
}
