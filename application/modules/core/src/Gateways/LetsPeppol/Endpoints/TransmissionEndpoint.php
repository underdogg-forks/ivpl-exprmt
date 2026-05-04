<?php

namespace Core\Gateways\LetsPeppol\Endpoints;

use Core\Contracts\GatewayClientInterface;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/**
 * LetsPeppol Transmission endpoint client.
 */
class TransmissionEndpoint
{
    public function __construct(private GatewayClientInterface $gateway)
    {
    }

    /**
     * Get transmission status by transmission ID.
     */
    public function getStatus(string $transmissionId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.status', ['headers' => $this->gateway->buildHeaders(), 'query' => ['transmission_id' => $transmissionId]]);
    }

    /**
     * Get receipt acknowledgment for a transmission.
     */
    public function getReceipt(string $transmissionId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.receipt', ['headers' => $this->gateway->buildHeaders(), 'query' => ['transmission_id' => $transmissionId]]);
    }

    /**
     * Get error details for a failed transmission.
     */
    public function getErrors(string $transmissionId): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.errors', ['headers' => $this->gateway->buildHeaders(), 'query' => ['transmission_id' => $transmissionId]]);
    }

    /**
     * List all transmissions with optional filtering.
     *
     * @param array<string, mixed> $filters
     */
    public function list(array $filters = []): ResponseInterface
    {
        return $this->gateway->request(RequestMethod::GET->value, 'transmissions.list', ['headers' => $this->gateway->buildHeaders(), 'query' => $filters]);
    }

    /**
     * Retry a failed transmission.
     */
    public function retry(string $transmissionId, ?string $reason = null): ResponseInterface
    {
        $payload = ['transmission_id' => $transmissionId];
        if ($reason !== null) {
            $payload['retry_reason'] = $reason;
        }

        return $this->gateway->request(RequestMethod::POST->value, 'transmissions.retry', ['headers' => $this->gateway->buildHeaders(), 'json' => $payload]);
    }
}
