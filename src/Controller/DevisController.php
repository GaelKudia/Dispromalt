<?php

namespace App\Controller;

use App\Entity\Devis;
use App\Form\DevisType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * DevisController — création + aperçu uniquement.
 * Le reste (validation / décaissement / aiguillage) est dans DashboardController.
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
        $form  = $this->createForm(DevisType::class, $devis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entete = $form->has('entete') ? ($form->get('entete')->getData() ?? '') : '';

            // ✅ Récupération du délai de travaux (jours)
            $delaiJours = 0;
            if ($form->has('delaiJours')) {
                $delaiJours = (int) $form->get('delaiJours')->getData();
            }

            // ✅ On stocke TOUT en session (y compris le délai)
            $request->getSession()->set('devis_brouillon', [
                'entete'      => $entete !== '' ? $entete : 'Nouveau Devis',
                'description' => $devis->getDescription(),
                'montant'     => $devis->getMontant(),
                'devise'      => $form->has('devise')
                                    ? ($form->get('devise')->getData() ?? 'USD')
                                    : 'USD',
                'delaiJours'  => $delaiJours,
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
            $devis->setStatut(Devis::STATUT_ATTENTE_CHEF);

            // ✅ On injecte le délai dans la description (workaround actuel)
            $delaiJours = (int) ($sessionData['delaiJours'] ?? 0);
            if ($delaiJours > 0) {
                $description = rtrim((string) $devis->getDescription());
                $devis->setDescription($description . "\n[DELAI:" . $delaiJours . "]");
            }

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