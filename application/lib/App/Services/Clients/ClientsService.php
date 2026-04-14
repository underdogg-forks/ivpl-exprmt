<?php

namespace App\Services\Clients;

use App\Contracts\IntegrationRepositoryInterface;
use App\Services\Integrations\IntegrationProviderFactory;

/**
 * Encapsulates client-related business logic that depends on external integrations.
 *
 * Keeping this logic in a dedicated service instead of the CI controller makes
 * it independently testable and keeps controllers thin.
 */
class ClientsService
{
    public function __construct(
        private IntegrationProviderFactory $providerFactory,
        private IntegrationRepositoryInterface $integrations,
    ) {
    }

    /**
     * Validate a Peppol participant ID via the registered LetsPeppol provider and log the outcome.
     *
     * Returns false immediately when LetsPeppol is not registered as a provider.
     *
     * @param  string $peppolId  Raw Peppol participant identifier (e.g. "0088:1234567890").
     * @return bool              True when the participant is valid.
     */
    public function validatePeppolId(string $peppolId): bool
    {
        if ( ! $this->providerFactory->has('letspeppol')) {
            return false;
        }

        $isValid = $this->providerFactory->make('letspeppol')->validateParticipant($peppolId);

        $this->integrations->log(
            'letspeppol',
            'participants.validate',
            $isValid ? 'success' : 'failed',
            ['peppol_id' => $peppolId],
        );

        return $isValid;
    }
}
