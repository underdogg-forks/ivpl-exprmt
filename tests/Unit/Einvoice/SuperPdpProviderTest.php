<?php

namespace Tests\Unit\Einvoice;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SuperPdpProvider;

/**
 * Stubs fetchToken() and request() so no real HTTP is made.
 */
class FakeSuperPdpProvider extends SuperPdpProvider
{
    public array $requestLog = [];
    public array $tokenLog = [];
    private array $responses;
    private int $callIndex = 0;
    private array $tokenResponse;
    private ?string $tokenError;

    public function __construct(
        array $responses = [],
        array $tokenResponse = ['access_token' => 'fake-token'],
        ?string $tokenError = null
    ) {
        $this->responses = $responses;
        $this->tokenResponse = $tokenResponse;
        $this->tokenError = $tokenError;
    }

    protected function fetchToken(string $tokenUrl, string $clientId, string $clientSecret): array
    {
        $this->tokenLog[] = compact('tokenUrl', 'clientId', 'clientSecret');

        if ($this->tokenError !== null) {
            throw new RuntimeException($this->tokenError);
        }

        return $this->tokenResponse;
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

class SuperPdpProviderTest extends TestCase
{
    private function defaultSettings(): array
    {
        return [
            'client_id' => 'cid',
            'client_secret' => 'csecret',
            'token_url' => 'https://api.superpdp.tech/oauth2/token',
            'api_base_url' => 'https://api.superpdp.tech',
            'invoice_endpoint' => '/v1.beta/invoices',
            'invoice_status_endpoint' => '/v1.beta/invoices/{id}',
            'incoming_invoices_endpoint' => '/v1.beta/invoices',
            'invoice_events_endpoint' => '/v1.beta/invoice_events',
            'disable_pre_check' => false,
        ];
    }

    // --- authenticate ---

    public function test_authenticate_calls_fetchToken_with_correct_args(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();

        // Act
        $result = $provider->authenticate($this->defaultSettings());

        // Assert
        $this->assertTrue($result);
        $this->assertCount(1, $provider->tokenLog);
        $this->assertSame('https://api.superpdp.tech/oauth2/token', $provider->tokenLog[0]['tokenUrl']);
        $this->assertSame('cid', $provider->tokenLog[0]['clientId']);
        $this->assertSame('csecret', $provider->tokenLog[0]['clientSecret']);
    }

    public function test_authenticate_throws_when_client_id_missing(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $settings = $this->defaultSettings();
        $settings['client_id'] = '';

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing SuperPDP OAuth2 settings.');
        $provider->authenticate($settings);
    }

    public function test_authenticate_throws_when_client_secret_missing(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $settings = $this->defaultSettings();
        $settings['client_secret'] = '';

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->authenticate($settings);
    }

    public function test_authenticate_throws_when_token_url_missing(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $settings = $this->defaultSettings();
        $settings['token_url'] = '';

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->authenticate($settings);
    }

    public function test_authenticate_throws_when_fetchToken_returns_no_access_token(): void
    {
        // Arrange — token endpoint returns response without access_token
        $provider = new FakeSuperPdpProvider([], ['error' => 'invalid_client']);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no access_token');
        $provider->authenticate($this->defaultSettings());
    }

    public function test_authenticate_propagates_fetchToken_exception(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider([], ['access_token' => 'tok'], 'SuperPDP OAuth error: connection refused');

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('connection refused');
        $provider->authenticate($this->defaultSettings());
    }

    // --- sendInvoice ---

    public function test_sendInvoice_throws_when_file_not_found(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($this->defaultSettings());

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invoice document not found');
        $provider->sendInvoice('/nonexistent/invoice.pdf', []);
    }

    public function test_sendInvoice_throws_when_access_token_absent(): void
    {
        // Arrange — settings applied manually so api_base_url is present but no token exchange happened
        $provider = new FakeSuperPdpProvider();
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');

        // Inject settings directly via authenticate with a provider that returns no token
        $noTokenProvider = new FakeSuperPdpProvider([], []);
        try {
            $noTokenProvider->authenticate($this->defaultSettings());
        } catch (RuntimeException) {
            // expected — no access_token returned
        }

        try {
            // Act + Assert
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Missing SuperPDP access token.');
            $noTokenProvider->sendInvoice($tmp, []);
        } finally {
            unlink($tmp);
        }
    }

    public function test_sendInvoice_makes_multipart_post(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($this->defaultSettings());
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');

        // Act
        $result = $provider->sendInvoice($tmp, ['ref' => 'INV-001']);
        unlink($tmp);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertSame('POST', $provider->requestLog[0]['method']);
        $this->assertTrue($provider->requestLog[0]['multipart']);
    }

    public function test_sendInvoice_appends_disable_pre_check_flag(): void
    {
        // Arrange
        $settings = $this->defaultSettings();
        $settings['disable_pre_check'] = true;
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($settings);
        $tmp = tempnam(sys_get_temp_dir(), 'inv') . '.pdf';
        file_put_contents($tmp, '%PDF-1.4');

        // Act
        $provider->sendInvoice($tmp, []);
        unlink($tmp);

        // Assert
        $this->assertStringContainsString('disable_pre_check=1', $provider->requestLog[0]['url']);
    }

    // --- getInvoiceStatus ---

    public function test_getInvoiceStatus_interpolates_id_in_url(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider([
            ['success' => true, 'external_id' => 'inv-5', 'status' => 'sent', 'message' => 'ok', 'http_code' => 200, 'request' => [], 'response' => []],
        ]);
        $provider->authenticate($this->defaultSettings());

        // Act
        $result = $provider->getInvoiceStatus('inv-5');

        // Assert
        $this->assertSame('inv-5', $result['external_id']);
        $this->assertStringContainsString('inv-5', $provider->requestLog[0]['url']);
        $this->assertStringNotContainsString('{id}', $provider->requestLog[0]['url']);
    }

    public function test_getInvoiceStatus_throws_when_endpoint_missing(): void
    {
        // Arrange
        $settings = $this->defaultSettings();
        $settings['invoice_status_endpoint'] = '';
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($settings);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->getInvoiceStatus('inv-1');
    }

    // --- receiveInvoices ---

    public function test_receiveInvoices_passes_filters_as_query(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($this->defaultSettings());

        // Act
        $provider->receiveInvoices(['status' => 'new', 'page' => 1]);

        // Assert
        $this->assertStringContainsString('status=new', $provider->requestLog[0]['url']);
    }

    public function test_receiveInvoices_throws_when_endpoint_missing(): void
    {
        // Arrange
        $settings = $this->defaultSettings();
        $settings['incoming_invoices_endpoint'] = '';
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($settings);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->receiveInvoices();
    }

    // --- getInvoiceEvents ---

    public function test_getInvoiceEvents_makes_get_request(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($this->defaultSettings());

        // Act
        $provider->getInvoiceEvents();

        // Assert
        $this->assertSame('GET', $provider->requestLog[0]['method']);
    }

    public function test_getInvoiceEvents_throws_when_endpoint_missing(): void
    {
        // Arrange
        $settings = $this->defaultSettings();
        $settings['invoice_events_endpoint'] = '';
        $provider = new FakeSuperPdpProvider();
        $provider->authenticate($settings);

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $provider->getInvoiceEvents();
    }

    // --- static metadata ---

    public function test_providerCode_returns_superpdp(): void
    {
        $this->assertSame('superpdp', SuperPdpProvider::providerCode());
    }

    public function test_providerName_returns_SuperPDP(): void
    {
        $this->assertSame('SuperPDP', SuperPdpProvider::providerName());
    }

    public function test_defaultSettings_contains_required_oauth_keys(): void
    {
        $settings = SuperPdpProvider::defaultSettings();

        foreach (['client_id', 'client_secret', 'token_url', 'api_base_url'] as $key) {
            $this->assertArrayHasKey($key, $settings);
        }
    }

    public function test_buildInvoicePayload_returns_metadata_unchanged(): void
    {
        // Arrange
        $provider = new FakeSuperPdpProvider();
        $metadata = ['ref' => 'INV-001', 'custom' => true];

        // Act
        $result = $provider->buildInvoicePayload((object)[], [], $metadata);

        // Assert
        $this->assertSame($metadata, $result);
    }
}
