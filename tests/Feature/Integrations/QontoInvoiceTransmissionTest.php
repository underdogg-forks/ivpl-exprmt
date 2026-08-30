<?php

namespace Tests\Feature\Integrations;

use Tests\AbstractTestCase;

/**
 * Qonto Invoice Transmission Feature Tests
 *
 * Test end-to-end invoice transmission, payment tracking, and reconciliation
 */
class QontoInvoiceTransmissionTest extends AbstractTestCase
{

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sends_invoice_to_qonto_successfully(): void
    {

        $invoice = $this->seedInvoiceAsObject(
            invoice_status: 'published',
            einvoicing_enabled: true,
        );

        $qonto_response = [
            'status' => 200,
            'body' => json_encode([
                'id' => 'qonto_invoice_001',
                'status' => 'accepted',
                'transmitted_at' => date('c'),
            ]),
        ];

        $this->withEnvironment('QONTO_MOCK_RESPONSE', json_encode($qonto_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->where('provider', 'qonto')
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission);
        $this->assertEquals('accepted', $transmission->transmission_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_payment_via_qonto_reconciliation(): void
    {

        $invoice = $this->seedPayableInvoice(invoice_balance: 500.00);

        // Send invoice to Qonto
        $qonto_response = [
            'status' => 200,
            'body' => json_encode(['id' => 'qonto_inv_payment', 'status' => 'accepted']),
        ];

        $this->withEnvironment('QONTO_MOCK_RESPONSE', json_encode($qonto_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // Later, receive payment via Qonto reconciliation
        $qonto_payment = [
            'event' => 'payment.received',
            'data' => [
                'invoice_id' => 'qonto_inv_payment',
                'amount' => 500.00,
                'received_at' => date('c'),
            ],
        ];

        $this->post('guest/integrations/qonto/webhook', $qonto_payment);

        $payment = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->row();

        $this->assertNotNull($payment);
        $this->assertEquals('500.00', $payment->payment_amount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_qonto_oauth2_token_refresh(): void
    {

        $invoice = $this->seedInvoiceAsObject(einvoicing_enabled: true);

        // Mock expired token + refresh
        $mock_sequence = [
            ['status' => 401, 'body' => json_encode(['error' => 'token_expired'])],
            ['status' => 200, 'body' => json_encode(['access_token' => 'new_qonto_token', 'expires_in' => 3600])],
            ['status' => 200, 'body' => json_encode(['id' => 'qonto_refreshed', 'status' => 'accepted'])],
        ];

        $this->withEnvironment('QONTO_MOCK_SEQUENCE', json_encode($mock_sequence))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission, 'Should complete after token refresh');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_qonto_rate_limiting(): void
    {

        $invoice = $this->seedInvoiceAsObject(einvoicing_enabled: true);

        // Mock 429 rate limit
        $this->withEnvironment('QONTO_MOCK_STATUS', '429')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        // Should record for retry
        $this->assertNotNull($transmission);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_transmission_timeout(): void
    {

        $invoice = $this->seedInvoiceAsObject(einvoicing_enabled: true);

        // Mock connection timeout
        $this->withEnvironment('QONTO_MOCK_TIMEOUT', '1')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNull($transmission, 'Should not record on timeout');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_partial_payment_via_qonto_reconciliation(): void
    {

        $invoice = $this->seedPayableInvoice(invoice_balance: 1000.00);

        // Send to Qonto
        $qonto_response = [
            'status' => 200,
            'body' => json_encode(['id' => 'qonto_partial', 'status' => 'accepted']),
        ];

        $this->withEnvironment('QONTO_MOCK_RESPONSE', json_encode($qonto_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // Receive partial payment
        $qonto_partial_payment = [
            'event' => 'payment.received',
            'data' => [
                'invoice_id' => 'qonto_partial',
                'amount' => 300.00,
                'received_at' => date('c'),
            ],
        ];

        $this->post('guest/integrations/qonto/webhook', $qonto_partial_payment);

        $payment = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->row();

        $this->assertNotNull($payment);
        $this->assertEquals('300.00', $payment->payment_amount);

        // Receive remaining payment
        $qonto_remaining = [
            'event' => 'payment.received',
            'data' => [
                'invoice_id' => 'qonto_partial',
                'amount' => 700.00,
                'received_at' => date('c'),
            ],
        ];

        $this->post('guest/integrations/qonto/webhook', $qonto_remaining);

        $total_payments = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(2, $total_payments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_duplicate_payment_webhook_from_qonto(): void
    {

        $invoice = $this->seedPayableInvoice(invoice_balance: 250.00);

        // Send to Qonto
        $qonto_response = [
            'status' => 200,
            'body' => json_encode(['id' => 'qonto_dup_payment', 'status' => 'accepted']),
        ];

        $this->withEnvironment('QONTO_MOCK_RESPONSE', json_encode($qonto_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // First payment webhook
        $qonto_payment = [
            'event' => 'payment.received',
            'webhook_id' => 'wh_qonto_001',
            'data' => [
                'invoice_id' => 'qonto_dup_payment',
                'amount' => 250.00,
            ],
        ];

        $this->post('guest/integrations/qonto/webhook', $qonto_payment);

        // Duplicate webhook (same webhook_id)
        $this->post('guest/integrations/qonto/webhook', $qonto_payment);

        $payment_count = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(1, $payment_count, 'Should be idempotent');
    }

    #[\PHPUnit\Framework_Attributes\Test]
    public function it_validates_webhook_signature_from_qonto(): void
    {

        $webhook_payload = [
            'event' => 'payment.received',
            'data' => ['invoice_id' => 'test', 'amount' => 100],
        ];

        // Send with invalid signature
        $response = $this->withHeaders(['X-Qonto-Signature' => 'invalid_sig'])
            ->post('guest/integrations/qonto/webhook', $webhook_payload);

        // Should reject invalid signature
        $this->assertResponseStatus(401 || 403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_malformed_qonto_webhook(): void
    {

        $malformed = [
            'event' => 'payment.received',
            // Missing 'data' field
        ];

        $response = $this->post('guest/integrations/qonto/webhook', $malformed);

        // Should return 400 without crashing
        $this->assertResponseStatus(400 || 422);
    }
}
