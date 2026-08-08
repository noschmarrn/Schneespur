<?php

namespace App\Support;

/**
 * Decides whether Laravel's host allow-list (TrustHosts) may be enforced.
 *
 * Enforcing it protects against Host-header injection, but it can only trust
 * what APP_URL names. Before the installer has run there is no APP_URL — the
 * shipped default is http://localhost — and enforcing against that default
 * rejects every real request with 400 Bad Request, the installer included.
 * See TrustedHostPolicyTest for the deployment that proved it.
 */
class TrustedHostPolicy
{
    /**
     * The value shipped in .env.example and used as the config default.
     */
    private const PLACEHOLDER_HOST = 'localhost';

    public static function shouldEnforce(?string $appUrl): bool
    {
        if ($appUrl === null || trim($appUrl) === '') {
            return false;
        }

        $host = parse_url(trim($appUrl), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return $host !== self::PLACEHOLDER_HOST;
    }
}
