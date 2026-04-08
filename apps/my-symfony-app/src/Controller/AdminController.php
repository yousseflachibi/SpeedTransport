<?php

namespace App\Controller;

use App\Entity\DemandeDevis;
use App\Entity\ContactUs;
use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin_dashboard")
     */
    public function dashboard(EntityManagerInterface $em): Response
    {
        $devisRepo = $em->getRepository(DemandeDevis::class);
        $contactRepo = $em->getRepository(ContactUs::class);
        $subRepo = $em->getRepository(Subscription::class);

        return $this->render('admin/dashboard.html.twig', [
            'totalDevis'       => $devisRepo->count([]),
            'unreadDevis'      => $devisRepo->count(['isRead' => false]),
            'totalContacts'    => $contactRepo->count([]),
            'unreadContacts'   => $contactRepo->count(['isRead' => false]),
            'totalSubs'        => $subRepo->count([]),
            'latestDevis'      => $devisRepo->findBy([], ['dateAction' => 'DESC'], 5),
        ]);
    }

    /**
     * @Route("/admin/devis", name="admin_devis_list")
     */
    public function devisList(EntityManagerInterface $em): Response
    {
        $devis = $em->getRepository(DemandeDevis::class)->findBy([], ['dateAction' => 'DESC']);
        return $this->render('admin/devis_list.html.twig', ['devis' => $devis]);
    }

    /**
     * @Route("/admin/devis/{id}", name="admin_devis_show")
     */
    public function devisShow(int $id, EntityManagerInterface $em): Response
    {
        $devis = $em->getRepository(DemandeDevis::class)->find($id);
        if (!$devis) {
            throw $this->createNotFoundException();
        }
        if (!$devis->getIsRead()) {
            $devis->setIsRead(true);
            $em->flush();
        }
        return $this->render('admin/devis_show.html.twig', ['devis' => $devis]);
    }

    /**
     * @Route("/admin/devis/{id}/delete", name="admin_devis_delete", methods={"POST"})
     */
    public function devisDelete(int $id, EntityManagerInterface $em): Response
    {
        $devis = $em->getRepository(DemandeDevis::class)->find($id);
        if ($devis) {
            $em->remove($devis);
            $em->flush();
        }
        return $this->redirectToRoute('admin_devis_list');
    }

    /**
     * @Route("/admin/contacts", name="admin_contacts_list")
     */
    public function contactsList(EntityManagerInterface $em): Response
    {
        $contacts = $em->getRepository(ContactUs::class)->findBy([], ['dateAction' => 'DESC']);
        return $this->render('admin/contacts_list.html.twig', ['contacts' => $contacts]);
    }

    /**
     * @Route("/admin/contacts/{id}", name="admin_contacts_show")
     */
    public function contactsShow(int $id, EntityManagerInterface $em): Response
    {
        $contact = $em->getRepository(ContactUs::class)->find($id);
        if (!$contact) {
            throw $this->createNotFoundException();
        }
        if (!$contact->getIsRead()) {
            $contact->setIsRead(true);
            $em->flush();
        }
        return $this->render('admin/contacts_show.html.twig', ['contact' => $contact]);
    }

    /**
     * @Route("/admin/subscriptions", name="admin_subs_list")
     */
    public function subscriptionsList(EntityManagerInterface $em): Response
    {
        $subs = $em->getRepository(Subscription::class)->findBy([], ['dateAction' => 'DESC']);
        return $this->render('admin/subs_list.html.twig', ['subs' => $subs]);
    }
}
