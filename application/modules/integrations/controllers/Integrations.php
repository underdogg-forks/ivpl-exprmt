<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use App\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;

#[AllowDynamicProperties]
class Integrations extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('integrations/mdl_integrations');
    }

    public function index(): void
    {
        if ($this->input->post('btn_submit')) {
            $clientId     = trim((string) $this->input->post('client_id'));
            $clientSecret = trim((string) $this->input->post('client_secret'));

            $this->mdl_integrations->saveSettings('letspeppol', [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'base_url'      => trim((string) $this->input->post('base_url')),
            ]);

            $settings = $this->mdl_integrations->settings('letspeppol');

            if (($settings['client_id'] ?? '') && ($settings['client_secret'] ?? '') && ($settings['base_url'] ?? '')) {
                try {
                    $factory  = new LetsPeppolOAuthProviderFactory();
                    $provider = $factory->make(
                        new App\Integration\IntegrationCredentials($settings['client_id'], $settings['client_secret']),
                        $settings['base_url']
                    );

                    $token = $provider->getAccessToken('client_credentials');
                    $this->mdl_integrations->saveToken('letspeppol', $token->getToken(), $token->getExpires());
                } catch (\Throwable $throwable) {
                    $this->mdl_integrations->log('letspeppol', 'oauth.token', 'failed', ['error' => $throwable->getMessage()]);
                }
            }

            $this->session->set_flashdata('alert_success', trans('record_successfully_saved'));
            redirect('integrations/index');
        }

        $settings = $this->mdl_integrations->settings('letspeppol');

        $this->layout->set([
            'settings' => $settings,
        ]);
        $this->layout->buffer('content', 'integrations/index');
        $this->layout->render();
    }
}
