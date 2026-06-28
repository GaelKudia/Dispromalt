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
    return $this->render('caisse/index.html.twig', [
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
}