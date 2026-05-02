<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Core\Adapters\LetsPeppol\Auth\LetsPeppolOAuthProviderFactory;
use Core\Services\Integrations\IntegrationSettingsService;

#[AllowDynamicProperties]
class Integrations extends Admin_Controller
{
    private IntegrationSettingsService $settingsService;

    public function __construct()
    {
        parent::__construct();

        $this->load->model('integrations/mdl_integrations');
        $this->load->library('crypt');

        $this->settingsService = new IntegrationSettingsService(
            $this->mdl_integrations,
            $this->crypt,
            new LetsPeppolOAuthProviderFactory()
        );
    }

    public function index(): void
    {
        if ($this->input->post('btn_submit')) {
            $this->settingsService->saveLetsPeppolSettings([
                'client_id' => $this->input->post('client_id'),
                'client_secret' => $this->input->post('client_secret'),
                'base_url' => $this->input->post('base_url'),
            ]);

            try {
                $this->settingsService->activeTokenOrCreate();
                $this->session->set_flashdata('alert_success', trans('record_successfully_saved'));
            } catch (\Throwable $throwable) {
                $this->mdl_integrations->log('letspeppol', 'oauth.token', 'failed', ['error' => $throwable->getMessage()]);
                $this->session->set_flashdata('alert_warning', trans('settings_saved_but_oauth_failed'));
            }

            redirect('integrations/index');
        }

        $this->layout->set([
            'settings' => $this->settingsService->letsPeppolSettings(),
        ]);
        $this->layout->buffer('content', 'integrations/index');
        $this->layout->render();
    }
}
