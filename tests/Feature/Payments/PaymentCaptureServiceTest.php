<?php

namespace Tests\Feature\Payments;

use Tests\AbstractTestCase;

class PaymentCaptureServiceTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_currency', 'setting_value' => 'USD']);
        $this->databaseInsertOrIgnore('ip_settings', ['setting_key' => 'gateway_paypal_payment_method', 'setting_value' => '1']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_successful_completed_payment(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-1',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-123',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-1');

        // Reset connection to ensure we see committed data
        $this->resetDatabaseConnection();

        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-123']);
        $this->assertNotEmpty($payment);
        $this->assertEquals($invoice['invoice_id'], $payment['invoice_id'] ?? null);
        $this->assertEquals('100.00', $payment['payment_amount'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_pending_payment(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 50.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-2',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-456',
                            'status' => 'PENDING',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-2');

        $this->resetDatabaseConnection();

        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-456']);
        $this->assertNotEmpty($payment);
        $this->assertStringContainsString('pending', mb_strtolower($payment['payment_note'] ?? ''));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_duplicate_payment_attempt(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 75.00);

        // First payment
        $paypal_response1 = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-3',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-DUP',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '75.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response1)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-3');

        $this->resetDatabaseConnection();
        $payment_count_1 = count($this->databaseFetchAll('ip_payments', ['payment_external_id' => 'CAP-DUP']));
        $this->assertEquals(1, $payment_count_1);

        // Second attempt with same capture ID
        $paypal_response2 = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-3-RETRY',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-DUP',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '75.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response2)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-3-RETRY');

        $this->resetDatabaseConnection();
        $payment_count_2 = count($this->databaseFetchAll('ip_payments', ['payment_external_id' => 'CAP-DUP']));
        $this->assertEquals(1, $payment_count_2); // Still only 1 payment
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_payment_with_mismatched_currency(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-4',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-CURR',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'EUR'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-4');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-CURR']);
        $this->assertEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_payment_with_insufficient_amount(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-5',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-AMOUNT',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '50.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-5');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-AMOUNT']);
        $this->assertEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_payment_within_tolerance(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-6',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-TOL',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00005', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-6');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-TOL']);
        $this->assertNotEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_payment_for_already_paid_invoice(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 0);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-7',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-PAID',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-7');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-PAID']);
        $this->assertEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_payment_for_nonexistent_invoice(): void
    {
        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-8',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-NOTFOUND',
                            'status' => 'COMPLETED',
                            'invoice_id' => 99999,
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-8');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-NOTFOUND']);
        $this->assertEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_declined_payment(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-9',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-DECLINED',
                            'status' => 'DECLINED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                            'processor_response' => ['response_code' => '1111'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-9');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-DECLINED']);
        $this->assertEmpty($payment);

        $merchant_responses = $this->databaseFetchAll('ip_merchant_responses', ['invoice_id' => $invoice['invoice_id'], 'merchant_response_successful' => 0]);
        $this->assertNotEmpty($merchant_responses);
        $this->assertStringContainsString('DECLINED', $merchant_responses[0]['merchant_response'] ?? '');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_invalid_paypal_response_structure(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode(['invalid' => 'structure'])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-10');

        $this->resetDatabaseConnection();
        $payments = $this->databaseFetchAll('ip_payments', []);
        $this->assertEmpty($payments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_missing_required_fields_in_paypal_response(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-11',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'status' => 'COMPLETED',
                            // Missing id, invoice_id, amount
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-11');

        $this->resetDatabaseConnection();
        $payments = $this->databaseFetchAll('ip_payments', []);
        $this->assertEmpty($payments);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_capture_id_exceeding_max_length(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $long_capture_id = str_repeat('A', 300);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-12',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => $long_capture_id,
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-12');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => $long_capture_id]);
        $this->assertEmpty($payment);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_merchant_response_on_successful_capture(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-RESP',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-RESP',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-RESP');

        $this->resetDatabaseConnection();
        $merchant_responses = $this->databaseFetchAll('ip_merchant_responses', ['invoice_id' => $invoice['invoice_id'], 'merchant_response_successful' => 1]);

        $this->assertNotEmpty($merchant_responses);
        $this->assertEquals('COMPLETED', $merchant_responses[0]['merchant_response'] ?? null);
        $this->assertStringContainsString('ORDER-RESP', $merchant_responses[0]['merchant_response_reference'] ?? '');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_order_id_with_hyphens(): void
    {
        $invoice = $this->seedPayableInvoice(invoice_balance: 100.00);

        $paypal_response = [
            ['status' => 200, 'body' => json_encode(['access_token' => 'token123'])],
            ['status' => 200, 'body' => json_encode([
                'id' => 'ORDER-WITH-HYPHENS',
                'purchase_units' => [[
                    'payments' => [
                        'captures' => [[
                            'id' => 'CAP-HYPHEN',
                            'status' => 'COMPLETED',
                            'invoice_id' => $invoice['invoice_id'],
                            'amount' => ['value' => '100.00', 'currency_code' => 'USD'],
                        ]],
                    ],
                ]],
            ])],
        ];

        $this->withEnvironment(['PAYPAL_MOCK_RESPONSES' => json_encode($paypal_response)]);
        $this->post('guest/gateways/paypal/paypal_capture_payment/ORDER-WITH-HYPHENS');

        $this->resetDatabaseConnection();
        $payment = $this->databaseFetchOne('ip_payments', ['payment_external_id' => 'CAP-HYPHEN']);
        $this->assertNotEmpty($payment);
    }

    private function seedPayableInvoice(float $invoice_balance = 100.00): array
    {
        $clientId = $this->seedClient(['client_name' => 'Test Client']);
        $invoiceId = $this->seedInvoice($clientId, ['invoice_status_id' => 2], ['invoice_balance' => (string) $invoice_balance]);

        return $this->databaseFetchOne('ip_invoices', ['invoice_id' => $invoiceId]);
    }

    private function databaseFetchAll(string $table, array $where): array
    {
        $db = $this->db();

        $whereClause = '';
        $params = [];
        if (!empty($where)) {
            $parts = [];
            foreach ($where as $key => $value) {
                $parts[] = $this->qi($key) . ' = :' . $key;
                $params[$key] = $value;
            }
            $whereClause = ' WHERE ' . implode(' AND ', $parts);
        }

        $sql = 'SELECT * FROM ' . $this->qi($table) . $whereClause;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function qi(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function db()
    {
        return $this->testDb();
    }

    private function testDb()
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $cache = new \PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s', env('DB_HOSTNAME'), env('DB_PORT'), env('DB_DATABASE')),
            env('DB_USERNAME'),
            env('DB_PASSWORD')
        );

        return $cache;
    }
}
