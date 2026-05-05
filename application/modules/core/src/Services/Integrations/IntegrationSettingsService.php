<?php

namespace Core\Services\Integrations;

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Adapters\Pagero\Auth\PageroOAuthProviderFactory;
use Core\Adapters\Sovos\Auth\SovosOAuthProviderFactory;
use Core\Contracts\CryptInterface;
use Core\Contracts\IntegrationRepositoryInterface;
use Core\Integration\IntegrationCredentials;

class IntegrationSettingsService
{
    public function __construct(
        private IntegrationRepositoryInterface $integrations,
        private CryptInterface $crypt,
        private LetsPeppolOAuthProviderFactory $oauthFactory,
        private ?PageroOAuthProviderFactory $pageroOAuthFactory = null,
        private ?SovosOAuthProviderFactory $sovosOAuthFactory = null,
    ) {
    }

    public function saveLetsPeppolSettings(array $settings): void
    {
        $normalized = [
            'client_id' => trim((string) ($settings['client_id'] ?? '')),
            'base_url' => trim((string) ($settings['base_url'] ?? '')),
        ];

        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));

        if ($clientSecret !== '') {
            $normalized['client_secret'] = $clientSecret;
        } else {
            $existingSettings = $this->letsPeppolSettings();

            if (!empty($existingSettings['client_secret'])) {
                $normalized['client_secret'] = (string) $existingSettings['client_secret'];
            }
        }
        $this->integrations->saveEncryptedSettings('letspeppol', $normalized, ['client_secret'], $this->crypt);
    }

    public function letsPeppolSettings(): array
    {
        return $this->integrations->settings('letspeppol', $this->crypt);
    }

    public function activeTokenOrCreate(): ?string
    {
        $settings = $this->letsPeppolSettings();
        $token = $this->integrations->activeToken('letspeppol');

        if ($token) {
            return $token;
        }

        if (empty($settings['client_id']) || empty($settings['client_secret']) || empty($settings['base_url'])) {
            return null;
        }

        $provider = $this->oauthFactory->make(
            new IntegrationCredentials($settings['client_id'], $settings['client_secret']),
            $settings['base_url']
        );

        $newToken = $provider->getAccessToken('client_credentials');
        $this->integrations->saveToken('letspeppol', $newToken->getToken(), $newToken->getExpires());

        return $newToken->getToken();
    }

    // ── StoreCove ─────────────────────────────────────────────────────────────

    /**
     * Persist StoreCove settings. The API key is encrypted at rest.
     *
     * StoreCove uses a static API key for authentication (no OAuth handshake).
     */
    public function saveStoreCoveSettings(array $settings): void
    {
        $normalized = [
            'base_url' => trim((string) ($settings['base_url'] ?? '')),
        ];

        $apiKey = trim((string) ($settings['api_key'] ?? ''));

        if ($apiKey !== '') {
            $normalized['api_key'] = $apiKey;
        } else {
            $existing = $this->storeCoveSettings();

            if (!empty($existing['api_key'])) {
                $normalized['api_key'] = (string) $existing['api_key'];
            }
        }

        $this->integrations->saveEncryptedSettings('storecove', $normalized, ['api_key'], $this->crypt);
        $this->integrations->invalidateToken('storecove');
    }

    /**
     * Retrieve StoreCove settings, decrypting the API key.
     *
     * @return array<string, string>
     */
    public function storeCoveSettings(): array
    {
        return $this->integrations->settings('storecove', $this->crypt);
    }

    // ── Pagero ────────────────────────────────────────────────────────────────

    /**
     * Persist Pagero OAuth credentials. The client secret is encrypted at rest.
     */
    public function savePageroSettings(array $settings): void
    {
        $normalized = [
            'client_id' => trim((string) ($settings['client_id'] ?? '')),
            'base_url'  => trim((string) ($settings['base_url'] ?? '')),
        ];

        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));

        if ($clientSecret !== '') {
            $normalized['client_secret'] = $clientSecret;
        } else {
            $existing = $this->pageroSettings();

            if (!empty($existing['client_secret'])) {
                $normalized['client_secret'] = (string) $existing['client_secret'];
            }
        }

        $this->integrations->saveEncryptedSettings('pagero', $normalized, ['client_secret'], $this->crypt);
        $this->integrations->invalidateToken('pagero');
    }

    /**
     * Retrieve Pagero settings, decrypting the client secret.
     *
     * @return array<string, string>
     */
    public function pageroSettings(): array
    {
        return $this->integrations->settings('pagero', $this->crypt);
    }

    /**
     * Return a valid Pagero access token, fetching a new one via OAuth2 if needed.
     *
     * Returns null when credentials are not configured.
     */
    public function activePageroTokenOrCreate(): ?string
    {
        $settings = $this->pageroSettings();
        $token    = $this->integrations->activeToken('pagero');

        if ($token) {
            return $token;
        }

        if (empty($settings['client_id']) || empty($settings['client_secret']) || empty($settings['base_url'])) {
            return null;
        }

        $factory  = $this->pageroOAuthFactory ?? new PageroOAuthProviderFactory();
        $provider = $factory->make(
            new IntegrationCredentials($settings['client_id'], $settings['client_secret']),
            $settings['base_url']
        );

        $newToken = $provider->getAccessToken('client_credentials');
        $this->integrations->saveToken('pagero', $newToken->getToken(), $newToken->getExpires());

        return $newToken->getToken();
    }

    // ── Sovos ─────────────────────────────────────────────────────────────────

    /**
     * Persist Sovos OAuth credentials. The client secret is encrypted at rest.
     */
    public function saveSovosSettings(array $settings): void
    {
        $normalized = [
            'client_id' => trim((string) ($settings['client_id'] ?? '')),
            'base_url'  => trim((string) ($settings['base_url'] ?? '')),
        ];

        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));

        if ($clientSecret !== '') {
            $normalized['client_secret'] = $clientSecret;
        } else {
            $existing = $this->sovosSettings();

            if (!empty($existing['client_secret'])) {
                $normalized['client_secret'] = (string) $existing['client_secret'];
            }
        }

        $this->integrations->saveEncryptedSettings('sovos', $normalized, ['client_secret'], $this->crypt);
        $this->integrations->invalidateToken('sovos');
    }

    /**
     * Retrieve Sovos settings, decrypting the client secret.
     *
     * @return array<string, string>
     */
    public function sovosSettings(): array
    {
        return $this->integrations->settings('sovos', $this->crypt);
    }

    /**
     * Return a valid Sovos access token, fetching a new one via OAuth2 if needed.
     *
     * Returns null when credentials are not configured.
     */
    public function activeSovosTokenOrCreate(): ?string
    {
        $settings = $this->sovosSettings();
        $token    = $this->integrations->activeToken('sovos');

        if ($token) {
            return $token;
        }

        if (empty($settings['client_id']) || empty($settings['client_secret']) || empty($settings['base_url'])) {
            return null;
        }

        $factory  = $this->sovosOAuthFactory ?? new SovosOAuthProviderFactory();
        $provider = $factory->make(
            new IntegrationCredentials($settings['client_id'], $settings['client_secret']),
            $settings['base_url']
        );

        $newToken = $provider->getAccessToken('client_credentials');
        $this->integrations->saveToken('sovos', $newToken->getToken(), $newToken->getExpires());

        return $newToken->getToken();
    }
}
