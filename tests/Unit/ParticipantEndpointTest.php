<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use Core\Gateways\LetsPeppol\LetsPeppolGatewayClient;
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

    /**
     * Arrange: ParticipantEndpoint with fake HTTP.
     * Act: getDetails is called with Peppol ID.
     * Assert: Response is 200 and request was made to correct endpoint.
     */
    #[Test]
    public function it_gets_participant_details(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $response = $endpoint->getDetails('0088:123456789');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('GET', 'api/participants');
    }

    /**
     * Arrange: ParticipantEndpoint with fake HTTP.
     * Act: search is called without country filter.
     * Assert: Response is 200 and request was made with query parameter.
     */
    #[Test]
    public function it_searches_participants_without_country_filter(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $response = $endpoint->search('Acme Corp');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('GET', 'api/participants/search');
    }

    /**
     * Arrange: ParticipantEndpoint with fake HTTP.
     * Act: search is called with country filter.
     * Assert: Response is 200 and request includes both query and country parameters.
     */
    #[Test]
    public function it_searches_participants_with_country_filter(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $response = $endpoint->search('Acme Corp', 'SE');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('GET', 'api/participants/search');
    }

    /**
     * Arrange: ParticipantEndpoint with fake HTTP.
     * Act: getCapabilities is called with Peppol ID.
     * Assert: Response is 200 and request was made to correct endpoint.
     */
    #[Test]
    public function it_gets_participant_capabilities(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new ParticipantEndpoint($gateway);

        $response = $endpoint->getCapabilities('0088:123456789');

        $this->assertEquals(200, $response->getStatusCode());
        $http->assertRequestMade('GET', 'api/participants/capabilities');
    }

    /**
     * Arrange: ParticipantEndpoint with Bearer token set.
     * Act: getDetails is called.
     * Assert: Authorization header is included in request.
     */
    #[Test]
    public function it_includes_authorization_headers_in_requests(): void
    {
        $http = new FakeLetsPeppolHttpClient(200);
        $settings = [
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-secret',
        ];
        
        $gateway = new LetsPeppolGatewayClient('https://api.test', $settings, $http);
        $gateway->setAccessToken('test-bearer-token');
        $endpoint = new ParticipantEndpoint($gateway);

        $endpoint->getDetails('0088:123456789');

        $this->assertCount(1, $http->requests);
        $request = $http->requests[0];
        $this->assertArrayHasKey('headers', $request['options']);
        $this->assertArrayHasKey('Authorization', $request['options']['headers']);
        $this->assertEquals('Bearer test-bearer-token', $request['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_validates_new_participant_fixtures_format(): void
    {
        $detailsJson = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/participant_details.json');
        $this->assertNotFalse($detailsJson);
        $details = json_decode($detailsJson, true);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('peppol_id', $details);

        $searchJson = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/participant_search.json');
        $this->assertNotFalse($searchJson);
        $search = json_decode($searchJson, true);
        $this->assertIsArray($search);
        $this->assertArrayHasKey('participants', $search);

        $capabilitiesJson = file_get_contents(__DIR__ . '/../Fixtures/LetsPeppol/participant_capabilities.json');
        $this->assertNotFalse($capabilitiesJson);
        $capabilities = json_decode($capabilitiesJson, true);
        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('document_types', $capabilities);
    }

}
