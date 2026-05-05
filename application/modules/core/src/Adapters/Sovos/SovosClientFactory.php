<?php

namespace Core\Adapters\Sovos;

use GuzzleHttp\Client;

/**
 * Builds a configured SovosClient with canonical endpoint map.
 */
class SovosClientFactory
{
    private const ENDPOINTS = [
        'participants.validate' => 'api/participants/validate',
        'participants.details'  => 'api/participants',
        'participants.search'   => 'api/participants/search',
        'invoices.send'         => 'api/invoices',
        'invoices.status'       => 'api/invoices',
        'invoices.cancel'       => 'api/invoices/cancel',
        'invoices.resend'       => 'api/invoices/resend',
    ];

    public function create(string $baseUrl, array $settings = []): SovosClient
    {
        return new SovosClient(
            new Client(),
            $baseUrl,
            self::ENDPOINTS,
            $settings,
        );
    }
}
