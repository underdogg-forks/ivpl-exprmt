<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// load the MX_Router class
require APPPATH . 'third_party/MX/Router.php';

/**
 * Enhanced CI3/MX Router with two capabilities:
 *
 *  1. PSR-4 controller naming  — `IntegrationsController.php` alongside legacy `Integrations.php`.
 *  2. Module URL aliases        — expose sub-modules at clean URLs without any routes.php entry.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  1. PSR-4 Controller Naming
 * ═══════════════════════════════════════════════════════════════════
 *  New module controllers can be named with a `Controller` suffix
 *  (PSR-4 style) instead of the legacy CI underscore suffix.
 *
 *  Resolution order for URL segment `integrations`:
 *    1. PSR-4  → modules/integrations/controllers/IntegrationsController.php
 *    2. Legacy → modules/integrations/controllers/Integrations.php
 *
 * ═══════════════════════════════════════════════════════════════════
 *  2. Module URL Aliases  (no routes.php required)
 * ═══════════════════════════════════════════════════════════════════
 *  Add entries to $moduleAliases to map a public URL segment to an
 *  internal parent-module/sub-module path.
 *
 *  Example — Integrations lives inside a "core" module:
 *
 *    File:  modules/core/controllers/integrations/IntegrationsController.php
 *    URL:   /integrations                  ← no "core/" in the URL
 *    Alias: 'integrations' => 'core/integrations'
 *
 *  MY_Router intercepts the URL segment and expands it to the real
 *  module path before MX resolves the controller — no routes.php
 *  entries needed.
 *
 *  Can all application/modules classes get namespaces?
 *  ────────────────────────────────────────────────────
 *  Yes — provided MY_Loader and MY_Router are aware of the mapping.
 *  The approach (documented in .github/prompt.md) is:
 *   • Declare namespace Module\<ModuleName> in each controller/model.
 *   • Name files IntegrationsController.php (PSR-4), keeping the URL the same.
 *   • Register `Module\\` → `application/modules/` in composer.json autoload.
 *   • MY_Router aliases the PSR-4 class so MX can instantiate it.
 *  Legacy controllers (no namespace) continue to work unchanged, so
 *  the migration is fully incremental.
 */
#[AllowDynamicProperties]
class MY_Router extends MX_Router
{
    /**
     * Map a URL segment to an internal "parentModule/subModule" path.
     *
     * Add entries here instead of adding routes to application/config/routes.php.
     *
     * Format:  'url_segment' => 'parent_module/sub_module'
     *
     * Example (Integrations inside a "core" module):
     *   'integrations' => 'core/integrations'
     *
     * The URL /integrations/save will resolve to:
     *   modules/core/controllers/integrations/IntegrationsController.php::save()
     *
     * @var array<string, string>
     */
    protected array $moduleAliases = [
        // Uncomment when Integrations moves into a "core" module:
        // 'integrations' => 'core/integrations',
    ];

    /**
     * Locate a module controller.
     *
     * Extends MX_Router::locate() with two additions applied before the
     * standard MX resolution:
     *
     *  a) Module alias expansion — rewrites segments when the first segment
     *     matches a key in $moduleAliases (no routes.php required).
     *
     *  b) PSR-4 controller name detection — when a module has
     *     `<Module>Controller.php` but not `<Module>.php`, class_alias()
     *     the PSR-4 class to the legacy name so MX can load it.
     *
     * @param  array $segments URI segments
     * @return array|null      Remaining segments after consuming module/directory
     */
    public function locate($segments)
    {
        $ext = $this->config->item('controller_suffix') . EXT;

        // ── a) Module alias expansion ──────────────────────────────────────
        if (isset($segments[0]) && isset($this->moduleAliases[$segments[0]])) {
            $alias      = $this->moduleAliases[$segments[0]];
            $aliasParts = explode('/', $alias);

            // Replace the public segment with the real parent/sub path.
            // e.g. ['integrations', 'save'] → ['core', 'integrations', 'save']
            $segments = array_merge($aliasParts, array_slice($segments, 1));
        }

        // ── b) PSR-4 controller name aliasing ─────────────────────────────
        if (isset($segments[0])) {
            $this->aliasPsr4Controller($segments[0], $ext);

            // Also handle sub-module PSR-4 names (e.g. core → integrations controller)
            if (isset($segments[1])) {
                $this->aliasPsr4Controller($segments[1], $ext, $segments[0]);
            }
        }

        return parent::locate($segments);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Protected helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * If the module/sub-module has a PSR-4 style controller file
     * (`<Name>Controller.php`) but NOT a legacy file (`<Name>.php`),
     * register a class_alias so MX can find it by the legacy class name.
     *
     * @param  string      $name       Module or sub-module segment
     * @param  string      $ext        Controller file extension (e.g. '.php')
     * @param  string|null $parentName Parent module name (for sub-module lookup)
     */
    protected function aliasPsr4Controller(string $name, string $ext, ?string $parentName = null): void
    {
        foreach (Modules::$locations as $location => $offset) {
            $base = $parentName
                ? $location . $parentName . '/controllers/' . $name . '/'
                : $location . $name . '/controllers/';

            if ( ! is_dir($base)) {
                continue;
            }

            $psr4Name   = ucfirst($name) . 'Controller';
            $legacyName = ucfirst($name);
            $psr4File   = $base . $psr4Name . $ext;
            $legacyFile = $base . $legacyName . $ext;

            if (is_file($psr4File) && ! is_file($legacyFile)) {
                if ( ! class_exists($legacyName, false) && class_exists($psr4Name, false)) {
                    class_alias($psr4Name, $legacyName);
                }
            }
        }
    }
}


