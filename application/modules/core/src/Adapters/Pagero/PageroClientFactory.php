<?php

namespace Core\Adapters\Pagero;

use GuzzleHttp\Client;

/**
 * Builds a configured PageroClient with canonical endpoint map.
 */
class PageroClientFactory
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

    public function create(string $baseUrl, array $settings = []): PageroClient
    {
        return new PageroClient(
            new Client(),
            $baseUrl,
            self::ENDPOINTS,
            $settings,
        );
    }
}
