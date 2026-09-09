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


class DevisController extends AbstractController
{





 #[Route('/dashboard/devis/nouveau', name: 'app_devis_nouveau')]
    public function nouveauDevis(Request $request): Response
    {
        $devis = new Devis();
        $form = $this->createForm(DevisType::class, $devis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entete = $form->has('entete') ? $form->get('entete')->getData() : '';
            
            $request->getSession()->set('devis_brouillon', [
                'entete' => $entete ?: (method_exists($devis, 'getEntete') ? $devis->getEntete() : 'Nouveau Devis'),
                'description' => $devis->getDescription(),
                'montant' => $devis->getMontant(),
                'devise' => $form->get('devise')->getData() ?? 'USD'
            ]);
            return $this->redirectToRoute('app_devis_apercu');
        }

        return $this->render('dashboard/devis_form.html.twig', [
            'form' => $form->createView()
        ]);
    }

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
            $devis->setMontant($sessionData['montant']);
            $devis->setStatut('ATTENTE_TRAITEMENT');
            $devis->setMontantInitial((string)$sessionData['montant']);
            
            $devis->setCreatedAt(new \DateTime());
            $devis->setUser($this->getUser()); 
            $devis->setCreatedBy($this->getUser()->getUserIdentifier());
            $devis->setDevise($sessionData['devise'] ?? 'USD');

            $em->persist($devis);
            $em->flush();

            $request->getSession()->remove('devis_brouillon');
            $this->addFlash('success', '🚀 Votre devis a été envoyé au circuit de validation financière !');
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('dashboard/devis_apercu.html.twig', [
            'devis' => $sessionData
        ]);
    }

    #[Route('/dashboard/compta/devis/valider-chef/{id}', name: 'app_devis_valider_chef', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function validerChefCompta(Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CHEF) {
            $this->addFlash('danger', 'Ce devis n\'est pas en attente de validation du Chef Comptable.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_ATTENTE_DIR);
        $em->flush();
        
        $this->addFlash('success', '✅ Validé par le Chef Comptable ! Envoyé au Directeur pour révision.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/compta/devis/valider-directeur/{id}', name: 'app_devis_valider_directeur', methods: ['POST'])]
    public function validerEtModifierDirecteur(Devis $devis, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_DG')) {
            throw $this->createAccessDeniedException('Vous n’avez pas les droits requis pour valider ce devis.');
        }

        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_DIR) {
            $this->addFlash('danger', 'Ce devis n\'est pas en attente de la Direction.');
            return $this->redirectToRoute('app_dashboard');
        }
        
        $nouveauMontant = $request->request->get('nouveau_montant');
        if ($nouveauMontant && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
            $devis->setMontant((float)$nouveauMontant);
        } else {
            $this->addFlash('danger', 'Veuillez saisir un montant d\'ajustement valide.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_ATTENTE_CAISSE);
        $em->flush();

        $this->addFlash('success', '⚖️ Prix ajusté et validé par la Direction. Transmis à la Caisse.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/compta/devis/decaisser/{id}', name: 'app_devis_decaisser', methods: ['POST'])]
    #[IsGranted('ROLE_COMPTA')]
    public function decaisserFonds(Devis $devis, EntityManagerInterface $em): Response
    {
        if ($devis->getStatut() !== Devis::STATUT_ATTENTE_CAISSE) {
            $this->addFlash('danger', 'Les fonds ne peuvent pas être décaissés pour ce devis.');
            return $this->redirectToRoute('app_dashboard');
        }

        $devis->setStatut(Devis::STATUT_DECAISSE);
        $em->flush();

        $this->addFlash('success', '💵 Fonds décaissés ! Statut mis à jour : Argent envoyé.');
        return $this->redirectToRoute('app_dashboard');
    }


    #[Route('/dashboard/compta/devis/aiguiller/{id}', name: 'app_devis_aiguiller', methods: ['POST'])]
#[IsGranted('ROLE_COMPTA')]
public function aiguillerDevis(Devis $devis, Request $request, EntityManagerInterface $em): Response
{
    $choix = $request->request->get('choix_procédure');

    switch ($choix) {
        case 'circuit_complet':
            $devis->setStatut(Devis::STATUT_ATTENTE_CHEF);
            break;
        case 'bon_caisse':
            $devis->setStatut('EN_ATTENTE_BON_SORTIE'); // Nouveau statut à créer
            break;
        case 'decaisser_direct':
            $devis->setStatut(Devis::STATUT_DECAISSE);
            break;
    }

    $em->flush();
    $this->addFlash('success', '✅ Procédure lancée avec succès.');
    return $this->redirectToRoute('app_dashboard');
   
}
#[Route('/devis/valider-caisse/{id}', name: 'app_devis_valider_caisse', methods: ['POST'])]
public function validerCaisse(Devis $devis, EntityManagerInterface $em): Response
{
    // On vérifie les droits manuellement ici
    if (!$this->isGranted('ROLE_COMPTA') && !$this->isGranted('ROLE_CAISSIER')) {
        throw $this->createAccessDeniedException('Vous n’avez pas les droits requis.');
    }

    $devis->setStatut(Devis::STATUT_DECAISSE);
    $devis->setDateDecaissement(new \DateTime());
    
    $em->flush();

    $this->addFlash('success', 'Décaissement effectué avec succès.');
    return $this->redirectToRoute('app_dashboard');
}

// Dans src/Controller/DevisController.php

#[Route('/dashboard/chef-service/valider-devis/{id}', name: 'app_chef_service_valider_devis', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
public function chefServiceValiderDevis(Devis $devis, Request $request, EntityManagerInterface $em): Response
{
    /** @var User $currentUser */
    $currentUser = $this->getUser();

    // 1. Vérifier que l'utilisateur est bien chef de service
    if (!$currentUser->isChefService()) {
        throw $this->createAccessDeniedException('Accès réservé aux chefs de service.');
    }

    // 2. Vérifier que le devis appartient à un utilisateur du même service
    $createur = $devis->getUser();
    if (!$createur || $createur->getService() !== $currentUser->getService()) {
        $this->addFlash('danger', '🛑 Ce devis ne concerne pas votre service.');
        return $this->redirectToRoute('app_dashboard');
    }

    // 3. Vérifier que le devis est dans un état modifiable (ex: ATTENTE_TRAITEMENT)
    if ($devis->getStatut() !== 'ATTENTE_TRAITEMENT') {
        $this->addFlash('danger', 'Ce devis ne peut plus être modifié ou validé.');
        return $this->redirectToRoute('app_dashboard');
    }

    // 4. Récupérer le nouveau montant (si fourni)
    $nouveauMontant = $request->request->get('nouveau_montant');
    if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
        // Enregistrer le montant initial (si ce n'est pas déjà fait)
        if ($devis->getMontantInitial() === null) {
            $devis->setMontantInitial((string) $devis->getMontant());
        }
        $devis->setMontant((float) $nouveauMontant);
    } else {
        // Si aucun montant n'est saisi, on conserve le montant actuel
        // Mais on peut considérer qu'il n'y a pas de modification
        // On peut ajouter un flash info si besoin
    }

    // 5. Changer le statut (par exemple : "VALIDÉ_CHEF_SERVICE")
    $devis->setStatut('VALIDÉ_CHEF_SERVICE');
    $em->flush();

    $this->addFlash('success', '✅ Devis validé par le chef de service et transmis pour la suite.');
    return $this->redirectToRoute('app_dashboard');
}

#[Route('/dashboard/coordonnateur/valider-devis/{id}', name: 'app_coordonnateur_valider_devis', methods: ['POST'])]
#[IsGranted('ROLE_COORDON')]
public function coordonnateurValiderDevis(Devis $devis, Request $request, EntityManagerInterface $em): Response
{
    // Vérifier que le devis a le bon statut
    if ($devis->getStatut() !== 'VALIDÉ_CHEF_SERVICE') {
        $this->addFlash('danger', 'Ce devis n\'est pas en attente de validation par le coordonnateur.');
        return $this->redirectToRoute('app_dashboard');
    }

    // Récupérer le nouveau montant (optionnel)
    $nouveauMontant = $request->request->get('nouveau_montant');
    if ($nouveauMontant !== null && is_numeric($nouveauMontant) && $nouveauMontant > 0) {
        if ($devis->getMontantInitial() === null) {
            $devis->setMontantInitial((string) $devis->getMontant());
        }
        $devis->setMontant((float) $nouveauMontant);
    }

    // Changer le statut (par exemple : en attente du chef comptable)
    $devis->setStatut(Devis::STATUT_ATTENTE_CHEF); // ou 'EN_ATTENTE_BON_SORTIE' selon le choix
    $em->flush();

    $this->addFlash('success', '✅ Devis validé par le coordonnateur et transmis à la comptabilité.');
    return $this->redirectToRoute('app_dashboard');
}


}