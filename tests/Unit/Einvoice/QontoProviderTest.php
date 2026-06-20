<?php

namespace Tests\Unit\Einvoice;

use PHPUnit\Framework\TestCase;
use QontoProvider;
use RuntimeException;

/**
 * Stubs request() so no real HTTP is made.
 */
class FakeQontoProvider extends QontoProvider
{
    public array $requestLog = [];
    private array $responses;
    private int $callIndex = 0;

    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    protected function request(
        string $method,
        string $url,
        array $payload = [],
        bool $multipart = false,
        array $requestDebug = []
    ): array {
        $this->requestLog[] = compact('method', 'url', 'payload', 'multipart', 'requestDebug');

        return $this->responses[$this->callIndex++] ?? [
            'success' => true,
            'external_id' => null,
            'status' => 'sent',
            'message' => 'ok',
            'http_code' => 200,
            'request' => ['url' => $url, 'method' => $method],
            'response' => [],
        ];
    }
}

class QontoProviderTest extends TestCase
{
    private function defaultSettings(): array
    {
        return [
            'access_token' => 'test-token',
            'staging_token' => '',
            'api_base_url' => 'https://thirdparty.qonto.com',
            'upload_endpoint' => '/v2/client_invoices/uploads',
            'invoice_endpoint' => '/v2/client_invoices',
            'send_invoice_endpoint' => '/v2/client_invoices/{id}/send_by_einvoice',
            'invoice_status_endpoint' => '/v2/client_invoices/{id}',
            'incoming_invoices_endpoint' => '/v2/supplier_invoices',
            'invoice_events_endpoint' => '/v2/client_invoices/{id}',
        ];
    }

    // --- authenticate ---

    public function test_authenticate_returns_true_with_valid_settings(): void
    {
        // Arrange
        $provider = new FakeQontoProvider();

        // Act
        $result = $provider->authenticate($this->defaultSettings());

        // Assert
        $this->assertTrue($result);
    }

    public function test_authenticate_throws_when_access_token_missing(): void
    {
        // Arrange
        $provider = new FakeQontoProvider();
        $settings = $this->defaultSettings();
        $settings['access_token'] = '';

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing Qonto setting: access_token');
        $provider->authenticate($settings);
    }

    public function test_authenticate_throws_when_api_base_url_missing(): void
    {
        // Arrange
        $provider = new FakeQontoProvider();
        $settings = $this->defaultSettings();
        $settings['api_base_url'] = '';

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing Qonto setting: api_base_url');
        $provider->authenticate($settings);
    }

    // --- sendInvoice ---

    public function test_sendInvoice_throws_when_file_not_found(): void
    {
        // Arrange
        $provider = new FakeQontoProvider();
        $provider->authenticate($this->defaultSettings());

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice document not found');
        $provider->sendInvoice('/nonexistent/invoice.pdf', []);
    }

    public function test_sendInvoice_returns_error_when_upload_fails(): void
    {
        // Arrange — first request() call (upload) returns failure
        $provider = new FakeQontoProvider([
            ['success' => false, 'external_id' => null, 'status' => 'error', 'message' => 'Upload failed', 'http_code' => 500, 'request' => [], 'response' => []],
        ]);
        $provider->authenticate($this->defaultSettings());
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');

        // Act
        $result = $provider->sendInvoice($tmp, []);
        unlink($tmp);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertCount(1, $provider->requestLog);
    }

    public function test_sendInvoice_returns_error_when_qonto_payload_missing(): void
    {
        // Arrange — upload succeeds with a real external_id but metadata has no qonto_invoice_payload
        $provider = new FakeQontoProvider([
            ['success' => true, 'external_id' => 'upload-1', 'status' => 'sent', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => ['data' => ['id' => 'upload-1']]],
        ]);
        $provider->authenticate($this->defaultSettings());
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');

        // Act
        $result = $provider->sendInvoice($tmp, []);
        unlink($tmp);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('qonto_invoice_payload', $result['message']);
    }

    public function test_sendInvoice_succeeds_through_all_three_steps(): void
    {
        // Arrange — upload → create → send
        $provider = new FakeQontoProvider([
            ['success' => true, 'external_id' => 'upload-1', 'status' => 'sent', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => ['data' => ['id' => 'upload-1']]],
            ['success' => true, 'external_id' => 'cinv-1', 'status' => 'sent', 'message' => 'ok', 'http_code' => 201, 'request' => [], 'response' => ['client_invoice' => ['id' => 'cinv-1']]],
            ['success' => true, 'external_id' => 'cinv-1', 'status' => 'sent', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => []],
        ]);
        $provider->authenticate($this->defaultSettings());
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');
        $metadata = ['qonto_invoice_payload' => ['client_invoice' => ['number' => 'INV-001']]];

        // Act
        $result = $provider->sendInvoice($tmp, $metadata);
        unlink($tmp);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(3, $provider->requestLog);
        $this->assertSame('cinv-1', $result['external_id']);
    }

    // --- getInvoiceStatus ---

    public function test_getInvoiceStatus_builds_correct_url(): void
    {
        // Arrange
        $provider = new FakeQontoProvider([
            ['success' => true, 'external_id' => 'inv-99', 'status' => 'sent', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => ['client_invoice' => ['status' => 'paid']]],
        ]);
        $provider->authenticate($this->defaultSettings());

        // Act
        $result = $provider->getInvoiceStatus('inv-99');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('inv-99', $provider->requestLog[0]['url']);
    }

    public function test_getInvoiceStatus_throws_when_endpoint_missing(): void
    {
        // Arrange
        $provider = new FakeQontoProvider();
        $settings = $this->defaultSettings();
        $settings['invoice_status_endpoint'] = '';
        $provider->authenticate($settings);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->getInvoiceStatus('inv-1');
    }

    // --- receiveInvoices ---

    public function test_receiveInvoices_passes_filters_as_query_string(): void
    {
        // Arrange
        $provider = new FakeQontoProvider([
            ['success' => true, 'external_id' => null, 'status' => 'received', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => []],
        ]);
        $provider->authenticate($this->defaultSettings());

        // Act
        $provider->receiveInvoices(['status' => 'pending', 'page' => 2]);

        // Assert
        $this->assertStringContainsString('status=pending', $provider->requestLog[0]['url']);
        $this->assertStringContainsString('page=2', $provider->requestLog[0]['url']);
    }

    // --- getInvoiceEvents ---

    public function test_getInvoiceEvents_makes_get_request(): void
    {
        // Arrange
        $provider = new FakeQontoProvider([
            ['success' => true, 'external_id' => null, 'status' => 'events_received', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => []],
        ]);
        $provider->authenticate($this->defaultSettings());

        // Act
        $provider->getInvoiceEvents();

        // Assert
        $this->assertSame('GET', $provider->requestLog[0]['method']);
    }

    // --- providerCode / providerName ---

    public function test_providerCode_returns_qonto(): void
    {
        $this->assertSame('qonto', QontoProvider::providerCode());
    }

    public function test_providerName_returns_qonto_pa(): void
    {
        $this->assertSame('Qonto PA', QontoProvider::providerName());
    }

    public function test_defaultSettings_contains_required_keys(): void
    {
        $settings = QontoProvider::defaultSettings();

        foreach (['access_token', 'api_base_url', 'upload_endpoint', 'invoice_endpoint', 'send_invoice_endpoint'] as $key) {
            $this->assertArrayHasKey($key, $settings);
        }
    }
}
