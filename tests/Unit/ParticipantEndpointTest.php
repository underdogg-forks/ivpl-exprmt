<?php

use App\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use App\Gateways\LetsPeppol\LetsPeppolGatewayClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class ParticipantEndpointTest extends TestCase
{
    /**
     * Arrange: ParticipantEndpoint with fake HTTP returning 200 OK.
     * Act: validatePeppolId is called.
     * Assert: Returns true for valid participant.
     */
    #[Test]
    public function it_returns_true_when_participant_is_valid(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);

        $gateway = new LetsPeppolGatewayClient(
            'https://api.letspeppol.test',
            [],
            $http
        );

        $endpoint = new ParticipantEndpoint($gateway);

        $result = $endpoint->validatePeppolId('0088:123456789');

        $this->assertTrue($result);
        $http->assertRequestMade('GET', 'https://api.letspeppol.test/api/participants/validate');

        $lastRequest = end($http->requests);
        $this->assertSame('0088:123456789', $lastRequest['options']['query']['peppol_id']);
    }

    /**
     * Arrange: ParticipantEndpoint with fake HTTP returning 404.
     * Act: validatePeppolId is called.
     * Assert: Returns false for invalid/not found participant.
     */
    #[Test]
    public function it_returns_false_when_participant_is_not_found(): void
    {
        $http = new FakeLetsPeppolHttpClient(404);

        $gateway  = new LetsPeppolGatewayClient('https://api.letspeppol.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $result = $endpoint->validatePeppolId('0088:999999999');

        $this->assertFalse($result);
    }

    /**
     * Arrange: ParticipantEndpoint with fake HTTP that throws exception.
     * Act: validatePeppolId is called.
     * Assert: Exception is caught and false is returned.
     */
    #[Test]
    public function it_handles_exceptions_gracefully(): void
    {
        $http = new FakeLetsPeppolHttpClient(200, new \RuntimeException('Network error'));

        $gateway  = new LetsPeppolGatewayClient('https://api.letspeppol.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $result = $endpoint->validatePeppolId('0088:123456789');

        $this->assertFalse($result);
    }

    /**
     * Arrange: Valid and invalid participant fixtures.
     * Act: Validate fixture structure.
     * Assert: Fixtures match expected format.
     */
    #[Test]
    public function it_validates_fixture_format(): void
    {
        // Valid participant fixture
        $validFixture = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/participant_valid.json');
        $this->assertNotFalse($validFixture);

        $validData = json_decode($validFixture, true);
        $this->assertIsArray($validData);
        $this->assertTrue($validData['valid']);
        $this->assertArrayHasKey('participant', $validData);
        $this->assertSame('0088:123456789', $validData['participant']['peppol_id']);

        // Invalid participant fixture
        $invalidFixture = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/participant_invalid.json');
        $this->assertNotFalse($invalidFixture);

        $invalidData = json_decode($invalidFixture, true);
        $this->assertIsArray($invalidData);
        $this->assertFalse($invalidData['valid']);
        $this->assertArrayHasKey('error', $invalidData);
    }
}
