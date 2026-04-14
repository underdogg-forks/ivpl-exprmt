<?php

namespace Tests\Fakes;

use App\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;

/**
 * Fake for ParticipantClient that controls validation results without HTTP.
 *
 * Construct with a status-code map so each Peppol ID resolves to a known result:
 *
 *   $fake = FakeParticipantClient::alwaysValid();
 *   $fake = FakeParticipantClient::alwaysInvalid();
 *   $fake = new FakeParticipantClient(['0088:123' => true, '0088:bad' => false]);
 *
 * After exercising the system under test:
 *   $fake->assertValidated('0088:123');
 *   $fake->assertNotValidated();
 */
class FakeParticipantClient extends ParticipantClient
{
    /** @var list<string> All Peppol IDs that were validated. */
    public array $validated = [];

    /** @var array<string, bool> Expected results per Peppol ID. */
    private array $results;

    private bool $defaultResult;

    public function __construct(array $results = [], bool $defaultResult = true)
    {
        // Skip ParticipantClient::__construct — we do not need a real LetsPeppolClient.
        $this->results       = $results;
        $this->defaultResult = $defaultResult;
    }

    public static function alwaysValid(): static
    {
        return new static([], true);
    }

    public static function alwaysInvalid(): static
    {
        return new static([], false);
    }

    public function validatePeppolId(string $peppolId, ?string $accessToken = null): bool
    {
        $this->validated[] = $peppolId;

        return $this->results[$peppolId] ?? $this->defaultResult;
    }

    // ── PHPUnit assertion helpers ──────────────────────────────────────────────

    public function assertValidated(string $peppolId): void
    {
        if ( ! in_array($peppolId, $this->validated, true)) {
            throw new \RuntimeException(
                "Expected Peppol ID [{$peppolId}] to be validated but it was not. " .
                'Validated: ' . json_encode($this->validated)
            );
        }
    }

    public function assertNotValidated(): void
    {
        if (count($this->validated) > 0) {
            throw new \RuntimeException(
                'Expected no validation calls but got: ' . json_encode($this->validated)
            );
        }
    }
}
