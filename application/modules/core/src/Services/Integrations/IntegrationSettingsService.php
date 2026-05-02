<?php

namespace Core\Services\Integrations;

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Contracts\CryptInterface;
use Core\Contracts\IntegrationRepositoryInterface;
use Core\Integration\IntegrationCredentials;

class IntegrationSettingsService
{
    public function __construct(
        private IntegrationRepositoryInterface $integrations,
        private CryptInterface $crypt,
        private LetsPeppolOAuthProviderFactory $oauthFactory,
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
}
