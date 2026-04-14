<?php

namespace App\Services\Integrations;

use App\Contracts\IntegrationProviderInterface;
use InvalidArgumentException;

/**
 * Resolves integration providers by name.
 *
 * Providers are registered as lazy factories (callables) so their dependencies
 * are only resolved when the provider is actually needed.  New providers
 * (StoreCove, Stripe, PayPal, …) can be plugged in without modifying existing code.
 *
 * Usage:
 *
 *   $factory = new IntegrationProviderFactory();
 *   $factory->register('letspeppol', fn () => new LetsPeppolProvider($settingsService));
 *   $factory->register('stripe',     fn () => new StripeProvider($settingsService));
 *
 *   $provider = $factory->make('letspeppol');
 */
class IntegrationProviderFactory
{
    /** @var array<string, callable(): IntegrationProviderInterface> */
    private array $providers = [];

    /**
     * Register a named provider factory callable.
     *
     * @param  string   $name     Unique provider key (e.g. 'letspeppol').
     * @param  callable $factory  Zero-argument callable that returns an IntegrationProviderInterface.
     * @return $this
     */
    public function register(string $name, callable $factory): static
    {
        $this->providers[$name] = $factory;

        return $this;
    }

    /**
     * Resolve and return a provider instance by name.
     *
     * @throws InvalidArgumentException When the provider is not registered.
     */
    public function make(string $name): IntegrationProviderInterface
    {
        if ( ! $this->has($name)) {
            throw new InvalidArgumentException("Integration provider '{$name}' is not registered.");
        }

        return ($this->providers[$name])();
    }

    /**
     * Check whether a provider is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}
