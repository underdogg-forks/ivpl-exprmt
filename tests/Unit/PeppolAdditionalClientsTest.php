<?php

declare(strict_types=1);

use Core\Adapters\LetsPeppol\Endpoints\CreditNoteClient;
use Core\Adapters\LetsPeppol\Endpoints\DocumentClient;
use Core\Adapters\LetsPeppol\Endpoints\InvoiceClient;
use Core\Adapters\LetsPeppol\Endpoints\ParticipantClient;
use Core\Adapters\LetsPeppol\Endpoints\TransmissionClient;
use Core\Adapters\LetsPeppol\LetsPeppolClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class PeppolAdditionalClientsTest extends TestCase
{
    private function client(array $map): array
    {
        $http = new FakeLetsPeppolHttpClient(200);
        return [$http, new LetsPeppolClient($http, 'https://api.test', $map)];
    }

    #[Test]
    public function it_covers_participant_methods(): void
    {
        [$http, $base] = $this->client([
            'participants.validate' => 'api/participants/validate',
            'participants.details' => 'api/participants',
            'participants.search' => 'api/participants/search',
            'participants.capabilities' => 'api/participants/capabilities',
        ]);
        $c = new ParticipantClient($base);
        $this->assertTrue($c->validatePeppolId('0088:1'));
        $c->getDetails('0088:1'); $c->search('Acme', 'SE'); $c->getCapabilities('0088:1');
        $this->assertCount(4, $http->requests);
    }

    #[Test]
    public function it_covers_invoice_credit_transmission_and_document_methods(): void
    {
        [$http, $base] = $this->client([
            'invoices.send' => 'api/invoices', 'invoices.status' => 'api/invoices', 'invoices.cancel' => 'api/invoices/cancel', 'invoices.resend' => 'api/invoices/resend',
            'credit_notes.send' => 'api/credit-notes', 'credit_notes.status' => 'api/credit-notes', 'credit_notes.cancel' => 'api/credit-notes/cancel',
            'transmissions.status' => 'api/transmissions', 'transmissions.receipt' => 'api/transmissions/receipt', 'transmissions.errors' => 'api/transmissions/errors', 'transmissions.list' => 'api/transmissions', 'transmissions.retry' => 'api/transmissions/retry',
            'documents.get' => 'api/documents', 'documents.download' => 'api/documents/download', 'documents.metadata' => 'api/documents/metadata', 'documents.list' => 'api/documents', 'documents.archive' => 'api/documents/archive',
        ]);
        $i = new InvoiceClient($base); $i->sendInvoice(['invoice_id'=>1]); $i->getStatus(1); $i->cancel(1,'x'); $i->resend(1,'y');
        $cn = new CreditNoteClient($base); $cn->send(['credit_note_id'=>1]); $cn->getStatus(1); $cn->cancel(1,'z');
        $t = new TransmissionClient($base); $t->getStatus('t'); $t->getReceipt('t'); $t->getErrors('t'); $t->list(['status'=>'failed']); $t->retry('t','r');
        $d = new DocumentClient($base); $d->get('d'); $d->download('d'); $d->getMetadata('d'); $d->list(['status'=>'delivered']); $d->archive('d','a');
        $this->assertCount(17, $http->requests);
    }
}
