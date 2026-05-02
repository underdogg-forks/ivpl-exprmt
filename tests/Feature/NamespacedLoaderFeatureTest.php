<?php

use Core\Adapters\LetsPeppol\Endpoints\LetsPeppolClient;
use Core\Enums\RequestMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NamespacedLoaderFeatureTest extends TestCase
{
    /**
     * Arrange: App namespace classes.
     * Act: resolve classes and enum value.
     * Assert: namespaced classes are autoloadable.
     */
    #[Test]
    public function it_autoloads_app_namespaced_classes()
    {
        $this->assertTrue(class_exists(LetsPeppolClient::class));
        $this->assertSame('POST', RequestMethod::POST->value);
    }
}
