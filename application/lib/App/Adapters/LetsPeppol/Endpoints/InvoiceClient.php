<?php

namespace App\Adapters\LetsPeppol\Endpoints;

use App\Adapters\LetsPeppol\LetsPeppolClient;
use App\Enums\RequestMethod;

class InvoiceClient
{
    public function __construct(private LetsPeppolClient $client)
    {
    }

    /**
     * Sends invoice payload to LetsPeppol.
     *
     * Request JSON example:
     * {"invoice_id":1,"invoice_number":"INV-1","client_peppol_id":"0088:123"}
     *
     * Response JSON example:
     * {"status":"accepted","id":"ext-123"}
     */
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
