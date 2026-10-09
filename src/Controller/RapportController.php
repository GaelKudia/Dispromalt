<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RapportController extends AbstractController
{
    #[Route('/rapport', name: 'app_rapport')]
    public function index(CategoryRepository $categoryRepository): Response
    {
        // On récupère toutes les catégories
        $categories = $categoryRepository->findAll();

        return $this->render('rapport/stock.html.twig', [
            'controller_name' => 'RapportController',
            'categories'      => $categories,
        ]);
    }
}