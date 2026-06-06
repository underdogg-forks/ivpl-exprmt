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
     * @return mixed|null Cached value, or null on cache miss / expiry.
     *                    Note: null cannot be distinguished from a stored null — use has() if needed.
     */
    public static function get(string $key): mixed
    {
        if (!isset(self::$cache[$key])) {
            return null;
        }

        $item = self::$cache[$key];

        // Check if expired
        if (self::isExpired($item)) {
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
        if (self::isExpired($item)) {
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

        // Try to serialize entire cache, fallback to per-item estimation on error
        try {
            $totalSize = strlen(serialize(self::$cache));
        } catch (\Throwable $e) {
            // Fallback: estimate size by attempting to serialize each item
            $totalSize = 0;
            foreach (self::$cache as $item) {
                try {
                    $totalSize += strlen(serialize($item));
                } catch (\Throwable $itemException) {
                    // If item cannot be serialized, use a minimal estimate
                    $totalSize += 100; // Small constant fallback per non-serializable item
                }
            }
        }

        $expired = 0;

        foreach (self::$cache as $item) {
            if (self::isExpired($item)) {
                $expired++;
            }
        }

        return [
            'total_items' => $totalItems,
            'expired_items' => $expired,
            'total_size_bytes' => $totalSize,
        ];
    }

    /**
     * Check if cache item is expired
     *
     * @param array $item Cache item
     * @return bool True if expired
     */
    private static function isExpired(array $item): bool
    {
        return $item['expires'] > 0 && $item['expires'] <= time();
    }
}
