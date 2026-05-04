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
    #[Test]
    public function it_covers_storecove_clients(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/StoreCove/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);
        $base = new StoreCoveClient($http, 'https://api.test', $this->endpointMap(), ['access_token' => 'token-sc']);

        $this->exerciseAllEndpoints(
            new StoreCoveParticipantClient($base),
            new StoreCoveInvoiceClient($base),
            new StoreCoveCreditNoteClient($base),
            new StoreCoveTransmissionClient($base),
            new StoreCoveDocumentClient($base)
        );

        $this->assertSame('Bearer token-sc', $http->requests[0]['options']['headers']['Authorization']);
        $this->assertCount(21, $http->requests);
    }

    #[Test]
    public function it_covers_pagero_clients(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/Pagero/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);
        $base = new PageroClient($http, 'https://api.test', $this->endpointMap(), ['access_token' => 'token-pa']);

        $this->exerciseAllEndpoints(
            new PageroParticipantClient($base),
            new PageroInvoiceClient($base),
            new PageroCreditNoteClient($base),
            new PageroTransmissionClient($base),
            new PageroDocumentClient($base)
        );

        $this->assertSame('Bearer token-pa', $http->requests[0]['options']['headers']['Authorization']);
        $this->assertCount(21, $http->requests);
    }

    #[Test]
    public function it_covers_sovos_clients(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../Fixtures/Sovos/participant_valid.json'), true);
        $http = new FakeLetsPeppolHttpClient($fixture['status_code']);
        $base = new SovosClient($http, 'https://api.test', $this->endpointMap(), ['access_token' => 'token-so']);

        $this->exerciseAllEndpoints(
            new SovosParticipantClient($base),
            new SovosInvoiceClient($base),
            new SovosCreditNoteClient($base),
            new SovosTransmissionClient($base),
            new SovosDocumentClient($base)
        );

        $this->assertSame('Bearer token-so', $http->requests[0]['options']['headers']['Authorization']);
        $this->assertCount(21, $http->requests);
    }

    private function endpointMap(): array
    {
        return [
            'participants.validate' => 'api/participants/validate',
            'participants.details' => 'api/participants',
            'participants.search' => 'api/participants/search',
            'participants.capabilities' => 'api/participants/capabilities',
            'invoices.send' => 'api/invoices',
            'invoices.status' => 'api/invoices/status',
            'invoices.cancel' => 'api/invoices/cancel',
            'invoices.resend' => 'api/invoices/resend',
            'credit_notes.send' => 'api/credit-notes',
            'credit_notes.status' => 'api/credit-notes/status',
            'credit_notes.cancel' => 'api/credit-notes/cancel',
            'transmissions.status' => 'api/transmissions/status',
            'transmissions.receipt' => 'api/transmissions/receipt',
            'transmissions.errors' => 'api/transmissions/errors',
            'transmissions.list' => 'api/transmissions',
            'transmissions.retry' => 'api/transmissions/retry',
            'documents.get' => 'api/documents',
            'documents.download' => 'api/documents/download',
            'documents.metadata' => 'api/documents/metadata',
            'documents.list' => 'api/documents/list',
            'documents.archive' => 'api/documents/archive',
        ];
    }

    private function exerciseAllEndpoints(
        object $participant,
        object $invoice,
        object $credit,
        object $transmission,
        object $document
    ): void {
        $this->assertTrue($participant->validatePeppolId('0088:1'));
        $participant->getDetails('0088:1');
        $participant->search('Acme', 'SE');
        $participant->getCapabilities('0088:1');

        $invoice->sendInvoice(['invoice_id' => 1]);
        $invoice->getStatus(1);
        $invoice->cancel(1, 'x');
        $invoice->resend(1, 'y');

        $credit->send(['credit_note_id' => 1]);
        $credit->getStatus(1);
        $credit->cancel(1, 'z');

        $transmission->getStatus('t');
        $transmission->getReceipt('t');
        $transmission->getErrors('t');
        $transmission->list(['status' => 'failed']);
        $transmission->retry('t', 'r');

        $document->get('d');
        $document->download('d');
        $document->getMetadata('d');
        $document->list(['status' => 'delivered']);
        $document->archive('d', 'a');
    }
}
