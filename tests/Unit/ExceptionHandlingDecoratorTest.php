<?php

use App\Contracts\IntegrationProviderInterface;
use App\Providers\ExceptionHandlingDecorator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that ExceptionHandlingDecorator:
 *  - transparently passes through return values when the inner provider succeeds
 *  - catches any Throwable from validateParticipant / sendInvoice and returns false
 *  - does NOT re-throw (callers are always exception-safe)
 */
class ExceptionHandlingDecoratorTest extends TestCase
{
    /**
     * Arrange: inner provider returns true.
     * Act: validateParticipant is called.
     * Assert: true is transparently forwarded.
     */
    #[Test]
    public function it_forwards_true_from_inner_validate_participant(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);
        $inner->method('validateParticipant')->willReturn(true);

        $decorator = new ExceptionHandlingDecorator($inner, 'test');

        $this->assertTrue($decorator->validateParticipant('0088:123'));
    }

    /**
     * Arrange: inner provider returns false.
     * Act: validateParticipant is called.
     * Assert: false is transparently forwarded.
     */
    #[Test]
    public function it_forwards_false_from_inner_validate_participant(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);
        $inner->method('validateParticipant')->willReturn(false);

        $decorator = new ExceptionHandlingDecorator($inner, 'test');

        $this->assertFalse($decorator->validateParticipant('0088:123'));
    }

    /**
     * Arrange: inner provider throws on validateParticipant.
     * Act: validateParticipant is called.
     * Assert: false is returned and no exception propagates.
     */
    #[Test]
    public function it_catches_exception_in_validate_participant_and_returns_false(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);
        $inner->method('validateParticipant')->willThrowException(new RuntimeException('network error'));

        $decorator = new ExceptionHandlingDecorator($inner, 'letspeppol');

        $result = $decorator->validateParticipant('0088:123');

        $this->assertFalse($result);
    }

    /**
     * Arrange: inner provider returns true for sendInvoice.
     * Act: sendInvoice is called.
     * Assert: true is transparently forwarded.
     */
    #[Test]
    public function it_forwards_true_from_inner_send_invoice(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);
        $inner->method('sendInvoice')->willReturn(true);

        $decorator = new ExceptionHandlingDecorator($inner, 'test');

        $this->assertTrue($decorator->sendInvoice(['invoice_id' => 1]));
    }

    /**
     * Arrange: inner provider throws on sendInvoice.
     * Act: sendInvoice is called.
     * Assert: false is returned and no exception propagates.
     */
    #[Test]
    public function it_catches_exception_in_send_invoice_and_returns_false(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);
        $inner->method('sendInvoice')->willThrowException(new RuntimeException('timeout'));

        $decorator = new ExceptionHandlingDecorator($inner, 'stripe');

        $result = $decorator->sendInvoice(['invoice_id' => 1]);

        $this->assertFalse($result);
    }

    /**
     * Arrange: inner provider implements the contract.
     * Act: decorator is constructed.
     * Assert: decorator itself implements the contract (Liskov).
     */
    #[Test]
    public function it_implements_the_integration_provider_interface(): void
    {
        $inner = $this->createMock(IntegrationProviderInterface::class);

        $decorator = new ExceptionHandlingDecorator($inner, 'test');

        $this->assertInstanceOf(IntegrationProviderInterface::class, $decorator);
    }
}
