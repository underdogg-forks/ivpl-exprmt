<?php

namespace Core\Adapters\Pagero\Auth;

use Core\Contracts\OAuthProviderFactoryInterface;
use Core\Integration\IntegrationCredentials;
use League\OAuth2\Client\Provider\GenericProvider;

class PageroOAuthProviderFactory implements OAuthProviderFactoryInterface
{
    public function make(IntegrationCredentials $credentials, string $baseUrl): GenericProvider
    {
        $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
        if (
            empty($baseUrl)
            || filter_var($baseUrl, FILTER_VALIDATE_URL) === false
            || strtolower((string) $scheme) !== 'https'
        ) {
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
