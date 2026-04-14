<?php

namespace App\Providers;

use App\Contracts\IntegrationProviderInterface;
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
            $sanitized = str_replace(["\r", "\n"], '', $throwable->getMessage());
            log_message('error', '[' . $this->providerName . '] validateParticipant failed: ' . $sanitized);

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
            $sanitized = str_replace(["\r", "\n"], '', $throwable->getMessage());
            log_message('error', '[' . $this->providerName . '] sendInvoice failed: ' . $sanitized);

            return false;
        }
    }
}
