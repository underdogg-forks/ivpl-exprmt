# Post-Merge Refactoring: PSR-4 Controller/Model Naming

This prompt is for AI agents to execute **after** the namespace consolidation PR is merged.

## Context

The InvoicePlane codebase uses CodeIgniter 3 with MX HMVC modules. All modules now have PSR-4 namespaces (`Modules\ModuleName\Controllers`, `Modules\ModuleName\Models`), but the class names don't follow PSR-4 conventions yet.

## Current State

### Controllers
- File: `application/modules/clients/controllers/Clients.php`
- Namespace: `Modules\Clients\Controllers`
- Class: `Clients`
- **Issue**: Should be `ClientsController` to follow PSR-4 suffix convention

### Models
- File: `application/modules/clients/models/Mdl_clients.php`
- Namespace: `Modules\Clients\Models`
- Class: `Mdl_Clients`
- **Issue**: Should be `Client` (singular, no prefix) to follow PSR-4 conventions

## Required Changes

### 1. Rename All Controllers (51 files)

**Pattern:**
```php
// BEFORE
namespace Modules\Clients\Controllers;
class Clients extends \Admin_Controller { }

// AFTER
namespace Modules\Clients\Controllers;
class ClientsController extends \Admin_Controller { }
```

**Affected Modules:**
- clients, custom_fields, custom_values, dashboard, email_templates, families
- filter, guest, import, integrations, invoice_groups, invoices
- layout, mailer, payment_methods, payments, products, projects
- quotes, reports, sessions, settings, setup, tasks, tax_rates
- units, upload, user_clients, users, welcome

**Files to Update:**
- Controller files: Add `Controller` suffix to class name
- All `$this->load->model()` calls
- All `Modules::run()` calls
- All URL routing references
- All test files

### 2. Rename All Models (43 files)

**Pattern:**
```php
// BEFORE
namespace Modules\Clients\Models;
class Mdl_Clients extends \Response_Model { }

// AFTER
namespace Modules\Clients\Models;
class Client extends \Response_Model { }
```

**Rules:**
- Remove `Mdl_` prefix
- Use singular form: `Clients` → `Client`, `Invoices` → `Invoice`
- Keep compound names: `Mdl_client_notes` → `ClientNote`

**Files to Update:**
- Model files: Remove `Mdl_` prefix, use singular
- All `$this->load->model()` calls throughout codebase
- All direct model instantiations
- All test files

### 3. Update Model Loader Calls

**Pattern:**
```php
// BEFORE
$this->load->model('clients/mdl_clients');
$this->mdl_clients->get_by_id($id);

// AFTER
$this->load->model('clients/client');
$this->client->get_by_id($id);
```

### 4. Update Modules::run() Calls

**Pattern:**
```php
// BEFORE
Modules::run('clients/index');

// AFTER - No change needed (routing based on file paths, not class names)
Modules::run('clients/index');
```

### 5. Directory Structure (Case-Sensitive Filesystems)

**Current Issue:**
- Composer PSR-4 expects `application/modules/Clients/Controllers/ClientsController.php`
- Actual structure: `application/modules/clients/controllers/Clients.php`

**Philosophy:** PSR-4 compliance is the leading principle. This section provides two options, with **Option B (full PSR-4)** as the recommended modern approach.

**Options:**

#### Option A: Keep lowercase directories (Legacy CI compatibility)
```bash
# No directory renaming needed
# MX HMVC loader works with lowercase directories
# Composer autoloading uses custom logic in MY_Loader.php
```

#### Option B: Full PSR-4 Compliance (Recommended for modern architecture)

**Philosophy:** PSR-4 is the leading principle. CodeIgniter conventions are adapted to fit PSR-4, not vice versa.

##### B.0: Architecture Philosophy - Why MY_* and Not MX_*?

**Critical Understanding: The MY_* Layer**

CodeIgniter 3 has a specific extension mechanism where custom core classes must be prefixed with `MY_` (configurable via `$config['subclass_prefix']`). This is **not optional** - it's how CI3 discovers and loads core overrides.

**Why Refactor MY_Router Instead of MX_Router?**

```
CodeIgniter Core Loading Hierarchy:
1. CI_Router (CodeIgniter core)
   ↓
2. MX_Router (Modular Extensions HMVC - extends CI_Router)
   ↓
3. MY_Router (YOUR custom extensions - extends MX_Router)
```

**Key Points:**

1. **MX_Router is library code** - It's from the Modular Extensions HMVC library (`application/third_party/MX/`). We don't modify third-party libraries directly.

2. **MY_Router is YOUR extension point** - This is where you customize routing behavior for your application. CI3 automatically loads `application/core/MY_Router.php` and uses it instead of the base router.

3. **Same pattern for all core classes:**
   - `MY_Loader` extends `MX_Loader` (for PSR-4 model loading)
   - `MY_Router` extends `MX_Router` (for PSR-4 module resolution)
   - This is the CI3 way - the `MY_*` prefix is CI3's extension mechanism

**The MY_* Layer Purpose:**

> **Yes, the MY_* layer exists solely to keep CI3 happy!**

The `MY_*` classes act as an **adapter layer** between CI3's conventions and PSR-4 standards:

- CI3 expects: `application/core/MY_Router.php`
- PSR-4 expects: `Modules\Clients\Controllers\ClientsController`
- MY_Router bridges these two worlds transparently

**Without MY_* classes:** CI3 won't load your extensions.  
**With MY_* classes:** Everything works, and your application code stays PSR-4 compliant.

##### B.0.1: Refactoring Base Classes - The Real PSR-4 Win

**Current State (CI3 Legacy Naming):**

```php
// application/core/Admin_Controller.php
class Admin_Controller extends CI_Controller { }

// application/core/Response_Model.php  
class Response_Model extends CI_Model { }

// Usage in modules
class ClientsController extends \Admin_Controller { }
class Client extends \Response_Model { }
```

**Target State (True PSR-4):**

```php
// application/core/AdminController.php
class AdminController extends CI_Controller { }

// application/core/ResponseModel.php
class ResponseModel extends CI_Model { }

// Usage in modules
class ClientsController extends \AdminController { }
class Client extends \ResponseModel { }
```

**Why This Matters:**

✅ **PSR-4 Compliant:** Class names match file names without underscores  
✅ **Modern PHP Standards:** PascalCase for all classes  
✅ **Consistent Naming:** `ClientsController`, `AdminController`, `ResponseModel` all follow the same pattern  
✅ **Better IDE Support:** Full autocomplete and navigation

**The Refactoring Steps:**

1. **Rename Base Class Files:**
   ```bash
   mv application/core/Admin_Controller.php application/core/AdminController.php
   mv application/core/Response_Model.php application/core/ResponseModel.php
   ```

2. **Update Class Definitions:**
   ```php
   // AdminController.php
   class AdminController extends CI_Controller { }
   
   // ResponseModel.php
   class ResponseModel extends CI_Model { }
   ```

3. **Update All Extends Statements:**
   ```php
   // Before
   class ClientsController extends \Admin_Controller { }
   class Client extends \Response_Model { }
   
   // After
   class ClientsController extends \AdminController { }
   class Client extends \ResponseModel { }
   ```

4. **Add Use Statements (Optional but Recommended):**
   ```php
   namespace Modules\Clients\Controllers;
   
   use AdminController;
   use Core\Services\Clients\ClientsService;
   
   class ClientsController extends AdminController { }
   ```

**The Complete Picture:**

```
CI3 Layer (Necessary for Framework):
├── MY_Router extends MX_Router     ← Adapter: CI3 → PSR-4 routing
├── MY_Loader extends MX_Loader     ← Adapter: CI3 → PSR-4 autoloading
└── CI3 autoloads these via MY_* prefix

Application Layer (Pure PSR-4):
├── AdminController                 ← Base controller (PSR-4 naming)
├── ResponseModel                   ← Base model (PSR-4 naming)
├── Modules\Clients\Controllers\ClientsController
└── Modules\Clients\Models\Client

Result: Clean PSR-4 everywhere except the thin MY_* adapter layer
```

**Summary:**

> The `MY_*` layer is a **thin compatibility shim** for CI3. Everything else - your base classes, controllers, models, services - follows pure PSR-4 standards. This is the best of both worlds: CI3 compatibility where required, modern standards everywhere else.

##### B.1: Directory Structure Changes

Rename all module directories to match PSR-4 capitalization:

```bash
# For each of 31 modules:
application/modules/clients/controllers/ → application/modules/Clients/Controllers/
application/modules/clients/models/     → application/modules/Clients/Models/
application/modules/clients/views/      → application/modules/Clients/Views/
application/modules/clients/helpers/    → application/modules/Clients/Helpers/

# Module name also gets proper casing:
application/modules/clients/            → application/modules/Clients/
application/modules/invoices/           → application/modules/Invoices/
application/modules/custom_fields/      → application/modules/CustomFields/
# ... etc for all 31 modules
```

**Impact:**
- **31 module directories** renamed (lowercase → PascalCase)
- **124+ subdirectories** renamed (controllers, models, views, helpers → capitalized)
- All file paths change, but class namespaces remain consistent
- Full PSR-4 autoloading works natively without custom loaders

##### B.2: Refactor MX HMVC Routing

Update `application/core/MY_Router.php` to support PSR-4 module paths:

```php
// MY_Router.php - Add PSR-4 module path resolver
class MY_Router extends MX_Router
{
    /**
     * Convert URL segment to PSR-4 module path
     * URL: /clients/index → Module: Clients/Controllers/ClientsController
     */
    protected function _set_module_path(string $urlSegment): string
    {
        // Early return: if module doesn't exist, bail
        // Note: show_404() terminates execution, never returns
        if (!$this->moduleExists($urlSegment)) {
            show_404();
        }
        
        // Convert snake_case to PascalCase
        $moduleName = $this->toPascalCase($urlSegment);
        $modulePath = APPPATH . "modules/{$moduleName}/";
        
        // Early return: validate module path exists
        if (!is_dir($modulePath)) {
            show_404();
        }
        
        return $modulePath;
    }
    
    private function toPascalCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
    
    private function moduleExists(string $segment): bool
    {
        // Early return: check if PSR-4 module directory exists
        $pascalCase = $this->toPascalCase($segment);
        return is_dir(APPPATH . "modules/{$pascalCase}");
    }
}
```

**Key Principles Applied:**
- **Early return pattern:** Exit fast on invalid conditions
- **PSR-4 leading:** URL segments map to PSR-4 module names
- **Backward compatibility:** Old URLs still work (routing layer handles conversion)

##### B.3: Refactor MX Module Loader

Update `application/third_party/MX/Loader.php` to eliminate `$this->load->model()` dependencies:

```php
// MX/Loader.php - Add PSR-4 auto-resolution
class MX_Loader extends CI_Loader
{
    /**
     * Override model() to use PSR-4 autoloading
     * 
     * Instead of: $this->load->model('clients/mdl_clients');
     * Just use:   $this->client = new \Modules\Clients\Models\Client();
     */
    public function model($model, $name = '', $db_conn = false)
    {
        // Early return: if already loaded, skip
        if (isset($this->_ci_models[$name ?: $model])) {
            return $this;
        }
        
        // Parse PSR-4 class from legacy path
        [$namespace, $class] = $this->parseModelPath($model);
        
        // Early return: if class doesn't exist, fall back to parent
        if (!class_exists($namespace . '\\' . $class)) {
            return parent::model($model, $name, $db_conn);
        }
        
        // Instantiate via PSR-4 autoloading
        $className = $namespace . '\\' . $class;
        $instance = new $className();
        
        // Inject into CI super-object
        $propertyName = $name ?: $this->toPropertyName($class);
        $this->_ci_cached_vars[$propertyName] = $instance;
        
        return $this;
    }
    
    private function parseModelPath(string $path): array
    {
        // Early return: handle simple case (no module prefix)
        if (!str_contains($path, '/')) {
            return ['', $path];
        }
        
        // Parse: "clients/mdl_clients" → ["Modules\Clients\Models", "Client"]
        [$module, $file] = explode('/', $path, 2);
        
        $moduleName = $this->toPascalCase($module);
        $className = $this->toClassName($file);
        
        return ["Modules\\{$moduleName}\\Models", $className];
    }
    
    private function toClassName(string $file): string
    {
        // Remove "Mdl_" prefix, convert to PascalCase
        $file = preg_replace('/^Mdl_/i', '', $file);
        return $this->toPascalCase($file);
    }
    
    private function toPropertyName(string $class): string
    {
        // Client → client, ClientNote → clientNote
        return lcfirst($class);
    }
    
    private function toPascalCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
}
```

**Benefits:**
- **No changes to existing code:** `$this->load->model('clients/mdl_clients')` still works
- **PSR-4 auto-resolution:** Loader converts legacy paths to PSR-4 classes
- **Gradual migration:** Can mix old and new approaches during transition
- **Early returns:** Fast validation before expensive operations

##### B.4: Update Modules::run() Calls

Refactor `Modules::run()` to support PSR-4 module routing:

```php
// application/third_party/MX/Modules.php
class Modules
{
    public static function run(string $module, ...$params): mixed
    {
        // Parse: "clients/view/123" → ["Clients", "view", 123]
        $segments = explode('/', $module);
        
        // Early return: invalid format
        if (count($segments) < 2) {
            return null;
        }
        
        $moduleName = self::toPascalCase(array_shift($segments));
        $method = array_shift($segments);
        
        // Build controller class name
        $controllerClass = "Modules\\{$moduleName}\\Controllers\\{$moduleName}Controller";
        
        // Early return: controller doesn't exist
        if (!class_exists($controllerClass)) {
            log_message('error', "Module controller not found: {$controllerClass}");
            return null;
        }
        
        $controller = new $controllerClass();
        
        // Early return: method doesn't exist
        if (!method_exists($controller, $method)) {
            log_message('error', "Method not found: {$controllerClass}::{$method}");
            return null;
        }
        
        // Merge additional params
        $args = array_merge($segments, $params);
        
        return call_user_func_array([$controller, $method], $args);
    }
    
    private static function toPascalCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
}
```

**Impact on existing code:**
- **Zero changes required:** All existing `Modules::run('clients/index')` calls work unchanged
- **PSR-4 resolution:** Routing layer converts URL segments to PSR-4 classes
- **Early return validation:** Invalid calls fail fast with logging

##### B.5: Migration Strategy

**Phase 1: Prepare Infrastructure (Week 1)**
1. Update `MY_Router.php` with PSR-4 module path resolver
2. Update `MX/Loader.php` with PSR-4 model auto-resolution
3. Update `MX/Modules.php` with PSR-4 controller resolution
4. Write comprehensive tests for new routing layer
5. Test with one pilot module (Clients)

**Phase 2: Rename Directories (Week 2)**
```bash
# Automated script to rename all directories
for module in application/modules/*; do
    base=$(basename "$module")
    pascal=$(echo "$base" | sed -r 's/(^|_)([a-z])/\U\2/g')
    
    # Early return: skip if already PascalCase
    [[ "$base" == "$pascal" ]] && continue
    
    # Rename module directory
    mv "application/modules/$base" "application/modules/$pascal"
    
    # Rename subdirectories
    cd "application/modules/$pascal"
    [[ -d "controllers" ]] && mv controllers Controllers
    [[ -d "models" ]] && mv models Models
    [[ -d "views" ]] && mv views Views
    [[ -d "helpers" ]] && mv helpers Helpers
done

# Update composer.json PSR-4 mappings
composer dump-autoload
```

**Phase 3: Rename Classes (Week 2-3)**
1. Controllers: Add `Controller` suffix (51 files)
2. Models: Remove `Mdl_` prefix, use singular (43 files)
3. Update all class references in files
4. No need to update `$this->load->model()` calls (handled by MX_Loader)
5. No need to update `Modules::run()` calls (handled by routing layer)

**Phase 4: Test & Validate (Week 3)**
1. Run full test suite
2. Test all module URLs
3. Verify PSR-4 autoloading works
4. Check for broken references
5. Staging environment testing

##### B.6: Benefits of Full PSR-4 Approach

**Developer Experience:**
- ✅ **Standard PSR-4:** No custom conventions to learn
- ✅ **IDE support:** Full autocomplete and navigation
- ✅ **Less magic:** Clear class resolution without custom loaders
- ✅ **Modern tooling:** Works with static analyzers (PHPStan, Psalm)

**Codebase Quality:**
- ✅ **No legacy baggage:** Clean PSR-4 throughout
- ✅ **Easy refactoring:** Classes can be moved/renamed safely
- ✅ **Better testing:** Standard autoloading in tests
- ✅ **Future-proof:** Ready for Laravel migration

**Migration Effort:**
- ✅ **One-time cost:** Upfront work pays long-term dividends
- ✅ **Automated:** Directory renaming is scriptable
- ✅ **Backward compatible:** Old code keeps working during transition
- ✅ **Gradual:** Can migrate module by module if needed

##### B.7: PHPUnit Test Compatibility Strategy

**Critical Requirement:** All PHPUnit tests must work flawlessly after PSR-4 refactoring.

###### Test Bootstrap Configuration

Update `tests/phpunit-parallel-bootstrap.php` to support PSR-4 autoloading:

```php
<?php
/**
 * PHPUnit bootstrap for PSR-4 refactored codebase
 * Loads Composer autoloader and defines CI stubs for unit tests
 */

// Early return: Check Composer autoloader exists
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    die('Composer dependencies not installed. Run: composer install' . PHP_EOL);
}

// Load Composer PSR-4 autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Define CI constants for tests
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/../system/');
}
if (!defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/../application/');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'testing');
}

// Stub CI base classes for unit tests (avoid full CI bootstrap)
if (!class_exists('CI_Model')) {
    class CI_Model {}
}
if (!class_exists('CI_Controller')) {
    class CI_Controller {}
}
if (!class_exists('Admin_Controller')) {
    class Admin_Controller extends CI_Controller {}
}
if (!class_exists('Response_Model')) {
    class Response_Model extends CI_Model {}
}
```

###### PHPUnit Configuration

Update `phpunit.xml.dist` to match PSR-4 structure:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/phpunit-parallel-bootstrap.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit Tests">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature Tests">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory suffix=".php">application/modules/Core/src</directory>
            <directory suffix=".php">application/modules/*/Controllers</directory>
            <directory suffix=".php">application/modules/*/Models</directory>
        </include>
    </source>
</phpunit>
```

###### Test Namespace Strategy

**Controllers Tests:**
```php
// tests/Unit/Controllers/Clients/ClientsControllerTest.php
<?php

namespace Tests\Unit\Controllers\Clients;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Modules\Clients\Controllers\ClientsController;

class ClientsControllerTest extends TestCase
{
    private ClientsController $controller;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Early return: Skip if CI dependencies not available
        if (!class_exists('\Admin_Controller')) {
            $this->markTestSkipped('CI base classes not available');
        }
        
        $this->controller = new ClientsController();
    }
    
    #[Test]
    public function it_instantiates_without_errors(): void
    {
        // Arrange - done in setUp()
        
        // Act & Assert
        $this->assertInstanceOf(ClientsController::class, $this->controller);
        $this->assertInstanceOf(\Admin_Controller::class, $this->controller);
    }
}
```

**Models Tests:**
```php
// tests/Unit/Models/Clients/ClientTest.php
<?php

namespace Tests\Unit\Models\Clients;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Modules\Clients\Models\Client;

class ClientTest extends TestCase
{
    private Client $model;
    
    protected function setUp(): void
    {
        parent::setUp();
        // Early return: Skip if CI dependencies not available
        if (!class_exists('\Response_Model')) {
            $this->markTestSkipped('CI base classes not available');
        }
        
        $this->model = new Client();
    }
    
    #[Test]
    public function it_extends_response_model(): void
    {
        // Arrange - done in setUp()
        
        // Act & Assert
        $this->assertInstanceOf(Client::class, $this->model);
        $this->assertInstanceOf(\Response_Model::class, $this->model);
    }
}
```

###### Test Execution Validation

**Pre-Refactoring Test Run:**
```bash
# Establish baseline - all tests must pass before refactoring
vendor/bin/phpunit --testdox

# Expected output:
# ✓ All existing tests pass
# ✓ No PSR-4 autoloading errors
# ✓ No namespace resolution errors
```

**Post-Refactoring Test Run:**
```bash
# After directory renaming - verify tests still pass
composer dump-autoload -o
vendor/bin/phpunit --testdox

# Expected output:
# ✓ All tests still pass
# ✓ PSR-4 autoloading works for Clients/ Controllers/ Models/
# ✓ No class-not-found errors
```

**Continuous Validation:**
```bash
# Run after each phase of refactoring
# Phase 1: Infrastructure changes
vendor/bin/phpunit tests/Unit/Infrastructure/

# Phase 2: Directory renaming (per module)
vendor/bin/phpunit tests/Unit/Controllers/Clients/
vendor/bin/phpunit tests/Unit/Models/Clients/

# Phase 3: Class renaming
vendor/bin/phpunit --filter ClientsController
vendor/bin/phpunit --filter Client

# Phase 4: Full suite
vendor/bin/phpunit
```

##### B.8: Dynamic Programming Principles

**Memoization Pattern:** Cache expensive computations to avoid redundant work.

###### Module Path Resolution Cache

```php
// application/core/MY_Router.php
class MY_Router extends MX_Router
{
    private static array $modulePathCache = [];
    
    protected function _set_module_path(string $urlSegment): string
    {
        // Early return: Check cache first (memoization)
        if (isset(self::$modulePathCache[$urlSegment])) {
            return self::$modulePathCache[$urlSegment];
        }
        
        // Early return: Module doesn't exist
        if (!$this->moduleExists($urlSegment)) {
            show_404();
        }
        
        $moduleName = $this->toPascalCase($urlSegment);
        $modulePath = APPPATH . "modules/{$moduleName}/";
        
        // Early return: Path doesn't exist
        if (!is_dir($modulePath)) {
            show_404();
        }
        
        // Cache result for future lookups (dynamic programming)
        self::$modulePathCache[$urlSegment] = $modulePath;
        
        return $modulePath;
    }
}
```

###### Class Name Conversion Cache

```php
// application/third_party/MX/Loader.php
class MX_Loader extends CI_Loader
{
    private static array $classNameCache = [];
    
    private function toClassName(string $file): string
    {
        // Early return: Check cache (memoization)
        if (isset(self::$classNameCache[$file])) {
            return self::$classNameCache[$file];
        }
        
        // Remove "Mdl_" prefix, convert to PascalCase
        $file = preg_replace('/^Mdl_/i', '', $file);
        $className = $this->toPascalCase($file);
        
        // Cache result (dynamic programming)
        self::$classNameCache[$file] = $className;
        
        return $className;
    }
}
```

###### Benefits of Memoization:
- ✅ **Performance:** Avoid repeated string conversions and filesystem checks
- ✅ **Scalability:** O(1) lookups after first access
- ✅ **Predictability:** Consistent results for same inputs
- ✅ **Memory-efficient:** Cache only computed results, not intermediate steps

##### B.9: DRY Programming Principles

**Don't Repeat Yourself:** Extract common patterns into reusable functions.

###### Centralized String Conversion

```php
// application/core/MY_String_Helper.php
<?php

if (!function_exists('to_pascal_case')) {
    /**
     * Convert snake_case or kebab-case to PascalCase
     * 
     * @param string $string Input string
     * @return string PascalCase string
     */
    function to_pascal_case(string $string): string
    {
        // Early return: Already PascalCase
        if (ctype_upper($string[0]) && !str_contains($string, '_')) {
            return $string;
        }
        
        return str_replace(['_', '-'], '', ucwords($string, '_-'));
    }
}

if (!function_exists('to_camel_case')) {
    /**
     * Convert PascalCase to camelCase
     * 
     * @param string $string Input string
     * @return string camelCase string
     */
    function to_camel_case(string $string): string
    {
        // Early return: Empty string
        if (empty($string)) {
            return $string;
        }
        
        return lcfirst($string);
    }
}

if (!function_exists('to_singular')) {
    /**
     * Convert plural to singular form (simple English rules)
     * 
     * @param string $word Plural word
     * @return string Singular word
     */
    function to_singular(string $word): string
    {
        // Early return: Already singular (no 's' at end)
        if (!str_ends_with($word, 's')) {
            return $word;
        }
        
        // Special cases (early returns)
        $specialCases = [
            'News' => 'News',
            'Series' => 'Series',
            'Species' => 'Species',
        ];
        
        if (isset($specialCases[$word])) {
            return $specialCases[$word];
        }
        
        // -ies → -y (e.g., Categories → Category)
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        
        // -ses → -s (e.g., Classes → Class)
        if (str_ends_with($word, 'ses')) {
            return substr($word, 0, -2);
        }
        
        // Default: Remove trailing 's'
        return substr($word, 0, -1);
    }
}
```

###### Use Helper Functions Everywhere

```php
// MY_Router.php - DRY
private function toPascalCase(string $string): string
{
    return to_pascal_case($string); // Reuse helper
}

// MX/Loader.php - DRY
private function toPascalCase(string $string): string
{
    return to_pascal_case($string); // Reuse helper
}

private function toPropertyName(string $class): string
{
    return to_camel_case($class); // Reuse helper
}

// MX/Modules.php - DRY
private static function toPascalCase(string $string): string
{
    return to_pascal_case($string); // Reuse helper
}
```

###### Benefits of DRY:
- ✅ **Single source of truth:** One place to fix bugs
- ✅ **Consistency:** Same behavior everywhere
- ✅ **Testability:** Test once, use everywhere
- ✅ **Maintainability:** Change once, update everywhere

##### B.10: SOLID Programming Principles

**Single Responsibility Principle (SRP):** Each class has one reason to change.

###### Router: Path Resolution Only

```php
// application/core/MY_Router.php
class MY_Router extends MX_Router
{
    // SRP: Only responsible for routing URL → Module Path
    protected function _set_module_path(string $urlSegment): string
    {
        $resolver = new ModulePathResolver();
        return $resolver->resolve($urlSegment);
    }
}

// application/core/ModulePathResolver.php
class ModulePathResolver
{
    private array $cache = [];
    
    // SRP: Only responsible for resolving module paths
    public function resolve(string $urlSegment): string
    {
        // Early return: Cache hit
        if (isset($this->cache[$urlSegment])) {
            return $this->cache[$urlSegment];
        }
        
        // Early return: Module doesn't exist
        if (!$this->moduleExists($urlSegment)) {
            show_404();
        }
        
        $moduleName = to_pascal_case($urlSegment);
        $modulePath = APPPATH . "modules/{$moduleName}/";
        
        // Early return: Path doesn't exist
        if (!is_dir($modulePath)) {
            show_404();
        }
        
        $this->cache[$urlSegment] = $modulePath;
        return $modulePath;
    }
    
    private function moduleExists(string $segment): bool
    {
        $pascalCase = to_pascal_case($segment);
        return is_dir(APPPATH . "modules/{$pascalCase}");
    }
}
```

**Open/Closed Principle (OCP):** Open for extension, closed for modification.

###### Strategy Pattern for Model Loading

```php
// application/third_party/MX/Loader.php
class MX_Loader extends CI_Loader
{
    private ModelLoadingStrategy $strategy;
    
    public function __construct()
    {
        // OCP: Can swap strategy without modifying loader
        $this->strategy = new PSR4ModelLoadingStrategy();
    }
    
    public function model($model, $name = '', $db_conn = false)
    {
        // Delegate to strategy (OCP)
        return $this->strategy->load($model, $name, $db_conn);
    }
}

// application/third_party/MX/Strategies/PSR4ModelLoadingStrategy.php
interface ModelLoadingStrategy
{
    public function load(string $model, string $name, $db_conn): mixed;
}

class PSR4ModelLoadingStrategy implements ModelLoadingStrategy
{
    public function load(string $model, string $name, $db_conn): mixed
    {
        // Early return: Already loaded
        if ($this->isLoaded($model, $name)) {
            return $this;
        }
        
        [$namespace, $class] = $this->parseModelPath($model);
        
        // Early return: Class doesn't exist
        if (!class_exists($namespace . '\\' . $class)) {
            return $this->fallbackToLegacy($model, $name, $db_conn);
        }
        
        return $this->instantiateModel($namespace, $class, $name);
    }
}
```

**Liskov Substitution Principle (LSP):** Subtypes must be substitutable for their base types.

###### Consistent Model Interface

```php
// All models extend same base and implement same interface
namespace Modules\Clients\Models;

class Client extends \Response_Model
{
    // LSP: Must have same method signature as base
    public function get_by_id(int $id): ?object
    {
        // Early return: Invalid ID
        if ($id <= 0) {
            return null;
        }
        
        return $this->db->where('id', $id)->get($this->table)->row();
    }
}

namespace Modules\Invoices\Models;

class Invoice extends \Response_Model
{
    // LSP: Same signature - substitutable
    public function get_by_id(int $id): ?object
    {
        // Early return: Invalid ID
        if ($id <= 0) {
            return null;
        }
        
        return $this->db->where('id', $id)->get($this->table)->row();
    }
}
```

**Interface Segregation Principle (ISP):** Clients shouldn't depend on interfaces they don't use.

###### Focused Interfaces

```php
// Small, focused interfaces (ISP)
interface Identifiable
{
    public function get_by_id(int $id): ?object;
}

interface Listable
{
    public function get_all(): array;
}

interface Searchable
{
    public function search(string $query): array;
}

// Models implement only what they need
class Client extends \Response_Model implements Identifiable, Listable, Searchable
{
    // Implements all three
}

class Setting extends \Response_Model implements Identifiable
{
    // Only implements Identifiable (ISP)
}
```

**Dependency Inversion Principle (DIP):** Depend on abstractions, not concretions.

###### Inject Dependencies

```php
// Controllers depend on abstractions (DIP)
class ClientsController extends \Admin_Controller
{
    private ClientRepositoryInterface $clientRepository;
    
    public function __construct(ClientRepositoryInterface $repo = null)
    {
        parent::__construct();
        
        // DIP: Depend on interface, not concrete class
        $this->clientRepository = $repo ?? new DatabaseClientRepository();
    }
    
    public function index(): void
    {
        // Early return: No clients
        $clients = $this->clientRepository->getAll();
        if (empty($clients)) {
            $this->load->view('clients/empty');
            return;
        }
        
        $this->load->view('clients/index', ['clients' => $clients]);
    }
}

interface ClientRepositoryInterface
{
    public function getAll(): array;
    public function getById(int $id): ?object;
}

class DatabaseClientRepository implements ClientRepositoryInterface
{
    // Concrete implementation
}

class CachedClientRepository implements ClientRepositoryInterface
{
    // Alternative implementation (DIP enables easy swap)
}
```

##### B.11: Early Return Patterns Throughout

**Every function uses early returns for guard clauses:**

```php
// Example 1: Router validation
protected function _set_module_path(string $urlSegment): string
{
    // Early return: Cache hit
    if (isset(self::$cache[$urlSegment])) {
        return self::$cache[$urlSegment];
    }
    
    // Early return: Invalid input
    if (empty($urlSegment)) {
        show_404();
    }
    
    // Early return: Module doesn't exist
    if (!$this->moduleExists($urlSegment)) {
        show_404();
    }
    
    // Happy path - only reached if all guards pass
    return $this->resolveModulePath($urlSegment);
}

// Example 2: Model loading
public function model($model, $name = '', $db_conn = false)
{
    // Early return: Already loaded
    if ($this->isLoaded($model, $name)) {
        return $this;
    }
    
    // Early return: Invalid model name
    if (empty($model)) {
        log_message('error', 'Empty model name provided');
        return $this;
    }
    
    // Happy path
    return $this->loadModel($model, $name, $db_conn);
}

// Example 3: Controller action
public function view(int $id): void
{
    // Early return: Invalid ID
    if ($id <= 0) {
        show_404();
    }
    
    // Early return: Client not found
    $client = $this->clientRepository->getById($id);
    if ($client === null) {
        show_404();
    }
    
    // Early return: No permission
    if (!$this->hasPermission('view_clients')) {
        show_error('Access denied', 403);
    }
    
    // Happy path - only executed if all guards pass
    $this->load->view('clients/view', ['client' => $client]);
}
```

**Benefits of Early Returns:**
- ✅ **Readability:** Validate preconditions first, happy path last
- ✅ **Reduced nesting:** No deeply nested if/else chains
- ✅ **Clear intent:** Each guard clause is a separate validation
- ✅ **Easier debugging:** Guard failures are isolated and logged
- ✅ **Performance:** Skip unnecessary work when preconditions fail

##### B.12: Estimated Effort (Full PSR-4)

- **Infrastructure Updates (MY_Router, MX_Loader, MX_Modules):** 12-16 hours
- **Helper Functions (DRY principles):** 4-6 hours
- **Directory Renaming (31 modules × 4 subdirs):** 4-6 hours (mostly automated)
- **Controller Renaming (51 files):** 8-12 hours
- **Model Renaming (43 files):** 10-15 hours
- **Test Updates & Validation:** 20-30 hours
- **Documentation Updates:** 4-6 hours
- **Total:** 62-91 hours (1.5-2.5 weeks for one developer)

**Note:** This is actually **less hands-on work** than Option A because:
- No need to update hundreds of `$this->load->model()` calls (MX_Loader handles it)
- No need to update hundreds of `Modules::run()` calls (routing handles it)
- Automated directory renaming script reduces manual work
- Clean PSR-4 structure reduces long-term maintenance burden
- Memoization improves runtime performance
- DRY principles reduce code duplication
- SOLID principles improve testability

**Recommendation:** Use Option B (full PSR-4) because:
- **PSR-4 is the standard:** Modern PHP expects PSR-4 compliance
- **Better developer experience:** Clear conventions, no magic
- **Future Laravel migration:** Directory structure already matches Laravel expectations
- **Less work than it seems:** Smart refactoring of MX layer eliminates most manual updates
- **One-time investment:** Pays dividends in maintainability and developer onboarding
- **Test compatibility:** PHPUnit tests work flawlessly with PSR-4
- **Performance gains:** Dynamic programming (memoization) improves speed
- **Code quality:** DRY and SOLID principles reduce bugs and improve maintainability
- **Early returns:** Clearer, more maintainable code throughout

## Test Coverage Requirements

### Controller Tests (51 test files)
```php
// tests/Unit/Controllers/ClientsControllerTest.php
namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Modules\Clients\Controllers\ClientsController;

class ClientsControllerTest extends TestCase
{
    #[Test]
    public function it_loads_index_page(): void
    {
        /* Arrange */
        $controller = new ClientsController();
        
        /* Act */
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        /* Assert */
        $this->assertStringContainsString('clients', $output);
    }
    
    // Add tests for all public methods
    // Aim for 100% code coverage
}
```

### Model Tests (43 test files)
```php
// tests/Unit/Models/ClientTest.php
namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Modules\Clients\Models\Client;

class ClientTest extends TestCase
{
    #[Test]
    public function it_retrieves_client_by_id(): void
    {
        /* Arrange */
        $model = new Client();
        $clientId = 1;
        
        /* Act */
        $result = $model->get_by_id($clientId);
        
        /* Assert */
        $this->assertNotNull($result);
        $this->assertEquals($clientId, $result->client_id);
    }
    
    // Add tests for all public methods
    // Aim for 100% code coverage
}
```

## Execution Plan (Option B: Full PSR-4)

### Phase 1: Infrastructure Refactoring (Week 1)
1. Update `MY_Router.php` with PSR-4 module resolution
2. Update `MX/Loader.php` with PSR-4 model auto-loading
3. Update `MX/Modules.php` with PSR-4 controller resolution
4. Write tests for routing layer changes
5. Test with pilot module (Clients)
6. Validate backward compatibility

### Phase 2: Directory Structure (Week 2)
1. Run automated directory renaming script (31 modules)
2. Update composer.json PSR-4 mappings
3. Run `composer dump-autoload`
4. Test module loading
5. Fix any broken paths

### Phase 3: Class Renaming (Week 2-3)
1. Rename all 51 controller classes (add `Controller` suffix)
2. Rename all 43 model classes (remove `Mdl_` prefix, use singular)
3. Update class references in files

### Phase 4: Testing & Validation (Week 3)
1. Write comprehensive controller tests (51 files)
2. Write comprehensive model tests (43 files)
3. Achieve 100% code coverage

## Estimated Effort (Option B: Full PSR-4)

- **Infrastructure Updates:** 12-16 hours
- **Directory Renaming:** 4-6 hours (automated script)
- **Controller Renaming:** 8-12 hours
- **Model Renaming:** 10-15 hours
- **Test Writing:** 30-40 hours
- **Testing & Validation:** 10-15 hours
- **Documentation:** 4-6 hours
- **Total:** 78-110 hours (2-3 weeks for one developer)

**Comparison with Option A:**
- Option A (keep lowercase): 68-107 hours total BUT requires manually updating hundreds of `$this->load->model()` and `Modules::run()` calls
- Option B (full PSR-4): 78-110 hours total BUT smart MX refactoring eliminates manual updates

**Key Advantage:** Option B's upfront investment in infrastructure (12-16 hours for MX refactoring) eliminates the need to manually update hundreds of calls throughout the codebase. The actual hands-on work is less, even though total hours are similar.

## Breaking Changes

### For Developers
- All controller class references must update
- All model class references must update
- All `$this->load->model()` calls must update
- All test files must update

### For Users
- No breaking changes (URL routing unchanged)
- No database changes
- No configuration changes

## Validation Checklist (Option B: Full PSR-4)

### Infrastructure
- [ ] `MY_Router.php` updated with PSR-4 module resolution
- [ ] `MX/Loader.php` updated with PSR-4 model auto-loading
- [ ] `MX/Modules.php` updated with PSR-4 controller resolution
- [ ] Early return patterns used throughout routing layer
- [ ] Infrastructure tests pass (routing, loading, module resolution)

### Directory Structure
- [ ] All 31 module directories renamed to PascalCase
- [ ] All `Controllers/` subdirectories capitalized
- [ ] All `Models/` subdirectories capitalized
- [ ] All `Views/` subdirectories capitalized
- [ ] Composer PSR-4 mappings updated
- [ ] `composer dump-autoload` executed successfully

### Class Renaming
- [ ] All 51 controllers renamed with `Controller` suffix
- [ ] All 43 models renamed (no `Mdl_` prefix, singular form)
- [ ] All namespace declarations updated
- [ ] All `use` statements updated

### Backward Compatibility
- [ ] Old `$this->load->model('clients/mdl_clients')` calls still work
- [ ] Old `Modules::run('clients/index')` calls still work
- [ ] URL routing unchanged for end users
- [ ] No breaking changes for existing integrations

### Testing
- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] 100% test coverage for new infrastructure code
- [ ] Comprehensive tests for controllers and models

### Documentation
- [ ] AGENTS.md updated with PSR-4 structure
- [ ] README updated with new directory layout
- [ ] Migration guide created for developers
- [ ] Code examples updated to show new patterns

## Commands for AI Agents (Option B: Full PSR-4)

### Step 1: Update Infrastructure

```bash
# 1. Update MY_Router.php with PSR-4 module resolver
# See Option B.2 above for full implementation

# 2. Update MX/Loader.php with PSR-4 model auto-loading
# See Option B.3 above for full implementation

# 3. Update MX/Modules.php with PSR-4 controller resolution
# See Option B.4 above for full implementation

# 4. Test infrastructure with pilot module
vendor/bin/phpunit tests/Unit/Infrastructure/
```

### Step 2: Rename Directories (Automated)

```bash
#!/bin/bash
# rename_modules_psr4.sh - Automated directory renaming script

cd application/modules || exit 1

for module_dir in */; do
    module=$(basename "$module_dir")
    
    # Convert to PascalCase: clients → Clients, custom_fields → CustomFields
    pascal=$(echo "$module" | sed -r 's/(^|_)([a-z])/\U\2/g')
    
    # Early return: skip if already correct
    [[ "$module" == "$pascal" ]] && continue
    
    echo "Renaming module: $module → $pascal"
    
    # Clean up any leftover .tmp directories from previous failed runs
    [[ -n "$pascal" && -d "$pascal.tmp" ]] && rm -rf -- "$pascal.tmp"
    
    # Two-step rename to handle case-insensitive filesystems
    mv "$module" "$pascal.tmp"
    mv "$pascal.tmp" "$pascal"
    
    # Rename subdirectories using same .tmp pattern
    cd "$pascal" || continue
    
    if [[ -d "controllers" ]]; then
        [[ -n "Controllers" && -d "Controllers.tmp" ]] && rm -rf -- "Controllers.tmp"
        mv controllers Controllers.tmp
        mv Controllers.tmp Controllers
    fi
    
    if [[ -d "models" ]]; then
        [[ -n "Models" && -d "Models.tmp" ]] && rm -rf -- "Models.tmp"
        mv models Models.tmp
        mv Models.tmp Models
    fi
    
    if [[ -d "views" ]]; then
        [[ -n "Views" && -d "Views.tmp" ]] && rm -rf -- "Views.tmp"
        mv views Views.tmp
        mv Views.tmp Views
    fi
    
    if [[ -d "helpers" ]]; then
        [[ -n "Helpers" && -d "Helpers.tmp" ]] && rm -rf -- "Helpers.tmp"
        mv helpers Helpers.tmp
        mv Helpers.tmp Helpers
    fi
    
    if [[ -d "libraries" ]]; then
        [[ -n "Libraries" && -d "Libraries.tmp" ]] && rm -rf -- "Libraries.tmp"
        mv libraries Libraries.tmp
        mv Libraries.tmp Libraries
    fi
    
    cd ..
done

echo "All directories renamed to PSR-4 format"
```

```bash
# Execute the script
chmod +x rename_modules_psr4.sh
./rename_modules_psr4.sh

# Update composer autoload
composer dump-autoload

# Verify PSR-4 structure
find application/modules -type d -name "Controllers" | wc -l  # Should be 31
find application/modules -type d -name "Models" | wc -l       # Should be ~31
```

### Step 3: Rename Controller Classes

```bash
# Pilot module first: Clients
cd application/modules/Clients/Controllers/

# Update class name in file
# Before: class Clients extends \Admin_Controller
# After:  class ClientsController extends \Admin_Controller

# Repeat for all 51 controllers
```

### Step 4: Rename Model Classes

```bash
# Pilot module: Clients
cd application/modules/Clients/Models/

# Update class name in file
# Before: class Mdl_Clients extends \Response_Model
# After:  class Client extends \Response_Model

# Repeat for all 43 models
```

### Step 5: Test Infrastructure

```bash
# Test that old code still works with new infrastructure
grep -r "load->model('clients/" application/modules/Clients/ --include="*.php"
grep -r "Modules::run('clients/" application/ --include="*.php"

# Both should work without modification thanks to MX_Loader and MX_Router

# Run pilot module tests
vendor/bin/phpunit --filter=Client
```

### Step 6: Validate All Modules

```bash
# Run linters
vendor/bin/pint application/modules/

# Run full test suite  
vendor/bin/phpunit

# Test in development environment
php -S localhost:8000 -t public/

# Check for errors
tail -f application/logs/log-*.php
```

### Step 7: Update Composer PSR-4 Mappings

```json
{
  "autoload": {
    "psr-4": {
      "Core\\": "application/modules/Core/src/",
      "Modules\\Clients\\Controllers\\": "application/modules/Clients/Controllers/",
      "Modules\\Clients\\Models\\": "application/modules/Clients/Models/",
      "Modules\\Invoices\\Controllers\\": "application/modules/Invoices/Controllers/",
      "Modules\\Invoices\\Models\\": "application/modules/Invoices/Models/",
      ...
    }
  }
}
```

```bash
# Regenerate autoload files
composer dump-autoload -o
```

## Notes for AI Agents (Option B Focus)

1. **Infrastructure First:** Update MX routing layer before touching any files
2. **Automated Directory Renaming:** Use the provided script, don't do manually
3. **Early Return Patterns:** All new router/loader code uses early returns - validate preconditions first, happy path last
4. **Backward Compatibility:** Old `$this->load->model()` and `Modules::run()` calls keep working
5. **PSR-4 Leading:** When in doubt, follow PSR-4 conventions over CI conventions

6. **Dynamic Programming (Memoization):**
   - Cache module path resolutions to avoid repeated filesystem checks
   - Cache class name conversions to avoid repeated string operations
   - Use static arrays for in-memory caching
   - Clear benefits: O(1) lookups after first access, improved performance

7. **DRY Principles:**
   - Extract `to_pascal_case()`, `to_camel_case()`, `to_singular()` into shared helpers
   - Reuse helpers in MY_Router, MX_Loader, MX_Modules
   - Single source of truth for string conversions
   - Test once, use everywhere

8. **SOLID Principles:**
   - **SRP:** Each class has one responsibility (ModulePathResolver, ModelLoadingStrategy)
   - **OCP:** Use strategy pattern for extensibility without modification
   - **LSP:** All models have consistent interfaces, substitutable for base types
   - **ISP:** Small, focused interfaces (Identifiable, Listable, Searchable)
   - **DIP:** Depend on abstractions (interfaces), not concrete implementations

9. **PHPUnit Test Compatibility:**
   - Update `tests/phpunit-parallel-bootstrap.php` to support PSR-4
   - Configure `phpunit.xml.dist` with correct source paths
   - Run tests after each phase to ensure flawless operation
   - Use `markTestSkipped()` for CI-dependent tests in isolation

10. **Watch for edge cases:**
    - Models with compound names: `Mdl_invoice_tax_rates` → `InvoiceTaxRate`
    - Modules with underscores: `custom_fields` → `CustomFields` directory
    - Controllers with underscores: `User_clients` → `UserClientsController` class
    - Recursive model loading: One model loading another (handle in MX_Loader)
    - AJAX endpoints: Ensure they still resolve correctly after routing changes

11. **Directory Renaming Order (Script implements these safety measures):**
    - **Always use two-step `.tmp` suffix** to avoid case-sensitivity issues
    - Example: `clients` → `clients.tmp` → `Clients`
    - This prevents problems on case-insensitive filesystems (macOS, Windows)
    - **Clean up `.tmp` directories** before renaming to handle failed previous runs
    - **Validate paths** before `rm -rf` operations using `-n` test
    - Apply this pattern to **both module directories and subdirectories**

12. **Testing Strategy:**
    - Unit tests for MX routing layer (mock CI super-object)
    - Integration tests for controllers (test actual HTTP requests)
    - Test database operations in models (use test database)
    - Mock external dependencies (APIs, email services)
    - Run `composer dump-autoload -o` before test runs
    - Validate 100% test coverage with `vendor/bin/phpunit --coverage-text`

13. **Code Review Focus:**
    - Verify PSR-4 autoloading works without MY_Loader hacks
    - Check that early returns are used consistently (guard clauses first)
    - Ensure no performance regressions (memoization should improve performance)
    - Validate IDE autocomplete works (test with PHPStorm/VS Code)
    - Verify DRY principles: No code duplication in string conversion
    - Check SOLID compliance: Classes have single responsibility

14. **Performance Considerations:**
    - Cache module path resolution results (dynamic programming)
    - Cache class name conversions (memoization)
    - Use opcache in production
    - Profile before/after to ensure no slowdowns
    - Early returns prevent unnecessary filesystem checks
    - Memoization provides measurable performance gains

15. **Project Guidelines Adherence (See B.13 for details):**
    - **`.junie/guidelines.md`:** Defense-in-depth security, DRY principles, test requirements
    - **`AGENTS.md`:** Naming conventions, repository layout, integration provider pattern
    - **`.github/copilot-instructions.md`:** PSR-12 standards, type hints, security-first, Query Builder
    - Validate compliance with provided checklists before committing
    - Run validation commands (pint, phpstan, phpunit) to verify adherence

##### B.13: Adherence to Project Guidelines and Rules

**Critical Requirement:** All refactoring work must strictly follow the established rules from project documentation.

###### Rules from `.junie/guidelines.md`

**Security Principles - Defense in Depth:**

```php
// Apply all security layers during refactoring
class ClientsController extends \Admin_Controller
{
    public function save(): void
    {
        // Early return: Check permissions first
        if (!$this->hasPermission('edit_clients')) {
            show_error('Access denied', 403);
        }
        
        // Layer 1: Global sanitization already applied by Admin_Controller
        // Layer 2: Format validation
        $clientName = $this->input->post('client_name');
        if (!preg_match('/^[\p{L}\p{N}\s\-\.]+$/u', $clientName)) {
            $this->session->set_flashdata('alert_error', 'Invalid client name format');
            redirect('clients/form');
        }
        
        // Layer 3: Business logic validation
        if ($this->client->nameExists($clientName, $this->input->post('client_id'))) {
            $this->session->set_flashdata('alert_error', 'Client name already exists');
            redirect('clients/form');
        }
        
        // Happy path - save client
        $this->client->save($this->input->post('client_id'));
    }
}
```

**DRY Principles:**

When refactoring discovers duplicated code (3+ occurrences), extract to helpers:

```php
// BEFORE refactoring - duplicated in multiple controllers
$moduleName = str_replace('_', '', ucwords($segment, '_'));
$className = str_replace('_', '', ucwords($model, '_'));
$propertyName = lcfirst($className);

// AFTER refactoring - extract to application/core/MY_String_Helper.php
function to_pascal_case(string $string): string
{
    // Early return: Already PascalCase
    if (ctype_upper($string[0]) && !str_contains($string, '_')) {
        return $string;
    }
    
    return str_replace(['_', '-'], '', ucwords($string, '_-'));
}

function to_camel_case(string $string): string
{
    // Early return: Empty string
    if (empty($string)) {
        return $string;
    }
    
    return lcfirst($string);
}
```

**Test Requirements:**

Every refactored class MUST have tests following the AAA pattern:

```php
<?php

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ModulePathResolverTest extends TestCase
{
    private ModulePathResolver $resolver;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ModulePathResolver();
    }
    
    #[Test]
    public function it_resolves_lowercase_module_to_pascal_case(): void
    {
        // Arrange
        $urlSegment = 'clients';
        
        // Act
        $path = $this->resolver->resolve($urlSegment);
        
        // Assert
        $this->assertStringContainsString('Clients/', $path);
    }
    
    #[Test]
    public function it_resolves_snake_case_module_to_pascal_case(): void
    {
        // Arrange
        $urlSegment = 'custom_fields';
        
        // Act
        $path = $this->resolver->resolve($urlSegment);
        
        // Assert
        $this->assertStringContainsString('CustomFields/', $path);
    }
    
    #[Test]
    public function it_returns_null_for_nonexistent_module(): void
    {
        // Arrange
        $urlSegment = 'nonexistent_module';
        
        // Act & Assert
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->resolver->resolve($urlSegment);
    }
}
```

###### Rules from `AGENTS.md`

**Naming Conventions:**

All refactored code must follow established naming patterns:

```php
// Controllers: PascalCase + Controller suffix
namespace Modules\Clients\Controllers;
class ClientsController extends \Admin_Controller { }

// Models: PascalCase, singular, no Mdl_ prefix
namespace Modules\Clients\Models;
class Client extends \Response_Model { }

// Services: PascalCase + Service suffix
namespace Core\Services\Clients;
class ClientsService { }

// Helpers: PascalCase + Helper suffix
namespace Core\Helpers;
class SecurityHelper { }

// Test classes: Match tested class + Test suffix
namespace Tests\Unit\Controllers\Clients;
class ClientsControllerTest extends TestCase { }

// Test methods: it_ prefix + snake_case
#[Test]
public function it_returns_client_by_id(): void { }
```

**Repository Layout Compliance:**

Maintain the established module structure:

```
application/modules/
├── Clients/              ← PascalCase directory (after refactor)
│   ├── Controllers/      ← Capitalized
│   │   └── ClientsController.php
│   ├── Models/           ← Capitalized
│   │   └── Client.php
│   └── Views/            ← Capitalized
│       └── index.php
└── Core/                 ← Core namespace
    └── src/
        ├── Contracts/
        ├── Helpers/
        ├── Providers/
        └── Services/
```

**Integration Provider Pattern:**

All new integrations follow the provider pattern:

```php
// 1. Implement the contract
class NewProviderGatewayProvider implements IntegrationProviderInterface
{
    public function validateParticipant(string $participantId): bool
    {
        // Early return: Empty ID
        if (empty($participantId)) {
            return false;
        }
        
        $gateway = $this->createGatewayClient();
        $endpoint = new ParticipantEndpoint($gateway);
        
        return $endpoint->validate($participantId);
    }
    
    public function sendInvoice(array $payload): bool
    {
        // Early return: Invalid payload
        if (empty($payload['invoice_id'])) {
            log_message('error', 'Missing invoice_id in payload');
            return false;
        }
        
        $gateway = $this->createGatewayClient();
        $endpoint = new InvoiceEndpoint($gateway);
        $response = $endpoint->send($payload);
        
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }
}

// 2. Register in factory
$factory->register('new_provider', fn() => new NewProviderGatewayProvider($settings));

// 3. Use through interface
$provider = $factory->make('new_provider');
$result = $provider->sendInvoice($payload);
```

###### Rules from `.github/copilot-instructions.md`

**PSR-12 Coding Standards:**

All refactored code must follow PSR-12:

```php
<?php

declare(strict_types=1);

namespace Modules\Clients\Controllers;

use Core\Services\Clients\ClientsService;
use Core\Helpers\SecurityHelper;

class ClientsController extends \Admin_Controller
{
    private ClientsService $clientsService;
    
    public function __construct()
    {
        parent::__construct();
        $this->clientsService = new ClientsService();
    }
    
    public function index(): void
    {
        // Early return: No permission
        if (!$this->hasPermission('view_clients')) {
            show_error('Access denied', 403);
        }
        
        $clients = $this->clientsService->getAll();
        
        // Early return: No clients
        if (empty($clients)) {
            $this->load->view('clients/empty');
            return;
        }
        
        $this->load->view('clients/index', ['clients' => $clients]);
    }
}
```

**Type Hints and Strict Comparison:**

```php
// Always use type hints
public function getById(int $id): ?object
{
    // Early return: Invalid ID
    if ($id <= 0) {
        return null;
    }
    
    // Use strict comparison
    $client = $this->db->where('id', $id)->get('ip_clients')->row();
    
    // Strict null check
    if ($client === null) {
        return null;
    }
    
    return $client;
}

// Array type hints
public function validateBatch(array $clients): array
{
    $errors = [];
    
    foreach ($clients as $client) {
        // Strict comparison for validation
        if ($client['status'] !== 'active') {
            $errors[] = "Client {$client['id']} is not active";
        }
    }
    
    return $errors;
}
```

**Test Structure (AAA Pattern):**

```php
#[Test]
public function it_validates_safe_filename(): void
{
    // Arrange
    $filename = '../../../etc/passwd';
    
    // Act
    $result = validate_safe_filename($filename);
    
    // Assert
    $this->assertFalse($result['valid']);
    $this->assertEquals('path_traversal', $result['error']);
}
```

**Security-First Development:**

```php
class UploadController extends \Admin_Controller
{
    public function uploadInvoiceLogo(): void
    {
        // Early return: No file uploaded
        if (empty($_FILES['logo'])) {
            show_error('No file uploaded');
        }
        
        // Security Layer 1: Validate filename
        $filename = basename($_FILES['logo']['name']);
        $validation = validate_safe_filename($filename);
        
        // Early return: Invalid filename
        if (!$validation['valid']) {
            log_message('error', 'Invalid filename: ' . sanitize_for_logging($filename));
            show_error('Invalid filename');
        }
        
        // Security Layer 2: Validate file extension
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Early return: Invalid extension
        if (!in_array($extension, $allowedExtensions, true)) {
            log_message('warning', 'Blocked extension: ' . sanitize_for_logging($extension));
            show_error('File type not allowed');
        }
        
        // Security Layer 3: Block SVG (XSS vector)
        if ($extension === 'svg') {
            log_message('warning', 'Blocked SVG upload');
            show_error('SVG files not allowed');
        }
        
        // Security Layer 4: Validate path
        $uploadPath = APPPATH . '../uploads/logos/';
        $targetPath = $uploadPath . $filename;
        
        // Early return: Path traversal attempt
        if (!validate_file_in_directory($targetPath, $uploadPath)) {
            log_message('error', 'Path traversal attempt: ' . sanitize_for_logging($targetPath));
            show_error('Invalid file path');
        }
        
        // Happy path - move uploaded file
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
            $this->session->set_flashdata('alert_success', 'Logo uploaded successfully');
        }
    }
}
```

**CodeIgniter 3 Query Builder:**

Always use Query Builder (never raw SQL):

```php
class Client extends \Response_Model
{
    public function getById(int $id): ?object
    {
        // Early return: Invalid ID
        if ($id <= 0) {
            return null;
        }
        
        // Use Query Builder - prevents SQL injection
        return $this->db
            ->where('client_id', $id)
            ->where('deleted', 0)
            ->get('ip_clients')
            ->row();
    }
    
    public function search(string $query): array
    {
        // Early return: Empty query
        if (empty(trim($query))) {
            return [];
        }
        
        // Use Query Builder with LIKE
        return $this->db
            ->like('client_name', $query)
            ->or_like('client_email', $query)
            ->where('deleted', 0)
            ->get('ip_clients')
            ->result();
    }
}
```

###### Enforcement Checklist

Before committing any refactored code, verify:

**From `.junie/guidelines.md`:**
- [ ] Defense-in-depth security applied (input sanitization, output encoding, validation)
- [ ] DRY principle followed (no code duplication, helpers extracted for 3+ occurrences)
- [ ] All security functions have tests
- [ ] Early returns used consistently
- [ ] Helper functions organized in appropriate files

**From `AGENTS.md`:**
- [ ] Naming conventions followed (Controller suffix, singular models, no Mdl_ prefix)
- [ ] Repository layout maintained (PascalCase directories, Capitalized subdirectories)
- [ ] Integration provider pattern used for external services
- [ ] Test methods use `it_` prefix and snake_case
- [ ] Module namespaces follow PSR-4 (`Modules\ModuleName\Controllers`)

**From `.github/copilot-instructions.md`:**
- [ ] PSR-12 coding standards enforced
- [ ] Type hints on all parameters and return types
- [ ] Strict comparison (`===`, `!==`) used everywhere
- [ ] AAA pattern (Arrange, Act, Assert) in all tests
- [ ] Security-first approach (validate paths, sanitize logs, block SVG)
- [ ] Query Builder used (no raw SQL)

###### Validation Commands

```bash
# 1. Verify PSR-12 compliance
vendor/bin/pint --test

# 2. Run PHPStan static analysis
vendor/bin/phpstan analyse --level 8

# 3. Run all tests with coverage
vendor/bin/phpunit --coverage-text --coverage-html=coverage/

# 4. Verify naming conventions
find application/modules/*/Controllers -name "*.php" | while read file; do
    if ! grep -q "Controller extends" "$file"; then
        echo "Missing Controller suffix: $file"
    fi
done

# 5. Check for security issues
grep -rn "echo \$_" application/modules/*/Views/ # Should find none (use html_escape)
grep -rn "include(\$" application/ # Should find none (path traversal risk)

# 6. Verify early returns pattern
grep -rn "if.*{$" application/modules/Core/src/ | wc -l # Should be low (use early returns)
```

## Success Criteria (Option B: Full PSR-4)

- ✅ **Infrastructure:** MX routing layer refactored to support PSR-4 natively
- ✅ **Directory Structure:** All modules use PascalCase naming (`Clients/`, `Invoices/`, `CustomFields/`)
- ✅ **Subdirectories:** All use capitalized names (`Controllers/`, `Models/`, `Views/`)
- ✅ **Controllers:** All have `Controller` suffix and proper PSR-4 namespaces
- ✅ **Models:** All are singular with no `Mdl_` prefix and proper PSR-4 namespaces
- ✅ **Backward Compatibility:** Old code patterns still work without modification
- ✅ **Early Returns:** All routing code uses early return pattern consistently
- ✅ **Tests:** 100% coverage for infrastructure, controllers, and models
- ✅ **Test Compatibility:** PHPUnit tests work flawlessly with PSR-4 autoloading
- ✅ **Dynamic Programming:** Memoization used for expensive operations (module path resolution, class name conversion)
- ✅ **DRY Programming:** Common patterns extracted into reusable helper functions
- ✅ **SOLID Principles:** Single responsibility, open/closed, Liskov substitution, interface segregation, dependency inversion
- ✅ **Autoloading:** Native PSR-4 autoloading works without custom loaders
- ✅ **IDE Support:** Full autocomplete and navigation in modern IDEs
- ✅ **Linters:** All static analysis tools pass (PHPStan Level 8+)
- ✅ **Staging:** Application works correctly in staging environment
- ✅ **Performance:** No performance regression (memoization provides gains)
- ✅ **Documentation:** All docs updated to reflect PSR-4 structure
- ✅ **`.junie/guidelines.md` Rules:** Security, DRY, testing requirements strictly followed
- ✅ **`AGENTS.md` Rules:** Naming conventions, repository layout, provider pattern enforced
- ✅ **`.github/copilot-instructions.md` Rules:** PSR-12, type hints, security-first, Query Builder used

## References

- PSR-4 Autoloading: https://www.php-fig.org/psr/psr-4/
- CodeIgniter 3 MX HMVC: https://github.com/wiredesignz/codeigniter-modular-extensions-hmvc
- InvoicePlane AGENTS.md: See repository for current architecture
- InvoicePlane CONTRIBUTING.md: See repository for contribution guidelines

---

**Last Updated:** 2026-05-02  
**Approach:** Option B - Full PSR-4 Compliance (Recommended)  
**Status:** Ready for execution after namespace consolidation PR merge  
**Estimated Duration:** 2-3 weeks for one developer (78-110 hours)  
**Key Principle:** PSR-4 is leading, CodeIgniter adapts to fit PSR-4
