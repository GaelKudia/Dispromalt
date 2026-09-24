<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Form\DevisType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * DevisController — UNIQUEMENT création + aperçu.
 * ⚠️ Toutes les routes de validation/décaissement/aiguillage sont dans DashboardController.
 */
class DevisController extends AbstractController
{
    // ============================================================
    // 1. FORMULAIRE DE CRÉATION
    // ============================================================
    #[Route('/dashboard/devis/nouveau', name: 'app_devis_nouveau')]
    public function nouveauDevis(Request $request): Response
    {
        $devis = new Devis();
        $form = $this->createForm(DevisType::class, $devis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entete = $form->has('entete') ? $form->get('entete')->getData() : '';

            $request->getSession()->set('devis_brouillon', [
                'entete'      => $entete ?: 'Nouveau Devis',
                'description' => $devis->getDescription(),
                'montant'     => $devis->getMontant(),
                'devise'      => $form->get('devise')->getData() ?? 'USD',
            ]);
            return $this->redirectToRoute('app_devis_apercu');
        }

        return $this->render('dashboard/devis_form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ============================================================
    // 2. APERÇU + ENREGISTREMENT DÉFINITIF
    // ============================================================
    #[Route('/dashboard/devis/apercu', name: 'app_devis_apercu')]
    public function apercuDevis(Request $request, EntityManagerInterface $em): Response
    {
        $sessionData = $request->getSession()->get('devis_brouillon');
        if (!$sessionData) {
            return $this->redirectToRoute('app_devis_nouveau');
        }

        if ($request->isMethod('POST')) {
            $devis = new Devis();

            if (method_exists($devis, 'setEntete')) {
                $devis->setEntete($sessionData['entete'] ?? 'Nouveau Devis');
            }

            $devis->setDescription($sessionData['description']);
            $devis->setMontant((float) $sessionData['montant']);
            $devis->setMontantInitial((string) $sessionData['montant']);
            $devis->setDevise($sessionData['devise'] ?? 'USD');
            $devis->setCreatedAt(new \DateTime());
            $devis->setUser($this->getUser());
            $devis->setCreatedBy($this->getUser()->getUserIdentifier());

            // 🎯 Statut initial : en attente du chef de service
            $devis->setStatut(Devis::STATUT_ATTENTE_CHEF);

            $em->persist($devis);
            $em->flush();

            $request->getSession()->remove('devis_brouillon');
            $this->addFlash('success', '🚀 Votre devis a été envoyé au circuit de validation.');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('dashboard/devis_apercu.html.twig', [
            'devis' => $sessionData,
        ]);
    }
}