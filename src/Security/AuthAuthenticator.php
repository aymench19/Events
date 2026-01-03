<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AuthAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private JwtService $jwtService,
        private UserRepository $userRepository
    ) {}

    public function supports(Request $request): ?bool
    {
        // Only support API endpoints with Bearer token
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return false;
        }

        // Only handle Bearer token authentication for API
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            throw new \Symfony\Component\Security\Core\Exception\AuthenticationException('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);
        $decoded = $this->jwtService->validateToken($token);

        if (!$decoded) {
            throw new \Symfony\Component\Security\Core\Exception\AuthenticationException('Invalid or expired token');
        }

        return new SelfValidatingPassport(
            new UserBadge($decoded['email'], function($email) {
                return $this->userRepository->findOneBy(['email' => $email]);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $exception->getMessageKey()], Response::HTTP_UNAUTHORIZED);
    }
}

