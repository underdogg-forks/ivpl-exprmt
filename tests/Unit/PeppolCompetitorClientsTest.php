<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Adapters\Pagero\Endpoints\ParticipantClient as PageroParticipantClient;
use Core\Adapters\Pagero\PageroClient;
use Core\Adapters\Sovos\Endpoints\ParticipantClient as SovosParticipantClient;
use Core\Adapters\Sovos\SovosClient;
use Core\Adapters\StoreCove\Endpoints\ParticipantClient as StoreCoveParticipantClient;
use Core\Adapters\StoreCove\StoreCoveClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class PeppolCompetitorClientsTest extends TestCase
{
    #[Test]
    public function it_validates_participants_using_storecove_client(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/StoreCove/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);

        $client = new StoreCoveClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate'], ['access_token' => 'token-sc']);

        $result = (new StoreCoveParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertTrue($result);
        $this->assertSame('Bearer token-sc', $http->requests[0]['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_validates_participants_using_pagero_client(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/Pagero/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);

        $client = new PageroClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate'], ['access_token' => 'token-pa']);

        $result = (new PageroParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertTrue($result);
        $this->assertSame('Bearer token-pa', $http->requests[0]['options']['headers']['Authorization']);
    }

    #[Test]
    public function it_validates_participants_using_sovos_client(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/Sovos/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);

        $client = new SovosClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate'], ['access_token' => 'token-so']);

        $result = (new SovosParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertTrue($result);
        $this->assertSame('Bearer token-so', $http->requests[0]['options']['headers']['Authorization']);
    }
}
