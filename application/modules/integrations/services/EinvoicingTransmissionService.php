<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * EinvoicingTransmissionService
 *
 * Handles E-Invoice transmission, retries, OAuth2 token refresh, and status tracking
 * Used by all einvoicing providers: LetsPeppol, SuperPDP, Qonto, etc.
 */
class EinvoicingTransmissionService
{
    private CI_DB_query_builder $db;
    private const MAX_RETRIES = 5;
    private const INITIAL_BACKOFF = 5; // seconds
    private const TOKEN_REFRESH_MARGIN = 300; // 5 minutes before expiry

    public function __construct()
    {
        $CI = &get_instance();
        $this->db = $CI->db;
    }

    /**
     * Create transmission record for invoice
     *
     * @param int $invoice_id
     * @param string $provider (letspeppol, superpdp, qonto, etc)
     * @param string $external_reference_id Provider's reference ID
     * @param string $status (pending, transmitted, delivered, failed)
     * @return int Transmission ID
     */
    public function createTransmission(
        int $invoice_id,
        string $provider,
        string $external_reference_id,
        string $status = 'pending'
    ): int {
        $transmission_data = [
            'invoice_id'            => $invoice_id,
            'provider'              => $provider,
            'external_reference_id' => $external_reference_id,
            'transmission_status'   => $status,
            'retry_count'           => 0,
        ];

        $this->db->insert('ip_einvoicing_transmissions', $transmission_data);
        return $this->db->insert_id();
    }

    /**
     * Get transmission record
     *
     * @param int $invoice_id
     * @param string $provider
     * @return object|null
     */
    public function getTransmission(int $invoice_id, string $provider): ?object
    {
        return $this->db
            ->where('invoice_id', $invoice_id)
            ->where('provider', $provider)
            ->get('ip_einvoicing_transmissions')
            ->row();
    }

    /**
     * Update transmission status
     *
     * @param int $transmission_id
     * @param string $status
     * @param string|null $error_message
     * @return bool
     */
    public function updateStatus(int $transmission_id, string $status, ?string $error_message = null): bool
    {
        $update_data = ['transmission_status' => $status];

        if ($error_message) {
            $update_data['last_error'] = $error_message;
        }

        $this->db
            ->where('transmission_id', $transmission_id)
            ->update('ip_einvoicing_transmissions', $update_data);

        return $this->db->affected_rows() > 0;
    }

    /**
     * Increment retry counter
     *
     * @param int $transmission_id
     * @return int New retry count
     */
    public function incrementRetry(int $transmission_id): int
    {
        $transmission = $this->db
            ->where('transmission_id', $transmission_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $new_count = ($transmission->retry_count ?? 0) + 1;

        $this->db
            ->where('transmission_id', $transmission_id)
            ->update('ip_einvoicing_transmissions', [
                'retry_count' => $new_count,
            ]);

        return $new_count;
    }

    /**
     * Check if transmission can be retried
     *
     * @param int $transmission_id
     * @return bool
     */
    public function canRetry(int $transmission_id): bool
    {
        $transmission = $this->db
            ->where('transmission_id', $transmission_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        if (!$transmission) {
            return false;
        }

        return ($transmission->retry_count ?? 0) < self::MAX_RETRIES;
    }

    /**
     * Calculate exponential backoff delay
     *
     * @param int $retry_attempt (1-based)
     * @return int Seconds to wait
     */
    public function getBackoffDelay(int $retry_attempt): int
    {
        return self::INITIAL_BACKOFF * (2 ** ($retry_attempt - 1));
    }

    /**
     * Check if OAuth2 token needs refresh
     *
     * @param int $token_expires_at Unix timestamp
     * @return bool
     */
    public function needsTokenRefresh(int $token_expires_at): bool
    {
        $now = time();
        return $token_expires_at <= ($now + self::TOKEN_REFRESH_MARGIN);
    }

    /**
     * Check if error is retryable
     *
     * @param int $http_status
     * @param string|null $error_code
     * @return bool
     */
    public function isRetryableError(int $http_status, ?string $error_code = null): bool
    {
        // Retryable: timeout, rate limit, server error
        if (in_array($http_status, [408, 429, 500, 502, 503, 504])) {
            return true;
        }

        // Specific error codes
        if ($error_code && in_array($error_code, ['NETWORK_TIMEOUT', 'CONNECTION_RESET', 'SERVICE_UNAVAILABLE'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if error indicates participant/recipient not found
     *
     * @param int $http_status
     * @param string|null $error_code
     * @return bool
     */
    public function isParticipantNotFoundError(int $http_status, ?string $error_code = null): bool
    {
        if ($http_status === 404) {
            return true;
        }

        return $error_code && in_array($error_code, [
            'PARTICIPANT_NOT_FOUND',
            'RECIPIENT_NOT_FOUND',
            'INVALID_IDENTIFIER',
        ]);
    }

    /**
     * Get retry wait time based on error
     *
     * @param int $http_status
     * @param int $retry_attempt
     * @param array|null $response_headers
     * @return int Seconds to wait
     */
    public function getRetryWait(
        int $http_status,
        int $retry_attempt,
        ?array $response_headers = null
    ): int {
        // Respect Retry-After header for rate limits
        if ($http_status === 429 && $response_headers && isset($response_headers['Retry-After'])) {
            return (int)$response_headers['Retry-After'];
        }

        // Exponential backoff for other retriable errors
        if ($this->isRetryableError($http_status)) {
            return $this->getBackoffDelay($retry_attempt);
        }

        return 0; // No retry
    }

    /**
     * Get transmissions pending retry
     *
     * @param string|null $provider Filter by provider
     * @return array Transmission records
     */
    public function getTransmissionsPendingRetry(?string $provider = null): array
    {
        $query = $this->db
            ->where_in('transmission_status', ['failed', 'rate_limited'])
            ->where('retry_count <', self::MAX_RETRIES)
            ->order_by('updated_at', 'ASC');

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->get('ip_einvoicing_transmissions')->result();
    }

    /**
     * Get transmissions ready for status polling
     *
     * @param string|null $provider
     * @return array Transmission records with status 'submitted' or 'pending'
     */
    public function getTransmissionsForStatusPolling(?string $provider = null): array
    {
        $query = $this->db
            ->where_in('transmission_status', ['submitted', 'pending'])
            ->where('updated_at <', date('Y-m-d H:i:s', strtotime('-5 minutes')))
            ->order_by('updated_at', 'ASC')
            ->limit(10);

        if ($provider) {
            $query->where('provider', $provider);
        }

        return $query->get('ip_einvoicing_transmissions')->result();
    }

    /**
     * Get max retries constant
     *
     * @return int
     */
    public static function getMaxRetries(): int
    {
        return self::MAX_RETRIES;
    }
}
