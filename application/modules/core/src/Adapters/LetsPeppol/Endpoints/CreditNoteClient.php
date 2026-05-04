<?php

namespace Core\Adapters\LetsPeppol\Endpoints;

use Core\Adapters\LetsPeppol\LetsPeppolClient;
use Core\Enums\RequestMethod;
use Psr\Http\Message\ResponseInterface;

class CreditNoteClient
{
    public function __construct(private LetsPeppolClient $client)
    {
    }

    /**
     * Request JSON:
     * {"credit_note_id":5}
     *
     * Response JSON:
     * {"status":"accepted"}
     */
    public function send(array $payload): ResponseInterface
    {
        $headers = ['Accept' => 'application/json'];

        $token = $this->client->settings('access_token');

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->client->request(RequestMethod::POST->value, 'credit_notes.send', [
            'headers' => $headers,
            'json' => $payload,
        ]);
    }

    /**
     * Request query JSON:
     * {"credit_note_id":5}
     *
     * Response JSON:
     * {"status":"delivered"}
     */
    public function getStatus(int $creditNoteId): ResponseInterface
    {
        $headers = ['Accept' => 'application/json'];

        $token = $this->client->settings('access_token');

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $this->client->request(RequestMethod::GET->value, 'credit_notes.status', [
            'headers' => $headers,
            'query' => ['credit_note_id' => $creditNoteId],
        ]);
    }

    /**
     * Request JSON:
     * {"credit_note_id":5,"cancel_reason":"reason"}
     *
     * Response JSON:
     * {"status":"cancelled"}
     */
    public function cancel(int $creditNoteId, ?string $reason = null): ResponseInterface
    {
        $headers = ['Accept' => 'application/json'];

        $token = $this->client->settings('access_token');

        if ($token !== null) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $payload = ['credit_note_id' => $creditNoteId];

        if ($reason !== null) {
            $payload['cancel_reason'] = $reason;
        }

        return $this->client->request(RequestMethod::POST->value, 'credit_notes.cancel', [
            'headers' => $headers,
            'json' => $payload,
        ]);
    }
}
