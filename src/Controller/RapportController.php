<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RapportController extends AbstractController
{
    #[Route('/rapport', name: 'app_rapport')]
    public function index(): Response
    {
        // On récupère toutes les catégories avec leurs articles associés
        $categories = $categoryRepository->findAll();

        return $this->render('rapport/stock.html.twig', [
            'controller_name' => 'RapportController',
        ]);
    }
}
