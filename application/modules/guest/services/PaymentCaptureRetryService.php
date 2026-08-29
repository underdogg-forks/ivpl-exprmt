<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * PaymentCaptureRetryService
 *
 * Handles payment capture retries, timeouts, rate limiting, and concurrent access
 * Implements exponential backoff and idempotency for payment processing
 */
class PaymentCaptureRetryService
{
    private CI_DB_query_builder $db;
    private const MAX_RETRIES = 3;
    private const INITIAL_BACKOFF_SECONDS = 2;
    private const RATE_LIMIT_WAIT_SECONDS = 60;

    public function __construct()
    {
        $CI = &get_instance();
        $this->db = $CI->db;
    }

    /**
     * Record payment with deduplication via external_capture_id
     *
     * Prevents double-recording if same capture is processed twice
     *
     * @param int $invoice_id
     * @param float $amount
     * @param string $external_capture_id Unique payment gateway capture ID
     * @param string $payment_method
     * @param array $additional_data
     * @return int|null Payment ID, or null if already exists
     */
    public function recordPayment(
        int $invoice_id,
        float $amount,
        string $external_capture_id,
        string $payment_method = 'paypal',
        array $additional_data = []
    ): ?int {
        // Check for existing payment with same external_capture_id
        $existing = $this->db
            ->where('external_capture_id', $external_capture_id)
            ->get('ip_payments')
            ->row();

        if ($existing) {
            return null; // Already recorded
        }

        $payment_data = array_merge([
            'invoice_id'         => $invoice_id,
            'payment_amount'     => $amount,
            'payment_date'       => date('Y-m-d H:i:s'),
            'payment_method_id'  => $this->getPaymentMethodId($payment_method),
            'payment_external_id' => $external_capture_id,
            'external_capture_id' => $external_capture_id,
            'capture_attempt_count' => 1,
        ], $additional_data);

        $this->db->insert('ip_payments', $payment_data);
        return $this->db->insert_id();
    }

    /**
     * Check if payment capture succeeded (idempotent read)
     *
     * @param string $external_capture_id
     * @return object|null Payment record if exists
     */
    public function getPaymentByCapture(string $external_capture_id): ?object
    {
        return $this->db
            ->where('external_capture_id', $external_capture_id)
            ->get('ip_payments')
            ->row();
    }

    /**
     * Record capture attempt for retry tracking
     *
     * @param int $invoice_id
     * @param string $external_capture_id
     * @param string|null $error_message
     * @return void
     */
    public function recordCaptureAttempt(
        int $invoice_id,
        string $external_capture_id,
        ?string $error_message = null
    ): void {
        // Get or create payment record with attempt tracking
        $existing = $this->db
            ->where('invoice_id', $invoice_id)
            ->where('external_capture_id', $external_capture_id)
            ->get('ip_payments')
            ->row();

        $update_data = [
            'capture_attempt_count' => ($existing ? $existing->capture_attempt_count + 1 : 1),
        ];

        if ($error_message) {
            $update_data['last_capture_error'] = $error_message;
        }

        if ($existing) {
            $this->db
                ->where('payment_id', $existing->payment_id)
                ->update('ip_payments', $update_data);
        }
    }

    /**
     * Calculate backoff delay for retry
     *
     * @param int $attempt_number (1-based)
     * @return int Seconds to wait
     */
    public function getBackoffDelay(int $attempt_number): int
    {
        return self::INITIAL_BACKOFF_SECONDS * (2 ** ($attempt_number - 1));
    }

    /**
     * Check if should retry based on error type
     *
     * @param int $http_status
     * @param string|null $error_type
     * @return bool
     */
    public function shouldRetry(int $http_status, ?string $error_type = null): bool
    {
        // Retry on timeout, connection error, or server error
        if (in_array($http_status, [408, 429, 500, 502, 503, 504])) {
            return true;
        }

        // Don't retry on client errors (4xx except 408, 429)
        if ($http_status >= 400 && $http_status < 500) {
            return false;
        }

        return false;
    }

    /**
     * Get wait time for rate limit (429)
     *
     * @param array|null $response_headers
     * @return int Seconds to wait
     */
    public function getRateLimitWait(?array $response_headers = null): int
    {
        if ($response_headers && isset($response_headers['Retry-After'])) {
            return (int)$response_headers['Retry-After'];
        }

        return self::RATE_LIMIT_WAIT_SECONDS;
    }

    /**
     * Check if payment can be retried
     *
     * @param string $external_capture_id
     * @return bool
     */
    public function canRetry(string $external_capture_id): bool
    {
        $payment = $this->db
            ->where('external_capture_id', $external_capture_id)
            ->get('ip_payments')
            ->row();

        if (!$payment) {
            return true; // Not yet attempted
        }

        return ($payment->capture_attempt_count ?? 0) < self::MAX_RETRIES;
    }

    /**
     * Update invoice balance after successful payment
     *
     * Atomic operation to prevent race conditions
     *
     * @param int $invoice_id
     * @param float $payment_amount
     * @return bool Success
     */
    public function updateInvoiceBalance(int $invoice_id, float $payment_amount): bool
    {
        $this->db->trans_start();

        $invoice = $this->db
            ->where('invoice_id', $invoice_id)
            ->get('ip_invoices')
            ->row();

        if (!$invoice) {
            $this->db->trans_rollback();
            return false;
        }

        $new_balance = max(0, (float)$invoice->invoice_balance - $payment_amount);

        $this->db
            ->where('invoice_id', $invoice_id)
            ->update('ip_invoices', [
                'invoice_balance' => $new_balance,
                'invoice_status' => ($new_balance <= 0) ? 'paid' : $invoice->invoice_status,
            ]);

        $this->db->trans_complete();

        return $this->db->trans_status() === false ? false : true;
    }

    /**
     * Get payment method ID by name
     *
     * @param string $name
     * @return int|null
     */
    private function getPaymentMethodId(string $name): ?int
    {
        $method = $this->db
            ->where('payment_method', $name)
            ->get('ip_payment_methods')
            ->row();

        if ($method) {
            return $method->payment_method_id;
        }

        $this->db->insert('ip_payment_methods', [
            'payment_method' => $name,
            'payment_method_active' => 1,
        ]);

        return $this->db->insert_id();
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
