<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * WebhookIdempotencyService
 *
 * Prevents duplicate webhook processing across all payment gateways and integrations
 * Uses provider + external_event_id as unique key to detect re-deliveries
 */
class WebhookIdempotencyService
{
    private CI_DB_query_builder $db;

    public function __construct()
    {
        $CI = &get_instance();
        $this->db = $CI->db;
    }

    /**
     * Check if webhook has already been processed
     *
     * @param string $provider Payment gateway (paypal, stripe, etc)
     * @param string $external_event_id Unique event ID from provider
     * @return bool True if already processed (is duplicate)
     */
    public function isProcessed(string $provider, string $external_event_id): bool
    {
        $event = $this->db
            ->where('provider', $provider)
            ->where('external_event_id', $external_event_id)
            ->get('ip_webhook_events')
            ->row();

        return $event !== null;
    }

    /**
     * Record webhook event to prevent future duplicates
     *
     * @param string $provider
     * @param string $external_event_id
     * @param string $event_type
     * @param string|array $payload Raw payload data
     * @return int Webhook event ID
     */
    public function recordEvent(
        string $provider,
        string $external_event_id,
        string $event_type,
        $payload
    ): int {
        $payload_hash = hash('sha256', is_array($payload) ? json_encode($payload) : (string)$payload);

        $event_data = [
            'provider'           => $provider,
            'external_event_id'  => $external_event_id,
            'event_type'         => $event_type,
            'payload_hash'       => $payload_hash,
            'processed_at'       => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('ip_webhook_events', $event_data);
        return $this->db->insert_id();
    }

    /**
     * Get last recorded event for a provider
     *
     * @param string $provider
     * @param string $external_event_id
     * @return object|null
     */
    public function getEvent(string $provider, string $external_event_id): ?object
    {
        return $this->db
            ->where('provider', $provider)
            ->where('external_event_id', $external_event_id)
            ->get('ip_webhook_events')
            ->row();
    }

    /**
     * Verify webhook signature matches payload hash
     *
     * @param string $stored_hash Hash stored in database
     * @param string|array $payload Current payload
     * @return bool
     */
    public function verifyPayloadHash(string $stored_hash, $payload): bool
    {
        $current_hash = hash('sha256', is_array($payload) ? json_encode($payload) : (string)$payload);
        return hash_equals($stored_hash, $current_hash);
    }

    /**
     * Clean up old webhook events (older than 90 days)
     *
     * @return int Number of events deleted
     */
    public function cleanupOldEvents(): int
    {
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));

        $this->db->where('processed_at <', $cutoff_date);
        $this->db->delete('ip_webhook_events');

        return $this->db->affected_rows();
    }
}
