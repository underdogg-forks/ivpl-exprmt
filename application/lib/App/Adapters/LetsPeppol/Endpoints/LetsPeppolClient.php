<?php


namespace App\Adapters\LetsPeppol\Endpoints;

use App\Enums\RequestMethod;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class LetsPeppolClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    public function sendInvoice(string $accessToken, array $payload): ResponseInterface
    {
        return $this->httpClient->request(RequestMethod::POST->value, rtrim($this->baseUrl, '/') . '/api/invoices', [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'json' => $payload,
        ]);
    }
}
