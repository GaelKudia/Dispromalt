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
    #[Route('/caisse', name: 'app_caisse_index')]
    public function index(DevisRepository $devisRepository, Request $request): Response
    {
        // Récupération des filtres de dates (optionnel)
        $dateDebutFiltre = $request->query->get('date_debut', date('Y-m-d'));
        $dateFinFiltre = $request->query->get('date_fin', date('Y-m-d'));

        // On peut filtrer par date ou récupérer la liste complète / en attente
        $devisListe = $devisRepository->findAll(); 
        
        $totalDevisUSD = 0;
        $totalDevisCDF = 0;
        foreach ($devisListe as $d) {
            // Optionnel : ne comptabiliser que ceux validés ou en attente selon ta logique
            if ($d->getDevise() === 'CDF') {
                $totalDevisCDF += $d->getMontant();
            } else {
                $totalDevisUSD += $d->getMontant();
            }
        }

        return $this->render('dashboard/caisse/index.html.twig', [
            'devisListe'    => $devisListe,
            'totalDevisUSD' => $totalDevisUSD,
            'totalDevisCDF' => $totalDevisCDF,
            'date_debut'    => $dateDebutFiltre,
            'date_fin'      => $dateFinFiltre,
        ]);
    }

    #[Route('/caisse/decaisser/{id}', name: 'app_caisse_valider')]
    public function decaisser(Devis $devis, EntityManagerInterface $em): Response
    {
        $devis->setStatut('DECAISSE');
        $devis->setDateDecaissement(new \DateTime());
        
        $em->flush();
        
        $this->addFlash('success', 'Fonds décaissés avec succès.');
        return $this->redirectToRoute('app_caisse_index');
    }

    #[Route('/caisse/visite/nouvelle', name: 'app_caisse_nouvelle_visite')]
    public function nouvelleVisite(Request $request, EntityManagerInterface $em): Response
    {
        $visite = new Visite();
        $visite->setStatut('En attente');
        $visite->setDateArrivee(new \DateTime());

        $form = $this->createForm(VisiteType::class, $visite, [
            'with_extra_fields' => false, 
        ]);
        
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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