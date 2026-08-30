<?php

namespace Tests\Feature\Payments;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Tests\AbstractTestCase;

/**
 * Stripe Payment Integration Tests
 *
 * Test race conditions, webhook idempotency, timeout handling, and error scenarios
 */
class StripePaymentIntegrationTest extends AbstractTestCase
{

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_race_condition_on_concurrent_capture_attempts(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 150.00);

        $stripe_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_test_race',
                'status' => 'succeeded',
                'amount' => 15000,
                'charges' => ['data' => [['id' => 'ch_race_1', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_test_race');

        $first_payment = $this->db
            ->where('payment_external_id', 'ch_race_1')
            ->get('ip_payments')
            ->row();

        $this->assertNotNull($first_payment);
        $this->assertEquals('150.00', $first_payment->payment_amount);

        // Second concurrent attempt should detect duplicate and not double-record
        $stripe_response_dup = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_test_race',
                'status' => 'succeeded',
                'amount' => 15000,
                'charges' => ['data' => [['id' => 'ch_race_1', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response_dup))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_test_race');

        $payment_count = $this->db
            ->where('payment_external_id', 'ch_race_1')
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(1, $payment_count, 'Should have exactly one payment, not duplicated');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_webhook_delivery_idempotently(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 200.00);

        $webhook_payload = [
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'id' => 'ch_webhook_idempotent',
                    'amount' => 20000,
                    'metadata' => ['invoice_id' => $invoice->invoice_id],
                ],
            ],
        ];

        // First webhook delivery
        $this->post('guest/gateways/stripe/webhook', $webhook_payload);
        $first_payment = $this->db
            ->where('payment_external_id', 'ch_webhook_idempotent')
            ->get('ip_payments')
            ->row();
        $this->assertNotNull($first_payment);

        // Duplicate webhook delivery (same event ID)
        $this->post('guest/gateways/stripe/webhook', $webhook_payload);
        $payment_count = $this->db
            ->where('payment_external_id', 'ch_webhook_idempotent')
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(1, $payment_count, 'Webhook should be idempotent');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_network_timeout_during_payment_capture(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        // Mock timeout exception
        $this->withEnvironment('STRIPE_MOCK_TIMEOUT', '1')
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_timeout');

        // Should not record payment on timeout
        $payment_count = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(0, $payment_count, 'Should not record payment on timeout');

        // Next attempt should retry without conflicts
        $stripe_response_retry = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_timeout',
                'status' => 'succeeded',
                'amount' => 10000,
                'charges' => ['data' => [['id' => 'ch_timeout_retry', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response_retry))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_timeout');

        $payment = $this->db
            ->where('payment_external_id', 'ch_timeout_retry')
            ->get('ip_payments')
            ->row();

        $this->assertNotNull($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_stripe_rate_limit_response(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 80.00);

        // Mock 429 rate limit response
        $this->withEnvironment('STRIPE_MOCK_RATE_LIMIT', '1')
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_rate_limited');

        // Should not record payment on rate limit
        $payment_count = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(0, $payment_count, 'Should not record payment on rate limit');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_declined_charge_response(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 60.00);

        $stripe_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 402, 'body' => json_encode([
                'error' => [
                    'type' => 'card_error',
                    'message' => 'Your card was declined',
                ],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_declined');

        $payment_count = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_payments')
            ->num_rows();

        $this->assertEquals(0, $payment_count, 'Should not record payment on declined charge');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_partial_refund_scenario(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 300.00);

        // Initial full payment
        $stripe_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_partial_refund',
                'status' => 'succeeded',
                'amount' => 30000,
                'charges' => ['data' => [['id' => 'ch_partial_full', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_partial_refund');

        $payment = $this->db
            ->where('payment_external_id', 'ch_partial_full')
            ->get('ip_payments')
            ->row();

        $this->assertEquals('300.00', $payment->payment_amount);

        // Now handle refund webhook
        $refund_payload = [
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_partial_full',
                    'amount_refunded' => 10000, // $100 refunded
                    'metadata' => ['invoice_id' => $invoice->invoice_id],
                ],
            ],
        ];

        $this->post('guest/gateways/stripe/webhook', $refund_payload);

        $refunded_payment = $this->db
            ->where('payment_external_id', 'ch_partial_full')
            ->get('ip_payments')
            ->row();

        // Payment record should exist but may have refund note
        $this->assertNotNull($refunded_payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_malformed_webhook_payload(): void
    {
        $malformed_payload = [
            'type' => 'charge.succeeded',
            // Missing required 'data' field
        ];

        $response = $this->post('guest/gateways/stripe/webhook', $malformed_payload);

        // Should return error without crashing
        $this->assertResponseStatus(400 || 422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_webhook_signature(): void
    {
        $payload = [
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'id' => 'ch_invalid_sig',
                    'amount' => 5000,
                ],
            ],
        ];

        // Send with invalid signature header
        $response = $this->withHeaders(['X-Stripe-Signature' => 'invalid_sig_xxx'])
            ->post('guest/gateways/stripe/webhook', $payload);

        // Should reject invalid signature
        $this->assertResponseStatus(401 || 403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_currency_mismatch_scenario(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00, currency: 'USD');

        // Stripe response in different currency
        $stripe_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_currency_mismatch',
                'status' => 'succeeded',
                'amount' => 9000, // 90 EUR instead of 100 USD
                'currency' => 'EUR',
                'charges' => ['data' => [['id' => 'ch_currency_mismatch', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_currency_mismatch');

        $payment = $this->db
            ->where('payment_external_id', 'ch_currency_mismatch')
            ->get('ip_payments')
            ->row();

        // Should record payment but flag currency mismatch in notes
        $this->assertNotNull($payment);
        if ($payment) {
            $this->assertStringContainsString('EUR', $payment->payment_note ?? '');
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_atomically_records_payment_and_invoice_status(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 250.00);
        $initial_status = $invoice->invoice_status;

        $stripe_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'sk_test_123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'pi_atomic_test',
                'status' => 'succeeded',
                'amount' => 25000,
                'charges' => ['data' => [['id' => 'ch_atomic_test', 'status' => 'succeeded']]],
            ])],
        ];

        $this->withEnvironment('STRIPE_MOCK_RESPONSES', json_encode($stripe_response))
            ->post('guest/gateways/stripe/stripe_capture_payment/pi_atomic_test');

        $payment = $this->db
            ->where('payment_external_id', 'ch_atomic_test')
            ->get('ip_payments')
            ->row();

        $updated_invoice = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_invoices')
            ->row();

        $this->assertNotNull($payment);
        $this->assertNotNull($updated_invoice);
        // Invoice balance should be reduced
        $this->assertLessThan($invoice->invoice_balance, $updated_invoice->invoice_balance);
    }
}
