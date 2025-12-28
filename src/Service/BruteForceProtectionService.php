<?php

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

class BruteForceProtectionService
{
    // Configuration: 10 failed attempts → 5 minute lockout, doubling each time
    // After first unlock: 5 new attempts allowed
    // Subsequent lockouts: 10 min → 20 min → 40 min, etc.
    private const FAILED_ATTEMPTS_THRESHOLD = 10;
    private const BASE_LOCKOUT_DURATION = 300; // seconds (5 minutes)
    private const LOCKOUT_MULTIPLIER = 2; // doubles for each batch

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoginAttemptRepository $loginAttemptRepository
    ) {
    }

    /**
     * Check if user account is locked due to too many failed attempts
     */
    public function isAccountLocked(User $user): bool
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if (!$loginAttempt) {
            return false;
        }

        return $loginAttempt->isLocked();
    }

    /**
     * Get remaining lockout time in seconds
     */
    public function getRemainingLockoutTime(User $user): ?int
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if (!$loginAttempt || !$loginAttempt->getLockedUntil()) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $remaining = $loginAttempt->getLockedUntil()->getTimestamp() - $now->getTimestamp();

        return max(0, $remaining);
    }

    /**
     * Record a failed login attempt
     * Returns remaining attempts before lockout, or null if account is now locked
     */
    public function recordFailedAttempt(User $user): ?int
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if (!$loginAttempt) {
            $loginAttempt = new LoginAttempt($user);
            $this->entityManager->persist($loginAttempt);
        }

        $loginAttempt->incrementFailedAttempts();
        $failedAttempts = $loginAttempt->getFailedAttempts();

        // Calculate lockout duration based on failed attempts
        // 10 attempts → 300s (5 min), 20 attempts → 600s (10 min), 30 attempts → 1200s (20 min), etc.
        if ($failedAttempts >= self::FAILED_ATTEMPTS_THRESHOLD) {
            $batchNumber = ceil($failedAttempts / self::FAILED_ATTEMPTS_THRESHOLD);
            $lockoutDuration = self::BASE_LOCKOUT_DURATION * pow(self::LOCKOUT_MULTIPLIER, $batchNumber - 1);

            $loginAttempt->lockAccount((int)$lockoutDuration);
            $this->entityManager->flush();

            return null; // Account is locked
        }

        $this->entityManager->flush();

        return self::FAILED_ATTEMPTS_THRESHOLD - $failedAttempts;
    }

    /**
     * Record a successful login and reset failed attempts
     */
    public function recordSuccessfulLogin(User $user): void
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if ($loginAttempt) {
            $loginAttempt->resetFailedAttempts();
            $this->entityManager->flush();
        }
    }

    /**
     * Manually reset login attempts for a user
     */
    public function resetLoginAttempts(User $user): void
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if ($loginAttempt) {
            $loginAttempt->resetFailedAttempts();
            $this->entityManager->flush();
        }
    }

    /**
     * Get information about account lockout status
     */
    public function getLockoutInfo(User $user): array
    {
        $loginAttempt = $this->loginAttemptRepository->findByUser($user);

        if (!$loginAttempt) {
            return [
                'is_locked' => false,
                'failed_attempts' => 0,
                'locked_until' => null,
                'remaining_time' => null,
            ];
        }

        $remainingTime = null;
        if ($loginAttempt->isLocked()) {
            $remainingTime = $this->getRemainingLockoutTime($user);
        }

        return [
            'is_locked' => $loginAttempt->isLocked(),
            'failed_attempts' => $loginAttempt->getFailedAttempts(),
            'locked_until' => $loginAttempt->getLockedUntil()?->format('Y-m-d H:i:s'),
            'remaining_time' => $remainingTime,
        ];
    }
}
