<?php

use App\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use App\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;
use Tests\Fakes\FakeParticipantClient;

class ParticipantClientTest extends TestCase
{
    /**
     * Arrange: FakeLetsPeppolHttpClient returning 200.
     * Act: validatePeppolId is called.
     * Assert: true is returned and a GET request was made.
     */
    #[Test]
    public function it_returns_true_for_successful_validation_response(): void
    {
        $http   = new FakeLetsPeppolHttpClient(200);
        $client = new LetsPeppolClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate']);

        $result = (new ParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertTrue($result);
        $http->assertRequestMade('GET', 'api/participants/validate');
    }

    /**
     * Arrange: FakeLetsPeppolHttpClient returning 404.
     * Act: validatePeppolId is called.
     * Assert: false is returned.
     */
    #[Test]
    public function it_returns_false_for_non_success_validation_response(): void
    {
        $http   = new FakeLetsPeppolHttpClient(404);
        $client = new LetsPeppolClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate']);

        $result = (new ParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertFalse($result);
    }

    /**
     * Arrange: FakeLetsPeppolHttpClient configured to throw.
     * Act: validatePeppolId is called.
     * Assert: false is returned (exception is swallowed).
     */
    #[Test]
    public function it_returns_false_when_validation_api_throws_exception(): void
    {
        $http   = new FakeLetsPeppolHttpClient(200, new \RuntimeException('boom'));
        $client = new LetsPeppolClient($http, 'https://api.test', ['participants.validate' => 'api/participants/validate']);

        $result = (new ParticipantClient($client))->validatePeppolId('0088:123456789');

        $this->assertFalse($result);
    }

    /**
     * Arrange: FakeParticipantClient (stateful fake, no HTTP at all).
     * Act: validate two IDs.
     * Assert: recorded calls and per-ID results are correct.
     */
    #[Test]
    public function it_records_validated_ids_and_returns_configured_results(): void
    {
        $fake = new FakeParticipantClient(['0088:good' => true, '0088:bad' => false]);

        $this->assertTrue($fake->validatePeppolId('0088:good'));
        $this->assertFalse($fake->validatePeppolId('0088:bad'));

        $fake->assertValidated('0088:good');
        $fake->assertValidated('0088:bad');
    }

    /**
     * Arrange: FakeParticipantClient::alwaysValid().
     * Act: validate any ID.
     * Assert: always returns true.
     */
    #[Test]
    public function it_always_returns_true_with_always_valid_fake(): void
    {
        $fake = FakeParticipantClient::alwaysValid();

        $this->assertTrue($fake->validatePeppolId('0088:anything'));
        $this->assertTrue($fake->validatePeppolId('0088:else'));

        $fake->assertValidated('0088:anything');
    }
}
