<?php

namespace App\Services\Integrations;

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use App\Integration\IntegrationCredentials;
use Crypt;
use Mdl_integrations;

class IntegrationSettingsService
{
    public function __construct(
        private Mdl_integrations $integrations,
        private Crypt $crypt,
        private LetsPeppolOAuthProviderFactory $oauthFactory,
    ) {
    }

    public function saveLetsPeppolSettings(array $settings): void
    {
        $normalized = [
            'client_id' => trim((string) ($settings['client_id'] ?? '')),
            'client_secret' => trim((string) ($settings['client_secret'] ?? '')),
            'base_url' => trim((string) ($settings['base_url'] ?? '')),
        ];

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
