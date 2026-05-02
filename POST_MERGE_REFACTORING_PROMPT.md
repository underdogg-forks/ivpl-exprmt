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

##### B.7: Estimated Effort (Full PSR-4)

- **Infrastructure Updates (MY_Router, MX_Loader, MX_Modules):** 12-16 hours
- **Directory Renaming (31 modules × 4 subdirs):** 4-6 hours (mostly automated)
- **Controller Renaming (51 files):** 8-12 hours
- **Model Renaming (43 files):** 10-15 hours
- **Testing & Validation:** 20-30 hours
- **Documentation Updates:** 4-6 hours
- **Total:** 58-85 hours (1.5-2 weeks for one developer)

**Note:** This is actually **less hands-on work** than Option A because:
- No need to update hundreds of `$this->load->model()` calls (MX_Loader handles it)
- No need to update hundreds of `Modules::run()` calls (routing handles it)
- Automated directory renaming script reduces manual work
- Clean PSR-4 structure reduces long-term maintenance burden

**Recommendation:** Use Option B (full PSR-4) because:
- **PSR-4 is the standard:** Modern PHP expects PSR-4 compliance
- **Better developer experience:** Clear conventions, no magic
- **Future Laravel migration:** Directory structure already matches Laravel expectations
- **Less work than it seems:** Smart refactoring of MX layer eliminates most manual updates
- **One-time investment:** Pays dividends in maintainability and developer onboarding

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
        // Arrange
        $controller = new ClientsController();
        
        // Act
        ob_start();
        $controller->index();
        $output = ob_get_clean();
        
        // Assert
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
        // Arrange
        $model = new Client();
        $clientId = 1;
        
        // Act
        $result = $model->get_by_id($clientId);
        
        // Assert
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
4. Run linters and fix issues
5. Test all modules

### Phase 4: Testing & Validation (Week 3)
1. Write comprehensive controller tests (51 files)
2. Write comprehensive model tests (43 files)
3. Achieve 100% code coverage
4. Run full test suite
5. Test in staging environment
6. Document any edge cases

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
- [ ] Manual testing in development environment
- [ ] Staging environment tested

### Code Quality
- [ ] Linters pass (PHP CS Fixer, PHPStan)
- [ ] No new PHPStan errors introduced
- [ ] PSR-4 autoloading works natively
- [ ] IDE autocomplete works correctly
- [ ] No console errors or warnings

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
    [[ -d "$pascal.tmp" ]] && rm -rf "$pascal.tmp"
    
    # Two-step rename to handle case-insensitive filesystems
    mv "$module" "$pascal.tmp"
    mv "$pascal.tmp" "$pascal"
    
    # Rename subdirectories using same .tmp pattern
    cd "$pascal" || continue
    
    if [[ -d "controllers" ]]; then
        [[ -d "Controllers.tmp" ]] && rm -rf "Controllers.tmp"
        mv controllers Controllers.tmp
        mv Controllers.tmp Controllers
    fi
    
    if [[ -d "models" ]]; then
        [[ -d "Models.tmp" ]] && rm -rf "Models.tmp"
        mv models Models.tmp
        mv Models.tmp Models
    fi
    
    if [[ -d "views" ]]; then
        [[ -d "Views.tmp" ]] && rm -rf "Views.tmp"
        mv views Views.tmp
        mv Views.tmp Views
    fi
    
    if [[ -d "helpers" ]]; then
        [[ -d "Helpers.tmp" ]] && rm -rf "Helpers.tmp"
        mv helpers Helpers.tmp
        mv Helpers.tmp Helpers
    fi
    
    if [[ -d "libraries" ]]; then
        [[ -d "Libraries.tmp" ]] && rm -rf "Libraries.tmp"
        mv libraries Libraries.tmp
        mv Libraries.tmp Libraries
    fi
    
    cd ..
done

echo "✅ All directories renamed to PSR-4 format"
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
3. **Early Return Patterns:** All new router/loader code uses early returns
4. **Backward Compatibility:** Old `$this->load->model()` and `Modules::run()` calls keep working
5. **PSR-4 Leading:** When in doubt, follow PSR-4 conventions over CI conventions

6. **Watch for edge cases:**
   - Models with compound names: `Mdl_invoice_tax_rates` → `InvoiceTaxRate`
   - Modules with underscores: `custom_fields` → `CustomFields` directory
   - Controllers with underscores: `User_clients` → `UserClientsController` class
   - Recursive model loading: One model loading another (handle in MX_Loader)
   - AJAX endpoints: Ensure they still resolve correctly after routing changes

7. **Directory Renaming Order:**
   - **Always use two-step `.tmp` suffix** to avoid case-sensitivity issues
   - Example: `clients` → `clients.tmp` → `Clients`
   - This prevents problems on case-insensitive filesystems (macOS, Windows)
   - **Clean up `.tmp` directories** before renaming to handle failed previous runs
   - Apply this pattern to **both module directories and subdirectories**

8. **Testing Strategy:**
   - Unit tests for MX routing layer (mock CI super-object)
   - Integration tests for controllers (test actual HTTP requests)
   - Test database operations in models (use test database)
   - Mock external dependencies (APIs, email services)

9. **Code Review Focus:**
   - Verify PSR-4 autoloading works without MY_Loader hacks
   - Check that early returns are used consistently
   - Ensure no performance regressions in routing layer
   - Validate IDE autocomplete works (test with PHPStorm/VS Code)

10. **Performance Considerations:**
    - Cache module path resolution results
    - Use opcache in production
    - Profile before/after to ensure no slowdowns
    - Early returns prevent unnecessary filesystem checks

## Success Criteria (Option B: Full PSR-4)

- ✅ **Infrastructure:** MX routing layer refactored to support PSR-4 natively
- ✅ **Directory Structure:** All modules use PascalCase naming (`Clients/`, `Invoices/`, `CustomFields/`)
- ✅ **Subdirectories:** All use capitalized names (`Controllers/`, `Models/`, `Views/`)
- ✅ **Controllers:** All have `Controller` suffix and proper PSR-4 namespaces
- ✅ **Models:** All are singular with no `Mdl_` prefix and proper PSR-4 namespaces
- ✅ **Backward Compatibility:** Old code patterns still work without modification
- ✅ **Early Returns:** All routing code uses early return pattern
- ✅ **Tests:** 100% coverage for infrastructure, controllers, and models
- ✅ **Autoloading:** Native PSR-4 autoloading works without custom loaders
- ✅ **IDE Support:** Full autocomplete and navigation in modern IDEs
- ✅ **Linters:** All static analysis tools pass (PHPStan Level 8+)
- ✅ **Staging:** Application works correctly in staging environment
- ✅ **Performance:** No performance regression in routing/loading
- ✅ **Documentation:** All docs updated to reflect PSR-4 structure

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
