# Gateway Pattern Migration Guide

## Overview

The LetsPeppol integration has been refactored to follow a gateway pattern inspired by `PaypalLib.php`. This pattern provides a consistent, testable, and extensible architecture for all payment and e-invoicing integrations.

## Key Components

### 1. GatewayClientInterface

The core contract that all gateway clients must implement:

```php
interface GatewayClientInterface
{
    public function request(string $method, string $uri, array $options = []): ResponseInterface;
    public function buildHeaders(array $options = []): array;
    public function authorize(): void;
    public function getSettings(?string $key = null, mixed $default = null): mixed;
}
```

### 2. ApiClient (Base Class)

Abstract base class that provides common functionality:
- Guzzle HTTP client wrapping
- Endpoint mapping (logical names → actual paths)
- Settings management
- Access token storage

```php
abstract class ApiClient implements GatewayClientInterface
{
    protected ClientInterface $client;
    protected string $baseUri;
    protected array $settings;
    protected ?string $accessToken = null;
    protected array $endpoints = [];
    
    abstract public function buildHeaders(array $options = []): array;
    abstract public function authorize(): void;
}
```

### 3. LetsPeppolGatewayClient

Concrete implementation for LetsPeppol:

```php
class LetsPeppolGatewayClient extends ApiClient
{
    protected array $endpoints = [
        'participants.validate' => 'api/participants/validate',
        'invoices.send'        => 'api/invoices',
    ];
    
    public function buildHeaders(array $options = []): array { /* ... */ }
    public function authorize(): void { /* OAuth2 flow */ }
}
```

### 4. Endpoint Clients

Specialized clients for specific API operations:
- `InvoiceEndpoint` - handles invoice submission
- `ParticipantEndpoint` - handles participant validation

### 5. Provider

Implements `IntegrationProviderInterface` and ties everything together:

```php
class LetsPeppolGatewayProvider implements IntegrationProviderInterface
{
    public function validateParticipant(string $participantId): bool;
    public function sendInvoice(array $payload): bool;
}
```

## Architecture Benefits

### 1. Separation of Concerns
- **ApiClient**: HTTP communication, endpoint mapping
- **GatewayClient**: Gateway-specific authorization and headers
- **Endpoint clients**: Domain-specific operations
- **Provider**: High-level interface for the application

### 2. Testability
- All components are interface-based and dependency-injected
- Fakes over mocks (e.g., `FakeLetsPeppolHttpClient`)
- JSON fixtures for expected responses
- No real HTTP requests in unit tests

### 3. Authorization Patterns
The `authorize()` method supports multiple auth patterns:
- **OAuth2** (LetsPeppol): Client credentials flow
- **Bearer tokens** (PayPal): Direct token-based auth
- **API keys** (Stripe): Header-based authentication

### 4. Endpoint Mapping
Logical endpoint names map to actual paths:
```php
'participants.validate' => 'api/participants/validate'
'invoices.send'        => 'api/invoices'
```

This decouples the code from API structure changes.

## Database Tables

### Integration/Gateway Tables Reuse

The existing `ip_integrations` tables are reused for gateway storage:

**Tables:**
- `ip_integrations` - Gateway provider records
- `ip_integration_settings` - Encrypted settings (client_id, client_secret, base_url)
- `ip_integration_tokens` - OAuth access tokens with expiry
- `ip_integration_logs` - Audit trail

**Naming Convention:**
- **Code**: Uses "gateway" terminology
- **Database**: Keeps "integration" for backward compatibility
- **UI**: Translations map "integration" → display label

This approach:
- ✅ Avoids database migration
- ✅ Maintains backward compatibility
- ✅ Reuses existing infrastructure
- ✅ Allows gradual refactoring

## Testing Strategy

### Fixtures over Mocks

Store expected API responses as JSON fixtures:

```
tests/Fixtures/LetsPeppol/
├── participant_valid.json
├── participant_invalid.json
├── invoice_sent.json
└── oauth_token.json
```

### Fakes over Mocks

Use stateful fakes for HTTP clients:

```php
$http = new FakeLetsPeppolHttpClient(200);
$client = new LetsPeppolGatewayClient('https://api.test', [], $http);
$client->request('GET', 'participants.validate');

$http->assertRequestMade('GET', 'participants.validate');
```

### Test Coverage

Every component has comprehensive unit tests:
- ✅ `LetsPeppolGatewayClientTest` - 6 test methods
- ✅ `InvoiceEndpointTest` - 2 test methods
- ✅ `ParticipantEndpointTest` - 4 test methods
- ✅ `LetsPeppolGatewayProviderTest` - 3 test methods

## Migration Path

### Old Pattern (Adapter-based)
```php
// Old: LetsPeppolClient (simple wrapper)
$client = new LetsPeppolClient($http, $baseUrl, $endpoints, $settings);
$response = $client->request('POST', 'invoices.send', $options);

// Old: Direct endpoint usage
$endpoint = new InvoiceClient($client);
$endpoint->sendInvoice($token, $payload);
```

### New Pattern (Gateway-based)
```php
// New: LetsPeppolGatewayClient (full-featured gateway)
$gateway = new LetsPeppolGatewayClient($baseUrl, $settings);
// Authorization happens automatically on construction

// New: Gateway-aware endpoint
$endpoint = new InvoiceEndpoint($gateway);
$endpoint->sendInvoice($payload);  // No token needed, handled by gateway
```

## Adding New Gateways

To add a new gateway (e.g., StoreCove, Stripe):

1. **Create gateway client:**
   ```php
   class StripeGatewayClient extends ApiClient
   {
       protected array $endpoints = [ /* ... */ ];
       
       public function buildHeaders(array $options = []): array { /* ... */ }
       public function authorize(): void { /* API key auth */ }
   }
   ```

2. **Create endpoint clients:**
   ```php
   class PaymentEndpoint
   {
       public function __construct(private GatewayClientInterface $gateway) {}
       public function createPayment(array $data): ResponseInterface { /* ... */ }
   }
   ```

3. **Create provider:**
   ```php
   class StripeProvider implements IntegrationProviderInterface
   {
       public function validateParticipant(string $id): bool { /* ... */ }
       public function sendInvoice(array $payload): bool { /* ... */ }
   }
   ```

4. **Register in factory:**
   ```php
   $factory->register('stripe', fn () => new StripeProvider($settingsService));
   ```

5. **Add tests and fixtures:**
   - Create `tests/Fixtures/Stripe/*.json`
   - Create `tests/Unit/StripeGatewayClientTest.php`
   - Follow the existing test patterns

## Exception Handling

The `ExceptionHandlingDecorator` automatically wraps all providers:

```php
$factory->make('letspeppol');  // Returns ExceptionHandlingDecorator<LetsPeppolGatewayProvider>
```

This means:
- All exceptions are caught and logged
- Methods return `false` on error
- No try/catch needed in controllers
- Consistent error handling across all gateways

## Summary

The gateway pattern provides:
- ✅ Consistent architecture across all integrations
- ✅ Testable components with dependency injection
- ✅ Reusable infrastructure (ApiClient base class)
- ✅ Clear separation of concerns
- ✅ Easy to extend with new gateways
- ✅ Backward compatible with existing database schema
- ✅ Automatic exception handling via decorator
- ✅ Comprehensive test coverage with fakes and fixtures
