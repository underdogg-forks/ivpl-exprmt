<?php

namespace Tests\Fixtures;

/**
 * Invoice Test Fixtures
 *
 * Provides seeding methods for test invoices with various states and configurations
 */
trait InvoiceFixtures
{
    /**
     * Seed a payable invoice for payment gateway testing
     *
     * @param float $invoice_balance
     * @param string $currency
     * @param string|null $invoice_status
     * @return object Invoice row
     */
    protected function seedPayableInvoice(
        float $invoice_balance = 100.00,
        string $currency = 'USD',
        ?string $invoice_status = 'published'
    ) {
        $client = $this->seedClient();

        $invoice_data = [
            'client_id'        => $client->client_id,
            'invoice_status'   => $invoice_status,
            'invoice_date'     => date('Y-m-d'),
            'invoice_due_date' => date('Y-m-d', strtotime('+30 days')),
            'invoice_total'    => $invoice_balance,
            'invoice_balance'  => $invoice_balance,
            'currency_code'    => $currency,
        ];

        $this->db->insert('ip_invoices', $invoice_data);
        $invoice_id = $this->db->insert_id();

        return $this->db->where('invoice_id', $invoice_id)->get('ip_invoices')->row();
    }

    /**
     * Seed an invoice for einvoicing integration testing
     *
     * @param string $invoice_status
     * @param bool $einvoicing_enabled
     * @return object Invoice row
     */
    protected function seedInvoice(
        string $invoice_status = 'published',
        bool $einvoicing_enabled = true
    ) {
        $client = $this->seedClient();

        $invoice_data = [
            'client_id'        => $client->client_id,
            'invoice_status'   => $invoice_status,
            'invoice_date'     => date('Y-m-d'),
            'invoice_due_date' => date('Y-m-d', strtotime('+30 days')),
            'invoice_total'    => 1000.00,
            'invoice_balance'  => 1000.00,
            'currency_code'    => 'USD',
        ];

        if ($einvoicing_enabled) {
            $invoice_data['einvoicing_enabled'] = 1;
        }

        $this->db->insert('ip_invoices', $invoice_data);
        $invoice_id = $this->db->insert_id();

        return $this->db->where('invoice_id', $invoice_id)->get('ip_invoices')->row();
    }

    /**
     * Seed a test client
     *
     * @return object Client row
     */
    protected function seedClient()
    {
        $client_data = [
            'client_name'     => 'Test Client ' . uniqid(),
            'client_active'   => 1,
            'client_language' => 'english',
        ];

        $this->db->insert('ip_clients', $client_data);
        $client_id = $this->db->insert_id();

        return $this->db->where('client_id', $client_id)->get('ip_clients')->row();
    }

    /**
     * Record a payment for an invoice
     *
     * @param int $invoice_id
     * @param float $amount
     * @param string $payment_method
     * @param string|null $external_id
     * @return int Payment ID
     */
    protected function recordPayment(
        int $invoice_id,
        float $amount,
        string $payment_method = 'paypal',
        ?string $external_id = null
    ): int {
        $payment_data = [
            'invoice_id'         => $invoice_id,
            'payment_amount'     => $amount,
            'payment_date'       => date('Y-m-d H:i:s'),
            'payment_method_id'  => $this->getPaymentMethodId($payment_method),
            'payment_external_id' => $external_id ?? uniqid(),
        ];

        $this->db->insert('ip_payments', $payment_data);
        return $this->db->insert_id();
    }

    /**
     * Get payment method ID by name
     *
     * @param string $name
     * @return int|null
     */
    protected function getPaymentMethodId(string $name): ?int
    {
        $method = $this->db
            ->where('payment_method', $name)
            ->get('ip_payment_methods')
            ->row();

        if ($method) {
            return $method->payment_method_id;
        }

        // Create if doesn't exist
        $this->db->insert('ip_payment_methods', [
            'payment_method' => $name,
            'payment_method_active' => 1,
        ]);

        return $this->db->insert_id();
    }

    /**
     * Record a webhook event for idempotency testing
     *
     * @param string $provider
     * @param string $external_event_id
     * @param string $event_type
     * @param string $payload_hash
     * @return int Webhook event ID
     */
    protected function recordWebhookEvent(
        string $provider,
        string $external_event_id,
        string $event_type,
        string $payload_hash
    ): int {
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
     * Check if webhook has already been processed (idempotency)
     *
     * @param string $provider
     * @param string $external_event_id
     * @return bool
     */
    protected function isWebhookProcessed(string $provider, string $external_event_id): bool
    {
        $event = $this->db
            ->where('provider', $provider)
            ->where('external_event_id', $external_event_id)
            ->get('ip_webhook_events')
            ->row();

        return $event !== null;
    }

    /**
     * Record an einvoicing transmission
     *
     * @param int $invoice_id
     * @param string $provider
     * @param string $external_reference_id
     * @param string $status
     * @return int Transmission ID
     */
    protected function recordEinvoicingTransmission(
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
}
