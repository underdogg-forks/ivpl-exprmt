# LetsPeppol integration flow (CI3 + App adapters)

## What is included

- Integrations screen (`/integrations/index`) with LetsPeppol settings (`base_url`, `client_id`, `client_secret`).
- Encrypted storage of `client_secret` in `ip_integration_settings`.
- OAuth token persistence in `ip_integration_tokens`.
- Integration event logging in `ip_integration_logs`.
- Client-level `client_peppol_id` storage and validation call during client save.
- Invoice-level "Send to LetsPeppol" action from invoice view menu.

## Adapter structure

- `App\Adapters\LetsPeppol\LetsPeppolClient`
  - wraps Guzzle `request()` signature
  - stores `baseUrl`, `endpoints`, and `settings`
- `App\Adapters\LetsPeppol\Endpoints\ParticipantClient`
  - validates peppol id
- `App\Adapters\LetsPeppol\Endpoints\InvoiceClient`
  - sends invoice payload

## Runtime behavior

1. Admin opens Integrations screen and saves LetsPeppol credentials.
2. Settings are persisted and `client_secret` is encrypted before write.
3. System tries to fetch OAuth token and stores token if successful.
4. When client is saved with `client_peppol_id`, system validates it against LetsPeppol.
   - value is always saved regardless of validation result
   - validation result is logged
5. Invoice view provides "Send to LetsPeppol" action.
6. Send action uses stored token (or refreshes it), sends invoice payload, and logs result.

## Next steps

- Add retries/backoff and token refresh grace period.
- Add adapter-specific payload mappers (UBL/Peppol formats).
- Add API response parser and richer UI status indicators.
