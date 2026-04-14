<?php

use App\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ParticipantClientTest extends TestCase
{
    /**
     * Arrange: mocked 200 response.
     * Act: validatePeppolId is called.
     * Assert: true is returned.
     */
    #[Test]
    public function it_returns_true_for_successful_validation_response()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willReturn($response);

        $participantClient = new ParticipantClient($client);

        $this->assertTrue($participantClient->validatePeppolId('0088:123456789'));
    }

    /**
     * Arrange: mocked 404 response.
     * Act: validatePeppolId is called.
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_for_non_success_validation_response()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willReturn($response);

        $participantClient = new ParticipantClient($client);

        $this->assertFalse($participantClient->validatePeppolId('0088:123456789'));
    }

    /**
     * Arrange: mocked client throwing exception.
     * Act: validatePeppolId is called.
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_when_validation_api_throws_exception()
    {
        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willThrowException(new RuntimeException('boom'));

        $participantClient = new ParticipantClient($client);

        $this->assertFalse($participantClient->validatePeppolId('0088:123456789'));
    }
}
