<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Adapters\Pagero\Endpoints\CreditNoteClient as PageroCreditNoteClient;
use Core\Adapters\Pagero\Endpoints\DocumentClient as PageroDocumentClient;
use Core\Adapters\Pagero\Endpoints\InvoiceClient as PageroInvoiceClient;
use Core\Adapters\Pagero\Endpoints\ParticipantClient as PageroParticipantClient;
use Core\Adapters\Pagero\Endpoints\TransmissionClient as PageroTransmissionClient;
use Core\Adapters\Pagero\PageroClient;
use Core\Adapters\Sovos\Endpoints\CreditNoteClient as SovosCreditNoteClient;
use Core\Adapters\Sovos\Endpoints\DocumentClient as SovosDocumentClient;
use Core\Adapters\Sovos\Endpoints\InvoiceClient as SovosInvoiceClient;
use Core\Adapters\Sovos\Endpoints\ParticipantClient as SovosParticipantClient;
use Core\Adapters\Sovos\Endpoints\TransmissionClient as SovosTransmissionClient;
use Core\Adapters\Sovos\SovosClient;
use Core\Adapters\StoreCove\Endpoints\CreditNoteClient as StoreCoveCreditNoteClient;
use Core\Adapters\StoreCove\Endpoints\DocumentClient as StoreCoveDocumentClient;
use Core\Adapters\StoreCove\Endpoints\InvoiceClient as StoreCoveInvoiceClient;
use Core\Adapters\StoreCove\Endpoints\ParticipantClient as StoreCoveParticipantClient;
use Core\Adapters\StoreCove\Endpoints\TransmissionClient as StoreCoveTransmissionClient;
use Core\Adapters\StoreCove\StoreCoveClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class PeppolCompetitorClientsTest extends TestCase
{
    #[Test] public function it_covers_storecove_all_endpoint_methods_with_fixture_payloads(): void { $this->runProvider('StoreCove'); }
    #[Test] public function it_covers_pagero_all_endpoint_methods_with_fixture_payloads(): void { $this->runProvider('Pagero'); }
    #[Test] public function it_covers_sovos_all_endpoint_methods_with_fixture_payloads(): void { $this->runProvider('Sovos'); }

    private function runProvider(string $provider): void
    {
        $fx = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/' . $provider . '/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fx['participant_validate']['status_code']);
        $map = $this->endpointMap();

        if ($provider === 'StoreCove') { $base = new StoreCoveClient($http, 'https://api.test', $map, ['access_token' => 'token']);
            $participant = new StoreCoveParticipantClient($base); $invoice = new StoreCoveInvoiceClient($base); $credit = new StoreCoveCreditNoteClient($base); $trans = new StoreCoveTransmissionClient($base); $doc = new StoreCoveDocumentClient($base);
        } elseif ($provider === 'Pagero') { $base = new PageroClient($http, 'https://api.test', $map, ['access_token' => 'token']);
            $participant = new PageroParticipantClient($base); $invoice = new PageroInvoiceClient($base); $credit = new PageroCreditNoteClient($base); $trans = new PageroTransmissionClient($base); $doc = new PageroDocumentClient($base);
        } else { $base = new SovosClient($http, 'https://api.test', $map, ['access_token' => 'token']);
            $participant = new SovosParticipantClient($base); $invoice = new SovosInvoiceClient($base); $credit = new SovosCreditNoteClient($base); $trans = new SovosTransmissionClient($base); $doc = new SovosDocumentClient($base);
        }

        $this->assertTrue($participant->validatePeppolId($fx['participant_validate']['peppol_id']));
        $participant->getDetails($fx['participant_details']['peppol_id']);
        $participant->search($fx['participant_search']['query'], $fx['participant_search']['country']);
        $participant->getCapabilities($fx['participant_capabilities']['peppol_id']);

        $invoice->sendInvoice($fx['invoice_send']);
        $invoice->getStatus($fx['invoice_status']['invoice_id']);
        $invoice->cancel($fx['invoice_cancel']['invoice_id'], $fx['invoice_cancel']['cancel_reason']);
        $invoice->resend($fx['invoice_resend']['invoice_id'], $fx['invoice_resend']['resend_reason']);

        $credit->send($fx['credit_send']);
        $credit->getStatus($fx['credit_status']['credit_note_id']);
        $credit->cancel($fx['credit_cancel']['credit_note_id'], $fx['credit_cancel']['cancel_reason']);

        $trans->getStatus($fx['transmission_status']['transmission_id']);
        $trans->getReceipt($fx['transmission_receipt']['transmission_id']);
        $trans->getErrors($fx['transmission_errors']['transmission_id']);
        $trans->list($fx['transmission_list']);
        $trans->retry($fx['transmission_retry']['transmission_id'], $fx['transmission_retry']['retry_reason']);

        $doc->get($fx['document_get']['document_id']);
        $doc->download($fx['document_download']['document_id']);
        $doc->getMetadata($fx['document_metadata']['document_id']);
        $doc->list($fx['document_list']);
        $doc->archive($fx['document_archive']['document_id'], $fx['document_archive']['archive_reason']);

        $this->assertCount(21, $http->requests);
        $this->assertSame('Bearer token', $http->requests[0]['options']['headers']['Authorization']);
    }

    private function endpointMap(): array
    {
        return ['participants.validate'=>'api/participants/validate','participants.details'=>'api/participants','participants.search'=>'api/participants/search','participants.capabilities'=>'api/participants/capabilities','invoices.send'=>'api/invoices','invoices.status'=>'api/invoices/status','invoices.cancel'=>'api/invoices/cancel','invoices.resend'=>'api/invoices/resend','credit_notes.send'=>'api/credit-notes','credit_notes.status'=>'api/credit-notes/status','credit_notes.cancel'=>'api/credit-notes/cancel','transmissions.status'=>'api/transmissions/status','transmissions.receipt'=>'api/transmissions/receipt','transmissions.errors'=>'api/transmissions/errors','transmissions.list'=>'api/transmissions','transmissions.retry'=>'api/transmissions/retry','documents.get'=>'api/documents','documents.download'=>'api/documents/download','documents.metadata'=>'api/documents/metadata','documents.list'=>'api/documents/list','documents.archive'=>'api/documents/archive'];
    }
}
