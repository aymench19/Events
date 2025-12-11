<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Automatically assign ROLE_PARTICIPANT
            $user->setRoles(['ROLE_PARTICIPANT']);

            // Hash the password
            $user->setPassword(
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );

            $entityManager->persist($user);
            $entityManager->flush();

            // Redirect to login page after registration
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function apiRegister(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!isset($data['email'], $data['password'], $data['password_confirmation'])) {
                return new JsonResponse(['error' => 'Missing required fields'], 400);
            }

            // Validate password confirmation
            if ($data['password'] !== $data['password_confirmation']) {
                return new JsonResponse(['error' => 'Passwords do not match'], 400);
            }

            // Check if user already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);
            if ($existingUser) {
                return new JsonResponse(['error' => 'Email already exists'], 409);
            }

            // Create new user
            $user = new User();
            $user->setEmail($data['email']);
            $user->setFirstName($data['firstName'] ?? 'User');
            $user->setLastName($data['lastName'] ?? 'User');
            $user->setRoles(['ROLE_PARTICIPANT']);
            $user->setPassword(
                $passwordHasher->hashPassword($user, $data['password'])
            );

            $entityManager->persist($user);
            $entityManager->flush();

            return new JsonResponse([
                'message' => 'User registered successfully',
                'user' => [
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Registration failed: ' . $e->getMessage()], 500);
        }
    }
}

