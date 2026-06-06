<?php

namespace Tests\Unit\Invoices;

use Tests\Support\CITestCase;

/**
 * Tests for invoice status definitions in Mdl_Invoices.
 *
 * These tests intentionally do not hit the database; they only verify
 * the status metadata (labels, CSS classes, hrefs) that drive the UI
 * and will need to stay consistent for the Peppol BIS 3.0 status mapping.
 */
class InvoiceStatusTest extends CITestCase
{
    private \Mdl_Invoices $model;

    protected function setUp(): void
    {
        parent::setUp();

        require_once APPPATH . 'modules/invoices/models/Mdl_invoices.php';

        $this->model = new \Mdl_Invoices();
    }

    public function test_statuses_returns_four_states(): void
    {
        $statuses = $this->model->statuses();

        $this->assertCount(4, $statuses);
    }

    public function test_status_keys_are_string_integers(): void
    {
        $statuses = $this->model->statuses();

        foreach (array_keys($statuses) as $key) {
            $this->assertMatchesRegularExpression('/^\d+$/', (string) $key, "Status key '{$key}' should be numeric");
        }
    }

    public function test_each_status_has_required_fields(): void
    {
        $statuses = $this->model->statuses();

        foreach ($statuses as $id => $status) {
            $this->assertArrayHasKey('label', $status, "Status {$id} missing 'label'");
            $this->assertArrayHasKey('class', $status, "Status {$id} missing 'class'");
            $this->assertArrayHasKey('href', $status, "Status {$id} missing 'href'");
        }
    }

    public function test_draft_is_status_1(): void
    {
        $statuses = $this->model->statuses();

        $this->assertArrayHasKey('1', $statuses);
        $this->assertSame('draft', $statuses['1']['class']);
    }

    public function test_sent_is_status_2(): void
    {
        $statuses = $this->model->statuses();

        $this->assertSame('sent', $statuses['2']['class']);
    }

    public function test_viewed_is_status_3(): void
    {
        $statuses = $this->model->statuses();

        $this->assertSame('viewed', $statuses['3']['class']);
    }

    public function test_paid_is_status_4(): void
    {
        $statuses = $this->model->statuses();

        $this->assertSame('paid', $statuses['4']['class']);
    }

    public function test_table_is_ip_invoices(): void
    {
        $this->assertSame('ip_invoices', $this->model->table);
    }

    public function test_primary_key(): void
    {
        $this->assertSame('ip_invoices.invoice_id', $this->model->primary_key);
    }
}
