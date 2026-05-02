<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Helpers\ValidatorHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidatorHelperTest extends TestCase
{
    #[Test]
    public function it_validates_required_field(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::required('value'));
        $this->assertTrue(ValidatorHelper::required([1, 2, 3]));
        $this->assertFalse(ValidatorHelper::required(''));
        $this->assertFalse(ValidatorHelper::required('   '));
        $this->assertFalse(ValidatorHelper::required([]));
    }

    #[Test]
    public function it_validates_minimum_length(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::minLength('hello', 3));
        $this->assertTrue(ValidatorHelper::minLength('hello', 5));
        $this->assertFalse(ValidatorHelper::minLength('hi', 3));
    }

    #[Test]
    public function it_validates_maximum_length(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::maxLength('hi', 5));
        $this->assertTrue(ValidatorHelper::maxLength('hello', 5));
        $this->assertFalse(ValidatorHelper::maxLength('hello world', 5));
    }

    #[Test]
    public function it_validates_numeric_value(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::numeric(123));
        $this->assertTrue(ValidatorHelper::numeric('123'));
        $this->assertTrue(ValidatorHelper::numeric(12.34));
        $this->assertFalse(ValidatorHelper::numeric('abc'));
    }

    #[Test]
    public function it_validates_integer_value(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::integer(123));
        $this->assertTrue(ValidatorHelper::integer('123'));
        $this->assertFalse(ValidatorHelper::integer(12.34));
        $this->assertFalse(ValidatorHelper::integer('abc'));
    }

    #[Test]
    public function it_validates_url(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::url('https://example.com'));
        $this->assertTrue(ValidatorHelper::url('http://www.example.com/page'));
        $this->assertFalse(ValidatorHelper::url('not-a-url'));
        $this->assertFalse(ValidatorHelper::url('example.com')); // Missing protocol
    }

    #[Test]
    public function it_validates_email(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::email('test@example.com'));
        $this->assertTrue(ValidatorHelper::email('user.name+tag@example.co.uk'));
        $this->assertFalse(ValidatorHelper::email('invalid-email'));
        $this->assertFalse(ValidatorHelper::email('missing@domain'));
        $this->assertFalse(ValidatorHelper::email('@example.com'));
    }

    #[Test]
    public function it_validates_date(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::date('2024-01-15'));
        $this->assertTrue(ValidatorHelper::date('15/01/2024', 'd/m/Y'));
        $this->assertFalse(ValidatorHelper::date('2024-13-40')); // Invalid date
        $this->assertFalse(ValidatorHelper::date('not-a-date'));
    }

    #[Test]
    public function it_validates_value_in_array(): void
    {
        // Arrange
        $allowed = ['red', 'green', 'blue'];

        // Act & Assert
        $this->assertTrue(ValidatorHelper::in('red', $allowed));
        $this->assertTrue(ValidatorHelper::in('blue', $allowed));
        $this->assertFalse(ValidatorHelper::in('yellow', $allowed));
    }

    #[Test]
    public function it_validates_regex_pattern(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(ValidatorHelper::regex('abc123', '/^[a-z0-9]+$/'));
        $this->assertFalse(ValidatorHelper::regex('ABC123', '/^[a-z0-9]+$/'));
        $this->assertTrue(ValidatorHelper::regex('test@example.com', '/^[\w\.\-]+@[\w\.\-]+$/'));
    }

    #[Test]
    public function it_validates_multiple_rules(): void
    {
        // Arrange
        $value = 'test@example.com';
        $rules = [
            'required',
            'minLength' => 5,
            'maxLength' => 50,
        ];

        // Act
        $errors = ValidatorHelper::validate($value, $rules);

        // Assert
        $this->assertEmpty($errors);
    }

    #[Test]
    public function it_returns_errors_for_failed_validation(): void
    {
        // Arrange
        $value = '';
        $rules = [
            'required',
            'minLength' => 5,
        ];

        // Act
        $errors = ValidatorHelper::validate($value, $rules);

        // Assert
        $this->assertNotEmpty($errors);
        $this->assertCount(2, $errors);
    }

    #[Test]
    public function it_handles_unknown_validation_rules(): void
    {
        // Arrange
        $value = 'test';
        $rules = ['unknownRule'];

        // Act
        $errors = ValidatorHelper::validate($value, $rules);

        // Assert
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Unknown validation rule', $errors[0]);
    }
}
