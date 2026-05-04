<?php

namespace Core\Adapters\Sovos\Endpoints;

use Core\Adapters\Sovos\SovosClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class TransmissionClient
{
    public function __construct(private SovosClient $client)
    {
    }

    public function getStatus(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.status', ['query' => ['transmission_id' => $transmissionId]]);
    }

    public function getReceipt(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.receipt', ['query' => ['transmission_id' => $transmissionId]]);
    }

    public function getErrors(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.errors', ['query' => ['transmission_id' => $transmissionId]]);
    }

    public function list(array $filters = []): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.list', ['query' => $filters]);
    }

    public function retry(string $transmissionId, ?string $reason = null): ResponseInterface
    {
        $payload = ['transmission_id' => $transmissionId];
        if ($reason !== null) {
            $payload['retry_reason'] = $reason;
        }
        return $this->client->request(RequestMethod::POST->value, 'transmissions.retry', ['json' => $payload]);
    }
}
