<?php

namespace Core\Providers;

use Core\Contracts\IntegrationProviderInterface;
use Throwable;

/**
 * Decorator that adds uniform exception safety to any IntegrationProviderInterface.
 *
 * Wrap a provider in this decorator (automatically done by IntegrationProviderFactory::make())
 * to ensure that any uncaught exception — network failure, OAuth token error, etc. —
 * is caught, sanitized and logged rather than propagating as a 500 error.
 *
 * This means every current and future provider (LetsPeppol, StoreCove, Stripe, PayPal, …)
 * gets exception safety for free, without duplicating try/catch blocks in each provider.
 *
 * Usage (handled automatically by IntegrationProviderFactory):
 *
 *   $provider = new ExceptionHandlingDecorator(new LetsPeppolProvider($settingsService), 'letspeppol');
 *   $provider->validateParticipant('0088:123');  // never throws
 */
class ExceptionHandlingDecorator implements IntegrationProviderInterface
{
    public function __construct(
        private IntegrationProviderInterface $inner,
        private string $providerName = 'unknown',
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * Returns false and logs a sanitized error message on any exception.
     */
    public function validateParticipant(string $participantId): bool
    {
        try {
            return $this->inner->validateParticipant($participantId);
        } catch (Throwable $throwable) {
            log_message('error', '[' . $this->providerName . '] validateParticipant failed: ' . $this->sanitize($throwable->getMessage()));

            return false;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Returns false and logs a sanitized error message on any exception.
     */
    public function sendInvoice(array $payload): bool
    {
        try {
            return $this->inner->sendInvoice($payload);
        } catch (Throwable $throwable) {
            log_message('error', '[' . $this->providerName . '] sendInvoice failed: ' . $this->sanitize($throwable->getMessage()));

            return false;
        }
    }

    /**
     * Strip CR/LF from a string to prevent log injection attacks.
     *
     * Mirrors the sanitize_for_logging() CI helper for use in namespaced Core\ classes
     * where CI global helpers may not be loaded.
     */
    private function sanitize(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
