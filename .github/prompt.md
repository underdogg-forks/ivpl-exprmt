# AI Prompt: PSR-4 Namespace Migration for InvoicePlane Modules

> **Purpose:** Copy-paste this prompt into any AI (GitHub Copilot, Claude, GPT-4, Gemini)
> to guide it through adding namespaces to every class in `application/modules`
> without breaking the CodeIgniter 3 / MX runtime.

---

## The Prompt

```
You are an expert PHP architect working on **InvoicePlane**, a self-hosted invoicing app
built on **CodeIgniter 3 (CI3)** with **Modular Extensions (MX / HMVC)**.

The codebase is incrementally migrating from legacy CI3 conventions toward **PSR-4
namespacing and Laravel-style patterns**, while keeping the CI3 runtime fully functional.

New `App\*` classes already live under `application/lib/App/` and are autoloaded via
Composer PSR-4.  The **next step** is to add namespaces to *all* existing module classes
in `application/modules/` without breaking anything.

─────────────────────────────────────────────────────────────────────────────────────────
CONTEXT: HOW CI3 / MX LOADS CONTROLLERS AND MODELS
─────────────────────────────────────────────────────────────────────────────────────────

CI3 uses class *names* (not file paths) to dispatch requests:

  URL: /invoices/view/1
  → MX looks for class `Invoices` in modules/invoices/controllers/Invoices.php
  → Instantiates it with `new Invoices()`

When namespaces are added, CI3's autoloader no longer finds the class by the short name.
The fix is to teach MY_Router to `class_alias()` the namespaced class to its short name,
or to register the module namespace in composer.json and use a MY_Loader shim.

Key files already in place (do NOT recreate them):
  • application/core/MY_Loader.php   — loads namespaced App\ classes via Composer PSR-4
  • application/core/MY_Router.php   — aliases PSR-4 controller names; supports $moduleAliases
  • application/third_party/MX/      — HMVC router/loader (do NOT modify)
  • AGENTS.md                        — canonical AI context document

─────────────────────────────────────────────────────────────────────────────────────────
OBJECTIVE
─────────────────────────────────────────────────────────────────────────────────────────

Apply the following changes **incrementally and safely** — one module at a time.

### 1. Add PSR-4 namespace declarations to all module controllers and models

For each module in `application/modules/<module>/`:

  **Controller** (`controllers/<Module>.php`):
  - Rename file to `controllers/<Module>Controller.php`  (PSR-4 convention)
  - Add `namespace Module\<Module>;` at the top
  - The class still extends `Admin_Controller` (or `Guest_Controller`)
  - Do NOT change the class body — only the namespace and file name

  **Model** (`models/Mdl_<module>.php`):
  - Keep the file name as-is (models are not URL-dispatched)
  - Add `namespace Module\<Module>;` at the top
  - The class still extends `MY_Model` or `CI_Model`

  **Views** — no change needed (views are plain PHP, no class)

### 2. Register module namespaces in composer.json

```json
{
  "autoload": {
    "psr-4": {
      "App\\": "application/lib/App/",
      "Module\\": "application/modules/"
    }
  }
}
```

Run `composer dump-autoload` after every change.

### 3. Update MY_Router to alias namespaced controllers

MY_Router already aliases PSR-4 `IntegrationsController` → `Integrations` via
`class_alias()`. Extend this to cover ALL modules by making the alias loop generic
rather than module-specific.

The pattern in MY_Router::aliasPsr4Controller() already does this — no extra work
needed as long as the controller file follows the `<Module>Controller.php` naming.

### 4. Update MY_Loader for namespaced models

When a CI controller does `$this->load->model('invoices/mdl_invoices')`, MX's
autoloader currently requires the file directly. After namespacing, the class will
be `Module\Invoices\Mdl_invoices`.

MY_Loader::model() already handles FQCNs — you can also register a Composer
class map to map the legacy short name to the FQCN so existing `$this->load->model()`
calls continue to work without editing every controller:

```php
// In MY_Loader::model(), after the namespaced check:
// Optionally: maintain a legacy alias map
$legacyMap = [
    'mdl_invoices' => 'Module\Invoices\Mdl_invoices',
    // ...
];
```

### 5. Lean on Laravel classes where possible

InvoicePlane already uses `illuminate/collections`. Extend this to:
  - `illuminate/support`  — Str, Arr, Collection helpers
  - `illuminate/contracts` — Interfaces (if not using full Eloquent)

For new services, follow Laravel's service provider registration pattern via
`App\Services\Integrations\IntegrationProviderFactory` (already in place).

─────────────────────────────────────────────────────────────────────────────────────────
CONSTRAINTS (DO NOT VIOLATE)
─────────────────────────────────────────────────────────────────────────────────────────

1. **Never modify** `application/third_party/MX/*.php` — these are vendored files.
2. **Do not add** entries to `application/config/routes.php` for URL aliasing —
   use `MY_Router::$moduleAliases` instead.
3. **Keep backward compatibility** — every legacy URL (`/invoices/view/1`) must
   continue to work exactly as before.
4. **One module at a time** — commit after each module; run PHPUnit before the next.
5. **PHP 8.1 minimum** — use enums, fibers, readonly properties freely.
6. **PSR-12** code style — run `composer phpcs` before committing.
7. **Test coverage** — every new class or modified class must have a PHPUnit test in
   `tests/Unit/<Module>/`.
8. **Security** — sanitize all log output with `sanitize_for_logging()` from
   `file_security_helper.php`. Use `html_escape()` for all view output.

─────────────────────────────────────────────────────────────────────────────────────────
MIGRATION CHECKLIST (tick off per module)
─────────────────────────────────────────────────────────────────────────────────────────

For each module, follow this order:

  [ ] 1. Add `namespace Module\<Module>;` to controller and models
  [ ] 2. Rename controller file to `<Module>Controller.php`
  [ ] 3. Run `composer dump-autoload`
  [ ] 4. Verify URL still works in browser / feature test
  [ ] 5. Run PHPUnit — all green?
  [ ] 6. Commit: "feat(module): namespace Module\<Module>"

Suggested migration order (least risky first):
  integrations → invoice_groups → products → payments → quotes → invoices → clients

─────────────────────────────────────────────────────────────────────────────────────────
KEY FILES FOR REFERENCE
─────────────────────────────────────────────────────────────────────────────────────────

  AGENTS.md                                               — full AI context
  .github/copilot-instructions.md                         — Copilot-specific guidance
  .junie/guidelines.md                                    — security & DRY guidelines
  application/core/MY_Router.php                         — PSR-4 routing + alias map
  application/core/MY_Loader.php                         — PSR-4 namespaced class loading
  application/lib/App/Contracts/IntegrationProviderInterface.php
  application/lib/App/Providers/ExceptionHandlingDecorator.php
  application/lib/App/Services/Integrations/IntegrationProviderFactory.php
  tests/phpunit-parallel-bootstrap.php                   — PHPUnit stubs for CI classes
  phpunit.xml.dist                                        — PHPUnit config
  composer.json                                           — Composer PSR-4 + dependencies

─────────────────────────────────────────────────────────────────────────────────────────
EXAMPLE OUTPUT — Invoices module after migration
─────────────────────────────────────────────────────────────────────────────────────────

**File:** `application/modules/invoices/controllers/InvoicesController.php`

```php
<?php

namespace Module\Invoices;

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Invoices controller.
 *
 * URL: /invoices, /invoices/view/{id}, /invoices/create, etc.
 * MX resolves this via MY_Router::aliasPsr4Controller() which aliases
 * InvoicesController → Invoices so CI can dispatch the request.
 */
class InvoicesController extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('invoices/mdl_invoices');
    }

    public function index(): void
    {
        // ... existing body unchanged ...
    }
}
```

**File:** `application/modules/invoices/models/Mdl_invoices.php` (unchanged file name)

```php
<?php

namespace Module\Invoices;

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Mdl_invoices extends MY_Model
{
    // ... existing body unchanged ...
}
```

─────────────────────────────────────────────────────────────────────────────────────────
DONE — the AI now has everything it needs to execute the migration safely.
─────────────────────────────────────────────────────────────────────────────────────────
```

---

## Quick-Reference: Adding a New Namespaced Module from Scratch

```bash
# 1. Create the module skeleton
mkdir -p application/modules/storecove/{controllers,models,views,config}

# 2. Create the controller (PSR-4 naming)
cat > application/modules/storecove/controllers/StorecoveController.php << 'PHP'
<?php
namespace Module\Storecove;
defined('BASEPATH') || exit('No direct script access allowed');
class StorecoveController extends Admin_Controller { public function index(): void {} }
PHP

# 3. Create the provider under App\
# application/lib/App/Providers/StorecoveProvider.php
# implements App\Contracts\IntegrationProviderInterface

# 4. Register in composer.json autoload.psr-4 (Module\ already mapped)
composer dump-autoload

# 5. Register in IntegrationProviderFactory (in the controller / bootstrap)
$factory->register('storecove', fn () => new StorecoveProvider($settingsService));

# 6. Add URL alias in MY_Router if inside a parent module
# $moduleAliases = ['storecove' => 'core/storecove'];

# 7. Write tests
# tests/Unit/StorecoveProviderTest.php
```

---

## Answers to Common Questions

| Question | Answer |
|---|---|
| Can I hide `core/` from the URL without `routes.php`? | Yes — add to `MY_Router::$moduleAliases`. |
| Do views need namespaces? | No — views are plain PHP partials, not classes. |
| Do helpers need namespaces? | No — CI helpers are function files, not classes. |
| Can I use `$this->load->model('invoices/mdl_invoices')` after namespacing? | Yes — MY_Loader handles both FQCNs and legacy strings. |
| Will Composer PSR-4 conflict with CI's autoloader? | No — CI's `_autoload()` runs first; Composer's `spl_autoload_register` is a fallback. |
| Do I need to update every `new Mdl_invoices()` call? | No — CI's `$this->load->model()` resolves the class; the namespace is transparent. |
