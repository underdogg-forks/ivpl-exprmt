<?php

use App\Enums\RequestMethod;
use PHPUnit\Framework\TestCase;

class RequestMethodTest extends TestCase
{
    public function testPostValue()
    {
        $this->assertSame('POST', RequestMethod::POST->value);
    }
}
