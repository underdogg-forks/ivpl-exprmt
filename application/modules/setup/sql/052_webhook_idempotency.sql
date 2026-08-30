-- Webhook idempotency tracking table
-- Prevents duplicate webhook processing across all integrations

CREATE TABLE IF NOT EXISTS `ip_webhook_events` (
  `webhook_event_id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL COMMENT 'paypal, stripe, letspeppol, superpdp, qonto',
  `external_event_id` varchar(255) NOT NULL COMMENT 'Provider-specific webhook event ID',
  `event_type` varchar(100) NOT NULL COMMENT 'charge.succeeded, payment.received, etc',
  `payload_hash` varchar(64) NOT NULL COMMENT 'SHA-256 of raw payload',
  `processed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`webhook_event_id`),
  UNIQUE KEY `unique_provider_event` (`provider`, `external_event_id`),
  KEY `idx_provider` (`provider`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_processed_at` (`processed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add retry tracking to payment captures
ALTER TABLE `ip_payments` ADD COLUMN IF NOT EXISTS `external_capture_id` varchar(255) COMMENT 'Payment gateway capture transaction ID' AFTER `payment_external_id`;
ALTER TABLE `ip_payments` ADD COLUMN IF NOT EXISTS `capture_attempt_count` int(11) DEFAULT 0 COMMENT 'Number of capture attempts' AFTER `external_capture_id`;
ALTER TABLE `ip_payments` ADD COLUMN IF NOT EXISTS `last_capture_error` text COMMENT 'Last error from capture attempt' AFTER `capture_attempt_count`;

-- Create einvoicing transmissions table if it doesn't exist
CREATE TABLE IF NOT EXISTS `ip_einvoicing_transmissions` (
  `transmission_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL COMMENT 'letspeppol, superpdp, qonto, etc',
  `external_reference_id` varchar(255) NOT NULL COMMENT 'Provider reference ID',
  `transmission_status` varchar(50) NOT NULL DEFAULT 'pending' COMMENT 'pending, transmitted, delivered, failed',
  `retry_count` int(11) DEFAULT 0 COMMENT 'Number of retry attempts',
  `last_error` text COMMENT 'Last error message',
  `webhook_event_id` int(11) COMMENT 'FK to webhook_events for idempotency',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`transmission_id`),
  UNIQUE KEY `unique_invoice_provider` (`invoice_id`, `provider`),
  KEY `idx_provider` (`provider`),
  KEY `idx_status` (`transmission_status`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add unique index for payment deduplication
ALTER TABLE `ip_payments` ADD UNIQUE INDEX `unique_external_capture` (`external_capture_id`) USING BTREE;
