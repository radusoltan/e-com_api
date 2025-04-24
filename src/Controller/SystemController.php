<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SystemController extends AbstractController
{
    #[Route('/', name: 'api_home')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
        ]);
    }

    #[Route('/favicon.ico', name: 'favicon', methods: ['GET'])]
    public function favicon(): Response
    {
        return new Response('', 204); // No Content
    }
}
