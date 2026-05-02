<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Helpers\SecurityHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SecurityHelperTest extends TestCase
{
    #[Test]
    public function it_sanitizes_xss_from_string(): void
    {
        // Arrange
        $input = '<script>alert("xss")</script>Hello';

        // Act
        $result = SecurityHelper::xssClean($input);

        // Assert
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('</script>', $result);
    }

    #[Test]
    public function it_sanitizes_xss_from_array(): void
    {
        // Arrange
        $input = [
            'name' => '<script>alert("xss")</script>John',
            'email' => 'john@example.com',
        ];

        // Act
        $result = SecurityHelper::xssClean($input);

        // Assert
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('<script>', $result['name']);
    }

    #[Test]
    public function it_validates_email(): void
    {
        // Arrange & Act & Assert
        $this->assertTrue(SecurityHelper::isValidEmail('test@example.com'));
        $this->assertFalse(SecurityHelper::isValidEmail('invalid-email'));
        $this->assertFalse(SecurityHelper::isValidEmail(''));
    }

    #[Test]
    public function it_generates_secure_token(): void
    {
        // Arrange & Act
        $token1 = SecurityHelper::generateToken(16);
        $token2 = SecurityHelper::generateToken(16);

        // Assert
        $this->assertIsString($token1);
        $this->assertEquals(32, strlen($token1)); // 16 bytes = 32 hex chars
        $this->assertNotEquals($token1, $token2);
    }

    #[Test]
    public function it_compares_strings_securely(): void
    {
        // Arrange
        $known = 'secret123';
        $correct = 'secret123';
        $incorrect = 'wrong123';

        // Act & Assert
        $this->assertTrue(SecurityHelper::secureCompare($known, $correct));
        $this->assertFalse(SecurityHelper::secureCompare($known, $incorrect));
    }

    #[Test]
    public function it_sanitizes_filename(): void
    {
        // Arrange & Act & Assert
        $this->assertEquals('test.txt', SecurityHelper::sanitizeFilename('test.txt'));
        $this->assertEquals('test.txt', SecurityHelper::sanitizeFilename('../../../test.txt'));
        $this->assertEquals('test_file.txt', SecurityHelper::sanitizeFilename('test file.txt'));
        $this->assertEquals('test_____file.txt', SecurityHelper::sanitizeFilename('test<>?/|file.txt'));
    }

    #[Test]
    public function it_detects_path_traversal(): void
    {
        // Arrange
        $allowedDir = '/tmp/uploads';
        $safePath = '/tmp/uploads/file.txt';
        $unsafePath = '/etc/passwd';

        // Create test directory
        if (!is_dir($allowedDir)) {
            mkdir($allowedDir, 0755, true);
        }
        touch($safePath);

        // Act & Assert
        $this->assertTrue(SecurityHelper::isPathSafe($safePath, $allowedDir));
        $this->assertFalse(SecurityHelper::isPathSafe($unsafePath, $allowedDir));

        // Cleanup
        unlink($safePath);
        rmdir($allowedDir);
    }

    #[Test]
    public function it_validates_csrf_token(): void
    {
        // Arrange
        $sessionToken = 'abc123';
        $validToken = 'abc123';
        $invalidToken = 'xyz789';

        // Act & Assert
        $this->assertTrue(SecurityHelper::validateCsrfToken($validToken, $sessionToken));
        $this->assertFalse(SecurityHelper::validateCsrfToken($invalidToken, $sessionToken));
    }
}
