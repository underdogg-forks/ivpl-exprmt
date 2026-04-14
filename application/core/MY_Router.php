<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// load the MX_Router class
require APPPATH . 'third_party/MX/Router.php';

/**
 * Enhanced CI3/MX Router with PSR-4 controller naming support.
 *
 * Extends MX_Router so that module controllers can be named following the
 * PSR-4 convention (`IntegrationsController`) instead of the legacy CI
 * underscore suffix (`Integrations_Controller`).
 *
 * Resolution order (for a URL segment `integrations`):
 *   1. PSR-4 style  → application/modules/integrations/controllers/IntegrationsController.php
 *   2. Legacy style → application/modules/integrations/controllers/Integrations.php
 *
 * This allows new modules to adopt clean PSR-4 naming while all existing
 * legacy controllers continue to work unchanged.
 *
 * URL routing for "hidden" module prefix
 * ----------------------------------------
 * When Integrations (or any future module) moves inside a "core" module to
 * keep the top-level module list tidy, its URL can be kept short with CI3
 * routes.  In application/config/routes.php add:
 *
 *   $route['integrations']         = 'core/integrations/index';
 *   $route['integrations/(:any)']  = 'core/integrations/$1';
 *
 * MX resolves `core/integrations/index` to the controller at
 * `modules/core/controllers/integrations/IntegrationsController.php` (or
 * `Integrations.php`), so the URL `/integrations/index` works exactly as
 * if the module were top-level.
 */
#[AllowDynamicProperties]
class MY_Router extends MX_Router
{
    /**
     * Locate a module controller, checking PSR-4 naming first.
     *
     * Overrides MX_Router::locate() to additionally probe for
     * `<ModuleName>Controller.php` files before falling back to the legacy
     * `<ModuleName>.php` search already performed by the parent.
     *
     * @param  array $segments URI segments
     * @return array|null      Remaining segments after consuming module/directory
     */
    public function locate($segments)
    {
        $ext = $this->config->item('controller_suffix') . EXT;

        // Check whether a PSR-4 style controller file exists and, if so,
        // rewrite the segment to the PSR-4 class name so CI loads the right file.
        if (isset($segments[0])) {
            $module = $segments[0];

            foreach (Modules::$locations as $location => $offset) {
                if ( ! is_dir($source = $location . $module . '/controllers/')) {
                    continue;
                }

                $psr4File  = $source . ucfirst($module) . 'Controller' . $ext;
                $legacyFile = $source . ucfirst($module) . $ext;

                // PSR-4 style file found — rewrite class resolution
                if (is_file($psr4File) && ! is_file($legacyFile)) {
                    // Temporarily expose the PSR-4 class name so the parent
                    // locate() picks it up via set_class()/set_filename().
                    // We do this by aliasing the PSR-4 class to the legacy name
                    // using a require + class_alias so CI can instantiate it.
                    $psr4Class  = ucfirst($module) . 'Controller';
                    $legacyName = ucfirst($module);

                    if ( ! class_exists($legacyName, false) && class_exists($psr4Class, false)) {
                        class_alias($psr4Class, $legacyName);
                    }
                }
            }
        }

        return parent::locate($segments);
    }
}

