-- =====================================================
-- 1.8.0 — Integration module schema
--
-- ip_merchant_clients: configuration registry for third-party API
--   integrations (e-invoicing, payment gateways, Peppol access points).
--   Each row is one configured integration account, not a customer record.
--   Endpoint URLs are owned by the PHP provider client classes, not here.
--
-- ip_merchant_responses: unified response log for all outbound/inbound
--   integration calls. Extends the existing payment-gateway response log
--   to cover e-invoice providers (SuperPDP, Qonto) and Peppol providers
--   (LetsPeppol). Existing columns are preserved as-is.
--
--   Column notes:
--     merchant_response        — original payment-gateway message/status text;
--                                also used for the provider's human-readable
--                                outcome message on einvoice/peppol rows.
--     merchant_response_reference — original payment-gateway reference;
--                                also used for the provider-assigned document
--                                or transaction reference on other row types.
-- =====================================================


-- ---------------------------------------------------------
-- Restructure ip_merchant_clients
-- ---------------------------------------------------------

-- Convert merchant_type to a proper enum
ALTER TABLE `ip_merchant_clients`
  MODIFY `merchant_type` ENUM('superpdp','qonto','letspeppol') NOT NULL;

-- Replace the settings JSON blob with typed auth credential columns.
-- Only the columns relevant to the row's auth_type will be populated.
ALTER TABLE `ip_merchant_clients`
  DROP COLUMN `settings_json`,
  MODIFY `auth_type` ENUM('oauth2','api_key') NOT NULL DEFAULT 'oauth2',
  ADD COLUMN `oauth_token_url`    VARCHAR(500) NULL AFTER `auth_type`,
  ADD COLUMN `oauth_client_id`    VARCHAR(255) NULL AFTER `oauth_token_url`,
  ADD COLUMN `oauth_client_secret` VARCHAR(500) NULL AFTER `oauth_client_id`,
  ADD COLUMN `api_key`            VARCHAR(500) NULL AFTER `oauth_client_secret`;

-- Re-seed SuperPDP with structured columns instead of JSON
UPDATE `ip_merchant_clients`
SET
  auth_type        = 'oauth2',
  oauth_token_url  = 'https://api.superpdp.tech/oauth2/token',
  oauth_client_id  = '',
  oauth_client_secret = ''
WHERE merchant_type = 'superpdp';


-- ---------------------------------------------------------
-- Extend ip_merchant_responses for einvoice and Peppol
-- ---------------------------------------------------------

ALTER TABLE `ip_merchant_responses`

  -- Link to the integration account that produced this response
  ADD COLUMN `merchant_client_id` INT(11) NULL
    COMMENT 'FK to ip_merchant_clients; NULL for legacy payment-gateway rows'
    AFTER `invoice_id`,

  -- Distinguish inbound documents (received invoices) from outbound submissions
  ADD COLUMN `direction` ENUM('in','out') NOT NULL DEFAULT 'out'
    AFTER `merchant_client_id`,

  -- What kind of integration event this row represents
  ADD COLUMN `record_type` ENUM(
    'payment',
    'invoice_submit',
    'invoice_status',
    'peppol_event'
  ) NOT NULL DEFAULT 'payment'
    AFTER `direction`,

  -- Provider-assigned document or transmission identifier
  ADD COLUMN `external_id` VARCHAR(255) NULL
    AFTER `record_type`,

  -- Normalised outcome status (accepted, rejected, pending, error, …)
  ADD COLUMN `status` VARCHAR(50) NULL
    AFTER `external_id`,

  -- HTTP status code returned by the provider API
  ADD COLUMN `http_code` SMALLINT NULL
    AFTER `status`,

  -- Structured error code as returned by the provider (not a free-text dump)
  ADD COLUMN `error_code` VARCHAR(100) NULL
    AFTER `http_code`,

  -- Human-readable error detail, capped — not a raw JSON blob
  ADD COLUMN `error_detail` VARCHAR(500) NULL
    AFTER `error_code`,

  -- Peppol-specific: sender or receiver participant identifier (e.g. 0106:12345678)
  ADD COLUMN `peppol_participant_id` VARCHAR(100) NULL
    AFTER `error_detail`,

  -- Peppol-specific: BIS document type identifier
  ADD COLUMN `peppol_document_type` VARCHAR(200) NULL
    AFTER `peppol_participant_id`,

  ADD INDEX `idx_merchant_client_id` (`merchant_client_id`),
  ADD INDEX `idx_record_type`        (`record_type`),
  ADD INDEX `idx_external_id`        (`external_id`),
  ADD INDEX `idx_status`             (`status`);


-- ---------------------------------------------------------
-- Drop the now-superseded einvoice response table.
-- All response logging goes through ip_merchant_responses.
-- ---------------------------------------------------------

DROP TABLE IF EXISTS `ip_einvoice_responses`;
