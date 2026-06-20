<?php

namespace Tests\Unit\Einvoice;

use MerchantProviderInterface;
use MerchantProviderRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MerchantProviderRegistryTest extends TestCase
{
    private function registry(): MerchantProviderRegistry
    {
        // Registry auto-discovers providers from the filesystem on construction.
        // APPPATH is defined by the test bootstrap, pointing at application/.
        return new MerchantProviderRegistry();
    }

    public function test_all_returns_array_of_registered_providers(): void
    {
        // Arrange + Act
        $providers = $this->registry()->all();

        // Assert
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
    }

    public function test_qonto_provider_is_auto_discovered(): void
    {
        // Arrange + Act
        $providers = $this->registry()->all();

        // Assert
        $this->assertArrayHasKey('qonto', $providers);
    }

    public function test_superpdp_provider_is_auto_discovered(): void
    {
        // Arrange + Act
        $providers = $this->registry()->all();

        // Assert
        $this->assertArrayHasKey('superpdp', $providers);
    }

    public function test_all_discovered_providers_implement_interface(): void
    {
        // Arrange
        $providers = $this->registry()->all();

        // Act + Assert
        foreach ($providers as $code => $className) {
            $this->assertTrue(
                is_subclass_of($className, MerchantProviderInterface::class),
                "Provider '{$code}' ({$className}) must implement MerchantProviderInterface"
            );
        }
    }

    public function test_getProvider_returns_instance_for_known_code(): void
    {
        // Arrange
        $registry = $this->registry();

        // Act
        $provider = $registry->getProvider('qonto');

        // Assert
        $this->assertInstanceOf(MerchantProviderInterface::class, $provider);
    }

    public function test_getProvider_returns_different_instances_each_call(): void
    {
        // Arrange
        $registry = $this->registry();

        // Act
        $a = $registry->getProvider('qonto');
        $b = $registry->getProvider('qonto');

        // Assert
        $this->assertNotSame($a, $b);
    }

    public function test_getProvider_throws_for_unknown_provider(): void
    {
        // Arrange
        $registry = $this->registry();

        // Act + Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown e-invoicing provider: nonexistent');
        $registry->getProvider('nonexistent');
    }

    public function test_all_discovered_providers_have_non_empty_providerCode(): void
    {
        // Arrange
        $providers = $this->registry()->all();

        // Act + Assert
        foreach ($providers as $code => $className) {
            $this->assertNotEmpty(
                $className::providerCode(),
                "Provider class {$className} must return a non-empty providerCode()"
            );
        }
    }

    public function test_all_discovered_providers_have_non_empty_providerName(): void
    {
        // Arrange
        $providers = $this->registry()->all();

        // Act + Assert
        foreach ($providers as $code => $className) {
            $this->assertNotEmpty(
                $className::providerName(),
                "Provider class {$className} must return a non-empty providerName()"
            );
        }
    }
}
