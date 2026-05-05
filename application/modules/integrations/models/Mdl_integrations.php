<?php

namespace Modules\Integrations\Models;

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}


#[AllowDynamicProperties]
class Mdl_integrations extends \CI_Model implements \Core\Contracts\IntegrationRepositoryInterface
{
    public function ensureProvider(string $provider, string $name): int
    {
        $integration = $this->db->get_where('ip_integrations', ['integration_provider' => $provider])->row();

        if ($integration) {
            return (int) $integration->integration_id;
        }

        $this->db->insert('ip_integrations', [
            'integration_name'     => $name,
            'integration_provider' => $provider,
            'integration_status'   => 1,
        ]);

        return (int) $this->db->insert_id();
    }

    public function saveEncryptedSettings(string $provider, array $settings, array $encryptedKeys, \Core\Contracts\CryptInterface $crypt): void
    {
        $integrationId = $this->ensureProvider($provider, ucfirst($provider));
        foreach ($settings as $key => $value) {
            $isEncrypted = in_array($key, $encryptedKeys, true) ? 1 : 0;
            $storedValue = $isEncrypted ? $crypt->encode($value) : $value;

            $row = $this->db->get_where('ip_integration_settings', [
                'integration_id' => $integrationId,
                'setting_key'    => $key,
            ])->row();

            if ($row) {
                $this->db->where('integration_setting_id', $row->integration_setting_id)
                    ->update('ip_integration_settings', [
                        'setting_value' => $storedValue,
                        'is_encrypted'  => $isEncrypted,
                    ]);
            } else {
                $this->db->insert('ip_integration_settings', [
                    'integration_id' => $integrationId,
                    'setting_key'    => $key,
                    'setting_value'  => $storedValue,
                    'is_encrypted'   => $isEncrypted,
                ]);
            }
        }
    }

    public function settings(string $provider, \Core\Contracts\CryptInterface $crypt): array
    {
        $rows = $this->db->select('s.setting_key, s.setting_value, s.is_encrypted')
            ->from('ip_integration_settings s')
            ->join('ip_integrations i', 'i.integration_id = s.integration_id')
            ->where('i.integration_provider', $provider)
            ->get()->result();

        $settings = [];

        foreach ($rows as $row) {
            $settings[$row->setting_key] = $row->is_encrypted ? $crypt->decode($row->setting_value) : $row->setting_value;
        }

        return $settings;
    }

    public function saveToken(string $provider, string $token, ?int $expiresAt = null): void
    {
        $integrationId = $this->ensureProvider($provider, 'LetsPeppol');

        $this->db->trans_start();

        $this->db->where('integration_id', $integrationId)->delete('ip_integration_tokens');

        $this->db->insert('ip_integration_tokens', [
            'integration_id' => $integrationId,
            'token_type'     => 'access_token',
            'token_value'    => $token,
            'expires_at'     => $expiresAt ? date('Y-m-d H:i:s', $expiresAt) : null,
        ]);

        $this->db->trans_complete();
    }

    public function invalidateToken(string $provider): void
    {
        $row = $this->db->get_where('ip_integrations', ['integration_provider' => $provider])->row();

        if ($row) {
            $this->db->where('integration_id', $row->integration_id)->delete('ip_integration_tokens');
        }
    }

    public function activeToken(string $provider): ?string
    {
        $row = $this->db->select('t.token_value, t.expires_at')
            ->from('ip_integration_tokens t')
            ->join('ip_integrations i', 'i.integration_id = t.integration_id')
            ->where('i.integration_provider', $provider)
            ->order_by('t.integration_token_id', 'desc')
            ->limit(1)
            ->get()->row();

        $expiryBuffer = 60; // seconds
        if ( ! $row || ($row->expires_at && strtotime($row->expires_at) < (time() + $expiryBuffer))) {
            return null;
        }

        return $row->token_value;
    }

    public function log(string $provider, string $event, string $status, array $context = []): void
    {
        $integrationId = $this->ensureProvider($provider, 'LetsPeppol');
        $logContext = json_encode($context);

        if ($logContext === false) {
            $logContext = null;
        }

        $this->db->insert('ip_integration_logs', [
            'integration_id' => $integrationId,
            'log_event'      => $event,
            'log_status'     => $status,
            'log_context'    => $logContext,
        ]);
    }
}
