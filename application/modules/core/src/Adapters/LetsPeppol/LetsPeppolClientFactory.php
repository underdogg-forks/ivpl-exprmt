<?php

namespace Core\Adapters\LetsPeppol;

use GuzzleHttp\Client;

/**
 * Builds a configured LetsPeppolClient with canonical endpoint map.
 *
 * Extracting this into a dedicated factory keeps LetsPeppolProvider free of
 * construction details and makes it easy to swap the HTTP client in tests.
 */
class LetsPeppolClientFactory
{
    private const ENDPOINTS = [
        'participants.validate' => 'api/participants/validate',
        'invoices.send'        => 'api/invoices',
    ];

    public function create(string $baseUrl, array $settings = []): LetsPeppolClient
    {
        return new LetsPeppolClient(
            new Client(),
            $baseUrl,
            self::ENDPOINTS,
            $settings,
        );
    }
}
