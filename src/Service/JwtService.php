<?php
namespace App\Service;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;

class JwtService
{
    private Configuration $config;

    public function __construct(string $jwtSecret)
    {
        $this->config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($jwtSecret)
        );
    }

    public function generateToken(string $email, array $roles): string
    {
        $now = new \DateTimeImmutable();
        $token = $this->config->builder()
            ->issuedAt($now)
            ->expiresAt($now->modify('+1 hour'))
            ->withClaim('email', $email)
            ->withClaim('roles', $roles)
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
                        }
    public function validateToken(string $token): ?array
    {
        try {
            $parsedToken = $this->config->parser()->parse($token);
            $constraints = $this->config->validationConstraints();
            if (!$this->config->validator()->validate($parsedToken, ...$constraints)) {
                return null;
            }

            if (! $parsedToken instanceof \Lcobucci\JWT\UnencryptedToken) {
                return null;
            }

            $claims = $parsedToken->claims();

            return [
                'email' => $claims->get('email'),
                'roles' => $claims->get('roles'),
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
