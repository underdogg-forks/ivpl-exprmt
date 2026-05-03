# Peppol E-Invoicing Process Guide

> **Audience:** Users implementing Peppol e-invoicing for the first time with InvoicePlane
> 
> **Goal:** Understand complete Peppol workflows from provider setup to invoice tracking, including edge cases

---

## What is Peppol?

**Peppol** (Pan-European Public Procurement Online) is an international network for sending electronic business documents (e-invoices, credit notes, orders) securely between businesses using standardized formats (UBL/CII).

**Key Concepts:**
- **Access Point (Provider):** Your gateway to the Peppol network (LetsPeppol, StoreCove, etc.)
- **Peppol ID:** Unique participant identifier (e.g., `0190:123456789` for organization number)
- **UBL Format:** Universal Business Language - XML-based invoice format
- **Four-Corner Model:** Sender → Sender's AP → Receiver's AP → Receiver

---

## Process Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. SETUP: Choose Provider & Configure Credentials                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. PARTICIPANT DISCOVERY: Search & Validate Recipient                       │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. PAYLOAD BUILDING: Create UBL-compliant Invoice                          │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. INVOICE TRANSMISSION: Send via Provider API                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. TRACKING: Monitor Invoice Through Peppol Network                        │
├─────────────────────────────────────────────────────────────────────────────┤
│ 6. ERROR HANDLING: Manage Failures & Retries                               │
├─────────────────────────────────────────────────────────────────────────────┤
│ 7. RECEIPT MANAGEMENT: Receive Confirmations & Rejections                  │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Process 1: Provider Setup & Configuration

### Goal
Connect InvoicePlane to your chosen Peppol Access Point provider using OAuth2 authentication.

### User Journey

#### Step 1.1: Choose Your Peppol Provider

**Available Providers (InvoicePlane supports ANY with SOLID open/closed principle):**
- LetsPeppol
- StoreCove
- OpenPeppol
- Pagero
- Basware
- Tradeshift
- Custom provider (implement `GatewayClientInterface`)

**Provider Requirements:**
- OAuth2 client credentials flow support
- RESTful API for invoice submission
- Peppol network connectivity
- Real-time status tracking

#### Step 1.2: Register with Provider

1. **Sign up** with your chosen provider
2. **Verify your business identity** (VAT number, business registration)
3. **Get your Peppol ID** (e.g., `0190:987654321`)
4. **Obtain OAuth2 credentials:**
   - Client ID (e.g., `letspeppol_abc123xyz`)
   - Client Secret (e.g., `secret_abc123...`)
   - API Base URL (e.g., `https://api.letspeppol.com`)

#### Step 1.3: Configure in InvoicePlane

**UI Navigation:**
```
Settings → Integrations → Add Integration
```

**Form Fields:**
- **Provider:** Select from dropdown (LetsPeppol, StoreCove, etc.)
- **Client ID:** Enter from provider dashboard
- **Client Secret:** Enter from provider dashboard (encrypted in database)
- **Base URL:** API endpoint URL
- **Environment:** Production / Sandbox (for testing)

**Behind the Scenes:**

1. **Database Storage:**
```sql
-- Record created in ip_integrations
INSERT INTO ip_integrations (integration_name, integration_provider, integration_status)
VALUES ('LetsPeppol', 'letspeppol', 1);

-- Settings stored encrypted in ip_integration_settings
INSERT INTO ip_integration_settings (integration_id, setting_key, setting_value, is_encrypted)
VALUES 
(1, 'client_id', 'letspeppol_abc123xyz', 0),
(1, 'client_secret', '[ENCRYPTED]', 1),
(1, 'base_url', 'https://api.letspeppol.com', 0),
(1, 'environment', 'production', 0);
```

2. **OAuth2 Connection Test:**
```php
// InvoicePlane uses league/oauth2-client (PHPUnit tested)
use League\OAuth2\Client\Provider\GenericProvider;
use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;

$factory = new LetsPeppolOAuthProviderFactory();
$provider = $factory->make($credentials, $baseUrl);

// Request access token using client credentials flow
$token = $provider->getAccessToken('client_credentials');

// Token stored in ip_integration_tokens
INSERT INTO ip_integration_tokens (integration_id, token_type, token_value, expires_at)
VALUES (1, 'access_token', 'eyJhbGc...', '2026-05-03 08:00:00');
```

3. **Validation:**
- ✅ Test API connection with `/api/health` endpoint
- ✅ Verify OAuth2 token generation
- ✅ Validate token expiry handling (auto-refresh)
- ✅ Show success/error message to user

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Invalid Client ID** | 401 Unauthorized during OAuth | Display: "Invalid credentials. Check Client ID and Secret." |
| **Invalid Client Secret** | 401 Unauthorized during OAuth | Display: "Invalid credentials. Check Client ID and Secret." |
| **Expired Token** | 401 Unauthorized during API call | Auto-refresh token via OAuth2 flow |
| **Network Timeout** | Connection timeout (30s) | Retry with exponential backoff (3 attempts) |
| **Provider API Down** | 5xx server error | Display: "Provider temporarily unavailable. Retry later." |
| **Invalid Base URL** | DNS resolution failure | Display: "Invalid API URL. Check provider documentation." |
| **Sandbox vs Production** | Wrong environment setting | Display: "Participant not found. Check environment (sandbox/production)." |

**Implementation:**
```php
// All edge cases handled in ExceptionHandlingDecorator
// Controllers never need try/catch - safety is automatic

$factory = (new IntegrationProviderFactory())
    ->register('letspeppol', fn() => new LetsPeppolProvider($settingsService));

// Returns false on ANY error (logged automatically)
$result = $factory->make('letspeppol')->validateParticipant($peppolId);
if (!$result) {
    // Handle gracefully - error already logged
}
```

---

## Process 2: Participant Discovery

### Goal
Search and validate Peppol participants before sending invoices to ensure delivery capability.

### User Journey

#### Step 2.1: Add Client with Peppol ID

**UI: Clients → Add New Client**

Form includes new field:
- **Peppol ID** (optional): `0190:987654321` or `9999:ABC123`

**Validation Before Saving:**
```php
use Core\Gateways\LetsPeppol\Endpoints\ParticipantEndpoint;

$gateway = new LetsPeppolGatewayClient($baseUrl, $settings);
$participant = new ParticipantEndpoint($gateway);

// Validate Peppol ID exists in network
$isValid = $participant->validatePeppolId($peppolId);

if (!$isValid) {
    show_error('Peppol ID not found in network. Verify with your client.');
}
```

**Database:**
```sql
-- Peppol ID stored in ip_clients
UPDATE ip_clients 
SET client_peppol_id = '0190:987654321'
WHERE client_id = 123;
```

#### Step 2.2: Search for Participants

**UI: Clients → Search Peppol Participants**

Search form:
- **Query:** Company name or ID
- **Country:** Filter by ISO 3166-1 alpha-2 code (optional)
- **Capability:** Filter by document type (Invoice, Credit Note, etc.)

**Implementation:**
```php
$results = $participant->search(
    query: 'ACME Corporation',
    country: 'NO',  // Norway - uses InvoicePlane's country_helper.php
    capability: ParticipantCapability::INVOICE
);

// Response:
[
    [
        'peppol_id' => '0192:999999999',
        'name' => 'ACME Corporation AS',
        'country' => 'NO',
        'capabilities' => ['invoice', 'credit_note', 'order'],
        'registered_at' => '2024-01-15T10:00:00Z'
    ],
    // ... more results
]
```

**Caching for Performance:**
```php
use Core\Helpers\CacheHelper;

// Cache search results for 1 hour
$results = CacheHelper::remember("peppol_search_{$query}_{$country}", function() use ($participant, $query, $country) {
    return $participant->search($query, $country);
}, 3600);
```

#### Step 2.3: Get Participant Details

**Use Case:** User clicks on search result to see full capabilities.

```php
$details = $participant->getDetails($peppolId);

// Response includes:
[
    'peppol_id' => '0192:999999999',
    'name' => 'ACME Corporation AS',
    'country' => 'NO',
    'capabilities' => [
        DocumentType::INVOICE,
        DocumentType::CREDIT_NOTE,
        DocumentType::ORDER_RESPONSE
    ],
    'endpoint_url' => 'https://ap.acme.com',
    'certificate_valid_until' => '2027-01-15T10:00:00Z',
    'status' => 'active'
]
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Peppol ID Not Found** | 404 from API | Display: "Participant not registered in Peppol network." |
| **Invalid Peppol ID Format** | Client-side validation | Display: "Invalid format. Use: scheme:identifier (e.g., 0192:999999999)" |
| **Multiple Matches** | Search returns 100+ results | Implement pagination (30 per page) |
| **No Matches** | Empty search results | Display: "No participants found. Try broader search." |
| **Participant Deactivated** | Status: 'inactive' | Display: "Participant no longer active. Verify with recipient." |
| **Certificate Expired** | certificate_valid_until < now | Display: "Participant certificate expired. Contact recipient." |
| **Capability Mismatch** | Recipient doesn't support invoice | Display: "Recipient cannot receive invoices via Peppol. Use email instead." |
| **Country Mismatch** | Wrong country filter | Allow "All Countries" search option |

**Real-World Example:**

```php
// Client enters Peppol ID: 0190:987654321

// Step 1: Validate format
if (!preg_match('/^\d{4}:[A-Z0-9]+$/', $peppolId)) {
    throw new ValidationException('Invalid Peppol ID format');
}

// Step 2: Validate in network
$isValid = $participant->validatePeppolId($peppolId);
if (!$isValid) {
    // Edge case: Maybe typo? Suggest search
    $suggestions = $participant->search(
        query: substr($peppolId, 5), // Search by identifier part
        country: null
    );
    
    if (count($suggestions) > 0) {
        show_error("Peppol ID not found. Did you mean: {$suggestions[0]['peppol_id']}?");
    } else {
        show_error("Peppol ID not found in network.");
    }
}

// Step 3: Check capabilities
$details = $participant->getDetails($peppolId);
if (!in_array(DocumentType::INVOICE->value, $details['capabilities'])) {
    show_error("Recipient cannot receive invoices via Peppol.");
}

// ✅ All validations passed - save client
```

---

## Process 3: Payload Building (UBL-Compliant)

### Goal
Convert InvoicePlane invoice data to UBL 2.1 XML format required by Peppol.

### User Journey

When user clicks **"Send via Peppol"** on an invoice, InvoicePlane:

1. **Loads invoice data** (items, taxes, client info)
2. **Validates completeness** (all required UBL fields present)
3. **Builds UBL-compliant payload**
4. **Signs digitally** (if required by provider)
5. **Submits to provider API**

### Implementation (PayloadBuilderService)

```php
namespace Core\Services\Peppol;

use Core\Enums\LetsPeppol\DocumentType;

class PayloadBuilderService
{
    /**
     * Build UBL 2.1 Invoice payload from InvoicePlane invoice.
     *
     * @param array $invoice InvoicePlane invoice data (from Mdl_invoices)
     * @param array $client Client data (from Mdl_clients)
     * @param array $items Invoice items (from Mdl_invoice_items)
     * @return array UBL-compliant payload for Peppol submission
     */
    public function buildInvoicePayload(array $invoice, array $client, array $items): array
    {
        // Validate required fields
        $this->validateInvoiceData($invoice, $client, $items);
        
        return [
            'document_type' => DocumentType::INVOICE->value,
            'ubl_version' => '2.1',
            'customization_id' => 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0',
            
            // Invoice metadata
            'invoice_number' => $invoice['invoice_number'],
            'issue_date' => date('Y-m-d', strtotime($invoice['invoice_date_created'])),
            'due_date' => date('Y-m-d', strtotime($invoice['invoice_date_due'])),
            'currency_code' => $invoice['invoice_currency_code'] ?? 'EUR',
            
            // Supplier (sender)
            'supplier' => [
                'endpoint_id' => $this->getOwnPeppolId(),
                'endpoint_scheme' => $this->getPeppolScheme(),
                'name' => get_setting('invoice_company_name'),
                'tax_id' => get_setting('invoice_company_vat_id'),
                'address' => [
                    'street' => get_setting('invoice_company_address'),
                    'city' => get_setting('invoice_company_city'),
                    'postal_code' => get_setting('invoice_company_zip'),
                    'country' => get_setting('invoice_company_country'),
                ],
            ],
            
            // Customer (receiver)
            'customer' => [
                'endpoint_id' => $client['client_peppol_id'],
                'endpoint_scheme' => $this->extractPeppolScheme($client['client_peppol_id']),
                'name' => $client['client_name'],
                'tax_id' => $client['client_vat_id'] ?? null,
                'address' => [
                    'street' => $client['client_address_1'],
                    'city' => $client['client_city'],
                    'postal_code' => $client['client_zip'],
                    'country' => $client['client_country'],
                ],
            ],
            
            // Invoice lines
            'lines' => array_map(function($item, $index) {
                return [
                    'id' => (string)($index + 1),
                    'name' => $item['item_name'],
                    'description' => $item['item_description'] ?? '',
                    'quantity' => (float)$item['item_quantity'],
                    'unit_code' => 'C62', // UBL code for "unit" (piece)
                    'price_amount' => (float)$item['item_price'],
                    'tax_percent' => (float)$item['item_tax_rate_percent'],
                    'tax_category' => 'S', // Standard rate
                    'line_total' => (float)$item['item_subtotal'],
                ];
            }, $items, array_keys($items)),
            
            // Totals
            'totals' => [
                'subtotal' => (float)$invoice['invoice_subtotal'],
                'tax_amount' => (float)$invoice['invoice_tax_total'],
                'total' => (float)$invoice['invoice_total'],
                'payable_amount' => (float)$invoice['invoice_balance'],
            ],
            
            // Payment terms
            'payment_terms' => [
                'note' => "Payment due within {$invoice['invoice_payment_terms']} days",
                'due_date' => date('Y-m-d', strtotime($invoice['invoice_date_due'])),
            ],
        ];
    }
    
    /**
     * Validate invoice data completeness for Peppol submission.
     *
     * @throws ValidationException if required fields missing
     */
    private function validateInvoiceData(array $invoice, array $client, array $items): void
    {
        // Required supplier fields
        $requiredSupplierSettings = [
            'invoice_company_name' => 'Company name',
            'invoice_company_vat_id' => 'Company VAT ID',
            'invoice_company_address' => 'Company address',
            'invoice_company_city' => 'Company city',
            'invoice_company_country' => 'Company country',
        ];
        
        foreach ($requiredSupplierSettings as $setting => $label) {
            if (empty(get_setting($setting))) {
                throw new ValidationException("Missing required field: {$label}");
            }
        }
        
        // Required customer fields
        if (empty($client['client_peppol_id'])) {
            throw new ValidationException('Client must have a Peppol ID for e-invoicing');
        }
        
        if (empty($client['client_name'])) {
            throw new ValidationException('Client name is required');
        }
        
        // Required invoice fields
        if (empty($invoice['invoice_number'])) {
            throw new ValidationException('Invoice number is required');
        }
        
        if (empty($items)) {
            throw new ValidationException('Invoice must have at least one line item');
        }
        
        // Validate Peppol ID format
        if (!preg_match('/^\d{4}:[A-Z0-9]+$/', $client['client_peppol_id'])) {
            throw new ValidationException('Invalid Peppol ID format');
        }
    }
    
    /**
     * Get own Peppol ID from settings or provider.
     */
    private function getOwnPeppolId(): string
    {
        $peppolId = get_setting('peppol_participant_id');
        
        if (empty($peppolId)) {
            throw new \RuntimeException('Your Peppol ID not configured. Set in Settings → Integrations.');
        }
        
        return $peppolId;
    }
    
    /**
     * Extract scheme from Peppol ID (e.g., "0192" from "0192:999999999").
     */
    private function extractPeppolScheme(string $peppolId): string
    {
        return explode(':', $peppolId)[0];
    }
    
    /**
     * Get Peppol scheme for own organization.
     */
    private function getPeppolScheme(): string
    {
        return $this->extractPeppolScheme($this->getOwnPeppolId());
    }
}
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Missing Company VAT ID** | Empty `get_setting('invoice_company_vat_id')` | Block send, display: "Configure company VAT ID in Settings → System Settings" |
| **Client Has No Peppol ID** | Empty `client_peppol_id` | Block send, display: "Client not registered in Peppol. Add Peppol ID or use email." |
| **Invalid Invoice Total** | Calculated total ≠ stored total | Display: "Invoice totals mismatch. Recalculate invoice." |
| **Empty Line Items** | `count($items) === 0` | Block send, display: "Cannot send empty invoice" |
| **Missing Tax Rates** | Item has no tax rate | Use default: 0% (tax-exempt) |
| **Multi-Currency Invoice** | Invoice currency ≠ EUR | Check provider supports currency (some only support EUR) |
| **Negative Amounts** | Negative line totals | Display: "Use Credit Note for negative amounts" |
| **Future Date Invoice** | Invoice date > today | Warning: "Invoice dated in future - proceed?" |
| **Own Peppol ID Not Set** | Setting missing | Block send, display: "Configure your Peppol ID in Settings → Integrations" |

---

## Process 4: Invoice Transmission

### Goal
Send invoice to Peppol network and receive transmission ID for tracking.

### User Journey

#### Step 4.1: User Clicks "Send via Peppol"

**UI: Invoices → View Invoice → Actions → Send via Peppol**

**Workflow:**
```
1. Build payload (Process 3)
2. Send to provider API
3. Store transmission record
4. Display confirmation with tracking link
```

**Implementation:**
```php
use Core\Gateways\LetsPeppol\Endpoints\InvoiceEndpoint;
use Core\Enums\LetsPeppol\TransmissionStatus;

class PeppolTransmissionService
{
    /**
     * Send invoice via Peppol and track transmission.
     *
     * @param int $invoiceId InvoicePlane invoice ID
     * @return array Transmission record
     * @throws TransmissionException on failure
     */
    public function sendInvoice(int $invoiceId): array
    {
        // Load invoice, client, items
        $invoice = $this->invoiceModel->where('invoice_id', $invoiceId)->get()->row_array();
        $client = $this->clientModel->where('client_id', $invoice['client_id'])->get()->row_array();
        $items = $this->itemModel->where('invoice_id', $invoiceId)->get()->result_array();
        
        // Build UBL payload
        $payload = $this->payloadBuilder->buildInvoicePayload($invoice, $client, $items);
        
        // Send via provider API
        $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
        $invoiceEndpoint = new InvoiceEndpoint($gateway);
        
        $response = $invoiceEndpoint->sendInvoice($payload);
        
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new TransmissionException('Failed to send invoice: ' . $response->getBody());
        }
        
        $body = json_decode($response->getBody(), true);
        
        // Store transmission record
        $transmission = [
            'invoice_id' => $invoiceId,
            'transmission_id' => $body['transmission_id'],
            'external_document_id' => $body['document_id'] ?? null,
            'status' => TransmissionStatus::QUEUED->value,
            'peppol_participant_id' => $client['client_peppol_id'],
            'payload_json' => json_encode($payload),
            'sent_at' => date('Y-m-d H:i:s'),
        ];
        
        $this->db->insert('ip_peppol_invoice_transmissions', $transmission);
        $transmission['id'] = $this->db->insert_id();
        
        // Log success
        log_message('info', "Invoice {$invoice['invoice_number']} sent via Peppol. Transmission ID: {$body['transmission_id']}");
        
        return $transmission;
    }
}
```

**Response Format:**
```json
{
  "transmission_id": "TXN-2026-05-03-ABC123",
  "document_id": "DOC-20260503-001",
  "status": "queued",
  "message": "Invoice queued for transmission",
  "estimated_delivery": "2026-05-03T08:00:00Z"
}
```

#### Step 4.2: Display Confirmation

**UI: Success Flash Message**
```
✓ Invoice #INV-2026-001 sent via Peppol
  Transmission ID: TXN-2026-05-03-ABC123
  Track status: [View Transmission]
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Duplicate Submission** | Invoice already has transmission record | Display: "Invoice already sent. Use 'Resend' instead." |
| **Provider API Down** | 5xx server error | Queue for retry, display: "Queued for sending when provider available" |
| **Rate Limit Exceeded** | 429 Too Many Requests | Queue with delay, display: "Queued (rate limit)" |
| **Invalid Payload** | 400 Bad Request + validation errors | Parse errors, display: "Payload validation failed: [specific field]" |
| **Recipient Endpoint Down** | Provider accepts but marks as delivery pending | Status: QUEUED, will retry automatically |
| **Oversized Payload** | 413 Payload Too Large | Compress attachments or split invoice |
| **Network Timeout** | Connection timeout during send | Retry 3x with exponential backoff |
| **Token Expired Mid-Request** | 401 during send | Auto-refresh token, retry once |
| **Client Deleted** | Client no longer exists | Block send, display: "Client not found" |
| **Invoice Already Paid** | Invoice status = paid | Warning: "Invoice already marked paid. Send anyway?" |

**Retry Logic:**
```php
private function sendWithRetry(callable $sendFn, int $maxAttempts = 3): mixed
{
    $attempt = 1;
    $delay = 1; // seconds
    
    while ($attempt <= $maxAttempts) {
        try {
            return $sendFn();
        } catch (NetworkException $e) {
            if ($attempt === $maxAttempts) {
                throw $e;
            }
            
            log_message('warning', "Send attempt {$attempt} failed: {$e->getMessage()}. Retrying in {$delay}s...");
            sleep($delay);
            
            $attempt++;
            $delay *= 2; // Exponential backoff: 1s, 2s, 4s
        }
    }
}
```

---

## Process 5: Invoice Tracking Through Peppol Network

### Goal
Monitor invoice transmission status in real-time from submission to delivery confirmation.

### Transmission Lifecycle

```
QUEUED → PROCESSING → SENT → DELIVERED ✓
   ↓          ↓         ↓         ↓
FAILED     FAILED    TIMEOUT   REJECTED
```

### User Journey

#### Step 5.1: Real-Time Status Display

**UI: Invoices → View Invoice → Peppol Status Badge**

```
┌─────────────────────────────────────────────────┐
│ Invoice #INV-2026-001          Status: DELIVERED│
├─────────────────────────────────────────────────┤
│ Peppol Transmission:                            │
│   ● Queued:     2026-05-03 06:00:00            │
│   ● Processing: 2026-05-03 06:00:15            │
│   ● Sent:       2026-05-03 06:01:30            │
│   ● Delivered:  2026-05-03 06:45:12 ✓          │
│                                                  │
│ Transmission ID: TXN-2026-05-03-ABC123         │
│ Recipient: ACME Corp (0192:999999999)          │
│ [View Detailed Log] [Download Receipt]         │
└─────────────────────────────────────────────────┘
```

#### Step 5.2: Status Polling (Background Job)

**Cron Job (runs every 5 minutes):**
```php
/**
 * Poll provider API for transmission status updates.
 *
 * Updates all QUEUED, PROCESSING, or SENT transmissions.
 */
public function pollTransmissionStatuses(): void
{
    // Get all active transmissions (not final state)
    $activeStatuses = [
        TransmissionStatus::QUEUED->value,
        TransmissionStatus::PROCESSING->value,
        TransmissionStatus::SENT->value,
    ];
    
    $transmissions = $this->db
        ->where_in('status', $activeStatuses)
        ->where('sent_at >', date('Y-m-d H:i:s', strtotime('-7 days'))) // Only recent
        ->get('ip_peppol_invoice_transmissions')
        ->result_array();
    
    foreach ($transmissions as $transmission) {
        try {
            // Query provider for current status
            $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
            $txEndpoint = new TransmissionEndpoint($gateway);
            
            $response = $txEndpoint->getStatus($transmission['transmission_id']);
            $status = json_decode($response->getBody(), true);
            
            // Update local record
            $updates = [
                'status' => $status['status'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            // Track state transitions
            if ($status['status'] === TransmissionStatus::DELIVERED->value && empty($transmission['delivered_at'])) {
                $updates['delivered_at'] = date('Y-m-d H:i:s');
                
                // Optionally mark invoice as sent
                $this->db->where('invoice_id', $transmission['invoice_id'])
                    ->update('ip_invoices', ['invoice_status_id' => 2]); // Sent status
            }
            
            if (in_array($status['status'], [TransmissionStatus::FAILED->value, TransmissionStatus::REJECTED->value])) {
                $updates['failed_at'] = date('Y-m-d H:i:s');
                $updates['error_code'] = $status['error_code'] ?? 'UNKNOWN';
                $updates['error_message'] = $status['error_message'] ?? 'No details available';
            }
            
            $this->db->where('id', $transmission['id'])
                ->update('ip_peppol_invoice_transmissions', $updates);
                
            log_message('info', "Updated transmission {$transmission['transmission_id']}: {$status['status']}");
            
        } catch (\Throwable $e) {
            log_message('error', "Failed to poll transmission {$transmission['transmission_id']}: " . $e->getMessage());
        }
    }
}
```

#### Step 5.3: Manual Status Refresh

**UI Button:** "Refresh Peppol Status"

```php
// User clicks button → immediately polls provider
$status = $this->transmissionService->refreshStatus($transmissionId);

// Display updated status to user
flash_message('success', "Status updated: {$status['status']}");
```

#### Step 5.4: Where Is My Invoice Right Now?

**Real-Time Location Tracking:**

```php
/**
 * Get invoice location within Peppol network.
 *
 * Provides detailed journey tracking from submission to delivery.
 */
public function getInvoiceLocation(int $invoiceId): array
{
    $transmission = $this->db
        ->where('invoice_id', $invoiceId)
        ->get('ip_peppol_invoice_transmissions')
        ->row_array();
    
    if (!$transmission) {
        return ['status' => 'NOT_SENT', 'message' => 'Invoice not sent via Peppol'];
    }
    
    // Get real-time status from provider
    $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
    $txEndpoint = new TransmissionEndpoint($gateway);
    
    $response = $txEndpoint->getStatus($transmission['transmission_id']);
    $providerStatus = json_decode($response->getBody(), true);
    
    return [
        'transmission_id' => $transmission['transmission_id'],
        'status' => $providerStatus['status'],
        'location' => $this->mapStatusToLocation($providerStatus['status']),
        'progress' => $this->calculateProgress($providerStatus['status']),
        'timeline' => [
            'queued' => $transmission['sent_at'],
            'processing' => $providerStatus['processing_started_at'] ?? null,
            'sent' => $providerStatus['sent_at'] ?? null,
            'delivered' => $transmission['delivered_at'],
        ],
        'recipient' => [
            'peppol_id' => $transmission['peppol_participant_id'],
            'name' => $providerStatus['recipient_name'] ?? 'Unknown',
            'endpoint' => $providerStatus['recipient_endpoint'] ?? null,
        ],
        'network_hops' => [
            'sender_ap' => [
                'name' => 'LetsPeppol',
                'status' => 'delivered',
                'timestamp' => $providerStatus['sent_at'],
            ],
            'receiver_ap' => [
                'name' => $providerStatus['receiver_ap_name'] ?? 'Unknown',
                'status' => $providerStatus['receiver_ap_status'] ?? 'pending',
                'timestamp' => $providerStatus['received_at'] ?? null,
            ],
            'final_recipient' => [
                'name' => $providerStatus['recipient_name'] ?? 'Unknown',
                'status' => $this->mapToFinalStatus($providerStatus['status']),
                'timestamp' => $transmission['delivered_at'],
            ],
        ],
    ];
}

/**
 * Map transmission status to human-readable location.
 */
private function mapStatusToLocation(string $status): string
{
    return match(TransmissionStatus::from($status)) {
        TransmissionStatus::QUEUED => 'Waiting in send queue at your Access Point',
        TransmissionStatus::PROCESSING => 'Being processed by your Access Point',
        TransmissionStatus::SENT => 'In transit to recipient\'s Access Point',
        TransmissionStatus::DELIVERED => 'Delivered to recipient\'s Access Point',
        TransmissionStatus::FAILED => 'Failed at your Access Point',
        TransmissionStatus::TIMEOUT => 'Timed out waiting for recipient response',
        TransmissionStatus::REJECTED => 'Rejected by recipient',
        TransmissionStatus::CANCELLED => 'Cancelled by sender',
    };
}

/**
 * Calculate delivery progress percentage.
 */
private function calculateProgress(string $status): int
{
    return match(TransmissionStatus::from($status)) {
        TransmissionStatus::QUEUED => 10,
        TransmissionStatus::PROCESSING => 30,
        TransmissionStatus::SENT => 60,
        TransmissionStatus::DELIVERED => 100,
        TransmissionStatus::FAILED, 
        TransmissionStatus::TIMEOUT, 
        TransmissionStatus::REJECTED, 
        TransmissionStatus::CANCELLED => 0,
    };
}
```

**UI Display:**

```
┌─────────────────────────────────────────────────────────────┐
│ Invoice Journey Through Peppol Network                      │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  [✓] Your Access Point (LetsPeppol)                        │
│       └─ Sent at: 2026-05-03 06:01:30                      │
│                                                              │
│  [~] Recipient's Access Point (StoreCove)                  │
│       └─ In transit... (60% complete)                      │
│                                                              │
│  [ ] Final Recipient (ACME Corporation)                     │
│       └─ Waiting for delivery...                           │
│                                                              │
│  Estimated delivery: 2-4 hours                             │
│  Last updated: 1 minute ago                                │
│  [Refresh Status]                                           │
└─────────────────────────────────────────────────────────────┘
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Status Stuck in PROCESSING** | Status unchanged for 2+ hours | Fetch errors, display: "Contact provider support" |
| **Status Stuck in SENT** | Status unchanged for 24+ hours | Check timeout, mark as TIMEOUT |
| **Recipient AP Unreachable** | Provider returns "endpoint unavailable" | Status: SENT, poll until available or timeout (48h) |
| **Status Regression** | Status goes from SENT → QUEUED | Invalid, log warning, ignore (keep higher status) |
| **Transmission ID Not Found** | 404 from provider | Display: "Transmission ID expired or not found. Contact support." |
| **Polling Too Frequent** | Rate limit on status checks | Implement exponential backoff (5min → 15min → 1h) |
| **Multiple Status Updates** | Status changes multiple times quickly | Store history in separate table (transmission_status_history) |
| **Provider Returns Different ID** | transmission_id ≠ stored ID | Log mismatch, use provider ID as source of truth |

---

## Process 6: Error Handling

### Goal
Retrieve detailed error information, manage failures, and retry transmissions.

### User Journey

#### Step 6.1: Error Detection

**Automatic (Polling Job):**
```php
if ($status['status'] === TransmissionStatus::FAILED->value) {
    // Fetch detailed errors
    $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
    $txEndpoint = new TransmissionEndpoint($gateway);
    
    $errorsResponse = $txEndpoint->getErrors($transmission['transmission_id']);
    $errors = json_decode($errorsResponse->getBody(), true);
    
    // Store errors
    $this->db->where('id', $transmission['id'])->update('ip_peppol_invoice_transmissions', [
        'status' => TransmissionStatus::FAILED->value,
        'failed_at' => date('Y-m-d H:i:s'),
        'error_code' => $errors['error_code'] ?? ErrorCode::UNKNOWN_ERROR->value,
        'error_message' => $errors['message'] ?? 'Unknown error',
    ]);
    
    // Store detailed errors in tracking table
    foreach ($errors['details'] ?? [] as $error) {
        $this->db->insert('ip_peppol_transmission_errors', [
            'transmission_id' => $transmission['id'],
            'error_code' => $error['code'],
            'error_type' => $error['type'],
            'error_message' => $error['message'],
            'error_field' => $error['field'] ?? null,
            'error_severity' => $error['severity'] ?? 'error',
        ]);
    }
    
    // Send email notification to user
    $this->notifyTransmissionFailure($transmission, $errors);
}
```

#### Step 6.2: Error Display to User

**UI: Invoices → View Invoice → Peppol Status (Red Alert)**

```
┌─────────────────────────────────────────────────────────────┐
│ ⚠ Transmission Failed                                       │
├─────────────────────────────────────────────────────────────┤
│ Transmission ID: TXN-2026-05-03-ABC123                     │
│ Failed at: 2026-05-03 06:05:30                             │
│                                                              │
│ Error Code: VALIDATION_FAILED                               │
│ Message: Invalid VAT number format                         │
│                                                              │
│ Detailed Errors:                                            │
│ • Field: supplier.tax_id                                   │
│   Issue: VAT number must start with country code (NO)     │
│   Severity: error                                          │
│                                                              │
│ • Field: customer.endpoint_id                              │
│   Issue: Peppol ID not registered in network               │
│   Severity: error                                          │
│                                                              │
│ [Fix Issues] [Retry Transmission] [Contact Support]       │
└─────────────────────────────────────────────────────────────┘
```

#### Step 6.3: Error Tracking Table

**UI: Dashboard → Peppol Errors (Admin View)**

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Peppol Transmission Errors (Last 30 Days)                                   │
├──────────────┬────────────────┬─────────────────┬──────────────┬───────────┤
│ Invoice      │ Error Code     │ Message         │ Failed At    │ Status    │
├──────────────┼────────────────┼─────────────────┼──────────────┼───────────┤
│ INV-2026-001 │ VALIDATION_    │ Invalid VAT     │ 2026-05-03   │ ⚠ Pending │
│              │ FAILED         │ format          │ 06:05        │   Action  │
│              │                │                 │              │           │
│ INV-2026-045 │ PARTICIPANT_   │ Recipient not   │ 2026-05-02   │ ✓ Resolved│
│              │ NOT_FOUND      │ in network      │ 15:30        │   (Retry) │
│              │                │                 │              │           │
│ INV-2026-099 │ TIMEOUT        │ Recipient AP    │ 2026-04-30   │ ✗ Failed  │
│              │                │ unreachable     │ 09:00        │   (Manual)│
└──────────────┴────────────────┴─────────────────┴──────────────┴───────────┘
```

#### Step 6.4: Retry Failed Transmission

**User Action:** Click "Retry Transmission" button

```php
/**
 * Retry failed transmission with optional payload fixes.
 *
 * @param int $transmissionId Local transmission record ID
 * @param array|null $updatedPayload Optional corrected payload (if validation failed)
 * @param string|null $reason Reason for retry (logged)
 * @return bool Success
 */
public function retryTransmission(int $transmissionId, ?array $updatedPayload = null, ?string $reason = null): bool
{
    $transmission = $this->db
        ->where('id', $transmissionId)
        ->get('ip_peppol_invoice_transmissions')
        ->row_array();
    
    if (!$transmission) {
        throw new \RuntimeException('Transmission not found');
    }
    
    // Only allow retry for failed/timeout/rejected
    $retryableStatuses = [
        TransmissionStatus::FAILED->value,
        TransmissionStatus::TIMEOUT->value,
        TransmissionStatus::REJECTED->value,
    ];
    
    if (!in_array($transmission['status'], $retryableStatuses)) {
        throw new \LogicException("Cannot retry transmission with status: {$transmission['status']}");
    }
    
    // Use updated payload or original
    $payload = $updatedPayload ?? json_decode($transmission['payload_json'], true);
    
    // Send retry request to provider
    $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
    $txEndpoint = new TransmissionEndpoint($gateway);
    
    $response = $txEndpoint->retry(
        transmissionId: $transmission['transmission_id'],
        payload: $payload,
        reason: $reason ?? 'Manual retry from InvoicePlane'
    );
    
    if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
        // Update status to QUEUED (restart lifecycle)
        $this->db->where('id', $transmissionId)->update('ip_peppol_invoice_transmissions', [
            'status' => TransmissionStatus::QUEUED->value,
            'payload_json' => json_encode($payload),
            'sent_at' => date('Y-m-d H:i:s'),
            'failed_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);
        
        log_message('info', "Retried transmission {$transmission['transmission_id']}");
        return true;
    }
    
    return false;
}
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **Max Retries Exceeded** | Retry count > 3 | Block retry, display: "Max retries exceeded. Contact support." |
| **Same Error After Retry** | Error code unchanged | Display: "Persistent error. Check invoice data manually." |
| **Error Code Not Recognized** | Unknown error code | Use generic message, log for investigation |
| **Partial Delivery** | Delivered to AP but not recipient | Status: SENT (not DELIVERED), keep polling |
| **Provider Changes Transmission ID** | New transmission_id in retry response | Update local record with new ID |
| **Invoice Modified After Send** | Invoice data changed | Warning: "Invoice modified. Resend instead of retry?" |
| **Retry Not Supported** | Provider returns 405 Method Not Allowed | Display: "Retry not supported. Create new transmission." |
| **Cascading Failures** | Multiple invoices fail with same error | Bulk retry option, display: "Provider issue detected" |

---

## Process 7: Receipt Management

### Goal
Receive and process application responses (acceptances/rejections) from recipients.

### User Journey

#### Step 7.1: Automatic Receipt Polling

**Cron Job (runs every 10 minutes):**
```php
/**
 * Poll for delivery receipts on delivered transmissions.
 */
public function pollDeliveryReceipts(): void
{
    $transmissions = $this->db
        ->where('status', TransmissionStatus::DELIVERED->value)
        ->where('receipt_json IS NULL') // Not yet received
        ->get('ip_peppol_invoice_transmissions')
        ->result_array();
    
    foreach ($transmissions as $transmission) {
        try {
            $gateway = new LetsPeppolGatewayClient($this->baseUrl, $this->settings);
            $txEndpoint = new TransmissionEndpoint($gateway);
            
            $response = $txEndpoint->getReceipt($transmission['transmission_id']);
            $receipt = json_decode($response->getBody(), true);
            
            if ($receipt['available']) {
                // Store receipt
                $this->db->where('id', $transmission['id'])->update('ip_peppol_invoice_transmissions', [
                    'receipt_json' => json_encode($receipt),
                ]);
                
                // Process receipt type
                $this->processReceipt($transmission, $receipt);
            }
        } catch (\Throwable $e) {
            log_message('debug', "No receipt yet for {$transmission['transmission_id']}");
        }
    }
}

/**
 * Process received application response.
 */
private function processReceipt(array $transmission, array $receipt): void
{
    $receiptType = ReceiptType::from($receipt['type']);
    $receiptStatus = ReceiptStatus::from($receipt['status']);
    
    switch ($receiptStatus) {
        case ReceiptStatus::ACCEPTED:
            // Invoice accepted by recipient
            log_message('info', "Invoice {$transmission['invoice_id']} accepted by recipient");
            
            // Optionally update invoice status
            $this->db->where('invoice_id', $transmission['invoice_id'])
                ->update('ip_invoices', ['invoice_status_id' => 3]); // Viewed/Accepted
            break;
            
        case ReceiptStatus::REJECTED:
            // Invoice rejected - may need credit note
            log_message('warning', "Invoice {$transmission['invoice_id']} rejected: {$receipt['rejection_reason']}");
            
            // Create task for user
            $this->createFollowUpTask(
                invoice_id: $transmission['invoice_id'],
                type: 'invoice_rejected',
                message: "Recipient rejected invoice: {$receipt['rejection_reason']}"
            );
            break;
            
        case ReceiptStatus::CONDITIONALLY_ACCEPTED:
            // Accepted with notes
            log_message('info', "Invoice {$transmission['invoice_id']} conditionally accepted: {$receipt['notes']}");
            break;
            
        case ReceiptStatus::UNDER_REVIEW:
            // Recipient reviewing invoice
            log_message('debug', "Invoice {$transmission['invoice_id']} under review");
            break;
    }
}
```

#### Step 7.2: Receipt Display to User

**UI: Invoices → View Invoice → Peppol Receipt**

```
┌─────────────────────────────────────────────────────────────┐
│ ✓ Application Response Received                            │
├─────────────────────────────────────────────────────────────┤
│ Receipt Type: Application Response (UBL)                   │
│ Status: ACCEPTED ✓                                          │
│ Received: 2026-05-03 07:15:00                              │
│                                                              │
│ Response Details:                                           │
│ • Status Code: AP (Accepted)                               │
│ • Responding Party: ACME Corporation                       │
│ • Response Date: 2026-05-03                                │
│ • Note: "Payment scheduled for 2026-05-30"                 │
│                                                              │
│ [Download Receipt XML] [View Full Details]                 │
└─────────────────────────────────────────────────────────────┘
```

### Edge Cases & Error Handling

| Scenario | Detection | Resolution |
|----------|-----------|------------|
| **No Receipt After 7 Days** | delivered_at + 7 days, no receipt | Mark as "assumed accepted", display warning |
| **Receipt After Invoice Paid** | Receipt arrives after payment | Informational only, display: "Payment already recorded" |
| **Rejection After Acceptance** | Status changes ACCEPTED → REJECTED | Invalid, log error, keep ACCEPTED status |
| **Multiple Receipts** | Provider sends multiple receipts | Store all, display most recent |
| **Malformed Receipt** | JSON parse error | Log raw receipt, display: "Receipt format error" |
| **Receipt for Unknown Transmission** | transmission_id not in database | Log, ignore (may be legacy data) |
| **Conditional Acceptance with Action** | Status notes require action | Create task, notify user |

---

## Process Summary: Complete Workflow Example

### Scenario: Send Invoice from InvoicePlane to ACME Corporation

**Setup (One-Time):**
1. ✅ Registered with LetsPeppol
2. ✅ Configured OAuth2 credentials in InvoicePlane Settings → Integrations
3. ✅ Set own Peppol ID: `0192:111111111` (Norway)
4. ✅ OAuth2 connection tested successfully (PHPUnit tested with `league/oauth2-client`)

**Invoice Creation:**
1. ✅ Created client "ACME Corporation" with Peppol ID: `0192:999999999`
2. ✅ Validated Peppol ID in network (participant exists, supports invoices)
3. ✅ Created invoice INV-2026-001 for €1,250.00

**Transmission:**
```php
// User clicks "Send via Peppol"

// Step 1: Build UBL payload
$payload = PayloadBuilderService::buildInvoicePayload($invoice, $client, $items);
// Validates: company VAT, client Peppol ID, line items, totals

// Step 2: Send to LetsPeppol
$result = InvoiceEndpoint::sendInvoice($payload);
// Returns: transmission_id = "TXN-2026-05-03-ABC123"

// Step 3: Store transmission record
INSERT INTO ip_peppol_invoice_transmissions 
(invoice_id, transmission_id, status, peppol_participant_id, sent_at)
VALUES (123, 'TXN-2026-05-03-ABC123', 'queued', '0192:999999999', NOW());

// Step 4: Display confirmation
flash_message('success', 'Invoice sent via Peppol. Transmission ID: TXN-2026-05-03-ABC123');
```

**Tracking Timeline:**

| Time | Status | Location | Action |
|------|--------|----------|--------|
| 06:00:00 | QUEUED | LetsPeppol queue | Automatic |
| 06:00:15 | PROCESSING | LetsPeppol validation | Automatic |
| 06:01:30 | SENT | In transit to StoreCove | Automatic |
| 06:45:12 | DELIVERED | StoreCove delivered to ACME | Automatic |
| 07:15:00 | ACCEPTED (Receipt) | ACME accepted invoice | Display to user |

**Where Is My Invoice?** (User clicks "Track"):

```
Current Location: Delivered to recipient ✓
Progress: 100%

Timeline:
• [✓] Queued at your Access Point (0 min)
• [✓] Processed by LetsPeppol (15 sec)
• [✓] Sent to StoreCove (1 min 15 sec)  
• [✓] Delivered to ACME Corporation (45 min)
• [✓] Accepted by recipient (1 hr 15 min)

Next: Awaiting payment confirmation
```

### Edge Cases Encountered

**During Setup:**
- ❌ Wrong Client Secret → 401 error → User re-enters credentials → ✅ Success

**During Participant Validation:**
- ❌ Typo in Peppol ID (`0192:99999999` vs `0192:999999999`) → Not found → Search suggests correct ID → ✅ User corrects

**During Transmission:**
- ❌ Missing company VAT ID → Validation fails before send → User adds VAT ID in settings → ✅ Retry succeeds

**During Tracking:**
- ⚠ Status stuck in SENT for 12 hours → Recipient's AP was down → Eventually delivered after 24h → ✅ Success
- ⚠ No receipt after 7 days → Auto-marked as "assumed accepted" → ✅ No blocking issue

---

## Database Schema

### InvoicePlane v1 (CodeIgniter 3)

```sql
-- Provider/Gateway configuration (already exists)
CREATE TABLE ip_integrations (
    integration_id INT PRIMARY KEY AUTO_INCREMENT,
    integration_name VARCHAR(255) NOT NULL,
    integration_provider VARCHAR(255) NOT NULL UNIQUE,
    integration_status TINYINT(1) DEFAULT 1,
    integration_created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    integration_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Encrypted settings (already exists)
CREATE TABLE ip_integration_settings (
    integration_setting_id INT PRIMARY KEY AUTO_INCREMENT,
    integration_id INT NOT NULL,
    setting_key VARCHAR(190) NOT NULL,
    setting_value TEXT NOT NULL,
    is_encrypted TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (integration_id, setting_key),
    FOREIGN KEY (integration_id) REFERENCES ip_integrations(integration_id) ON DELETE CASCADE
);

-- OAuth2 tokens (already exists)
CREATE TABLE ip_integration_tokens (
    integration_token_id INT PRIMARY KEY AUTO_INCREMENT,
    integration_id INT NOT NULL,
    token_type VARCHAR(50) NOT NULL,
    token_value TEXT NOT NULL,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (integration_id) REFERENCES ip_integrations(integration_id) ON DELETE CASCADE
);

-- Peppol ID in clients (already exists)
ALTER TABLE ip_clients 
ADD COLUMN client_peppol_id VARCHAR(255) NULL AFTER client_tax_code;

-- NEW: Invoice transmissions tracking
CREATE TABLE ip_peppol_invoice_transmissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    transmission_id VARCHAR(255) NOT NULL UNIQUE,
    external_document_id VARCHAR(255),
    status ENUM('queued','processing','sent','delivered','failed','timeout','rejected','cancelled') DEFAULT 'queued',
    peppol_participant_id VARCHAR(100) NOT NULL,
    payload_json JSON NOT NULL,
    receipt_json JSON,
    sent_at DATETIME,
    delivered_at DATETIME,
    failed_at DATETIME,
    error_code VARCHAR(100),
    error_message TEXT,
    retry_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES ip_invoices(invoice_id) ON DELETE CASCADE,
    INDEX idx_transmission_id (transmission_id),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at)
);

-- NEW: Detailed error tracking
CREATE TABLE ip_peppol_transmission_errors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transmission_id INT NOT NULL,
    error_code VARCHAR(100) NOT NULL,
    error_type VARCHAR(50),
    error_message TEXT,
    error_field VARCHAR(255),
    error_severity ENUM('error','warning','info') DEFAULT 'error',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transmission_id) REFERENCES ip_peppol_invoice_transmissions(id) ON DELETE CASCADE,
    INDEX idx_transmission_id (transmission_id),
    INDEX idx_error_code (error_code)
);

-- NEW: Received documents (future: when receiving invoices)
CREATE TABLE ip_peppol_received_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id VARCHAR(255) NOT NULL UNIQUE,
    document_type ENUM('invoice','credit_note','order','order_response') NOT NULL,
    transmission_id VARCHAR(255),
    sender_peppol_id VARCHAR(100) NOT NULL,
    status ENUM('pending','processing','imported','rejected') DEFAULT 'pending',
    ubl_xml LONGTEXT,
    metadata_json JSON,
    received_at DATETIME,
    processed_at DATETIME,
    imported_invoice_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (imported_invoice_id) REFERENCES ip_invoices(invoice_id) ON DELETE SET NULL,
    INDEX idx_document_id (document_id),
    INDEX idx_sender (sender_peppol_id),
    INDEX idx_status (status)
);

-- NEW: Participant cache (reduce API calls)
CREATE TABLE ip_peppol_participants_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    peppol_id VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255),
    country VARCHAR(2),
    capabilities JSON,
    endpoint_url VARCHAR(500),
    status ENUM('active','inactive') DEFAULT 'active',
    cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    INDEX idx_peppol_id (peppol_id),
    INDEX idx_expires_at (expires_at)
);
```

### InvoicePlane v2 (Laravel + Filament)

```php
// Laravel migrations - same schema, eloquent models

namespace App\Models\Peppol;

use Illuminate\Database\Eloquent\Model;

class InvoiceTransmission extends Model
{
    protected $table = 'peppol_invoice_transmissions';
    
    protected $fillable = [
        'invoice_id',
        'transmission_id',
        'status',
        'peppol_participant_id',
        'payload_json',
        'sent_at',
    ];
    
    protected $casts = [
        'status' => TransmissionStatus::class,  // Enum casting
        'payload_json' => 'array',
        'receipt_json' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
    
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
    
    public function errors(): HasMany
    {
        return $this->hasMany(TransmissionError::class, 'transmission_id');
    }
}
```

---

## SOLID Principles Applied

### Open/Closed Principle (OCP)

**ANY provider can be added without modifying existing code:**

```php
// Add StoreCove provider
class StoreCoveProvider implements IntegrationProviderInterface
{
    public function validateParticipant(string $participantId): bool { /* ... */ }
    public function sendInvoice(array $payload): bool { /* ... */ }
}

// Register in factory (ONLY place to modify)
$factory->register('storecove', fn() => new StoreCoveProvider($settingsService));

// Everything else works unchanged:
// - Database tables (provider-agnostic)
// - UI forms (dynamic provider dropdown)
// - Tracking logic (same statuses)
// - Error handling (same patterns)
```

### Dependency Inversion Principle (DIP)

**High-level code depends on interfaces, not concrete implementations:**

```php
// Controller depends on interface (not LetsPeppolProvider)
public function sendInvoice(int $invoiceId)
{
    $provider = $this->factory->make($this->selectedProvider); // Could be ANY provider
    $result = $provider->sendInvoice($payload);
}

// Tests use fakes (not mocks)
$fake = new FakeLetsPeppolHttpClient(200);
$gateway = new LetsPeppolGatewayClient('https://api.test', [], $fake);
```

### Single Responsibility Principle (SRP)

**Each class has ONE job:**
- `PayloadBuilderService`: Build UBL payloads
- `InvoiceTrackingService`: Track transmission status
- `ErrorTrackingService`: Manage errors
- `ParticipantSearchService`: Search participants
- `InvoiceEndpoint`: Invoice API operations
- `TransmissionEndpoint`: Transmission API operations

---

## Testing Strategy

### PHPUnit Tests (Framework-Agnostic)

**Same tests work in both v1 (CI3) and v2 (Laravel):**

```php
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fakes\FakeLetsPeppolHttpClient;

class InvoiceTransmissionTest extends TestCase
{
    #[Test]
    public function it_sends_invoice_successfully(): void
    {
        // Arrange
        $http = new FakeLetsPeppolHttpClient(200, [
            'transmission_id' => 'TXN-TEST-123',
            'status' => 'queued',
        ]);
        
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);
        
        $payload = ['invoice_number' => 'INV-001', /* ... */];
        
        // Act
        $response = $endpoint->sendInvoice($payload);
        $body = json_decode($response->getBody(), true);
        
        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('TXN-TEST-123', $body['transmission_id']);
        $http->assertRequestMade('POST', 'invoices.send');
    }
    
    #[Test]
    public function it_handles_validation_errors_from_provider(): void
    {
        // Arrange
        $http = new FakeLetsPeppolHttpClient(400, [
            'error_code' => 'VALIDATION_FAILED',
            'message' => 'Invalid VAT number',
            'details' => [
                ['field' => 'supplier.tax_id', 'message' => 'Must start with NO'],
            ],
        ]);
        
        $gateway = new LetsPeppolGatewayClient('https://api.test', [], $http);
        $endpoint = new InvoiceEndpoint($gateway);
        
        // Act
        $response = $endpoint->sendInvoice(['invalid' => 'payload']);
        
        // Assert
        $this->assertEquals(400, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertEquals('VALIDATION_FAILED', $body['error_code']);
    }
}
```

**Fixtures for Realistic Testing:**
```php
// tests/Fixtures/LetsPeppol/invoice_sent.json
{
  "transmission_id": "TXN-2026-05-03-ABC123",
  "document_id": "DOC-20260503-001",
  "status": "queued",
  "recipient": {
    "peppol_id": "0192:999999999",
    "name": "ACME Corporation AS"
  },
  "estimated_delivery": "2026-05-03T08:00:00Z",
  "message": "Invoice queued for transmission"
}
```

---

## Copy/Paste Migration: v1 → v2

**Same Code, Different Framework:**

### Service Layer (Identical)

```php
// InvoicePlane v1 (CodeIgniter)
$service = new \Core\Services\Peppol\InvoiceTrackingService($gateway, $db);
$location = $service->getInvoiceLocation($invoiceId);

// InvoicePlane v2 (Laravel) - EXACT SAME CODE
$service = new Core\Services\Peppol\InvoiceTrackingService($gateway, $db);
$location = $service->getInvoiceLocation($invoiceId);
```

### Database Queries

```php
// v1: CodeIgniter Query Builder
$this->db->where('invoice_id', $invoiceId)
    ->get('ip_peppol_invoice_transmissions')
    ->row_array();

// v2: Laravel Eloquent
InvoiceTransmission::where('invoice_id', $invoiceId)->first()->toArray();

// Solution: Repository pattern abstracts both
interface TransmissionRepository {
    public function findByInvoiceId(int $invoiceId): ?array;
}
```

### Tests (100% Identical)

```php
// Both v1 and v2 use SAME test files
// tests/Unit/InvoiceTrackingServiceTest.php works in both!

$fake = new FakeLetsPeppolHttpClient(200);
$service = new InvoiceTrackingService($gateway, $db);
$result = $service->getInvoiceLocation(123);

$this->assertEquals('DELIVERED', $result['status']);
```

---

## Error Code Reference

### ErrorCode Enum (20+ Peppol Standard Codes)

| Code | Meaning | User Action |
|------|---------|-------------|
| `VALIDATION_FAILED` | Payload doesn't meet UBL schema | Fix indicated field, retry |
| `PARTICIPANT_NOT_FOUND` | Recipient Peppol ID invalid | Verify ID with client |
| `ENDPOINT_UNAVAILABLE` | Recipient's AP unreachable | Wait 24h, auto-retry |
| `AUTHENTICATION_FAILED` | OAuth2 token invalid | Re-enter credentials |
| `RATE_LIMIT_EXCEEDED` | Too many requests | Queue for later |
| `DUPLICATE_DOCUMENT` | Invoice number already sent | Use different invoice number |
| `UNSUPPORTED_DOCUMENT_TYPE` | Recipient can't receive this type | Check capabilities |
| `INVALID_VAT_NUMBER` | VAT format incorrect | Check VAT format for country |
| `CERTIFICATE_INVALID` | Digital signature issue | Contact provider |
| `TIMEOUT` | Network timeout | Auto-retry 3x |
| `INSUFFICIENT_FUNDS` | Provider account issue | Contact provider billing |
| `NETWORK_ERROR` | Peppol network issue | Wait, auto-retry |

---

## Production Checklist

### Before Going Live

- [ ] Provider account verified (not sandbox)
- [ ] OAuth2 credentials configured (production environment)
- [ ] Own Peppol ID registered and validated
- [ ] Company VAT ID configured in settings
- [ ] Test invoice sent successfully to test recipient
- [ ] Polling cron jobs configured (5min status, 10min receipts)
- [ ] Error notification emails configured
- [ ] Backup strategy for transmission records
- [ ] Monitoring configured (Sentry, logs)
- [ ] Team trained on error resolution

### Monitoring & Alerts

**Alert Triggers:**
- ⚠ Failed transmissions > 5% in 24h
- ⚠ No deliveries in 2 hours (provider issue?)
- ⚠ Error rate spike (network issue?)
- ⚠ OAuth token refresh failures
- ⚠ Polling job not running

---

## SMART Stories for Implementation

### Story 1: Provider Configuration
**As a** InvoicePlane admin  
**I want to** configure Peppol provider credentials  
**So that** I can send e-invoices via Peppol network

**Acceptance Criteria:**
- Can select provider from dropdown
- Can enter Client ID, Client Secret, Base URL
- System validates OAuth2 connection immediately
- Credentials encrypted in database
- Success/error message displayed
- Settings editable after initial configuration

**Edge Cases:**
- Invalid credentials show clear error
- Token auto-refreshes on expiry
- Multiple providers can be configured (future)

---

### Story 2: Client Peppol ID Validation
**As a** user  
**I want to** validate client Peppol IDs before saving  
**So that** I ensure invoices can be delivered

**Acceptance Criteria:**
- Peppol ID field on client form (optional)
- Real-time validation on blur
- Search button if ID not found
- Capability check (can receive invoices)
- Validation results cached (1 hour)
- Manual override option for admins

**Edge Cases:**
- Invalid format shows pattern example
- Not found suggests search
- Inactive participant shows warning
- No capability shows alternative methods

---

### Story 3: Invoice Transmission with Tracking
**As a** user  
**I want to** send invoices via Peppol and track delivery  
**So that** I know invoices reached recipients

**Acceptance Criteria:**
- "Send via Peppol" button on invoice view
- Payload validation before send
- Transmission ID displayed immediately
- Status badge updates automatically
- Timeline shows all state transitions
- "Track Invoice" page shows network location
- Error details if transmission fails
- Retry option for failed transmissions

**Edge Cases:**
- Duplicate send prevented
- Status polling handles provider downtime
- Timeout after 48h (mark as failed)
- Receipt handling (accepted/rejected)
- Can cancel queued/processing transmissions

---

### Story 4: Error Management Dashboard
**As an** admin  
**I want to** see all Peppol errors in one place  
**So that** I can resolve issues efficiently

**Acceptance Criteria:**
- Dashboard shows failed transmissions (last 30 days)
- Filterable by error code
- Sortable by date
- Bulk retry option
- Error details with resolution hints
- Export to CSV for analysis

**Edge Cases:**
- Errors older than 30 days archived
- Resolved errors marked distinctly
- Critical errors highlighted
- Recurring errors grouped

---

## Next Steps for AI Implementation

### Phase 1: Core Services (20-30 hours)
- [ ] `PayloadBuilderService` with UBL 2.1 compliance
- [ ] `InvoiceTrackingService` with real-time location
- [ ] `ErrorTrackingService` with error persistence
- [ ] `ParticipantSearchService` with caching
- [ ] Database migrations (4 new tables)

### Phase 2: UI Integration (15-20 hours)
- [ ] Settings → Integrations → Provider configuration form
- [ ] Clients → Add Peppol ID field with validation
- [ ] Invoices → "Send via Peppol" button
- [ ] Invoices → Peppol status badge + timeline
- [ ] Dashboard → Peppol errors widget
- [ ] Translations (EN, NL, DE, FR)

### Phase 3: Background Jobs (10-15 hours)
- [ ] Status polling cron job (5min interval)
- [ ] Receipt polling cron job (10min interval)
- [ ] Token refresh job (hourly)
- [ ] Error notification job (real-time)
- [ ] Cleanup job (archive old records)

### Phase 4: Testing (15-25 hours)
- [ ] Unit tests for all services (target: 100% coverage)
- [ ] Integration tests with fakes
- [ ] E2E tests with Playwright (complete workflows)
- [ ] Load tests (100+ concurrent sends)
- [ ] Edge case tests (all scenarios from this doc)

### Phase 5: Documentation (5-8 hours)
- [ ] User guide (with screenshots)
- [ ] Admin guide (troubleshooting)
- [ ] API documentation
- [ ] Deployment guide
- [ ] Migration guide (v1 → v2)

**Total Estimated Effort:** 65-98 hours (1.5-2.5 weeks)

---

## Glossary

**Access Point (AP):** Service provider that connects you to Peppol network  
**Four-Corner Model:** Sender → Sender's AP → Receiver's AP → Receiver  
**Peppol ID:** Unique participant identifier (scheme:identifier)  
**UBL:** Universal Business Language (XML invoice format)  
**CII:** Cross Industry Invoice (alternative to UBL)  
**Application Response:** Recipient's acceptance/rejection message  
**MDN:** Message Disposition Notification (delivery confirmation)  
**Transmission ID:** Unique identifier for tracking invoice through network  
**Document ID:** Provider's internal document reference  
**Endpoint:** API URL where participant receives documents  
**Capability:** Document types a participant can receive  
**Scheme:** Identifier type (0192 = Norwegian org number, 9999 = other)

---

## Resources

- [Peppol BIS Billing 3.0](https://docs.peppol.eu/poacc/billing/3.0/) - Invoice specification
- [UBL 2.1 Documentation](http://docs.oasis-open.org/ubl/UBL-2.1.html) - XML format
- [ISO 6523 ICD](https://docs.peppol.eu/edelivery/codelists/) - Participant identifier schemes
- [Peppol Directory](https://directory.peppol.eu/) - Search registered participants
- InvoicePlane country codes: `application/helpers/country_helper.php`

---

## Support

**For Provider Issues:**
- LetsPeppol: support@letspeppol.com
- StoreCove: support@storecove.com
- Check provider status page

**For InvoicePlane Integration:**
- GitHub: https://github.com/InvoicePlane/InvoicePlane/issues
- Community: https://community.invoiceplane.com
- This guide: Complete workflow reference
