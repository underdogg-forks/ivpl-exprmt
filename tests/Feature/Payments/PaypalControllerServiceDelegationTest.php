<?php

namespace Tests\Feature\Payments;

use Tests\AbstractTestCase;

class PaypalControllerServiceDelegationTest extends AbstractTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_delegates_capture_payment_to_service(): void
    {
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_currency', 'setting_value' => 'USD']);
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_payment_method', 'setting_value' => '1']);

        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-SERVICE',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-SERVICE',
                            'status' => 'COMPLETED',
                            'invoice_id' => (string) $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $response = $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-SERVICE');

        $this->assertNoApplicationError($response);
        $this->assertResponseStatusCode($response, 200);

        $this->resetDatabaseConnection();

        // If service is properly delegated to, payment should be recorded
        $this->assertDatabaseHas('ip_payments', ['payment_external_id' => 'CAP-SERVICE', 'invoice_id' => $invoice['invoice_id']]);

        // Merchant response should be recorded by service
        $this->assertDatabaseHas('ip_merchant_responses', [
            'invoice_id' => $invoice['invoice_id'],
            'merchant_response_successful' => 1,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_performs_single_invoice_query_not_multiple(): void
    {
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_currency', 'setting_value' => 'USD']);
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_payment_method', 'setting_value' => '1']);

        $invoice = $this->seedPayableInvoice(invoice_balance: 50.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-SINGLE',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-SINGLE',
                            'status' => 'COMPLETED',
                            'invoice_id' => (string) $invoice['invoice_id'],
                            'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-SINGLE');

        $this->resetDatabaseConnection();

        $this->assertDatabaseHas('ip_payments', ['payment_external_id' => 'CAP-SINGLE', 'invoice_id' => $invoice['invoice_id']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_declined_payment_through_service(): void
    {
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_currency', 'setting_value' => 'USD']);

        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-DECLINED',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-DECLINED',
                            'status' => 'DECLINED',
                            'invoice_id' => (string) $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                            'processor_response' => ['response_code' => '1234'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-DECLINED');

        $this->resetDatabaseConnection();

        // Service should record failed transaction
        $this->assertDatabaseHas('ip_merchant_responses', [
            'invoice_id' => $invoice['invoice_id'],
            'merchant_response_successful' => 0,
        ]);

        $merchant_response = $this->databaseFetchOne('ip_merchant_responses', [
            'invoice_id' => $invoice['invoice_id'],
            'merchant_response_successful' => 0,
        ]);
        $this->assertStringContainsString('DECLINED', $merchant_response['merchant_response'] ?? '');
    }

    private function seedPayableInvoice(float $invoice_balance = 100.00)
    {
        $clientId = $this->seedClient(['client_name' => 'Test Client']);
        $invoiceId = $this->seedInvoice($clientId, ['invoice_status_id' => 2], ['invoice_balance' => (string) $invoice_balance]);

        return $this->databaseFetchOne('ip_invoices', ['invoice_id' => $invoiceId]);
    }
}
