<?php

namespace Tests\Unit\Setup;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards the setup SQL runner (Mdl_setup::execute_contents) against the class of
 * bug that broke the 1.8.0 integrations migration: a semicolon inside a COMMENT
 * string or a comment made the naive explode(';') runner send truncated,
 * syntactically invalid fragments to MySQL/MariaDB.
 *
 * The scan test also fails CI if any future migration reintroduces a semicolon
 * inside a string literal, so this can't silently happen again.
 */
#[Group('unit')]
class SqlStatementSplitterTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if ( ! defined('BASEPATH')) {
            define('BASEPATH', dirname(__DIR__, 3) . '/application/');
        }

        require_once dirname(__DIR__, 3) . '/application/helpers/sql_helper.php';
    }

    #[Test]
    public function it_splits_top_level_statements(): void
    {
        $sql = "CREATE TABLE a (id INT);\nCREATE TABLE b (id INT);";

        $this->assertSame(
            ['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'],
            split_sql_statements($sql)
        );
    }

    #[Test]
    public function it_keeps_semicolons_inside_string_literals_together(): void
    {
        // The exact shape that produced the four install errors.
        $sql = "ALTER TABLE `ip_merchant_responses`\n"
            . "  ADD COLUMN `merchant_client_id` INT NULL\n"
            . "    COMMENT 'FK to ip_merchant_clients; NULL for legacy payment-gateway rows'\n"
            . "    AFTER `invoice_id`;";

        $statements = split_sql_statements($sql);

        $this->assertCount(1, $statements);
        $this->assertStringContainsString(
            "COMMENT 'FK to ip_merchant_clients; NULL for legacy payment-gateway rows'",
            $statements[0]
        );
    }

    #[Test]
    public function it_ignores_semicolons_in_line_and_block_comments(): void
    {
        $sql = "-- step one; then two\n"
            . "CREATE TABLE a (id INT);\n"
            . "/* inline; comment */ CREATE TABLE b (id INT);";

        $this->assertCount(2, split_sql_statements($sql));
    }

    #[Test]
    public function it_handles_doubled_quote_escapes(): void
    {
        $sql = "INSERT INTO t (v) VALUES ('O''Brien; Jr.'); SELECT 1;";

        $statements = split_sql_statements($sql);

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("'O''Brien; Jr.'", $statements[0]);
    }

    #[Test]
    public function it_drops_empty_and_whitespace_only_segments(): void
    {
        $this->assertSame(['SELECT 1'], split_sql_statements(";;\n  SELECT 1 ;\n ; "));
    }

    /**
     * Regression guard: every shipped setup migration must split into statements
     * whose string literals are balanced — i.e. never truncated mid-string, which
     * is what reaches the database and errors out.
     */
    #[Test]
    public function it_produces_no_broken_fragments_for_any_setup_migration(): void
    {
        $files = glob(BASEPATH . 'modules/setup/sql/*.sql');

        $this->assertNotEmpty($files, 'No setup migration files found.');

        foreach ($files as $file) {
            foreach (split_sql_statements((string) file_get_contents($file)) as $statement) {
                $this->assertTrue(
                    $this->hasBalancedStringLiterals($statement),
                    sprintf(
                        "Migration %s split into a fragment with an unterminated string literal:\n%s",
                        basename($file),
                        $statement
                    )
                );
            }
        }
    }

    /**
     * A statement is well-formed for our purposes when, after removing all
     * comments and complete string/identifier literals, no stray quote remains.
     */
    private function hasBalancedStringLiterals(string $statement): bool
    {
        $stripped = preg_replace('~/\*.*?\*/~s', '', $statement);
        $stripped = preg_replace('/--[^\n]*|#[^\n]*/', '', (string) $stripped);
        $stripped = preg_replace("/'(?:''|\\\\.|[^'])*'/s", '', (string) $stripped);
        $stripped = preg_replace('/"(?:""|\\\\.|[^"])*"/s', '', (string) $stripped);
        $stripped = preg_replace('/`[^`]*`/s', '', (string) $stripped);

        return strpos((string) $stripped, "'") === false
            && strpos((string) $stripped, '"') === false
            && strpos((string) $stripped, '`') === false;
    }
}
