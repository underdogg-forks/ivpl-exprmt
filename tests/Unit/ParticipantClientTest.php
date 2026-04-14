<?php

use App\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ParticipantClientTest extends TestCase
{
    public function testValidatePeppolIdReturnsTrueOn2xx()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willReturn($response);

        $participantClient = new ParticipantClient($client);

        $this->assertTrue($participantClient->validatePeppolId('0088:123456789'));
    }

    public function testValidatePeppolIdReturnsFalseOnNon2xx()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);

        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willReturn($response);

        $participantClient = new ParticipantClient($client);

        $this->assertFalse($participantClient->validatePeppolId('0088:123456789'));
    }

    public function testValidatePeppolIdReturnsFalseWhenApiThrows()
    {
        $client = $this->createMock(LetsPeppolClient::class);
        $client->method('request')->willThrowException(new RuntimeException('boom'));

        $participantClient = new ParticipantClient($client);

        $this->assertFalse($participantClient->validatePeppolId('0088:123456789'));
    }
}
