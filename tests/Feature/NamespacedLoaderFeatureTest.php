<?php

use App\Adapters\LetsPeppol\Endpoints\LetsPeppolClient;
use App\Enums\RequestMethod;
use PHPUnit\Framework\TestCase;

class NamespacedLoaderFeatureTest extends TestCase
{
    public function testAppNamespacesAreAutoloadable()
    {
        $this->assertTrue(class_exists(LetsPeppolClient::class));
        $this->assertSame('POST', RequestMethod::POST->value);
    }
}
