<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Helpers\CacheHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CacheHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CacheHelper::clear();
    }

    protected function tearDown(): void
    {
        CacheHelper::clear();
        parent::tearDown();
    }

    #[Test]
    public function it_stores_and_retrieves_value(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';

        // Act
        CacheHelper::set($key, $value);
        $result = CacheHelper::get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    #[Test]
    public function it_checks_if_key_exists(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';

        // Act & Assert
        $this->assertFalse(CacheHelper::has($key));

        CacheHelper::set($key, $value);
        $this->assertTrue(CacheHelper::has($key));
    }

    #[Test]
    public function it_deletes_value(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';
        CacheHelper::set($key, $value);

        // Act
        CacheHelper::delete($key);

        // Assert
        $this->assertFalse(CacheHelper::has($key));
        $this->assertNull(CacheHelper::get($key));
    }

    #[Test]
    public function it_clears_all_cache(): void
    {
        // Arrange
        CacheHelper::set('key1', 'value1');
        CacheHelper::set('key2', 'value2');
        CacheHelper::set('key3', 'value3');

        // Act
        CacheHelper::clear();

        // Assert
        $this->assertFalse(CacheHelper::has('key1'));
        $this->assertFalse(CacheHelper::has('key2'));
        $this->assertFalse(CacheHelper::has('key3'));
    }

    #[Test]
    public function it_implements_remember_pattern(): void
    {
        // Arrange
        $key = 'expensive_operation';
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return 'computed_value';
        };

        // Act - First call should execute callback
        $result1 = CacheHelper::remember($key, $callback);

        // Second call should use cached value
        $result2 = CacheHelper::remember($key, $callback);

        // Assert
        $this->assertEquals('computed_value', $result1);
        $this->assertEquals('computed_value', $result2);
        $this->assertEquals(1, $callCount); // Callback called only once
    }

    #[Test]
    public function it_respects_ttl(): void
    {
        // Arrange
        $key = 'short_lived';
        $value = 'test_value';
        $ttl = 1; // 1 second

        // Act
        CacheHelper::set($key, $value, $ttl);

        // Assert - value should exist immediately
        $this->assertTrue(CacheHelper::has($key));
        $this->assertEquals($value, CacheHelper::get($key));

        // Wait for expiration (minimum time to avoid flakiness)
        sleep(1);

        // Assert - value should be expired
        $this->assertFalse(CacheHelper::has($key));
        $this->assertNull(CacheHelper::get($key));
    }

    #[Test]
    public function it_provides_cache_statistics(): void
    {
        // Arrange
        CacheHelper::set('key1', 'value1');
        CacheHelper::set('key2', 'value2');

        // Act
        $stats = CacheHelper::stats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_items', $stats);
        $this->assertArrayHasKey('expired_items', $stats);
        $this->assertArrayHasKey('total_size_bytes', $stats);
        $this->assertEquals(2, $stats['total_items']);
    }

    #[Test]
    public function it_caches_complex_data_types(): void
    {
        // Arrange
        $complexData = [
            'array' => [1, 2, 3],
            'object' => (object)['prop' => 'value'],
            'null' => null,
            'bool' => true,
        ];

        // Act
        CacheHelper::set('complex', $complexData);
        $result = CacheHelper::get('complex');

        // Assert
        $this->assertEquals($complexData, $result);
    }
}
