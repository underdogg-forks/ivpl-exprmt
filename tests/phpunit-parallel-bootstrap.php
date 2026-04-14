<?php

/*
 * PSR-7 / PSR-18 / GuzzleHttp interface stubs.
 *
 * These must come first (before global class declarations) to avoid PHP
 * "Cannot mix namespace declarations" errors.  They allow PHPUnit to generate
 * mocks for PSR interfaces when vendor packages are not fully installed.
 */
namespace Psr\Http\Message {
    if ( ! interface_exists(\Psr\Http\Message\MessageInterface::class)) {
        interface MessageInterface {}
    }
    if ( ! interface_exists(\Psr\Http\Message\RequestInterface::class)) {
        interface RequestInterface extends MessageInterface {}
    }
    if ( ! interface_exists(\Psr\Http\Message\ResponseInterface::class)) {
        interface ResponseInterface extends MessageInterface
        {
            /** Returns the HTTP status code. */
            public function getStatusCode(): int;
        }
    }
    if ( ! interface_exists(\Psr\Http\Message\StreamInterface::class)) {
        interface StreamInterface {}
    }
}

namespace GuzzleHttp {
    if ( ! interface_exists(\GuzzleHttp\ClientInterface::class)) {
        interface ClientInterface
        {
            /** Sends an HTTP request. */
            public function request(string $method, $uri, array $options = []): \Psr\Http\Message\ResponseInterface;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/vendor/autoload.php';

    if (file_exists(dirname(__DIR__) . '/bootstrap/autoload.php')) {
        require_once dirname(__DIR__) . '/bootstrap/autoload.php';
    }

    // Register Tests\ namespace so Fakes are autoloaded without requiring --dev install.
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Tests\\')) {
            $path = dirname(__DIR__) . '/tests/' . str_replace('\\', '/', substr($class, strlen('Tests\\'))) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        }
    });

    /*
     * CodeIgniter global function stubs.
     *
     * These stubs provide no-op implementations of CI helper functions used
     * by App\ classes so unit tests can run without a full CI3 bootstrap.
     */
    if ( ! function_exists('log_message')) {
        /** No-op stub: CI's log_message() is not available in unit tests. */
        function log_message(string $level, string $message): void {}
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
            class Mdl_integrations extends CI_Model implements \App\Contracts\IntegrationRepositoryInterface
            {
                /** Ensures a provider row exists and returns its ID. */
                public function ensureProvider(string $provider, string $name): int { return 0; }

                /** Persists encrypted integration settings. */
                public function saveEncryptedSettings(string $provider, array $settings, array $encryptedKeys, \App\Contracts\CryptInterface $crypt): void {}

                /** Returns decrypted integration settings keyed by setting key. */
                public function settings(string $provider, \App\Contracts\CryptInterface $crypt): array { return []; }

                /** Persists an OAuth access token for the given provider. */
                public function saveToken(string $provider, string $token, ?int $expiresAt = null): void {}

                /** Returns the currently active (non-expired) OAuth token, or null. */
                public function activeToken(string $provider): ?string { return null; }

                /** Appends an audit log entry for a provider action. */
                public function log(string $provider, string $action, string $status, array $context = []): void {}
            }
        }
    }

    if ( ! class_exists('Crypt')) {
        if (defined('BASEPATH') && file_exists(dirname(__DIR__) . '/application/libraries/Crypt.php')) {
            require_once dirname(__DIR__) . '/application/libraries/Crypt.php';
        } else {
            #[AllowDynamicProperties]
            class Crypt implements \App\Contracts\CryptInterface
            {
                public function encode(string $value): string { return base64_encode($value); }
                public function decode(string $value): string { return base64_decode($value); }
            }
        }
    }
}

