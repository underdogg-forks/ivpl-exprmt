<?php

namespace Core\Adapters\StoreCove;

use GuzzleHttp\Client;

/**
 * Builds a configured StoreCoveClient with canonical endpoint map.
 */
class StoreCoveClientFactory
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

    public function create(string $baseUrl, array $settings = []): StoreCoveClient
    {
        return new StoreCoveClient(
            new Client(),
            $baseUrl,
            self::ENDPOINTS,
            $settings,
        );
    }
}
