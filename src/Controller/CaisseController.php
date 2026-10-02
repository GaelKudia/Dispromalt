<?php

namespace App\Controller;

use App\Entity\MouvementCaisse;
use App\Entity\User;
use App\Form\MouvementCaisseType;
use App\Repository\MouvementCaisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/caisse')]
#[IsGranted('ROLE_COMPTA')]
class CaisseController extends AbstractController
{
    #[Route('/entree', name: 'app_caisse_entree', methods: ['GET', 'POST'])]
    public function entree(
        Request $request,
        EntityManagerInterface $em,
        MouvementCaisseRepository $mouvementRepo
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $mouvement = new MouvementCaisse();
        $mouvement->setType(MouvementCaisse::TYPE_ENTREE);
        $mouvement->setEffectuePar($user);

        $form = $this->createForm(MouvementCaisseType::class, $mouvement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($mouvement);
            $em->flush();

            $this->addFlash('success', sprintf(
                '✅ Entrée enregistrée : %s %s — %s',
                number_format((float) $mouvement->getMontant(), 2, ',', ' '),
                $mouvement->getDevise(),
                $mouvement->getProvenance()
            ));
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('caisse/entree.html.twig', [
            'form'   => $form->createView(),
            'soldes' => [
                'USD' => $mouvementRepo->getSolde(MouvementCaisse::DEVISE_USD),
                'CDF' => $mouvementRepo->getSolde(MouvementCaisse::DEVISE_CDF),
            ],
        ]);
    }

    #[Route('/mouvements', name: 'app_caisse_mouvements', methods: ['GET'])]
    public function historique(MouvementCaisseRepository $repo): Response
    {
        return $this->render('caisse/mouvements.html.twig', [
            'mouvements' => $repo->findAllOrdered(),
            'soldeUSD'   => $repo->getSolde(MouvementCaisse::DEVISE_USD),
            'soldeCDF'   => $repo->getSolde(MouvementCaisse::DEVISE_CDF),
        ]);
    }
}