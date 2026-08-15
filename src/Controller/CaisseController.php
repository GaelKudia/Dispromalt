<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Entity\Article;
use App\Entity\Visite; 
use App\Entity\Devis; 

use App\Form\DemandeType;
use App\Form\BonSortieType;
use App\Form\VisiteType;
use App\Form\StockType;
use App\Form\DevisType;

use App\Repository\DemandeRepository;
use App\Repository\DevisRepository;

use App\Repository\BonSortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CaisseController extends AbstractController
{

// src/Controller/CaisseController.php

#[Route('/caisse', name: 'app_caisse_index')]
public function index(DevisRepository $devisRepository): Response
{
    // On ne récupère que les devis ayant le statut 'ATTENTE_CAISSE'
    return $this->render('dashboard/caisse/index.html.twig', [
        'devis_a_payer' => $devisRepository->findBy(['statut' => 'ATTENTE_CAISSE']),
    ]);
}

#[Route('/caisse/decaisser/{id}', name: 'app_caisse_valider')]
public function decaisser(Devis $devis, EntityManagerInterface $em): Response
{
    $devis->setStatut('DECAISSE');
    $devis->setDateDecaissement(new \DateTime());
    // Ici, tu peux aussi enregistrer un historique ou une ligne de sortie de caisse
    
    $em->flush();
    
    $this->addFlash('success', 'Fonds décaissés avec succès.');
    return $this->redirectToRoute('app_caisse_index');

    
}

// Dans src/Controller/CaisseController.php
#[Route('/caisse/visite/nouvelle', name: 'app_caisse_nouvelle_visite')]
public function nouvelleVisite(Request $request, EntityManagerInterface $em): Response
{
    $visite = new Visite();
    $visite->setStatut('En attente');
    $visite->setDateArrivee(new \DateTime());

    // 🚀 C'EST ICI QU'ON CACHE LES CHAMPS POUR LE CAISSIER
    $form = $this->createForm(VisiteType::class, $visite, [
        'with_extra_fields' => false, 
    ]);
    
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        
        // Si un responsable a été sélectionné pour la délégation
        if ($visite->getResponsableDelegue()) {
            $visite->setStatut('Délégué');
        }

        $em->persist($visite);
        $em->flush();

        $this->addFlash('success', 'Visite enregistrée et déléguée avec succès.');
        return $this->redirectToRoute('app_caisse_index');
    }

 return $this->render('dashboard/caisse/nouvelle_visite.html.twig', [
        'formVisite' => $form->createView(),
    ]);

}
}