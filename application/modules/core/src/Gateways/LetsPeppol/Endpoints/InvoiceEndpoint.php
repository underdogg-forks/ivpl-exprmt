<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Invoice endpoint client.
 *
 * Handles invoice submission to the LetsPeppol gateway.
 * Uses the gateway client's request() method and buildHeaders() for consistency.
 */
class InvoiceEndpoint
{
    public function __construct(
        private GatewayClientInterface $gateway
    ) {
    }

    /**
     * Send invoice payload to LetsPeppol.
     *
     * Request JSON example:
     * {"invoice_id":1,"invoice_number":"INV-1","client_peppol_id":"0088:123"}
     *
     * Response JSON example (from fixture):
     * {"status":"accepted","id":"ext-123","transmission_id":"trans-456"}
     *
     * @param  array<string, mixed> $payload  Invoice data
     * @return ResponseInterface
     */
    public function sendInvoice(array $payload): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();

        return $this->gateway->request(RequestMethod::POST->value, 'invoices.send', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
