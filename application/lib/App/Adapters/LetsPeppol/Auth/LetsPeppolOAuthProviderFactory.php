<?php

declare(strict_types=1);

namespace App\Adapters\LetsPeppol\Auth;

use App\Integration\IntegrationCredentials;
use League\OAuth2\Client\Provider\GenericProvider;

final class LetsPeppolOAuthProviderFactory
{
    public function make(IntegrationCredentials $credentials, string $baseUrl): GenericProvider
    {
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
