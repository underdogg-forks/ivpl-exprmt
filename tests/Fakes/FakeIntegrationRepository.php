<?php

namespace Tests\Fakes;

use Core\Contracts\CryptInterface;
use Core\Contracts\IntegrationRepositoryInterface;

/**
 * In-memory fake for IntegrationRepositoryInterface.
 *
 * Stores settings, tokens, and log entries in memory.
 * Tests can inspect recorded calls via the public `$logs`, `$tokens`, and
 * `$settings` properties, or use the assertion helpers.
 *
 * Usage:
 *   $repo = new FakeIntegrationRepository();
 *   $repo->setSettings('letspeppol', ['client_id' => 'x', 'client_secret' => 's']);
 *   $repo->setActiveToken('letspeppol', 'tok-abc');
 *
 *   // After exercising code under test:
 *   $repo->assertLogged('letspeppol', 'participants.validate', 'success');
 */
class FakeIntegrationRepository implements IntegrationRepositoryInterface
{
    /** @var array<string, array<string, string>> Provider settings, keyed by provider. */
    public array $settings = [];

    /** @var array<string, list<string>> Encrypted key names per provider, set by saveEncryptedSettings(). */
    private array $encryptedKeys = [];

    /** @var array<string, string|null> Active tokens keyed by provider. */
    public array $tokens = [];

    /** @var list<array{provider: string, action: string, status: string, context: array<string,mixed>}> */
    public array $logs = [];

    /** @var list<array{provider: string, token: string, expiresAt: int|null}> */
    public array $savedTokens = [];

    // ── Seeding helpers ───────────────────────────────────────────────────────

    /**
     * @param list<string> $encryptedKeys Keys that are stored encrypted (will be decoded on settings() read).
     */
    public function setSettings(string $provider, array $settings, array $encryptedKeys = []): static
    {
        $this->settings[$provider] = $settings;
        $this->encryptedKeys[$provider] = $encryptedKeys;

        return $this;
    }

    public function setActiveToken(string $provider, ?string $token): static
    {
        $this->tokens[$provider] = $token;

        return $this;
    }

    // ── Interface implementation ───────────────────────────────────────────────

    public function ensureProvider(string $provider, string $name): int
    {
        return 1;
    }

    public function saveEncryptedSettings(
        string $provider,
        array $settings,
        array $encryptedKeys,
        CryptInterface $crypt,
    ): void {
        // Track which keys are encrypted so settings() can decode them.
        $this->encryptedKeys[$provider] = $encryptedKeys;

        foreach ($encryptedKeys as $key) {
            if (isset($settings[$key])) {
                $settings[$key] = $crypt->encode($settings[$key]);
            }
        }

        $this->settings[$provider] = $settings;
    }

    public function settings(string $provider, CryptInterface $crypt): array
    {
        $raw = $this->settings[$provider] ?? [];
        $keys = $this->encryptedKeys[$provider] ?? [];

        foreach ($keys as $key) {
            if (isset($raw[$key])) {
                $raw[$key] = $crypt->decode($raw[$key]);
            }
        }

        return $raw;
    }

    public function saveToken(string $provider, string $token, ?int $expiresAt = null): void
    {
        $this->tokens[$provider]  = $token;
        $this->savedTokens[]      = compact('provider', 'token', 'expiresAt');
    }

    public function activeToken(string $provider): ?string
    {
        return $this->tokens[$provider] ?? null;
    }

    public function invalidateToken(string $provider): void
    {
        $this->tokens[$provider] = null;
    }

    public function log(string $provider, string $action, string $status, array $context = []): void
    {
        $this->logs[] = compact('provider', 'action', 'status', 'context');
    }

    // ── PHPUnit assertion helpers ──────────────────────────────────────────────

    /**
     * Assert that a log entry matching the given criteria was recorded.
     */
    public function assertLogged(string $provider, string $action, string $status): void
    {
        foreach ($this->logs as $entry) {
            if (
                $entry['provider'] === $provider
                && $entry['action'] === $action
                && $entry['status'] === $status
            ) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected log entry [{$provider}:{$action}:{$status}] was not recorded. " .
            'Actual logs: ' . json_encode($this->logs, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Assert that no log entries were recorded.
     */
    public function assertNotLogged(): void
    {
        if (count($this->logs) > 0) {
            throw new \RuntimeException(
                'Expected no log entries but got: ' . json_encode($this->logs, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * Assert that a token was saved for the given provider.
     */
    public function assertTokenSaved(string $provider, string $token): void
    {
        foreach ($this->savedTokens as $entry) {
            if ($entry['provider'] === $provider && $entry['token'] === $token) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected token [{$token}] for [{$provider}] was not saved."
        );
    }
}
