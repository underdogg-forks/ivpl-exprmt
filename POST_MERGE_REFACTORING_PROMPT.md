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
- Composer PSR-4 expects `application/modules/clients/Controllers/ClientsController.php`
- Actual structure: `application/modules/clients/controllers/Clients.php`

**Options:**

#### Option A: Keep lowercase directories (Recommended for CI compatibility)
```bash
# No directory renaming needed
# MX HMVC loader works with lowercase directories
# Composer autoloading uses custom logic in MY_Loader.php
```

#### Option B: Rename to PSR-4 capitalized directories
```bash
# Rename all directories
application/modules/clients/controllers/ → Controllers/
application/modules/clients/models/ → Models/
application/modules/clients/views/ → Views/

# Update MX configuration
# Update CI config paths
# Test all module loading
```

**Recommendation:** Use Option A (keep lowercase) because:
- MX HMVC expects lowercase directories
- CI conventions use lowercase directories
- Custom autoloading via `MY_Loader.php` handles the mismatch
- Less disruption to existing file paths

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

## Execution Plan

### Phase 1: Controllers (Week 1)
1. Rename all 51 controller classes (add `Controller` suffix)
2. Update all `Modules::run()` calls
3. Update all test files
4. Run full test suite
5. Fix any breaking changes

### Phase 2: Models (Week 2)
1. Rename all 43 model classes (remove `Mdl_` prefix, use singular)
2. Update all `$this->load->model()` calls
3. Update all model property references
4. Update all test files
5. Run full test suite
6. Fix any breaking changes

### Phase 3: Testing (Week 3)
1. Write comprehensive controller tests (51 files)
2. Write comprehensive model tests (43 files)
3. Achieve 100% code coverage
4. Document any edge cases

### Phase 4: Validation (Week 4)
1. Run linters (PHP CS Fixer, PHPStan)
2. Run full test suite
3. Test in staging environment
4. Code review
5. Merge to develop

## Estimated Effort

- **Controller Renaming:** 8-12 hours
- **Model Renaming:** 10-15 hours
- **Test Writing:** 40-60 hours
- **Bug Fixes:** 10-20 hours
- **Total:** 68-107 hours (2-3 weeks for one developer)

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

## Validation Checklist

- [ ] All 51 controllers renamed with `Controller` suffix
- [ ] All 43 models renamed (no `Mdl_` prefix, singular form)
- [ ] All `$this->load->model()` calls updated
- [ ] All `Modules::run()` calls verified
- [ ] All test files updated
- [ ] 100% test coverage for controllers
- [ ] 100% test coverage for models
- [ ] Linters pass (PHP CS Fixer, PHPStan)
- [ ] Full test suite passes
- [ ] Staging environment tested
- [ ] Documentation updated

## Commands for AI Agents

### Step 1: Analyze Scope
```bash
# Count controllers
find application/modules -name "*.php" -path "*/controllers/*" | wc -l

# Count models
find application/modules -name "*.php" -path "*/models/*" | wc -l

# Find all Modules::run() calls
grep -r "Modules::run" application/ --include="*.php" | wc -l

# Find all $this->load->model() calls
grep -r "->load->model" application/ --include="*.php" | wc -l
```

### Step 2: Rename Controllers (Pilot Module)
```bash
# Start with clients module as pilot
cd application/modules/clients/controllers/

# Rename class in file
# Change: class Clients extends \Admin_Controller
# To: class ClientsController extends \Admin_Controller
```

### Step 3: Rename Models (Pilot Module)
```bash
cd application/modules/clients/models/

# Rename class in file
# Change: class Mdl_Clients extends \Response_Model
# To: class Client extends \Response_Model
```

### Step 4: Update References
```bash
# Update all load->model calls in clients module
grep -r "load->model('clients/" application/modules/clients/ --include="*.php"

# Update manually or use sed (carefully!)
```

### Step 5: Write Tests
```bash
# Create test file
mkdir -p tests/Unit/Controllers
touch tests/Unit/Controllers/ClientsControllerTest.php

# Run tests
vendor/bin/phpunit tests/Unit/Controllers/ClientsControllerTest.php
```

### Step 6: Validate Pilot
```bash
# Run linters
vendor/bin/pint application/modules/clients/

# Run tests
vendor/bin/phpunit --filter=Clients

# If successful, proceed to all modules
```

## Notes for AI Agents

1. **Start with pilot module** (clients) to validate approach
2. **Use grep/sed carefully** - manual review recommended
3. **Test incrementally** - don't rename everything at once
4. **Watch for edge cases:**
   - Models with compound names (e.g., `Mdl_invoice_tax_rates` → `InvoiceTaxRate`)
   - Controllers with underscores (e.g., `User_clients` → `UserClientsController`)
   - Recursive model loading (one model loading another)
   - AJAX endpoints that return model data

5. **PSR-4 Directory Casing:**
   - Keep lowercase directories (`controllers/`, `models/`)
   - Only class names need to match PSR-4 conventions
   - MX HMVC and MY_Loader handle the directory case mismatch

6. **Testing Strategy:**
   - Unit tests for models (database operations)
   - Integration tests for controllers (HTTP requests)
   - Use test database fixtures
   - Mock external dependencies (APIs, email)

7. **Code Review Focus:**
   - Verify all class renames
   - Check for missed references
   - Ensure tests pass
   - Validate autoloading works

## Success Criteria

- ✅ All controller classes have `Controller` suffix
- ✅ All model classes are singular with no `Mdl_` prefix
- ✅ 100% test coverage for controllers and models
- ✅ All tests pass
- ✅ Linters pass
- ✅ Application works in staging environment
- ✅ No console errors or warnings
- ✅ Documentation updated

## References

- PSR-4 Autoloading: https://www.php-fig.org/psr/psr-4/
- CodeIgniter 3 MX HMVC: https://github.com/wiredesignz/codeigniter-modular-extensions-hmvc
- InvoicePlane AGENTS.md: See repository for current architecture
- InvoicePlane CONTRIBUTING.md: See repository for contribution guidelines

---

**Last Updated:** 2026-05-02  
**Status:** Ready for execution after namespace consolidation PR merge  
**Estimated Duration:** 2-3 weeks for one developer
