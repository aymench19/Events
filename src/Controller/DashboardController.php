<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(Request $request): Response
    {
        $jwtToken = $request->getSession()->get('jwt_token');
        $userEmail = $request->getSession()->get('user_email');

        return $this->render('dashboard.html.twig', [
            'jwt_token' => $jwtToken,
            'user_email' => $userEmail,
        ]);
    }
}
