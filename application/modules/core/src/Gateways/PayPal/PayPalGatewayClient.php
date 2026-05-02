<?php

namespace Core\Gateways\PayPal;

use Core\Gateways\ApiClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;

/**
 * PayPal gateway API client.
 *
 * Example implementation showing how to create a new gateway following the pattern.
 * Based on the existing PaypalLib.php but using the new gateway architecture.
 */
class PayPalGatewayClient extends ApiClient
{
    /**
     * PayPal API endpoints mapping.
     *
     * Note: Endpoints with path parameters (like {id}) should not use endpoint keys.
     * Instead, build the full path in the calling code, e.g.:
     * $gateway->request('POST', "v2/checkout/orders/{$orderId}/capture", $options)
     */
    protected array $endpoints = [
        'orders.create' => 'v2/checkout/orders',
        'oauth.token'   => 'v1/oauth2/token',
    ];

    private string $partnerAttributionId = 'ANGELLFREEInc_SP';

    public function __construct(
        string $baseUri,
        array $settings = [],
        ?ClientInterface $client = null
    ) {
        // Support sandbox/demo mode
        if (!empty($settings['demo']) && $settings['demo']) {
            $baseUri = 'https://api-m.sandbox.paypal.com';
        }

        parent::__construct($baseUri, $settings, $client);

        // Auto-authorize on construction if credentials are available
        if ($this->hasCredentials()) {
            $this->authorize();
        }
    }

    /**
     * {@inheritDoc}
     *
     * Build PayPal-specific headers with Bearer token and partner attribution.
     */
    public function buildHeaders(array $options = []): array
    {
        $headers = [
            'Content-Type'                  => $options['content_type'] ?? 'application/json',
            'PayPal-Partner-Attribution-Id' => $this->partnerAttributionId,
        ];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        // Optional PayPal-Request-Id for idempotency
        if (!empty($options['request_id'])) {
            $headers['PayPal-Request-Id'] = $options['request_id'];
        }

        // Optional Prefer header for detailed responses
        if (!empty($options['prefer'])) {
            $headers['Prefer'] = $options['prefer'];
        }

        return $headers;
    }

    /**
     * {@inheritDoc}
     *
     * Authorize with PayPal using client credentials OAuth2 flow.
     */
    public function authorize(): void
    {
        if (!$this->hasCredentials()) {
            log_message('debug', 'PayPal authorization skipped: missing credentials');

            return;
        }

        try {
            log_message('debug', 'PayPal authorization started');

            $response = $this->client->request('POST', $this->baseUri . '/v1/oauth2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'auth'        => [$this->settings['client_id'], $this->settings['client_secret']],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]);

            $data              = json_decode($response->getBody()->getContents(), true);
            $this->accessToken = $data['access_token'] ?? null;

            log_message('debug', 'PayPal authorization completed');
        } catch (ClientException $e) {
            log_message('error', 'PayPal authorization failed: ' . $this->sanitize($e->getMessage()));
        } catch (\Throwable $e) {
            log_message('error', 'PayPal authorization error: ' . $this->sanitize($e->getMessage()));
        }
    }

    /**
     * Generate UUID v4 for PayPal-Request-Id (idempotency).
     */
    public function generateRequestId(string $context = ''): string
    {
        $data = random_bytes(16);
        // version 4
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // variant RFC 4122
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', mb_str_split(bin2hex($data), 4));

        return 'ip' . ($context ? "-{$context}" : '') . '-' . $uuid;
    }

    /**
     * Check if required credentials are present in settings.
     */
    private function hasCredentials(): bool
    {
        return !empty($this->settings['client_id'])
            && !empty($this->settings['client_secret']);
    }

    /**
     * Sanitize log messages to prevent log injection.
     */
    private function sanitize(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
