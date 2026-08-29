<?php

namespace Tests\Feature\Integrations;

use Tests\AbstractTestCase;
use Tests\Fixtures\InvoiceFixtures;

/**
 * LetsPeppol Invoice Transmission Feature Tests
 *
 * Test end-to-end E-Invoice generation, transmission, and status tracking
 */
class LetsPeppolInvoiceTransmissionTest extends AbstractTestCase
{
    use \Tests\InteractsWithDatabase;
    use InvoiceFixtures;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_sends_invoice_to_letspeppol_successfully(): void
    {

        $invoice = $this->seedInvoice(
            invoice_status: 'published',
            einvoicing_enabled: true,
        );

        // Mock LetsPeppol API response
        $letspeppol_response = [
            'status' => 200,
            'body' => json_encode([
                'id' => 'lp_invoice_001',
                'uuid' => 'uuid-letspeppol-001',
                'status' => 'accepted',
                'transmitted_at' => date('c'),
            ]),
        ];

        $this->withEnvironment('LETSPEPPOL_MOCK_RESPONSE', json_encode($letspeppol_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // Verify transmission record created
        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->where('provider', 'letspeppol')
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission);
        $this->assertEquals('accepted', $transmission->transmission_status);
        $this->assertEquals('lp_invoice_001', $transmission->external_reference_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_letspeppol_oauth2_token_refresh(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // Mock expired token + refresh flow
        $mock_sequence = [
            ['status' => 401, 'body' => json_encode(['error' => 'token_expired'])],
            ['status' => 200, 'body' => json_encode(['access_token' => 'new_token_xxx', 'expires_in' => 3600])],
            ['status' => 200, 'body' => json_encode(['id' => 'lp_refreshed_001', 'status' => 'accepted'])],
        ];

        $this->withEnvironment('LETSPEPPOL_MOCK_SEQUENCE', json_encode($mock_sequence))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission, 'Should complete transmission after token refresh');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_letspeppol_rate_limiting(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // Mock 429 rate limit
        $this->withEnvironment('LETSPEPPOL_MOCK_STATUS', '429')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        // Should record failed transmission for retry
        $this->assertNotNull($transmission);
        $this->assertStringContainsString('rate', mb_strtolower($transmission->transmission_status ?? ''));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_polls_transmission_status_from_letspeppol(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // Initial transmission
        $initial_response = [
            'status' => 200,
            'body' => json_encode([
                'id' => 'lp_status_001',
                'status' => 'submitted',
            ]),
        ];

        $this->withEnvironment('LETSPEPPOL_MOCK_RESPONSE', json_encode($initial_response))
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // Poll for status update
        $status_response = [
            'status' => 200,
            'body' => json_encode([
                'id' => 'lp_status_001',
                'status' => 'delivered',
                'delivered_at' => date('c'),
            ]),
        ];

        $this->withEnvironment('LETSPEPPOL_MOCK_RESPONSE', json_encode($status_response))
            ->post("admin/einvoicing/poll_status/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertEquals('delivered', $transmission->transmission_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_participant_validation_failure(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // Mock participant not found
        $this->withEnvironment('LETSPEPPOL_MOCK_STATUS', '404')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission);
        $this->assertStringContainsString('not found', mb_strtolower($transmission->transmission_status ?? ''));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_ubl_xml_format_before_transmission(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // This should validate XML schema before sending to LetsPeppol
        $this->post("admin/einvoicing/validate/{$invoice->invoice_id}");

        $this->assertResponseStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_timeout_during_letspeppol_transmission(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // Mock connection timeout
        $this->withEnvironment('LETSPEPPOL_MOCK_TIMEOUT', '1')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        // Should not have transmission record on timeout
        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNull($transmission, 'Should not record transmission on timeout');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_retries_failed_letspeppol_transmissions(): void
    {

        $invoice = $this->seedInvoice(einvoicing_enabled: true);

        // First attempt fails
        $this->withEnvironment('LETSPEPPOL_MOCK_STATUS', '503')
            ->post("admin/einvoicing/send/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertNotNull($transmission);
        $this->assertEquals(0, $transmission->retry_count ?? 0);

        // Retry should increment count
        $success_response = [
            'status' => 200,
            'body' => json_encode(['id' => 'lp_retry_001', 'status' => 'accepted']),
        ];

        $this->withEnvironment('LETSPEPPOL_MOCK_RESPONSE', json_encode($success_response))
            ->post("admin/einvoicing/retry/{$invoice->invoice_id}");

        $transmission = $this->db
            ->where('invoice_id', $invoice->invoice_id)
            ->get('ip_einvoicing_transmissions')
            ->row();

        $this->assertEquals('accepted', $transmission->transmission_status);
        $this->assertEquals(1, $transmission->retry_count ?? 0);
    }
}
