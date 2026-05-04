<?php

namespace Core\Adapters\Sovos\Endpoints;

use Core\Adapters\Sovos\SovosClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class CreditNoteClient
{
    public function __construct(private SovosClient $client)
    {
    }

    public function send(array $payload): ResponseInterface
    {
        return $this->client->request(RequestMethod::POST->value, 'credit_notes.send', ['json' => $payload]);
    }

    public function getStatus(int $creditNoteId): ResponseInterface
    {
        return $this->client->request(RequestMethod::GET->value, 'credit_notes.status', ['query' => ['credit_note_id' => $creditNoteId]]);
    }

    public function cancel(int $creditNoteId, ?string $reason = null): ResponseInterface
    {
        $payload = ['credit_note_id' => $creditNoteId];
        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }
        return $this->client->request(RequestMethod::POST->value, 'credit_notes.cancel', ['json' => $payload]);
    }
}
