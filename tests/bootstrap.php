<?php

define('CI_TESTING', true);

$basePath = dirname(__DIR__);

// Full kernel setup: constants, env helpers, dotenv
require_once $basePath . '/bootstrap/kernel.php';
require_once $basePath . '/tests/Integration/bootstrap.php';

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF']    = '/index.php';

// -----------------------------------------------------------------
// Mock-based CI3 layer for lightweight unit tests.
//
// The kernel above defines BASEPATH/APPPATH/etc. and loads helpers but
// intentionally does NOT boot the full CI3 framework (CI_TESTING guard).
// The mocks below provide a minimal CI super-object so that model unit
// tests can run without a database or web server.
// -----------------------------------------------------------------

// CI3 base model class (guards against BASEPATH are already satisfied)
if (! class_exists('CI_Model', false)) {
    require_once BASEPATH . 'core/Model.php';
}

// Mock support layer
require_once __DIR__ . '/Support/CI/MockDB.php';
require_once __DIR__ . '/Support/CI/MockSession.php';
require_once __DIR__ . '/Support/CI/MockSettings.php';
require_once __DIR__ . '/Support/CI/MockLibrary.php';
require_once __DIR__ . '/Support/CI/MockLoader.php';
require_once __DIR__ . '/Support/CI/CITestDouble.php';

// Global get_instance() — makes CI_Model::__get() resolve to the mock
if (! function_exists('get_instance')) {
    function &get_instance(): CITestDouble
    {
        return CITestDouble::ref();
    }
}

// Initialise the singleton so models can be constructed in tests immediately
CITestDouble::instance();

// Silence CI3 log calls that may surface during model loading
if (! function_exists('log_message')) {
    function log_message(string $level, string $message): void {}
}

// Return translation keys rather than locale strings so tests stay locale-agnostic
if (! function_exists('trans')) {
    function trans(string $line, ?string $id = '', mixed $default = null): string
    {
        return $default ?? $line;
    }
}

// get_setting() delegates to the mock settings store
if (! function_exists('get_setting')) {
    function get_setting(string $key): mixed
    {
        return get_instance()->mdl_settings->setting($key);
    }
}

// Application model hierarchy (class guards prevent double-loading)
if (! class_exists('MY_Model', false)) {
    require_once APPPATH . 'core/MY_Model.php';
}
if (! class_exists('Form_Validation_Model', false)) {
    require_once APPPATH . 'core/Form_Validation_Model.php';
}
if (! class_exists('Response_Model', false)) {
    require_once APPPATH . 'core/Response_Model.php';
}

// eInvoice module — interface must be loaded before any provider class
if (! interface_exists('MerchantProviderInterface', false)) {
    require_once APPPATH . 'modules/einvoice/libraries/MerchantProviderInterface.php';
}
// Flat providers
foreach (glob(APPPATH . 'modules/einvoice/libraries/providers/*Provider.php') as $_providerFile) {
    $class = basename($_providerFile, '.php');
    if (! class_exists($class, false)) {
        require_once $_providerFile;
    }
}
// Nested providers (e.g. LetsPeppol/): load all supporting files first, then the Provider
foreach (glob(APPPATH . 'modules/einvoice/libraries/providers/*/*Provider.php') as $_providerFile) {
    $_dir = dirname($_providerFile);
    // API client and other peer files
    foreach (glob($_dir . '/*.php') as $_peerFile) {
        if ($_peerFile !== $_providerFile) {
            require_once $_peerFile;
        }
    }
    // Endpoint classes
    foreach (glob($_dir . '/Endpoints/*.php') as $_endpointFile) {
        require_once $_endpointFile;
    }
    $class = basename($_providerFile, '.php');
    if (! class_exists($class, false)) {
        require_once $_providerFile;
    }
}
// Also load Fake helpers used by tests (namespace-autoloaded, but listed here for completeness)
unset($_providerFile, $_dir, $_peerFile, $_endpointFile, $class);
if (! class_exists('MerchantProviderRegistry', false)) {
    require_once APPPATH . 'modules/einvoice/libraries/MerchantProviderRegistry.php';
}
if (! class_exists('MerchantClient', false)) {
    require_once APPPATH . 'modules/einvoice/libraries/MerchantClient.php';
}
