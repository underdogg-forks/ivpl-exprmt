-- =====================================================
-- InvoicePlane eInvoice module — initial table setup
--
-- NOTE: as of v1.8.0 this file is superseded by the core migration
-- application/modules/setup/sql/044_1.8.0.sql which restructures
-- ip_merchant_clients and extends ip_merchant_responses.
-- This file is retained for reference only.
-- =====================================================

-- ip_merchant_clients: one row per configured integration account.
-- merchant_type identifies the provider class; credentials are stored
-- in typed auth columns (not JSON). Endpoint URLs live in the PHP
-- provider client classes, not in this table.
CREATE TABLE IF NOT EXISTS ip_merchant_clients (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  merchant_type       ENUM('superpdp','qonto','letspeppol') NOT NULL,
  label               VARCHAR(255) NULL,
  enabled             TINYINT(1) DEFAULT 0,
  auth_type           ENUM('oauth2','api_key') NOT NULL DEFAULT 'oauth2',
  oauth_token_url     VARCHAR(500) NULL,
  oauth_client_id     VARCHAR(255) NULL,
  oauth_client_secret VARCHAR(500) NULL,
  api_key             VARCHAR(500) NULL,
  created_at          DATETIME NULL,
  updated_at          DATETIME NULL,

  UNIQUE KEY uniq_merchant_type_label (merchant_type, label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Response logging is handled by ip_merchant_responses (core table),
-- extended in 044_1.8.0.sql. No separate einvoice response table.


-- =====================================================
-- Optional default SuperPDP provider
-- =====================================================

INSERT INTO ip_merchant_clients (
  merchant_type,
  label,
  enabled,
  auth_type,
  oauth_token_url,
  oauth_client_id,
  oauth_client_secret,
  created_at,
  updated_at
)
SELECT
  'superpdp',
  'SuperPDP',
  0,
  'oauth2',
  'https://api.superpdp.tech/oauth2/token',
  '',
  '',
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1
  FROM ip_merchant_clients
  WHERE merchant_type = 'superpdp'
);
