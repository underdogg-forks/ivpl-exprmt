<?php

declare(strict_types=1);

namespace App\Adapters\LetsPeppol\Endpoints;

use App\Enums\RequestMethod;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class LetsPeppolClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $baseUrl,
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
