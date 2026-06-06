<?php

namespace Tests;

use core\CiKernel;

abstract class CiTestCase extends AbstractTestCase
{
    /** @var \CI_Controller|null */
    protected $CI;

    protected function setUp(): void
    {
        parent::setUp();

        CiKernel::boot('testing');

        $this->CI = & get_instance();

        $_GET    = [];
        $_POST   = [];
        $_SERVER = [];

        $this->skipWithoutDatabase();
    }

    /**
     * Skip the current test if no database connection is available.
     * Tests that perform DB inserts/queries should call this first.
     *
     * Checks both the trait's raw mysqli connection (used by seedModel /
     * databaseInsert / databaseFetchOne) and the CI DB (used by model
     * methods) so that tests relying on either path are skipped correctly.
     */
    protected function skipWithoutDatabase(): void
    {
        if ($this->CI === null) {
            $this->markTestSkipped('CI3 instance not available.');
        }

        // Delegate to the trait implementation which probes raw mysqli.
        // This prevents tests that call seedModel() from proceeding when
        // no real database is reachable, even though MockDB never throws.
        parent::skipWithoutDatabase();
    }

    protected function ci(): self
    {
        return $this;
    }

    protected function get(string $uri, array $query = []): Integration\Support\HttpResponse
    {
        return $this->request('GET', $uri, $query);
    }
}
