# Namespacing InvoicePlane safely (without breaking runtime)

Short answer: **not all at once**.

InvoicePlane currently runs on CodeIgniter 3 + HMVC (MX), where class discovery and inheritance rely heavily on legacy class names (`CI_*`, `MX_*`, `MY_*`) and convention-based loading.

## Why a big-bang namespace rewrite will break

- Core extension points depend on `MY_` subclass naming (`$config['subclass_prefix'] = 'MY_'`).
- The custom loader bootstraps `MX_Loader` directly from legacy paths.
- HMVC bootstrap classes instantiate and type-check legacy class names (`MX_Lang`, `MX_Config`, `CI`, etc.).

## Safe migration strategy

1. **Freeze behavior with tests first**
   - Add smoke tests for auth, invoice create/view/pdf, payments, cron/CLI, and email sending.

2. **Move only new code to namespaces first**
   - Keep existing CI/MX classes untouched.
   - Add PSR-4 namespace roots for new service code (for example `App\...`), while keeping legacy entrypoints.

3. **Introduce compatibility bridges**
   - For each migrated class, keep a legacy alias/shim layer (`class_alias`) so old references still resolve.
   - Do this in reversible steps, module by module.

4. **Migrate by vertical slice**
   - Example order: helpers/services -> models -> controllers -> module loaders.
   - Keep one module “hybrid” at a time and ship incrementally.

5. **Only then touch framework-adjacent internals**
   - `MY_*` core classes and MX internals should be last.
   - Consider these effectively framework glue and maintain compatibility wrappers indefinitely.

## Practical rule of thumb

If a class is instantiated by CodeIgniter/MX internals, routing, or subclass prefix conventions, assume it must keep a legacy entrypoint even after namespacing.


## Current project convention

- New namespaced code uses `App\...` (Laravel-style naming) and is autoloaded via Composer.
- Legacy MX compatibility shims are autoloaded from `bootstrap/autoload.php` so framework glue remains isolated from domain/application code.
