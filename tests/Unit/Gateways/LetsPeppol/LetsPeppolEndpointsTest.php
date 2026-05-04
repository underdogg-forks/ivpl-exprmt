<?php

namespace Tests\Unit\Gateways\LetsPeppol;

use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use Core\Gateways\LetsPeppol\PeppolApiClient;
use Core\Gateways\LetsPeppol\Transformers\ApiResponseTransformer;
use ErrorException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LetsPeppolEndpointsTest extends TestCase
{
    #[Test]
    public function it_handles_error_path_from_api_client(): void
    {
        $response = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/api_error.json'), true);
        $client = new FakePeppolApiClient(['participants.validate' => $response], new ErrorException('boom'));
        $endpoint = new ParticipantEndpoint($client, new ApiResponseTransformer());

        $this->expectException(ErrorException::class);
        $endpoint->validate('0088:123');
    }

    #[Test]
    public function it_interacts_with_api_client_and_transforms_response(): void
    {
        $response = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/participant_validate_success.json'), true);
        $client = new FakePeppolApiClient(['participants.validate' => $response]);
        $endpoint = new ParticipantEndpoint($client, new ApiResponseTransformer());

        $dto = $endpoint->validate('0088:123456789');

        $this->assertSame('participants.validate', $client->lastEndpoint);
        $this->assertSame(['peppol_id' => '0088:123456789'], $client->lastQuery);
        $this->assertSame('ok', $dto->getStatus());
        $this->assertSame('participant-1', $dto->getId());
    }
}

class FakePeppolApiClient extends PeppolApiClient
{
    public string $lastEndpoint = '';
    public array $lastQuery = [];

    public function __construct(private array $responses, private ?\Throwable $throwable = null)
    {
        parent::__construct('https://fake.test');
    }

    public function authorize(): void
    {
    }

    public function get(string $endpointKey, array $query = []): array
    {
        if ($this->throwable) {
            throw $this->throwable;
        }

        $this->lastEndpoint = $endpointKey;
        $this->lastQuery = $query;

        return $this->responses[$endpointKey] ?? [];
    }

    public function post(string $endpointKey, array $payload = []): array
    {
        if ($this->throwable) {
            throw $this->throwable;
        }

        $this->lastEndpoint = $endpointKey;
        $this->lastQuery = $payload;

        return $this->responses[$endpointKey] ?? [];
    }
}
