<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\JwtService;
use App\Service\BruteForceProtectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JwtService $jwtService,
        BruteForceProtectionService $bruteForceService,
        AuthenticationUtils $authUtils
    ): Response {
        // Get login errors if any
        $error = $authUtils->getLastAuthenticationError();
        $lastUsername = $authUtils->getLastUsername();

        // Allow passing username via query string (we use this after redirects)
        $queryUsername = $request->query->get('username');
        if (!empty($queryUsername)) {
            $lastUsername = $queryUsername;
        }

        if ($request->isMethod('POST')) {
            $email = $request->request->get('_username');
            $password = $request->request->get('_password');

            $user = $userRepository->findOneBy(['email' => $email]);

            // Check if user is locked out
            if ($user && $bruteForceService->isAccountLocked($user)) {
                $lockoutInfo = $bruteForceService->getLockoutInfo($user);
                $this->addFlash('error', sprintf(
                    'Account locked due to too many failed login attempts. Try again in %d seconds.',
                    $lockoutInfo['remaining_time']
                ));
                return $this->redirectToRoute('app_login');
            }

            if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
                // Record failed attempt if user exists
                if ($user) {
                    $remainingAttempts = $bruteForceService->recordFailedAttempt($user);
                    if ($remainingAttempts === null) {
                        $this->addFlash('error', 'Too many failed attempts. Account locked for 1 minute.');
                    } else {
                        $this->addFlash('error', sprintf(
                            'Invalid credentials. %d attempts remaining before lockout.',
                            $remainingAttempts
                        ));
                    }
                } else {
                    $this->addFlash('error', 'Invalid credentials');
                }
                // Redirect to login with username in query so the GET can show lockout info
                return $this->redirectToRoute('app_login', ['username' => $email]);
            }

            // Successful login - reset failed attempts
            $bruteForceService->recordSuccessfulLogin($user);

            $token = $jwtService->generateToken($user->getEmail(), $user->getRoles());

            $this->addFlash('success', 'Login successful');
            
            // Store the token in session
            $request->getSession()->set('jwt_token', $token);
            $request->getSession()->set('user_email', $user->getEmail());
            $request->getSession()->set('user_roles', $user->getRoles());

            // Create authentication token for Symfony security
            $authToken = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->container->get('security.token_storage')->setToken($authToken);

            return $this->redirectToRoute('app_dashboard');
        }

        // If we have a last username, check lockout info for that user so the UI can show a timer
        $lockout = [
            'is_locked' => false,
            'failed_attempts' => 0,
            'locked_until' => null,
            'remaining_time' => null,
        ];

        if (!empty($lastUsername)) {
            $userForLock = $userRepository->findOneBy(['email' => $lastUsername]);
            if ($userForLock) {
                $lockout = $bruteForceService->getLockoutInfo($userForLock);
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'lockout' => $lockout,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This will be handled by Symfony's logout listener
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function apiLogin(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        JwtService $jwtService,
        BruteForceProtectionService $bruteForceService
    ): JsonResponse {

        $data = json_decode($request->getContent(), true);

        $user = $userRepository->findOneBy(['email' => $data['email']]);

        // Check if user is locked out
        if ($user && $bruteForceService->isAccountLocked($user)) {
            $lockoutInfo = $bruteForceService->getLockoutInfo($user);
            return new JsonResponse([
                'error' => 'Account locked due to too many failed login attempts',
                'retry_after' => $lockoutInfo['remaining_time'],
            ], 429); // 429 Too Many Requests
        }

        if (!$user || !$passwordHasher->isPasswordValid($user, $data['password'])) {
            // Record failed attempt if user exists
            if ($user) {
                $remainingAttempts = $bruteForceService->recordFailedAttempt($user);
                if ($remainingAttempts === null) {
                    return new JsonResponse([
                        'error' => 'Too many failed attempts. Account locked for 1 minute.',
                        'retry_after' => 60,
                    ], 429);
                } else {
                    return new JsonResponse([
                        'error' => 'Invalid credentials',
                        'attempts_remaining' => $remainingAttempts,
                    ], 401);
                }
            }

            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }

        // Successful login - reset failed attempts
        $bruteForceService->recordSuccessfulLogin($user);

        $token = $jwtService->generateToken($user->getEmail(), $user->getRoles());

        return new JsonResponse([
            'token' => $token,
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/login/lockout-info', name: 'login_lockout_info', methods: ['GET'])]
    public function lockoutInfo(Request $request, UserRepository $userRepository, BruteForceProtectionService $bruteForceService): JsonResponse
    {
        $username = $request->query->get('username');

        if (empty($username)) {
            return new JsonResponse(['error' => 'username required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findOneBy(['email' => $username]);

        if (!$user) {
            return new JsonResponse(['is_locked' => false, 'remaining_time' => null]);
        }

        $info = $bruteForceService->getLockoutInfo($user);

        return new JsonResponse([
            'is_locked' => $info['is_locked'],
            'remaining_time' => $info['remaining_time'],
            'failed_attempts' => $info['failed_attempts'],
        ]);
    }
}

