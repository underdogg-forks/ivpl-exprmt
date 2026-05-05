<?php

namespace Core\Adapters\Sovos\Auth;

use Core\Integration\IntegrationCredentials;
use League\OAuth2\Client\Provider\GenericProvider;

class SovosOAuthProviderFactory
{
    public function make(IntegrationCredentials $credentials, string $baseUrl): GenericProvider
    {
        if (empty($baseUrl) || filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('A valid base URL is required');
        }

        $normalizedBaseUrl = rtrim($baseUrl, '/');

        return new GenericProvider([
            'clientId'                => $credentials->clientId(),
            'clientSecret'            => $credentials->clientSecret(),
            'urlAuthorize'            => $normalizedBaseUrl . '/oauth/authorize',
            'urlAccessToken'          => $normalizedBaseUrl . '/oauth/token',
            'urlResourceOwnerDetails' => $normalizedBaseUrl . '/oauth/resource',
        ]);
    }
}
