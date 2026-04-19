<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Computes a deterministic, non-reversible identifier for an email address
 * using HMAC-SHA256 with a dedicated pepper. Emails are never persisted in
 * clear — only their hash is.
 */
final class EmailHasher
{
    public function __construct(
        #[Autowire(env: 'APP_EMAIL_HMAC_KEY')]
        private readonly string $key,
    ) {
        if ($this->key === '') {
            throw new \LogicException('APP_EMAIL_HMAC_KEY must be set to a non-empty value.');
        }
    }

    public function hash(string $email): string
    {
        return hash_hmac('sha256', $this->normalize($email), $this->key);
    }

    private function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
