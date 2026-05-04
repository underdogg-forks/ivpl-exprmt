<?php

namespace Core\Adapters\Pagero\Endpoints;

use Core\Adapters\Pagero\PageroClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class TransmissionClient
{
    public function __construct(private PageroClient $client)
    {
    }

    /**
     * Request query JSON:
     * {"transmission_id":"tr-1"}
     *
     * Response JSON:
     * {"status":"delivered"}
     */
    public function getStatus(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.status', [
            'query' => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * Request query JSON:
     * {"transmission_id":"tr-1"}
     *
     * Response JSON:
     * {"receipt":"..."}
     */
    public function getReceipt(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.receipt', [
            'query' => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * Request query JSON:
     * {"transmission_id":"tr-1"}
     *
     * Response JSON:
     * {"errors":[]}
     */
    public function getErrors(string $transmissionId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.errors', [
            'query' => ['transmission_id' => $transmissionId],
        ]);
    }

    /**
     * Request query JSON:
     * {"status":"failed"}
     *
     * Response JSON:
     * {"transmissions":[]}
     */
    public function list(array $filters = []): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'transmissions.list', [
            'query' => $filters,
        ]);
    }

    /**
     * Request JSON:
     * {"transmission_id":"tr-1","retry_reason":"reason"}
     *
     * Response JSON:
     * {"status":"queued"}
     */
    public function retry(string $transmissionId, ?string $reason = null): ResponseInterface
    {
        $payload = ['transmission_id' => $transmissionId];

        if ($reason !== null) {
            $payload['retry_reason'] = $reason;
        }

        return $this->client->request(RequestMethod::POST->value, 'transmissions.retry', [
            'json' => $payload,
        ]);
    }
}
