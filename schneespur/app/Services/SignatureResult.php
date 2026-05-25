<?php

namespace App\Services;

class SignatureResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $keyId,
        public readonly string $message,
        public readonly bool $isAllowed,
    ) {}

    public static function verified(string $keyId): self
    {
        return new self(
            status: 'verified',
            keyId: $keyId,
            message: 'Signatur verifiziert',
            isAllowed: true,
        );
    }

    public static function unsigned(): self
    {
        return new self(
            status: 'unsigned',
            keyId: null,
            message: 'Modul ist nicht signiert',
            isAllowed: true,
        );
    }

    public static function failed(string $keyId, string $reason): self
    {
        return new self(
            status: 'failed',
            keyId: $keyId,
            message: $reason,
            isAllowed: false,
        );
    }

    public static function expiredTrust(): self
    {
        return new self(
            status: 'expired_trust',
            keyId: null,
            message: 'Trust-Liste ist abgelaufen',
            isAllowed: false,
        );
    }
}
