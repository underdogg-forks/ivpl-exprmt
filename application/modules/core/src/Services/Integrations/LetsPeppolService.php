<?php

namespace Core\Services\Integrations;

use Core\Providers\LetsPeppolProvider;

/**
 * @deprecated Use Core\Providers\LetsPeppolProvider via IntegrationProviderFactory instead.
 *             This class delegates to LetsPeppolProvider to preserve backward compatibility.
 */
class LetsPeppolService extends LetsPeppolProvider
{
    public function __construct(IntegrationSettingsService $settingsService)
    {
        parent::__construct($settingsService);
    }

    /**
     * @deprecated Use validateParticipant() via IntegrationProviderInterface instead.
     */
    public function validateParticipantId(string $peppolId): bool
    {
        return $this->validateParticipant($peppolId);
    }
}
