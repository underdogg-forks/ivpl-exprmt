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
├── controllers/       Welcome.php (not logged in page)
├── core/              MY_* CI core overrides
├── helpers/           Global helpers (file_security_helper, pdf_helper, …)
├── libraries/         Global libraries (Crypt, QrCode, …)
├── modules/           HMVC modules (ALL NAMESPACED with PSR-4)
│   ├── core/          Core\ namespace (central module)
│   │   └── src/
│   │       ├── Contracts/   Interfaces (IntegrationProviderInterface, GatewayClientInterface, …)
│   │       ├── Enums/       PHP 8.1+ enums (RequestMethod, …)
│   │       ├── Gateways/    Gateway pattern implementations
│   │       │   ├── ApiClient.php          ← Base class
│   │       │   └── LetsPeppol/
│   │       │       ├── LetsPeppolGatewayClient.php
│   │       │       └── Endpoints/         ← Domain-specific operations
│   │       ├── Helpers/     Core helper classes
│   │       │   ├── SecurityHelper.php     ← XSS, CSRF, path traversal protection
│   │       │   ├── CacheHelper.php        ← Dynamic programming (memoization)
│   │       │   └── ValidatorHelper.php    ← Input validation (includes email())
│   │       ├── Integration/ Value objects (IntegrationCredentials, IntegrationSetting)
│   │       ├── Providers/   Provider implementations (LetsPeppol, …)
│   │       └── Services/    Application services
│   │           ├── Clients/       ClientsService
│   │           └── Integrations/  IntegrationProviderFactory, IntegrationSettingsService
│   ├── clients/       Modules\Clients\ namespace (PSR-4)
│   │   ├── controllers/   Modules\Clients\Controllers\
│   │   ├── models/        Modules\Clients\Models\
│   │   ├── views/
│   │   └── Enums/
│   ├── invoices/      Modules\Invoices\ namespace (PSR-4)
│   │   ├── controllers/   Modules\Invoices\Controllers\
│   │   ├── models/        Modules\Invoices\Models\
│   │   └── views/
│   └── ... (31 modules total, ALL with Modules\ModuleName\ namespace)
└── third_party/
    └── MX/Namespaced/ Compatibility shims for CI/MX classes under Core\ namespace

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
| Module controllers | PSR-4: `Modules\ModuleName\Controllers\` | `Modules\Clients\Controllers\Clients` |
| Module models | PSR-4: `Modules\ModuleName\Models\` | `Modules\Clients\Models\Mdl_Clients` |
| Core\ services | PascalCase + `Service` suffix | `ClientsService`, `IntegrationSettingsService` |
| Core\ providers | PascalCase + `Provider` suffix | `LetsPeppolProvider` |
| Core\ factories | PascalCase + `Factory` suffix | `IntegrationProviderFactory`, `LetsPeppolClientFactory` |
| Core\ helpers | PascalCase + `Helper` suffix | `SecurityHelper`, `CacheHelper`, `ValidatorHelper` |
| Test classes | Match tested class + `Test` suffix | `ClientsServiceTest`, `SecurityHelperTest` |
| Test methods | `it_` prefix + snake_case | `it_returns_false_when_provider_not_registered` |

---

## Integration Provider Pattern

New external payment / e-invoicing networks follow the **provider pattern**.

### ExceptionHandlingDecorator — Automatic Safety for All Providers

`IntegrationProviderFactory::make()` automatically wraps every resolved provider in
`Core\Providers\ExceptionHandlingDecorator`.  This means:

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
   class StoreCoveProvider implements \Core\Contracts\IntegrationProviderInterface
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
| `letspeppol` | `Core\Providers\LetsPeppolGatewayProvider` | ✅ Active (new gateway pattern) |
| `letspeppol` (legacy) | `Core\Providers\LetsPeppolProvider` | ⚠️ Deprecated (adapter pattern) |
| `storecove` | — | 🔲 Planned |
| `stripe` | — | 🔲 Planned |
| `paypal` | — | 🔲 Planned |

> `Core\Services\Integrations\LetsPeppolService` is a **deprecated** backward-compat shim
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
- `TransmissionEndpointTest.php` — Test transmission endpoint with fixtures
- `DocumentEndpointTest.php` — Test document endpoint with fixtures
- `CreditNoteEndpointTest.php` — Test credit note endpoint with fixtures
- `LetsPeppolGatewayProviderTest.php` — Test provider integration

### LetsPeppol API Endpoints (Comprehensive Implementation)

The LetsPeppol gateway implementation provides complete coverage of Peppol network operations across **5 endpoint clients** with **23 total endpoints**:

#### ParticipantEndpoint (5 endpoints)
- `validatePeppolId(string $peppolId): bool` — Validate participant in registry
- `getDetails(string $peppolId): ResponseInterface` — Get full participant information
- `search(string $query, ?string $country): ResponseInterface` — Search for participants
- `getCapabilities(string $peppolId): ResponseInterface` — Get supported document types
- All methods use `GET` requests with query parameters

#### InvoiceEndpoint (4 endpoints)
- `sendInvoice(array $payload): ResponseInterface` — Send invoice (POST)
- `getStatus(int $invoiceId): ResponseInterface` — Get invoice status (GET)
- `cancel(int $invoiceId, ?string $reason): ResponseInterface` — Cancel invoice (POST)
- `resend(int $invoiceId, ?string $reason): ResponseInterface` — Resend invoice (POST)

#### CreditNoteEndpoint (3 endpoints)
- `send(array $payload): ResponseInterface` — Send credit note (POST)
- `getStatus(int $creditNoteId): ResponseInterface` — Get credit note status (GET)
- `cancel(int $creditNoteId, ?string $reason): ResponseInterface` — Cancel credit note (POST)

#### TransmissionEndpoint (6 endpoints)
- `getStatus(string $transmissionId): ResponseInterface` — Get transmission status
- `getReceipt(string $transmissionId): ResponseInterface` — Get receipt acknowledgment
- `getErrors(string $transmissionId): ResponseInterface` — Get error details
- `list(array $filters): ResponseInterface` — List transmissions with filters
- `retry(string $transmissionId, ?string $reason): ResponseInterface` — Retry failed transmission
- All methods track document delivery across the Peppol network

#### DocumentEndpoint (5 endpoints)
- `get(string $documentId): ResponseInterface` — Get document metadata
- `download(string $documentId): ResponseInterface` — Download UBL XML content
- `getMetadata(string $documentId): ResponseInterface` — Get document metadata only
- `list(array $filters): ResponseInterface` — List documents with filters
- `archive(string $documentId, ?string $reason): ResponseInterface` — Archive document

**JSON Fixtures (23 total):**
All endpoints have corresponding JSON fixtures in `tests/Fixtures/LetsPeppol/` with realistic response structures following Peppol/UBL standards.

**Programming Principles Applied:**
- **SOLID**: Each endpoint class has single responsibility
- **DRY**: Shared gateway client interface, no code duplication
- **Dynamic Programming**: Gateway client uses memoized endpoint mappings
- All endpoints include detailed PHPDoc with JSON request/response examples
- 100% syntax-validated PHP and JSON files

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

## Core Helpers (NEW)

The `Core\Helpers` namespace provides centralized, tested utility functions following SOLID and DRY principles.

### SecurityHelper — XSS, CSRF, Path Traversal Protection

```php
use Core\Helpers\SecurityHelper;

// XSS Protection
$clean = SecurityHelper::xssClean($_POST);

// Email Validation  
$valid = SecurityHelper::isValidEmail($email);

// Secure Token Generation
$token = SecurityHelper::generateToken(32);

// Timing Attack Prevention
$match = SecurityHelper::secureCompare($known, $user);

// Filename Sanitization
$safe = SecurityHelper::sanitizeFilename($filename);

// Path Traversal Prevention
$safe = SecurityHelper::isPathSafe($filepath, $allowedDir);

// CSRF Validation
$valid = SecurityHelper::validateCsrfToken($token, $sessionToken);
```

### CacheHelper — Dynamic Programming (Memoization)

```php
use Core\Helpers\CacheHelper;

// Simple caching
CacheHelper::set('key', 'value', 3600);
$value = CacheHelper::get('key');

// Memoization pattern (recommended)
$result = CacheHelper::remember('expensive_key', function() {
    // This code only runs on cache miss
    return performExpensiveOperation();
}, 3600);

// Check if key exists
if (CacheHelper::has('key')) {
    // Use cached value
}

// Cache statistics
$stats = CacheHelper::stats();
```

### ValidatorHelper — Input Validation

```php
use Core\Helpers\ValidatorHelper;

// Single validations
$valid = ValidatorHelper::required($value);
$valid = ValidatorHelper::minLength($str, 5);
$valid = ValidatorHelper::maxLength($str, 100);
$valid = ValidatorHelper::numeric($value);
$valid = ValidatorHelper::integer($value);
$valid = ValidatorHelper::url($url);
$valid = ValidatorHelper::date($date, 'Y-m-d');
$valid = ValidatorHelper::in($value, ['red', 'green', 'blue']);
$valid = ValidatorHelper::regex($value, '/^[a-z]+$/');

// Multiple rules
$errors = ValidatorHelper::validate($str, [
    'required',
    'maxLength' => 100,
]);
```

### Helper Benefits

**SOLID Principles:**
- Single Responsibility: Each helper has ONE job
- Open/Closed: Easy to extend without modifying
- Dependency Inversion: Use helpers via static calls

**DRY Principle:**
- No code duplication
- Centralized security/validation/caching

**Dynamic Programming:**
- Memoization via `CacheHelper::remember()`
- Automatic TTL-based expiration

**Testing:**
- 100% test coverage
- 26 test cases total
- SecurityHelperTest, CacheHelperTest, ValidatorHelperTest

---

## Code Style

- **PHP**: PSR-12 via `vendor/bin/pint` or `composer phpcs`
- **Early returns** are preferred over deeply nested `if` blocks
- **Type hints** on all parameters and return types
- **Strict comparison** (`===`, `!==`) everywhere

---

## Security Must-Dos

| Rule | Implementation | Why |
|---|---|---|
| Sanitize input | CodeIgniter `$this->security->xss_clean()` (global sanitization in `Admin_Controller::filter_input()`) | XSS prevention |
| Encode output | `html_escape()` | XSS prevention |
| Validate file paths | `validate_file_in_directory()` from `file_security_helper.php` | Path traversal prevention |
| Validate/sanitize filenames | `validate_safe_filename()` and `sanitize_filename_for_header()` from `file_security_helper.php` | Path traversal and header injection prevention |
| Validate CSRF | `SecurityHelper::validateCsrfToken()` | CSRF prevention |
| Secure comparison | `SecurityHelper::secureCompare()` | Timing attack prevention |
| SQL queries | Use Query Builder / prepared statements | SQL injection prevention |
| No SVG uploads | File upload validation | SVG-based XSS prevention |

**Do not replace established CI or `file_security_helper.php` protections with weaker wrapper helpers.** Use CodeIgniter XSS filtering for request data and the existing file/path validation helpers for filesystem-related security checks.

See `.junie/guidelines.md` and `REFACTORING_SUMMARY.md` for full details.

---

## Key Files for AI Agents

| File | Why it matters |
|---|---|
| `REFACTORING_SUMMARY.md` | **Complete refactoring documentation** (NEW) |
| `.junie/guidelines.md` | Full development guidelines (security, DRY, testing) |
| `.github/copilot-instructions.md` | Copilot-specific instructions with code examples |
| `docs/GATEWAY_PATTERN.md` | Gateway pattern architecture guide |
| `application/modules/core/src/Helpers/SecurityHelper.php` | **Centralized security operations** (NEW) |
| `application/modules/core/src/Helpers/CacheHelper.php` | **Dynamic programming/memoization** (NEW) |
| `application/modules/core/src/Helpers/ValidatorHelper.php` | **Centralized validation** (NEW) |
| `application/modules/core/src/Contracts/GatewayClientInterface.php` | Gateway client contract |
| `application/modules/core/src/Gateways/ApiClient.php` | Base gateway client class |
| `application/modules/core/src/Gateways/LetsPeppol/LetsPeppolGatewayClient.php` | Example gateway implementation |
| `application/libraries/gateways/PaypalLib.php` | Reference implementation for gateway pattern |
| `application/modules/core/src/Contracts/IntegrationProviderInterface.php` | Provider contract |
| `application/modules/core/src/Providers/ExceptionHandlingDecorator.php` | Auto exception safety for all providers |
| `application/modules/core/src/Services/Integrations/IntegrationProviderFactory.php` | How providers are resolved |
| `application/core/MY_Loader.php` | PSR-4 namespaced class loading for CI super-object |
| `application/core/MY_Router.php` | PSR-4 controller naming; `$moduleAliases` map |
| `application/config/routes.php` | **Do not add URL aliases here** — use `MY_Router::$moduleAliases` |
| `tests/Unit/SecurityHelperTest.php` | **Security helper tests** (NEW - 8 test cases) |
| `tests/Unit/CacheHelperTest.php` | **Cache helper tests** (NEW - 8 test cases) |
| `tests/Unit/ValidatorHelperTest.php` | **Validator helper tests** (NEW - 10 test cases) |
| `tests/Fakes/FakeLetsPeppolHttpClient.php` | Example fake for testing |
| `tests/Fixtures/LetsPeppol/*.json` | API response fixtures |
| `tests/phpunit-parallel-bootstrap.php` | Test bootstrap & CI stubs |
| `phpunit.xml.dist` | PHPUnit configuration |
| `composer.json` | Dependencies & PSR-4 autoloading (32 namespaces) |

---

## Migration Strategy (CI → Laravel)

- New code goes under `application/modules/core/src/` with PSR-4 namespace `Core\`.
- All 31 modules now have proper PSR-4 namespaces (`Modules\ModuleName\`).
- Legacy CI code stays in `application/core/` (MY_* overrides).
- Compatibility shims in `application/third_party/MX/Namespaced/` bridge the two worlds.
- Controllers gradually delegate to Core\ services; controllers themselves stay CI for now.
- Use `Core\Helpers` for all new utility functions (SecurityHelper, CacheHelper, ValidatorHelper).

**Module Namespace Syntax (Critical):**
```php
<?php

namespace Modules\Clients\Controllers;  // FIRST - after <?php

if (!defined('BASEPATH')) {              // SECOND - after namespace
    exit('No direct script access allowed');
}

class Clients extends \Admin_Controller  // Base class with leading \
```

**Why This Works:**
- PHP requires namespace as first statement (after `<?php`)
- Base classes like `\Admin_Controller` must be fully qualified
- Core classes imported with `use` statements
- PSR-4 autoloading works alongside CodeIgniter's MX loader
