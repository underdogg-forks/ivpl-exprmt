<?php

namespace App\Adapters\LetsPeppol\Endpoints;

use App\Adapters\LetsPeppol\LetsPeppolClient;
use App\Enums\RequestMethod;

class InvoiceClient
{
    public function __construct(private LetsPeppolClient $client)
    {
    }

    public function sendInvoice(string $accessToken, array $payload)
    {
        return $this->client->request(RequestMethod::POST->value, 'invoices.send', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'json' => $payload,
        ]);
    }
}
