<?php

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Cache Helper for implementing dynamic programming/memoization
 * 
 * This class provides centralized caching functionality to optimize
 * expensive operations (Dynamic Programming principle).
 */
class CacheHelper
{
    /** @var array In-memory cache */
    private static array $cache = [];

    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @return mixed|null Cached value or null if not found
     */
    public static function get(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];
        
        // Check if expired
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            self::delete($key);
            return null;
        }

        return $item['value'];
    }

    /**
     * Store value in cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to store
     * @param int $ttl Time to live in seconds (0 = forever)
     * @return void
     */
    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        self::$cache[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? time() + $ttl : 0,
        ];
    }

    /**
     * Check if key exists and is not expired
     * 
     * @param string $key Cache key
     * @return bool True if exists and valid
     */
    public static function has(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        $item = self::$cache[$key];
        
        // Check if expired
        if ($item['expires'] > 0 && $item['expires'] < time()) {
            self::delete($key);
            return false;
        }

        return true;
    }

    /**
     * Delete value from cache
     * 
     * @param string $key Cache key
     * @return void
     */
    public static function delete(string $key): void
    {
        unset(self::$cache[$key]);
    }

    /**
     * Clear all cache
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$cache = [];
    }

    /**
     * Remember pattern: Get from cache or execute callback and cache result
     * 
     * This is the core dynamic programming pattern - memoization.
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to execute if cache miss
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or computed value
     */
    public static function remember(string $key, callable $callback, int $ttl = 3600): mixed
    {
        if (self::has($key)) {
            return self::$cache[$key]['value'];
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function stats(): array
    {
        $totalItems = count(self::$cache);
        $totalSize = strlen(serialize(self::$cache));
        $expired = 0;

        foreach (self::$cache as $item) {
            if ($item['expires'] > 0 && $item['expires'] < time()) {
                $expired++;
            }
        }

        return [
            'total_items' => $totalItems,
            'expired_items' => $expired,
            'total_size_bytes' => $totalSize,
        ];
    }
}
