<?php

namespace Core\Contracts;

/**
 * Contract for persisting and retrieving integration settings, tokens, and audit logs.
 *
 * Implementing this interface instead of depending on the concrete `Mdl_integrations`
 * CI model allows services to be unit-tested without a database or CI runtime.
 * Production code passes `Mdl_integrations`; tests pass a `FakeIntegrationRepository`.
 */
interface IntegrationRepositoryInterface
{
    /**
     * Ensure a provider row exists in the integrations table and return its ID.
     */
    public function ensureProvider(string $provider, string $name): int;

    /**
     * Persist encrypted integration settings for a provider.
     *
     * @param string[]         $encryptedKeys  List of setting keys to encrypt before storing.
     * @param CryptInterface   $crypt          Encryption service.
     */
    public function saveEncryptedSettings(
        string $provider,
        array $settings,
        array $encryptedKeys,
        CryptInterface $crypt,
    ): void;

    /**
     * Return decrypted integration settings for a provider, keyed by setting key.
     *
     * @return array<string, string>
     */
    public function settings(string $provider, CryptInterface $crypt): array;

    /**
     * Persist an OAuth access token for the given provider.
     */
    public function saveToken(string $provider, string $token, ?int $expiresAt = null): void;

    /**
     * Return the currently active (non-expired) OAuth token string, or null.
     */
    public function activeToken(string $provider): ?string;

    /**
     * Append an audit log entry for a provider action.
     *
     * @param array<string, mixed> $context  Arbitrary key/value data to store with the log entry.
     */
    public function log(string $provider, string $action, string $status, array $context = []): void;
}
