<?php

namespace Tests\Unit\Helpers;

use Tests\Support\CITestCase;

/**
 * Tests for standardize_amount() from number_helper.php.
 *
 * This function normalises locale-formatted strings (e.g. "1.234,56")
 * into plain floats — critical for correct invoice totals and the
 * eventual Peppol/UBL amount fields which must use a period decimal.
 */
class StandardizeAmountTest extends CITestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('standardize_amount')) {
            require_once APPPATH . 'helpers/number_helper.php';
        }
    }

    // -----------------------------------------------------------------
    // Period-decimal, comma-thousands  (US/EN locale)
    // -----------------------------------------------------------------

    public function test_already_numeric_value_is_returned_as_is(): void
    {
        $this->setSetting('thousands_separator', ',');
        $this->setSetting('decimal_point', '.');

        $result = standardize_amount(1234.56);

        $this->assertSame(1234.56, $result);
    }

    public function test_string_float_is_returned_as_is_when_already_numeric(): void
    {
        $this->setSetting('thousands_separator', ',');
        $this->setSetting('decimal_point', '.');

        $result = standardize_amount('1234.56');

        $this->assertSame('1234.56', $result);
    }

    public function test_comma_thousands_period_decimal(): void
    {
        $this->setSetting('thousands_separator', ',');
        $this->setSetting('decimal_point', '.');

        $result = standardize_amount('1,234.56');

        $this->assertSame('1234.56', (string) $result);
    }

    // -----------------------------------------------------------------
    // Period-thousands, comma-decimal  (EU / DE / NL locale)
    // -----------------------------------------------------------------

    public function test_period_thousands_comma_decimal(): void
    {
        $this->setSetting('thousands_separator', '.');
        $this->setSetting('decimal_point', ',');

        $result = standardize_amount('1.234,56');

        $this->assertSame('1234.56', (string) $result);
    }

    public function test_no_thousands_separator_comma_decimal(): void
    {
        $this->setSetting('thousands_separator', '');
        $this->setSetting('decimal_point', ',');

        $result = standardize_amount('1234,56');

        $this->assertSame('1234.56', (string) $result);
    }

    // -----------------------------------------------------------------
    // Edge cases
    // -----------------------------------------------------------------

    public function test_null_is_passed_through(): void
    {
        $result = standardize_amount(null);

        $this->assertNull($result);
    }

    public function test_zero_string_is_returned_unchanged(): void
    {
        $result = standardize_amount('0');

        $this->assertSame('0', $result);
    }
}
