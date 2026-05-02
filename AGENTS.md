# AGENTS.md — AI Agent Context for InvoicePlane

> **Purpose:** This file gives AI coding agents (GitHub Copilot, Codex, Claude, etc.) the context they need to contribute effectively without reinventing the wheel.  Keep it up to date as the codebase evolves.

---

## Project at a Glance

| Item | Detail |
|---|---|
| **Name** | InvoicePlane |
| **Type** | Self-hosted invoicing application |
| **License** | MIT |
| **Backend** | PHP 8.1+ · CodeIgniter 3 (legacy) → migrating to Laravel |
| **Frontend** | JavaScript · jQuery · HTML · CSS |
| **Database** | MySQL / MariaDB |
| **Tests** | PHPUnit 11+ (unit), Playwright (e2e) |
| **Package manager** | Composer (PHP) · npm / Yarn (JS) |

---

## Repository Layout

```
application/
├── config/            CI config files
├── controllers/       Global CI controllers
├── core/              MY_* CI core overrides
├── helpers/           Global helpers (file_security_helper, pdf_helper, …)
├── lib/
│   └── App/           PSR-4 namespaced code (new / migrating code lives here)
│       ├── Adapters/  External service adapters (LetsPeppol, …) — ⚠️ Legacy pattern
│       ├── Contracts/ Interfaces (IntegrationProviderInterface, GatewayClientInterface, …)
│       ├── Enums/     PHP 8.1 enums (RequestMethod, …)
│       ├── Gateways/  Gateway pattern implementations (NEW)
│       │   ├── ApiClient.php          ← Base class
│       │   └── LetsPeppol/
│       │       ├── LetsPeppolGatewayClient.php
│       │       └── Endpoints/         ← Domain-specific operations
│       ├── Integration/ Value objects (IntegrationCredentials, IntegrationSetting)
│       ├── Providers/ Concrete provider implementations
│       └── Services/  Application services
│           ├── Clients/       ClientsService
│           └── Integrations/  IntegrationProviderFactory, IntegrationSettingsService
├── modules/           HMVC modules (clients, invoices, integrations, …)
│   └── <module>/
│       ├── controllers/
│       ├── models/
│       └── views/
└── third_party/
    └── MX/Namespaced/ Compatibility shims for CI/MX classes under App\ namespace

bootstrap/
└── autoload.php       Registers MX/CI shims via spl_autoload_register

tests/
├── Fakes/             Test fakes (FakeLetsPeppolHttpClient, …)
├── Fixtures/          JSON fixtures for expected API responses
│   └── LetsPeppol/    LetsPeppol API response fixtures
├── Unit/              PHPUnit unit tests
├── Feature/           PHPUnit feature/integration tests
├── e2e/               Playwright end-to-end tests
└── phpunit-parallel-bootstrap.php  Bootstrap (defines CI stubs for unit tests)
```

---

## Naming Conventions

| Layer | Convention | Example |
|---|---|---|
| CI controllers | PascalCase, extends Admin_Controller | `Clients`, `Invoices` |
| CI models | `Mdl_` prefix + snake_case | `Mdl_invoices`, `Mdl_integrations` |
| App\ services | PascalCase + `Service` suffix | `ClientsService`, `IntegrationSettingsService` |
| App\ providers | PascalCase + `Provider` suffix | `LetsPeppolProvider` |
| App\ factories | PascalCase + `Factory` suffix | `IntegrationProviderFactory`, `LetsPeppolClientFactory` |
| Test classes | Match tested class + `Test` suffix | `ClientsServiceTest` |
| Test methods | `it_` prefix + snake_case | `it_returns_false_when_provider_not_registered` |

---

## Integration Provider Pattern

New external payment / e-invoicing networks follow the **provider pattern**.

### ExceptionHandlingDecorator — Automatic Safety for All Providers

`IntegrationProviderFactory::make()` automatically wraps every resolved provider in
`App\Providers\ExceptionHandlingDecorator`.  This means:

- **No try/catch needed in controllers or services** — exception safety is free.
- Any future provider (StoreCove, Stripe, PayPal) gets the same protection without extra code.
- The decorator logs a sanitized error message and returns `false` on any `Throwable`.

```php
// You never need to write this:
try {
    $result = $factory->make('stripe')->sendInvoice($payload);
} catch (Throwable $e) { ... }

// This is all you need — the decorator handles the rest:
$result = $factory->make('stripe')->sendInvoice($payload);   // never throws
```

### PSR-4 Controller Naming

New module controllers can use the PSR-4 `IntegrationsController` style instead of the
legacy `Integrations_Controller`.  `MY_Router` detects `IntegrationsController.php` and
aliases it so MX can load it without changes to the URL or routing rules.

```php
// application/modules/integrations/controllers/IntegrationsController.php
class IntegrationsController extends Admin_Controller { ... }   // PSR-4 style ✅
// OR the legacy style still works:
// application/modules/integrations/controllers/Integrations.php
class Integrations extends Admin_Controller { ... }
```

### Hiding a Module Prefix from the URL (e.g. "core" module)

When controllers are grouped inside a `core` module to keep the top-level module list
tidy, use `MY_Router::$moduleAliases` — **no routes.php changes needed**:

```php
// application/core/MY_Router.php — edit the $moduleAliases property:
protected array $moduleAliases = [
    'integrations' => 'core/integrations',
    'storecove'    => 'core/storecove',
];
```

`/integrations/index` now resolves to
`modules/core/controllers/integrations/IntegrationsController.php` (PSR-4) or
`modules/core/controllers/integrations/Integrations.php` (legacy).

MY_Router intercepts the URL segment before MX runs, expands it to the real module
path, and the rest of the resolution is standard MX — no routes.php entries needed.

### Adding a New Provider (e.g. StoreCove)

1. **Implement the contract:**
   ```php
   // application/lib/App/Providers/StoreCoveProvider.php
   class StoreCoveProvider implements \App\Contracts\IntegrationProviderInterface
   {
       public function validateParticipant(string $participantId): bool { /* … */ }
       public function sendInvoice(array $payload): bool { /* … */ }
   }
   ```

2. **Register in the controller / bootstrap:**
   ```php
   $factory = (new IntegrationProviderFactory())
       ->register('storecove', fn () => new StoreCoveProvider($settingsService));
   ```

3. **Use generically – no changes to callers:**
   ```php
   $provider = $factory->make('storecove');
   $provider->sendInvoice($payload);
   ```

4. **Write unit tests** in `tests/Unit/StoreCoveProviderTest.php`.

### Existing Providers

| Key | Class | Status |
|---|---|---|
| `letspeppol` | `App\Providers\LetsPeppolGatewayProvider` | ✅ Active (new gateway pattern) |
| `letspeppol` (legacy) | `App\Providers\LetsPeppolProvider` | ⚠️ Deprecated (adapter pattern) |
| `storecove` | — | 🔲 Planned |
| `stripe` | — | 🔲 Planned |
| `paypal` | — | 🔲 Planned |

> `App\Services\Integrations\LetsPeppolService` is a **deprecated** backward-compat shim
> that extends `LetsPeppolProvider`.  Do **not** use it in new code.

---

## Gateway Pattern (New Architecture)

The **gateway pattern** is the new recommended architecture for all payment and e-invoicing
integrations, inspired by `PaypalLib.php`. It supersedes the older adapter pattern.

### Architecture Overview

```
application/lib/App/
├── Contracts/
│   └── GatewayClientInterface.php    ← Core contract
├── Gateways/
│   ├── ApiClient.php                 ← Abstract base class
│   └── LetsPeppol/
│       ├── LetsPeppolGatewayClient.php  ← Concrete gateway client
│       └── Endpoints/
│           ├── InvoiceEndpoint.php      ← Domain-specific operations
│           └── ParticipantEndpoint.php
├── Providers/
│   └── LetsPeppolGatewayProvider.php ← IntegrationProviderInterface impl
```

### Key Components

#### 1. GatewayClientInterface

Defines the contract all gateway clients must implement:

```php
interface GatewayClientInterface
{
    public function request(string $method, string $uri, array $options = []): ResponseInterface;
    public function buildHeaders(array $options = []): array;
    public function authorize(): void;
    public function getSettings(?string $key = null, mixed $default = null): mixed;
}
```

#### 2. ApiClient (Base Class)

Abstract base providing common functionality:
- Guzzle HTTP client wrapping with decorated `request()` method
- Endpoint mapping (logical names → actual paths)
- Settings management (`getSettings()`)
- Access token storage

Concrete gateway clients extend this and implement:
- `buildHeaders()` — gateway-specific headers
- `authorize()` — gateway-specific auth (OAuth2, Bearer token, API key)

#### 3. Gateway Client (e.g., LetsPeppolGatewayClient)

Concrete implementation with:
- Endpoint mapping
- OAuth2/Bearer token authorization
- Header building logic
- Auto-authorization on construction (when credentials present)

```php
$gateway = new LetsPeppolGatewayClient($baseUrl, $settings);
// ↑ Authorization happens automatically
```

#### 4. Endpoint Clients

Domain-specific operation clients that depend on `GatewayClientInterface`:

```php
class InvoiceEndpoint
{
    public function __construct(private GatewayClientInterface $gateway) {}
    
    public function sendInvoice(array $payload): ResponseInterface
    {
        $headers = $this->gateway->buildHeaders();
        return $this->gateway->request('POST', 'invoices.send', [
            'headers' => $headers,
            'json'    => $payload,
        ]);
    }
}
```

#### 5. Gateway Provider

Implements `IntegrationProviderInterface` and ties everything together:

```php
class LetsPeppolGatewayProvider implements IntegrationProviderInterface
{
    public function validateParticipant(string $participantId): bool
    {
        $gateway = $this->createGatewayClient();
        $endpoint = new ParticipantEndpoint($gateway);
        return $endpoint->validatePeppolId($participantId);
    }
    
    public function sendInvoice(array $payload): bool
    {
        $gateway = $this->createGatewayClient();
        $endpoint = new InvoiceEndpoint($gateway);
        $response = $endpoint->sendInvoice($payload);
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}
```

### Testing Strategy

**Prefer fakes over mocks:**

```php
$http = new FakeLetsPeppolHttpClient(200);
$gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
$gateway->request('GET', 'participants.validate');

$http->assertRequestMade('GET', 'participants.validate');
```

**Use JSON fixtures for expected responses:**

```
tests/Fixtures/LetsPeppol/
├── participant_valid.json
├── participant_invalid.json
├── invoice_sent.json
└── oauth_token.json
```

**Test structure:**
- `LetsPeppolGatewayClientTest.php` — Test gateway client (authorization, headers, requests)
- `InvoiceEndpointTest.php` — Test invoice endpoint with fixtures
- `ParticipantEndpointTest.php` — Test participant endpoint with fixtures
- `LetsPeppolGatewayProviderTest.php` — Test provider integration

### Gateway vs Adapter Pattern

| Aspect | Adapter (Old) | Gateway (New) |
|---|---|---|
| **Base class** | None | `ApiClient` |
| **Authorization** | Manual token passing | Automatic via `authorize()` |
| **Headers** | Built in endpoints | Centralized in `buildHeaders()` |
| **Settings** | Passed to each call | Stored in gateway via `getSettings()` |
| **Testing** | Mocks | Fakes + Fixtures |
| **Reusability** | Limited | High (base class shared) |

### Database Tables

Gateway data is stored in existing "integration" tables (backward compatible):

- `ip_integrations` — Gateway provider records
- `ip_integration_settings` — Encrypted settings (client_id, client_secret, base_url)
- `ip_integration_tokens` — OAuth tokens with expiry
- `ip_integration_logs` — Audit trail

**Naming Convention:**
- **Code**: "gateway" terminology
- **Database**: "integration" (unchanged)
- **UI**: Translations handle display

### Adding a New Gateway

See `docs/GATEWAY_PATTERN.md` for detailed guide. Quick summary:

1. Extend `ApiClient` → implement `buildHeaders()` and `authorize()`
2. Create endpoint clients (e.g., `PaymentEndpoint`)
3. Create provider implementing `IntegrationProviderInterface`
4. Add tests with fakes and fixtures
5. Register in `IntegrationProviderFactory`

---

## Running Tests

```bash
# PHP unit tests (requires vendor/ installed via composer install)
vendor/bin/phpunit --configuration phpunit.xml.dist

# Or via Composer script
composer test:php

# Playwright e2e tests (requires npm install)
npx playwright test
# or
npm run test:e2e
```

### Test Bootstrap (CI stubs)

`tests/phpunit-parallel-bootstrap.php` defines lightweight stubs for `CI_Model`,
`Mdl_integrations`, and `Crypt` so unit tests can mock these classes without
bootstrapping the full CodeIgniter framework.

---

## Code Style

- **PHP**: PSR-12 via `vendor/bin/pint` or `composer phpcs`
- **Early returns** are preferred over deeply nested `if` blocks
- **Type hints** on all parameters and return types
- **Strict comparison** (`===`, `!==`) everywhere

---

## Security Must-Dos

| Rule | Why |
|---|---|
| Sanitize before logging | Log injection prevention |
| Encode all output (`html_escape()`) | XSS prevention |
| Use Query Builder / prepared statements | SQL injection prevention |
| Validate file paths with `file_security_helper.php` | Path traversal prevention |
| No SVG uploads | SVG-based XSS prevention |

See `.junie/guidelines.md` for full details.

---

## Key Files for AI Agents

| File | Why it matters |
|---|---|
| `.junie/guidelines.md` | Full development guidelines (security, DRY, testing) |
| `.github/copilot-instructions.md` | Copilot-specific instructions with code examples |
| `docs/GATEWAY_PATTERN.md` | **Gateway pattern architecture guide** (NEW) |
| `application/lib/App/Contracts/GatewayClientInterface.php` | **Gateway client contract** (NEW) |
| `application/lib/App/Gateways/ApiClient.php` | **Base gateway client class** (NEW) |
| `application/lib/App/Gateways/LetsPeppol/LetsPeppolGatewayClient.php` | **Example gateway implementation** (NEW) |
| `application/libraries/gateways/PaypalLib.php` | **Reference implementation** for gateway pattern |
| `application/lib/App/Contracts/IntegrationProviderInterface.php` | Provider contract |
| `application/lib/App/Providers/ExceptionHandlingDecorator.php` | Auto exception safety for all providers |
| `application/lib/App/Services/Integrations/IntegrationProviderFactory.php` | How providers are resolved (applies decorator automatically) |
| `application/core/MY_Loader.php` | PSR-4 namespaced class loading for CI super-object |
| `application/core/MY_Router.php` | PSR-4 controller naming; `$moduleAliases` map (no routes.php needed) |
| `application/config/routes.php` | **Do not add URL aliases here** — use `MY_Router::$moduleAliases` instead |
| `.github/prompt.md` | Copy-paste AI prompt for full PSR-4 namespace migration of all modules |
| `tests/Fakes/FakeLetsPeppolHttpClient.php` | **Example fake for testing** (prefer fakes over mocks) |
| `tests/Fixtures/LetsPeppol/*.json` | **API response fixtures** for deterministic testing |
| `tests/phpunit-parallel-bootstrap.php` | Test bootstrap & CI stubs |
| `phpunit.xml.dist` | PHPUnit configuration |
| `composer.json` | Dependencies & autoloading |

---

## Migration Strategy (CI → Laravel)

- New code goes under `application/lib/App/` with PSR-4 namespace `App\`.
- Legacy CI code stays in `application/modules/` and `application/core/`.
- Compatibility shims in `application/third_party/MX/Namespaced/` bridge the two worlds.
- Controllers gradually delegate to App\ services; controllers themselves stay CI for now.
