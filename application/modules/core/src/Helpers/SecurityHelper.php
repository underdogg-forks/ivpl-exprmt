<?php

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Security Helper for common security operations
 *
 * This class provides centralized security functions to ensure
 * uniform security practices across the application (DRY principle).
 */
class SecurityHelper
{
    /**
     * Perform basic sanitization by removing control characters, null bytes, and HTML tags.
     *
     * IMPORTANT: This method only performs basic filtering and is NOT sufficient to prevent
     * all XSS attacks. Specifically:
     * - strip_tags() only removes complete HTML tags, not partial or malformed markup
     * - It does NOT neutralize attribute-injection vectors (e.g., `" onmouseover='alert(1)'`)
     * - It does NOT handle context-specific encoding (HTML, JS, CSS, URL contexts)
     *
     * Callers MUST apply proper, context-aware output encoding when rendering user data:
     * - For HTML text context: use htmlspecialchars() or html_escape()
     * - For HTML attributes: use attribute encoding
     * - For JavaScript/CSS/URL contexts: use appropriate encoding for that context
     *
     * This method should be considered a basic input filter, not a substitute for output escaping.
     *
     * @param string|array $data Data to sanitize
     * @return string|array Sanitized data
     */
    public static function xssClean(string|array $data): string|array
    {
        if (is_array($data)) {
            return array_map([self::class, 'xssClean'], $data);
        }

        // Remove null bytes
        $data = str_replace(chr(0), '', $data);

        // Remove control characters
        $data = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $data);

        // Strip tags
        $data = strip_tags($data);

        return $data;
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Generate secure random token
     *
     * @param int $length Token length (must be a positive integer)
     * @return string Random token
     * @throws \InvalidArgumentException When length is not positive
     */
    public static function generateToken(int $length = 32): string
    {
        if ($length <= 0) {
            throw new \InvalidArgumentException('Token length must be a positive integer, got: ' . $length);
        }

        return bin2hex(random_bytes($length));
    }

    /**
     * Constant-time string comparison to prevent timing attacks
     *
     * @param string $known Known string
     * @param string $user User-provided string
     * @return bool True if strings match
     */
    public static function secureCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * Sanitize filename for safe storage
     *
     * @param string $filename Original filename
     * @return string Safe filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Check if a file path is within allowed directory (prevents path traversal)
     *
     * @param string $filepath File path to check
     * @param string $allowedDir Allowed directory
     * @return bool True if safe, false otherwise
     */
    public static function isPathSafe(string $filepath, string $allowedDir): bool
    {
        $realPath = realpath($filepath);
        $allowedPath = realpath($allowedDir);

        if ($realPath === false || $allowedPath === false) {
            return false;
        }

        // Normalize allowed path with trailing separator to prevent prefix bypass
        $allowedPath = rtrim($allowedPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($realPath . DIRECTORY_SEPARATOR, $allowedPath);
    }

    /**
     * Validate CSRF token
     *
     * @param string $token Token to validate
     * @param string $sessionToken Session token
     * @return bool True if valid
     */
    public static function validateCsrfToken(string $token, string $sessionToken): bool
    {
        return self::secureCompare($sessionToken, $token);
    }
}
