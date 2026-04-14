<?php

use App\Enums\RequestMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RequestMethodTest extends TestCase
{
    /**
     * Arrange: enum value.
     * Act: POST value is accessed.
     * Assert: value equals POST.
     */
    #[Test]
    public function it_returns_post_enum_value()
    {
        $this->assertSame('POST', RequestMethod::POST->value);
    }
}
