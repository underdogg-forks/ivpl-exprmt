<?php

declare(strict_types=1);

namespace Core\Helpers;

/**
 * Validator Helper for input validation
 *
 * Single Responsibility: This class only handles validation logic.
 * Open/Closed: Easy to extend with new validation rules without modifying existing ones.
 */
class ValidatorHelper
{
    /**
     * Validate required field
     *
     * @param mixed $value Value to validate
     * @return bool True if not empty
     */
    public static function required(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return !empty($value);
    }

    /**
     * Validate minimum length
     *
     * @param string $value Value to validate
     * @param int $min Minimum length
     * @return bool True if meets minimum
     */
    public static function minLength(string $value, int $min): bool
    {
        return mb_strlen($value) >= $min;
    }

    /**
     * Validate maximum length
     *
     * @param string $value Value to validate
     * @param int $max Maximum length
     * @return bool True if within maximum
     */
    public static function maxLength(string $value, int $max): bool
    {
        return mb_strlen($value) <= $max;
    }

    /**
     * Validate numeric value
     *
     * @param mixed $value Value to validate
     * @return bool True if numeric
     */
    public static function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    /**
     * Validate integer value
     *
     * @param mixed $value Value to validate
     * @return bool True if integer
     */
    public static function integer(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate URL
     *
     * @param string $value Value to validate
     * @return bool True if valid URL
     */
    public static function url(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate email address
     *
     * @param string $value Email to validate
     * @return bool True if valid email
     */
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate date format
     *
     * @param string $date Date string
     * @param string $format Expected format (default Y-m-d)
     * @return bool True if valid date in format
     */
    public static function date(string $date, string $format = 'Y-m-d'): bool
    {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate value is in array
     *
     * @param mixed $value Value to validate
     * @param array $allowed Allowed values
     * @return bool True if in array
     */
    public static function in(mixed $value, array $allowed): bool
    {
        return in_array($value, $allowed, true);
    }

    /**
     * Validate regex pattern
     *
     * @param string $value Value to validate
     * @param string $pattern Regex pattern
     * @return bool True if matches pattern
     */
    public static function regex(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    /**
     * Validate multiple rules at once
     *
     * @param mixed $value Value to validate
     * @param array $rules Array of validation rules
     * @return array Array of errors (empty if valid)
     */
    public static function validate(mixed $value, array $rules): array
    {
        $errors = [];

        foreach ($rules as $rule => $params) {
            if (is_int($rule)) {
                $rule = $params;
                $params = [];
            }

            if (!is_array($params)) {
                $params = [$params];
            }

            $method = $rule;
            if (!method_exists(static::class, $method)) {
                $errors[] = "Unknown validation rule: {$rule}";
                continue;
            }

            array_unshift($params, $value);

            try {
                if (!call_user_func_array([static::class, $method], $params)) {
                    $errors[] = "Validation failed for rule: {$rule}";
                }
            } catch (\TypeError $e) {
                $errors[] = "Validation failed for rule: {$rule} - {$e->getMessage()}";
            } catch (\Throwable $e) {
                $errors[] = "Validation failed for rule: {$rule} - {$e->getMessage()}";
            }
        }

        return $errors;
    }
}
