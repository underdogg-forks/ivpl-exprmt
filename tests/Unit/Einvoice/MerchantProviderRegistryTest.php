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
        return new MerchantProviderRegistry();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_discovers_at_least_one_provider_on_construction(): void
    {
        /* Arrange */

        /* Act */
        $providers = $this->registry()->all();

        /* Assert */
        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_auto_discovers_the_qonto_provider(): void
    {
        /* Arrange */

        /* Act */
        $providers = $this->registry()->all();

        /* Assert */
        $this->assertArrayHasKey('qonto', $providers);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_auto_discovers_the_superpdp_provider(): void
    {
        /* Arrange */

        /* Act */
        $providers = $this->registry()->all();

        /* Assert */
        $this->assertArrayHasKey('superpdp', $providers);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_auto_discovers_the_letspeppol_provider(): void
    {
        /* Arrange */

        /* Act */
        $providers = $this->registry()->all();

        /* Assert */
        $this->assertArrayHasKey('letspeppol', $providers);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_registers_only_classes_that_implement_the_provider_interface(): void
    {
        /* Arrange */
        $providers = $this->registry()->all();

        /* Act + Assert */
        foreach ($providers as $code => $className) {
            $this->assertTrue(
                is_subclass_of($className, MerchantProviderInterface::class),
                "Provider '{$code}' ({$className}) must implement MerchantProviderInterface"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_provider_instance_for_a_known_code(): void
    {
        /* Arrange */
        $registry = $this->registry();

        /* Act */
        $provider = $registry->getProvider('qonto');

        /* Assert */
        $this->assertInstanceOf(MerchantProviderInterface::class, $provider);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_new_instance_on_each_get_provider_call(): void
    {
        /* Arrange */
        $registry = $this->registry();

        /* Act */
        $a = $registry->getProvider('qonto');
        $b = $registry->getProvider('qonto');

        /* Assert */
        $this->assertNotSame($a, $b);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_throws_for_an_unknown_provider_code(): void
    {
        /* Arrange */
        $registry = $this->registry();

        /* Act */
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown e-invoicing provider: nonexistent');

        /* Assert */
        $registry->getProvider('nonexistent');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_every_discovered_provider_to_return_a_non_empty_code(): void
    {
        /* Arrange */
        $providers = $this->registry()->all();

        /* Act + Assert */
        foreach ($providers as $code => $className) {
            $this->assertNotEmpty(
                $className::providerCode(),
                "Provider class {$className} must return a non-empty providerCode()"
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_every_discovered_provider_to_return_a_non_empty_name(): void
    {
        /* Arrange */
        $providers = $this->registry()->all();

        /* Act + Assert */
        foreach ($providers as $code => $className) {
            $this->assertNotEmpty(
                $className::providerName(),
                "Provider class {$className} must return a non-empty providerName()"
            );
        }
    }
}
