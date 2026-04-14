<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/bootstrap/autoload.php')) {
    require_once dirname(__DIR__) . '/bootstrap/autoload.php';
}

/*
 * CodeIgniter / Modular Extensions stubs for unit testing.
 *
 * These lightweight stubs allow unit tests to mock or extend CI base classes
 * without bootstrapping the full CodeIgniter framework.  They are only loaded
 * when the real classes are not already defined (i.e. outside a CI request).
 */
if ( ! class_exists('CI_Model')) {
    class CI_Model {}
}

if ( ! class_exists('Mdl_integrations')) {
    // Load the real class if BASEPATH is defined (CI context), otherwise use stub.
    if (defined('BASEPATH')) {
        require_once dirname(__DIR__) . '/application/modules/integrations/models/Mdl_integrations.php';
    } else {
        #[AllowDynamicProperties]
        class Mdl_integrations extends CI_Model
        {
            public function ensureProvider(string $provider, string $name): int { return 0; }
            public function saveEncryptedSettings(string $provider, array $settings, array $encryptedKeys, Crypt $crypt): void {}
            public function settings(string $provider, Crypt $crypt): array { return []; }
            public function saveToken(string $provider, string $token, ?int $expiresAt = null): void {}
            public function activeToken(string $provider): ?string { return null; }
            public function log(string $provider, string $action, string $status, array $context = []): void {}
        }
    }
}

if ( ! class_exists('Crypt')) {
    if (defined('BASEPATH') && file_exists(dirname(__DIR__) . '/application/libraries/Crypt.php')) {
        require_once dirname(__DIR__) . '/application/libraries/Crypt.php';
    } else {
        #[AllowDynamicProperties]
        class Crypt
        {
            public function encode(string $value): string { return base64_encode($value); }
            public function decode(string $value): string { return base64_decode($value); }
        }
    }
}

