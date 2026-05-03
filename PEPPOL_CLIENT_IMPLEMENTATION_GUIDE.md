# PEPPOL_CLIENT_IMPLEMENTATION_GUIDE.md

## AI Agent Prompt: Build Dual-Compatible Peppol API Client

> **Purpose:** This guide provides AI agents with comprehensive instructions to implement a production-ready Peppol API client that works seamlessly with **both InvoicePlane v1 (CodeIgniter 3)** and **InvoicePlane v2 (Laravel + Filament)**.

---

## Project Vision

We're building **ONE interpretation** of a Peppol API client that:
- ✅ Works with **ANY** Peppol provider (LetsPeppol, StoreCove, OpenPeppol, etc.)
- ✅ Compatible with **InvoicePlane v1** (CodeIgniter 3 + MX HMVC)
- ✅ **Copy/pastable to InvoicePlane v2** (Laravel + Filament + Services + PHPUnit)
- ✅ Follows SOLID, DRY, Dynamic Programming principles
- ✅ Type-safe with PHP 8.1+ enums
- ✅ 100% test coverage with fixtures

---

## Architecture Overview

```
Core\
├── Contracts\
│   └── GatewayClientInterface.php        ← Provider-agnostic contract
├── Gateways\
│   ├── ApiClient.php                     ← Base class (framework-agnostic)
│   └── LetsPeppol\
│       ├── LetsPeppolGatewayClient.php   ← Provider implementation
│       └── Endpoints\                    ← Domain-specific operations
│           ├── InvoiceEndpoint.php       ← Invoice operations
│           ├── CreditNoteEndpoint.php    ← Credit note operations
│           ├── TransmissionEndpoint.php  ← Transmission tracking
│           ├── DocumentEndpoint.php      ← Document retrieval
│           └── ParticipantEndpoint.php   ← Participant lookup
├── Enums\LetsPeppol\
│   ├── TransmissionStatus.php            ← Transmission states
│   ├── InvoiceStatus.php                 ← Invoice states
│   ├── CreditNoteStatus.php              ← Credit note states
│   ├── DocumentType.php                  ← Peppol document types
│   ├── DocumentStatus.php                ← Document states
│   ├── ReceiptType.php                   ← Receipt types
│   ├── ReceiptStatus.php                 ← Receipt states
│   ├── StatusCode.php                    ← UBL status codes
│   ├── ErrorCode.php                     ← Error codes
│   └── ParticipantCapability.php         ← Participant capabilities
├── Providers\
│   └── LetsPeppolGatewayProvider.php     ← High-level provider interface
└── Services\
    ├── InvoiceTrackingService.php        ← Track invoices through network
    ├── PayloadBuilderService.php         ← Build Peppol-compliant payloads
    ├── ErrorTrackingService.php          ← Track and manage errors
    └── ParticipantSearchService.php      ← Search/manage participants
```

---

## Core Requirements

### 1. Track Sent and Received Invoices

**What we need:**
- Store invoice transmission records with full lifecycle tracking
- Link InvoicePlane invoices to Peppol transmission IDs
- Track state changes over time (queued → sent → delivered)
- Support real-time status queries

**Database Tables (v1 - CodeIgniter):**
```sql
-- Track invoice transmissions
CREATE TABLE ip_peppol_invoice_transmissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    transmission_id VARCHAR(255) NOT NULL UNIQUE,
    external_document_id VARCHAR(255),
    status ENUM('queued','processing','sent','delivered','failed','cancelled','rejected'),
    peppol_participant_id VARCHAR(100) NOT NULL,
    sent_at DATETIME,
    delivered_at DATETIME,
    failed_at DATETIME,
    error_code VARCHAR(100),
    error_message TEXT,
    payload_json JSON,
    receipt_json JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ip_invoices(invoice_id) ON DELETE CASCADE,
    INDEX idx_transmission_id (transmission_id),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- Track received invoices (future: when we receive invoices via Peppol)
CREATE TABLE ip_peppol_received_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id VARCHAR(255) NOT NULL UNIQUE,
    document_type ENUM('invoice','credit_note','order','order_response'),
    transmission_id VARCHAR(255),
    sender_peppol_id VARCHAR(100) NOT NULL,
    document_number VARCHAR(255),
    amount DECIMAL(20, 2),
    currency VARCHAR(3),
    received_at DATETIME NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    invoice_id INT,  -- Link to created invoice (if auto-imported)
    document_xml TEXT,  -- Full UBL XML
    metadata_json JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_id (document_id),
    INDEX idx_sender (sender_peppol_id),
    INDEX idx_received_at (received_at),
    INDEX idx_processed (processed)
);
```

**Laravel Migration (v2):**
```php
// database/migrations/2026_05_03_create_peppol_invoice_transmissions_table.php
Schema::create('peppol_invoice_transmissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
    $table->string('transmission_id')->unique();
    $table->string('external_document_id')->nullable();
    $table->enum('status', ['queued', 'processing', 'sent', 'delivered', 'failed', 'cancelled', 'rejected']);
    $table->string('peppol_participant_id', 100);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->string('error_code', 100)->nullable();
    $table->text('error_message')->nullable();
    $table->json('payload_json')->nullable();
    $table->json('receipt_json')->nullable();
    $table->timestamps();
    
    $table->index('transmission_id');
    $table->index('status');
    $table->index('sent_at');
});
```

**Service Implementation (v1 - CodeIgniter):**
```php
// application/modules/core/src/Services/InvoiceTrackingService.php
namespace Core\Services;

use Core\Enums\LetsPeppol\TransmissionStatus;
use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Gateways\LetsPeppol\Endpoints\TransmissionEndpoint;

class InvoiceTrackingService
{
    public function __construct(
        private InvoiceEndpoint $invoiceEndpoint,
        private TransmissionEndpoint $transmissionEndpoint,
        private \CI_DB_query_builder $db
    ) {}

    /**
     * Track invoice location in Peppol network at any moment.
     */
    public function getInvoiceLocation(int $invoiceId): array
    {
        // Get transmission record from database
        $transmission = $this->db
            ->where('invoice_id', $invoiceId)
            ->order_by('created_at', 'DESC')
            ->get('peppol_invoice_transmissions')
            ->row_array();
        
        if (!$transmission) {
            return ['status' => 'not_sent', 'invoice_id' => $invoiceId];
        }
        
        // Query live status from Peppol network
        $response = $this->transmissionEndpoint->getStatus($transmission['transmission_id']);
        $statusData = json_decode($response->getBody()->getContents(), true);
        
        // Update local database with latest status
        $this->updateTransmissionStatus($transmission['id'], $statusData);
        
        return [
            'invoice_id' => $invoiceId,
            'transmission_id' => $transmission['transmission_id'],
            'status' => $statusData['status'],
            'sent_at' => $statusData['sent_at'] ?? null,
            'delivered_at' => $statusData['delivered_at'] ?? null,
            'recipient' => $statusData['recipient'] ?? $transmission['peppol_participant_id'],
            'location' => $this->determineLocation($statusData),
        ];
    }

    /**
     * Determine where invoice is in the Peppol network.
     */
    private function determineLocation(array $statusData): string
    {
        return match(TransmissionStatus::from($statusData['status'])) {
            TransmissionStatus::QUEUED => 'In local queue awaiting transmission',
            TransmissionStatus::PROCESSING => 'Being prepared for Peppol network',
            TransmissionStatus::SENT => 'Transmitted to Peppol Access Point',
            TransmissionStatus::DELIVERED => 'Delivered to recipient Access Point',
            TransmissionStatus::FAILED => 'Transmission failed - see error details',
            TransmissionStatus::CANCELLED => 'Cancelled before delivery',
            TransmissionStatus::TIMEOUT => 'Network timeout - retry pending',
            TransmissionStatus::REJECTED => 'Rejected by recipient',
        };
    }

    /**
     * Update transmission status in database.
     */
    private function updateTransmissionStatus(int $id, array $statusData): void
    {
        $updateData = [
            'status' => $statusData['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        if (isset($statusData['delivered_at'])) {
            $updateData['delivered_at'] = $statusData['delivered_at'];
        }
        
        $this->db->where('id', $id)->update('peppol_invoice_transmissions', $updateData);
    }
}
```

**Service Implementation (v2 - Laravel):**
```php
// app/Services/InvoiceTrackingService.php
namespace App\Services;

use App\Enums\LetsPeppol\TransmissionStatus;
use App\Gateways\LetsPeppol\Endpoints\TransmissionEndpoint;
use App\Models\PeppolInvoiceTransmission;

class InvoiceTrackingService
{
    public function __construct(
        private TransmissionEndpoint $transmissionEndpoint
    ) {}

    public function getInvoiceLocation(int $invoiceId): array
    {
        $transmission = PeppolInvoiceTransmission::where('invoice_id', $invoiceId)
            ->latest()
            ->first();
        
        if (!$transmission) {
            return ['status' => 'not_sent', 'invoice_id' => $invoiceId];
        }
        
        $response = $this->transmissionEndpoint->getStatus($transmission->transmission_id);
        $statusData = json_decode($response->getBody()->getContents(), true);
        
        $transmission->update([
            'status' => $statusData['status'],
            'delivered_at' => $statusData['delivered_at'] ?? null,
        ]);
        
        return [
            'invoice_id' => $invoiceId,
            'transmission_id' => $transmission->transmission_id,
            'status' => $statusData['status'],
            'location' => TransmissionStatus::from($statusData['status'])->description(),
        ];
    }
}
```

---

### 2. Track Errors

**What we need:**
- Capture and store all transmission errors
- Link errors to specific invoices/transmissions
- Categorize errors by type (recipient, validation, network, etc.)
- Enable error analytics and retry logic

**Database Tables (v1 - CodeIgniter):**
```sql
CREATE TABLE ip_peppol_transmission_errors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transmission_id VARCHAR(255) NOT NULL,
    error_code VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    error_category ENUM('recipient','validation','network','authentication','timeout','format'),
    error_details JSON,
    occurred_at DATETIME NOT NULL,
    resolved BOOLEAN DEFAULT FALSE,
    resolved_at DATETIME,
    retry_attempted BOOLEAN DEFAULT FALSE,
    retry_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transmission_id (transmission_id),
    INDEX idx_error_code (error_code),
    INDEX idx_occurred_at (occurred_at),
    INDEX idx_resolved (resolved)
);
```

**Service Implementation (v1 - CodeIgniter):**
```php
// application/modules/core/src/Services/ErrorTrackingService.php
namespace Core\Services;

use Core\Enums\LetsPeppol\ErrorCode;
use Core\Gateways\LetsPeppol\Endpoints\TransmissionEndpoint;

class ErrorTrackingService
{
    public function __construct(
        private TransmissionEndpoint $transmissionEndpoint,
        private \CI_DB_query_builder $db
    ) {}

    /**
     * Record transmission error.
     */
    public function recordError(string $transmissionId, array $errorData): int
    {
        $data = [
            'transmission_id' => $transmissionId,
            'error_code' => $errorData['error_code'],
            'error_message' => $errorData['error_message'],
            'error_category' => $this->categorizeError($errorData['error_code']),
            'error_details' => json_encode($errorData['details'] ?? []),
            'occurred_at' => $errorData['occurred_at'] ?? date('Y-m-d H:i:s'),
        ];
        
        $this->db->insert('peppol_transmission_errors', $data);
        return $this->db->insert_id();
    }

    /**
     * Get all errors for a transmission.
     */
    public function getTransmissionErrors(string $transmissionId): array
    {
        // First check database
        $localErrors = $this->db
            ->where('transmission_id', $transmissionId)
            ->order_by('occurred_at', 'DESC')
            ->get('peppol_transmission_errors')
            ->result_array();
        
        // Then fetch latest from network
        try {
            $response = $this->transmissionEndpoint->getErrors($transmissionId);
            $networkError = json_decode($response->getBody()->getContents(), true);
            
            // Store if not already recorded
            $this->recordError($transmissionId, $networkError);
        } catch (\Throwable $e) {
            // Network errors are non-fatal here
        }
        
        return $localErrors;
    }

    /**
     * Categorize error code for analytics.
     */
    private function categorizeError(string $errorCode): string
    {
        return match(ErrorCode::tryFrom($errorCode)) {
            ErrorCode::INVALID_RECIPIENT => 'recipient',
            ErrorCode::RECIPIENT_UNAVAILABLE => 'recipient',
            ErrorCode::SCHEMA_VALIDATION_ERROR => 'validation',
            ErrorCode::INVALID_DOCUMENT => 'validation',
            ErrorCode::INVALID_FORMAT => 'format',
            ErrorCode::NETWORK_ERROR => 'network',
            ErrorCode::TIMEOUT => 'timeout',
            ErrorCode::AUTHENTICATION_FAILED => 'authentication',
            ErrorCode::AUTHORIZATION_FAILED => 'authentication',
            default => 'unknown',
        };
    }
}
```

---

### 3. Search for Participants

**What we need:**
- Search Peppol network by company name or organization number
- Validate participant IDs before sending
- Cache participant lookup results
- Support country-specific filtering

**Service Implementation (v1 - CodeIgniter):**
```php
// application/modules/core/src/Services/ParticipantSearchService.php
namespace Core\Services;

use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;
use Core\Helpers\CacheHelper;

class ParticipantSearchService
{
    public function __construct(
        private ParticipantEndpoint $participantEndpoint,
        private \CI_DB_query_builder $db
    ) {}

    /**
     * Search for participants with caching.
     */
    public function search(string $query, ?string $countryCode = null): array
    {
        $cacheKey = "peppol_search_{$query}_{$countryCode}";
        
        return CacheHelper::remember($cacheKey, function() use ($query, $countryCode) {
            $response = $this->participantEndpoint->search($query, $countryCode);
            $results = json_decode($response->getBody()->getContents(), true);
            
            // Cache each participant for faster validation
            foreach ($results['participants'] ?? [] as $participant) {
                $this->cacheParticipant($participant);
            }
            
            return $results;
        }, 3600);  // Cache for 1 hour
    }

    /**
     * Validate and cache participant.
     */
    public function validateParticipant(string $peppolId): bool
    {
        $cacheKey = "peppol_participant_{$peppolId}";
        
        return CacheHelper::remember($cacheKey, function() use ($peppolId) {
            return $this->participantEndpoint->validatePeppolId($peppolId);
        }, 86400);  // Cache for 24 hours
    }

    /**
     * Get participant details with caching.
     */
    public function getParticipantDetails(string $peppolId): ?array
    {
        $cacheKey = "peppol_details_{$peppolId}";
        
        return CacheHelper::remember($cacheKey, function() use ($peppolId) {
            try {
                $response = $this->participantEndpoint->getDetails($peppolId);
                return json_decode($response->getBody()->getContents(), true);
            } catch (\Throwable $e) {
                return null;
            }
        }, 86400);
    }

    private function cacheParticipant(array $participant): void
    {
        $cacheKey = "peppol_participant_{$participant['peppol_id']}";
        CacheHelper::set($cacheKey, true, 86400);
        
        $detailsKey = "peppol_details_{$participant['peppol_id']}";
        CacheHelper::set($detailsKey, $participant, 86400);
    }
}
```

---

### 4. Build Payloads (UBL/Peppol-Compliant)

**What we need:**
- Convert InvoicePlane invoice data → UBL 2.1 XML
- Convert InvoicePlane credit note data → UBL CreditNote XML
- Support all required Peppol BIS fields
- Validation before transmission

**Service Implementation (v1 - CodeIgniter):**
```php
// application/modules/core/src/Services/PayloadBuilderService.php
namespace Core\Services;

use Core\Enums\LetsPeppol\DocumentType;

class PayloadBuilderService
{
    /**
     * Build invoice payload for Peppol transmission.
     *
     * Converts InvoicePlane invoice data to Peppol-compliant structure.
     *
     * @param array $invoice  Invoice data from ip_invoices table
     * @param array $client   Client data from ip_clients table
     * @param array $items    Invoice items from ip_invoice_items table
     * @return array Peppol-compliant payload
     */
    public function buildInvoicePayload(array $invoice, array $client, array $items): array
    {
        return [
            'invoice_id' => $invoice['invoice_id'],
            'invoice_number' => $invoice['invoice_number'],
            'issue_date' => $invoice['invoice_date_created'],
            'due_date' => $invoice['invoice_date_due'],
            
            // Supplier (sender) - InvoicePlane instance
            'supplier' => [
                'peppol_id' => get_setting('peppol_participant_id'),
                'name' => get_setting('invoice_company_name'),
                'street' => get_setting('invoice_address_1'),
                'city' => get_setting('invoice_city'),
                'postal_code' => get_setting('invoice_zip'),
                'country_code' => get_setting('invoice_country'),
                'tax_id' => get_setting('invoice_tax_code'),
            ],
            
            // Customer (recipient)
            'customer' => [
                'peppol_id' => $client['client_peppol_id'],
                'name' => $client['client_name'],
                'street' => $client['client_address_1'],
                'city' => $client['client_city'],
                'postal_code' => $client['client_zip'],
                'country_code' => $client['client_country'],
                'tax_id' => $client['client_tax_code'],
            ],
            
            // Line items
            'items' => array_map(function($item) {
                return [
                    'id' => $item['item_id'],
                    'name' => $item['item_name'],
                    'description' => $item['item_description'],
                    'quantity' => floatval($item['item_quantity']),
                    'unit_price' => floatval($item['item_price']),
                    'tax_rate' => floatval($item['item_tax_rate']),
                    'tax_amount' => floatval($item['item_tax_total']),
                    'total' => floatval($item['item_total']),
                ];
            }, $items),
            
            // Totals
            'totals' => [
                'subtotal' => floatval($invoice['invoice_item_subtotal']),
                'tax_total' => floatval($invoice['invoice_item_tax_total']),
                'total' => floatval($invoice['invoice_total']),
                'currency' => $invoice['invoice_currency'],
            ],
            
            // Peppol metadata
            'document_type' => DocumentType::INVOICE->value,
            'process_id' => 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0',
            'customization_id' => 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0',
        ];
    }

    /**
     * Build credit note payload for Peppol transmission.
     */
    public function buildCreditNotePayload(array $creditNote, array $invoice, array $client, array $items): array
    {
        // Similar structure to invoice but with credit note specifics
        $payload = $this->buildInvoicePayload($invoice, $client, $items);
        
        // Override with credit note data
        $payload['credit_note_id'] = $creditNote['credit_note_id'];
        $payload['credit_note_number'] = $creditNote['credit_note_number'];
        $payload['invoice_reference'] = $invoice['invoice_number'];
        $payload['document_type'] = DocumentType::CREDIT_NOTE->value;
        $payload['credit_reason'] = $creditNote['credit_note_reason'] ?? 'Credit note';
        
        return $payload;
    }

    /**
     * Validate payload before transmission.
     */
    public function validatePayload(array $payload): array
    {
        $errors = [];
        
        // Required fields
        $required = ['invoice_number', 'issue_date', 'supplier', 'customer', 'items', 'totals'];
        foreach ($required as $field) {
            if (!isset($payload[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }
        
        // Validate Peppol IDs
        if (!isset($payload['customer']['peppol_id']) || empty($payload['customer']['peppol_id'])) {
            $errors[] = "Customer must have a valid Peppol ID";
        }
        
        // Validate amounts
        if (isset($payload['totals']['total']) && $payload['totals']['total'] <= 0) {
            $errors[] = "Invoice total must be greater than zero";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
```

---

### 5. Real-Time Invoice Location Tracking

**Dashboard Component (v1 - CodeIgniter View):**
```php
// application/modules/invoices/views/partial_peppol_status.php
<?php if ($invoice_peppol_status): ?>
<div class="panel panel-default">
    <div class="panel-heading">
        <i class="fa fa-network-wired"></i> Peppol Transmission Status
    </div>
    <div class="panel-body">
        <div class="peppol-status-tracker">
            <div class="status-badge status-<?php echo html_escape($invoice_peppol_status['status']); ?>">
                <?php echo html_escape(ucfirst($invoice_peppol_status['status'])); ?>
            </div>
            
            <div class="status-timeline">
                <div class="timeline-step <?php echo $invoice_peppol_status['sent_at'] ? 'completed' : ''; ?>">
                    <span class="step-icon"><i class="fa fa-paper-plane"></i></span>
                    <span class="step-label">Sent</span>
                    <?php if ($invoice_peppol_status['sent_at']): ?>
                        <span class="step-time"><?php echo html_escape($invoice_peppol_status['sent_at']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="timeline-connector"></div>
                
                <div class="timeline-step <?php echo $invoice_peppol_status['delivered_at'] ? 'completed' : ''; ?>">
                    <span class="step-icon"><i class="fa fa-check-circle"></i></span>
                    <span class="step-label">Delivered</span>
                    <?php if ($invoice_peppol_status['delivered_at']): ?>
                        <span class="step-time"><?php echo html_escape($invoice_peppol_status['delivered_at']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="status-location">
                <strong>Current Location:</strong>
                <p><?php echo html_escape($invoice_peppol_status['location']); ?></p>
            </div>
            
            <?php if (isset($invoice_peppol_status['transmission_id'])): ?>
                <div class="status-details">
                    <strong>Transmission ID:</strong>
                    <code><?php echo html_escape($invoice_peppol_status['transmission_id']); ?></code>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
```

**Filament Resource (v2 - Laravel):**
```php
// app/Filament/Resources/InvoiceResource.php - Add to form
use Filament\Forms\Components\ViewField;

ViewField::make('peppol_status')
    ->label('Peppol Transmission')
    ->view('filament.forms.components.peppol-status-tracker')
    ->visible(fn ($record) => $record?->peppol_transmissions()->exists()),
```

---

### 6. Prepare for All Endpoints

**Complete Service Layer (v1 - CodeIgniter):**

```php
// application/modules/core/src/Services/PeppolTransmissionService.php
namespace Core\Services;

use Core\Gateways\LetsPeppol\Endpoints\{
    InvoiceEndpoint,
    CreditNoteEndpoint,
    TransmissionEndpoint,
    DocumentEndpoint,
    ParticipantEndpoint
};

/**
 * Unified service for all Peppol transmission operations.
 * 
 * Orchestrates endpoint clients and provides high-level business logic.
 */
class PeppolTransmissionService
{
    public function __construct(
        private InvoiceEndpoint $invoiceEndpoint,
        private CreditNoteEndpoint $creditNoteEndpoint,
        private TransmissionEndpoint $transmissionEndpoint,
        private DocumentEndpoint $documentEndpoint,
        private ParticipantEndpoint $participantEndpoint,
        private InvoiceTrackingService $trackingService,
        private PayloadBuilderService $payloadBuilder,
        private ErrorTrackingService $errorTracking,
        private \CI_DB_query_builder $db
    ) {}

    /**
     * Send invoice to Peppol network with full tracking.
     */
    public function sendInvoice(int $invoiceId): array
    {
        // Load invoice data
        $invoice = $this->db->where('invoice_id', $invoiceId)->get('ip_invoices')->row_array();
        $client = $this->db->where('client_id', $invoice['client_id'])->get('ip_clients')->row_array();
        $items = $this->db->where('invoice_id', $invoiceId)->get('ip_invoice_items')->result_array();
        
        // Build payload
        $payload = $this->payloadBuilder->buildInvoicePayload($invoice, $client, $items);
        
        // Validate payload
        $validation = $this->payloadBuilder->validatePayload($payload);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }
        
        // Send to Peppol network
        try {
            $response = $this->invoiceEndpoint->sendInvoice($payload);
            $result = json_decode($response->getBody()->getContents(), true);
            
            // Record transmission
            $this->db->insert('peppol_invoice_transmissions', [
                'invoice_id' => $invoiceId,
                'transmission_id' => $result['transmission_id'],
                'external_document_id' => $result['id'],
                'status' => $result['status'],
                'peppol_participant_id' => $client['client_peppol_id'],
                'sent_at' => date('Y-m-d H:i:s'),
                'payload_json' => json_encode($payload),
            ]);
            
            return ['success' => true, 'transmission_id' => $result['transmission_id']];
        } catch (\Throwable $e) {
            $this->errorTracking->recordError('pending', [
                'error_code' => 'SEND_FAILED',
                'error_message' => $e->getMessage(),
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get real-time invoice location in Peppol network.
     */
    public function getInvoiceLocation(int $invoiceId): array
    {
        return $this->trackingService->getInvoiceLocation($invoiceId);
    }

    /**
     * Monitor invoice until delivered or failed.
     */
    public function monitorTransmission(string $transmissionId, int $maxAttempts = 20): array
    {
        $attempts = 0;
        
        while ($attempts < $maxAttempts) {
            $response = $this->transmissionEndpoint->getStatus($transmissionId);
            $status = json_decode($response->getBody()->getContents(), true);
            
            if (in_array($status['status'], ['delivered', 'failed', 'cancelled', 'rejected'])) {
                return $status;  // Terminal state reached
            }
            
            sleep(10);  // Poll every 10 seconds
            $attempts++;
        }
        
        return ['status' => 'timeout', 'message' => 'Monitoring timeout after ' . ($maxAttempts * 10) . ' seconds'];
    }
}
```

---

## Migration Path: v1 → v2

### Copy/Paste Strategy

**Step 1: Copy Enums (identical in both versions):**
```bash
# Enums are framework-agnostic
cp -r application/modules/core/src/Enums/LetsPeppol app/Enums/LetsPeppol
```

**Step 2: Copy Contracts (identical):**
```bash
cp application/modules/core/src/Contracts/GatewayClientInterface.php app/Contracts/
```

**Step 3: Copy Gateways (minimal changes):**
```bash
cp -r application/modules/core/src/Gateways/LetsPeppol app/Gateways/LetsPeppol
```

**Step 4: Adapt Services (database layer changes):**
- Replace `CI_DB_query_builder` → Laravel Eloquent models
- Replace `$this->db->` → Eloquent query builder
- Keep business logic identical

**Step 5: Copy Tests (minimal changes):**
```bash
cp -r tests/Unit/LetsPeppol tests/Unit/
cp -r tests/Fixtures/LetsPeppol tests/Fixtures/
```

**Changes needed in Laravel:**
- Remove `CI_DB_query_builder` type hints
- Use Eloquent models instead of Query Builder
- Update namespaces: `Core\` → `App\`

---

## Complete Implementation Checklist

### Phase 1: Core Infrastructure (DONE ✅)
- [x] Create `GatewayClientInterface` contract
- [x] Implement `ApiClient` base class
- [x] Implement `LetsPeppolGatewayClient`
- [x] Create 5 endpoint clients (23 endpoints total)
- [x] Create 10 Peppol/UBL enums
- [x] Add 23 JSON fixtures
- [x] Add 40 PHPUnit tests for endpoints

### Phase 2: Service Layer (TODO)
- [ ] Implement `InvoiceTrackingService`
- [ ] Implement `PayloadBuilderService`
- [ ] Implement `ErrorTrackingService`
- [ ] Implement `ParticipantSearchService`
- [ ] Implement `PeppolTransmissionService` (orchestrator)
- [ ] Add comprehensive tests for all services

### Phase 3: Database Layer (TODO)
- [ ] Create migration: `ip_peppol_invoice_transmissions`
- [ ] Create migration: `ip_peppol_received_documents`
- [ ] Create migration: `ip_peppol_transmission_errors`
- [ ] Create migration: `ip_peppol_participant_cache`
- [ ] Add foreign key constraints
- [ ] Add database indexes for performance

### Phase 4: UI/Controller Integration (TODO)
- [ ] Add Peppol status widget to invoice view
- [ ] Add real-time tracking dashboard
- [ ] Add participant search interface
- [ ] Add error monitoring interface
- [ ] Add retry functionality for failed transmissions
- [ ] Add WebSocket/polling for live updates

### Phase 5: Laravel v2 Preparation (TODO)
- [ ] Create Eloquent models for v2
- [ ] Create Filament resources for v2
- [ ] Adapt services for Eloquent (keep business logic identical)
- [ ] Create Laravel migrations
- [ ] Copy tests to Laravel structure

---

## Testing Strategy

### Unit Tests (Framework-Agnostic)
```php
// tests/Unit/InvoiceTrackingServiceTest.php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InvoiceTrackingServiceTest extends TestCase
{
    #[Test]
    public function it_tracks_invoice_through_peppol_network(): void
    {
        // Arrange
        $fakeEndpoint = new FakeTransmissionEndpoint();
        $fakeDb = new FakeDatabaseConnection();
        $service = new InvoiceTrackingService($fakeEndpoint, $fakeDb);
        
        // Act
        $location = $service->getInvoiceLocation(1);
        
        // Assert
        $this->assertEquals('delivered', $location['status']);
        $this->assertStringContainsString('Delivered to recipient', $location['location']);
    }
}
```

### Integration Tests (Framework-Specific)
- Test with real database
- Test with HTTP mocks
- Test error scenarios
- Test retry logic

---

## Key Implementation Notes

### 1. Status Enum Usage
```php
use Core\Enums\LetsPeppol\TransmissionStatus;

// Type-safe status checks
$status = TransmissionStatus::from($responseData['status']);

// Match expression for logic
$action = match($status) {
    TransmissionStatus::DELIVERED => 'mark_as_sent',
    TransmissionStatus::FAILED => 'retry_transmission',
    TransmissionStatus::CANCELLED => 'update_invoice_status',
    default => 'poll_again',
};
```

### 2. Payload Validation
```php
// Always validate before sending
$validation = $payloadBuilder->validatePayload($payload);
if (!$validation['valid']) {
    throw new InvalidPayloadException(implode(', ', $validation['errors']));
}
```

### 3. Error Handling Pattern
```php
try {
    $response = $endpoint->sendInvoice($payload);
    // Success path
} catch (\Throwable $e) {
    // Record error for tracking
    $errorTracking->recordError($transmissionId, [
        'error_code' => 'TRANSMISSION_FAILED',
        'error_message' => $e->getMessage(),
    ]);
    
    // Return user-friendly error
    return ['success' => false, 'error' => 'Transmission failed'];
}
```

### 4. Caching Strategy
```php
// Cache participant lookups (expensive network calls)
$participant = CacheHelper::remember("participant_{$peppolId}", function() use ($peppolId) {
    return $participantEndpoint->getDetails($peppolId);
}, 86400);  // 24 hours

// Cache search results
$results = CacheHelper::remember("search_{$query}_{$country}", function() use ($query, $country) {
    return $participantEndpoint->search($query, $country);
}, 3600);  // 1 hour
```

---

## Real-Time Tracking Implementation

### Polling Approach (Simple)
```javascript
// assets/js/peppol-tracker.js
function pollInvoiceStatus(invoiceId) {
    setInterval(async () => {
        const response = await fetch(`/invoices/peppol_status/${invoiceId}`);
        const status = await response.json();
        updateStatusDisplay(status);
    }, 10000);  // Poll every 10 seconds
}
```

### WebSocket Approach (Advanced)
```php
// Future: WebSocket server for real-time updates
// When transmission status changes, push to connected clients
$redis->publish("invoice:{$invoiceId}:status", json_encode($statusData));
```

---

## Country Code Integration

InvoicePlane already has comprehensive ISO country codes in `application/helpers/country_helper.php`.

**Using Existing Country Codes:**
```php
// Get all country codes
$countries = get_country_list('en');  // Returns ['AF' => 'Afghanistan', 'AL' => 'Albania', ...]

// Validate country code
$countryCode = 'SE';
$countryName = get_country_name('en', $countryCode);  // Returns 'Sweden'

// Use in participant search
$results = $participantSearchService->search($query, $countryCode);
```

**No need for separate country enum** - use existing helper functions.

---

## Success Criteria

### Functional Requirements
- [ ] Can send invoices to any Peppol participant
- [ ] Can track invoice location in real-time
- [ ] Can handle transmission errors gracefully
- [ ] Can search for participants by name/country
- [ ] Can validate participants before sending
- [ ] Can send credit notes
- [ ] Can retrieve documents
- [ ] Can monitor transmission status
- [ ] Can retry failed transmissions

### Technical Requirements
- [ ] 100% test coverage for services
- [ ] All enums used instead of magic strings
- [ ] All database queries use Query Builder/Eloquent
- [ ] All errors logged and tracked
- [ ] All payloads validated before transmission
- [ ] All participant lookups cached
- [ ] All network calls have timeout handling
- [ ] All responses sanitized before logging

### Framework Compatibility
- [ ] Core gateway code works in CI3
- [ ] Core gateway code works in Laravel
- [ ] Enums identical in both versions
- [ ] Business logic identical in both versions
- [ ] Only database layer differs (Query Builder vs Eloquent)
- [ ] Tests portable with minimal changes

---

## Estimated Effort

| Phase | Effort | Description |
|---|---|---|
| Service Layer | 16-24h | 5 services with full business logic |
| Database Layer | 8-12h | 4 migrations + indexes |
| UI Integration | 12-16h | Status widgets, search, error display |
| Testing | 16-24h | Service tests, integration tests |
| Laravel v2 Port | 8-12h | Eloquent adaptation, Filament resources |
| **Total** | **60-88h** | **1.5-2 weeks** |

---

## Programming Principles Applied

### SOLID
- **Single Responsibility**: Each service has one job (tracking, payloads, errors, search)
- **Open/Closed**: Add new providers without changing services
- **Liskov Substitution**: Any `GatewayClientInterface` implementation works
- **Interface Segregation**: Small, focused endpoint clients
- **Dependency Inversion**: Services depend on interfaces, not concrete classes

### DRY
- Shared `GatewayClientInterface` across all providers
- Shared enums for all status codes
- Shared payload builder logic
- Shared tracking service

### Dynamic Programming
- Memoization for participant lookups (`CacheHelper::remember()`)
- Memoization for search results
- O(1) enum lookups via match expressions

---

## Next Steps for AI Agents

1. **Implement Service Layer** (Phase 2) - Start with `InvoiceTrackingService`
2. **Create Database Migrations** (Phase 3) - v1 SQL + v2 Laravel migrations
3. **Add Service Tests** - 100% coverage requirement
4. **Integrate with Controllers** - Add Peppol actions to `invoices` module
5. **Build UI Components** - Status tracker, search interface
6. **Prepare Laravel Port** - Eloquent models, Filament resources

---

## Commands for AI Agents

```bash
# Phase 2: Implement services
cd /repo
touch application/modules/core/src/Services/InvoiceTrackingService.php
touch application/modules/core/src/Services/PayloadBuilderService.php
touch application/modules/core/src/Services/ErrorTrackingService.php
touch application/modules/core/src/Services/ParticipantSearchService.php
touch application/modules/core/src/Services/PeppolTransmissionService.php

# Phase 3: Create migrations
touch application/migrations/20260503_create_peppol_invoice_transmissions.sql
touch application/migrations/20260503_create_peppol_received_documents.sql
touch application/migrations/20260503_create_peppol_transmission_errors.sql

# Phase 4: Add tests
touch tests/Unit/InvoiceTrackingServiceTest.php
touch tests/Unit/PayloadBuilderServiceTest.php
touch tests/Unit/ErrorTrackingServiceTest.php
touch tests/Unit/ParticipantSearchServiceTest.php
touch tests/Unit/PeppolTransmissionServiceTest.php

# Validate
composer dump-autoload
vendor/bin/phpunit tests/Unit/
vendor/bin/pint
php -l application/modules/core/src/Services/*.php
```

---

## Summary

This guide provides complete specifications for building a **dual-compatible Peppol API client** that:

1. **Tracks invoices** through the Peppol network in real-time
2. **Builds payloads** compliant with Peppol BIS 3.0 and UBL 2.1
3. **Tracks errors** comprehensively for debugging and analytics
4. **Searches participants** with caching for performance
5. **Works identically** in CI3 and Laravel with minimal adaptation
6. **Follows SOLID/DRY/Dynamic Programming** principles religiously
7. **Provides real-time location** of invoices at any moment

**The gateway is provider-agnostic** - the same code works with LetsPeppol, StoreCove, or any Peppol provider by swapping the gateway client implementation.
