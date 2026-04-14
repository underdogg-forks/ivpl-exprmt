<?php

use App\Contracts\IntegrationProviderInterface;
use App\Services\Integrations\IntegrationProviderFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntegrationProviderFactoryTest extends TestCase
{
    /**
     * Arrange: a provider factory callable is registered.
     * Act: make() is called for that provider.
     * Assert: the provider instance is returned.
     */
    #[Test]
    public function it_resolves_a_registered_provider(): void
    {
        $provider = $this->createMock(IntegrationProviderInterface::class);

        $factory = new IntegrationProviderFactory();
        $factory->register('letspeppol', fn () => $provider);

        $this->assertSame($provider, $factory->make('letspeppol'));
    }

    /**
     * Arrange: no providers registered.
     * Act: make() is called for an unknown name.
     * Assert: InvalidArgumentException is thrown.
     */
    #[Test]
    public function it_throws_for_unregistered_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Integration provider 'storecove' is not registered.");

        (new IntegrationProviderFactory())->make('storecove');
    }

    /**
     * Arrange: provider is registered.
     * Act: has() is called.
     * Assert: true for registered name, false for unknown name.
     */
    #[Test]
    public function it_reports_whether_a_provider_is_registered(): void
    {
        $factory = new IntegrationProviderFactory();
        $factory->register('stripe', fn () => $this->createMock(IntegrationProviderInterface::class));

        $this->assertTrue($factory->has('stripe'));
        $this->assertFalse($factory->has('paypal'));
    }

    /**
     * Arrange: two providers registered.
     * Act: each is resolved separately.
     * Assert: factory callable is invoked lazily (once per make() call).
     */
    #[Test]
    public function it_invokes_factory_callable_lazily(): void
    {
        $callCount = 0;

        $factory = new IntegrationProviderFactory();
        $factory->register('letspeppol', function () use (&$callCount) {
            $callCount++;

            return $this->createMock(IntegrationProviderInterface::class);
        });

        $this->assertSame(0, $callCount, 'Factory must not be called before make()');

        $factory->make('letspeppol');
        $factory->make('letspeppol');

        $this->assertSame(2, $callCount, 'Factory is invoked on every make() call');
    }

    /**
     * Arrange: register() is called.
     * Act: result is used for chaining.
     * Assert: register() returns the same factory instance.
     */
    #[Test]
    public function it_supports_fluent_chaining_of_register(): void
    {
        $factory = new IntegrationProviderFactory();

        $result = $factory->register('letspeppol', fn () => $this->createMock(IntegrationProviderInterface::class));

        $this->assertSame($factory, $result);
    }
}
