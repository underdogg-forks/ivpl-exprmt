<?php

namespace App\Contracts;

/**
 * Contract for integration providers (LetsPeppol, StoreCove, Stripe, PayPal, …).
 *
 * Each provider must be able to validate a participant and submit a document.
 */
interface IntegrationProviderInterface
{
    /**
     * Validate that a participant / recipient is registered and reachable.
     *
     * @param  string $participantId  Provider-specific participant identifier (e.g. Peppol ID).
     * @return bool                   True when the participant is valid.
     */
    public function validateParticipant(string $participantId): bool;

    /**
     * Send / submit an invoice document to the provider.
     *
     * @param  array<string, mixed> $payload  Provider-specific document payload.
     * @return bool                           True on successful submission.
     */
    public function sendInvoice(array $payload): bool;
}
